<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Infrastructure\Persistence\Repositories;

use App\Models\AuditLog;
use App\Models\EligibilityCheck;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TeachingGroup;
use App\Models\TechnicalNote;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;
use Src\TeachingEligibility\Verification\Domain\Enums\EligibilityResult;
use Src\TeachingEligibility\Verification\Domain\Exceptions\AssignmentCannotBeReverifiedException;
use Src\TeachingEligibility\Verification\Domain\ValueObjects\EligibilityDecision;

final class EloquentEligibilityCheckRepository implements EligibilityCheckRepositoryInterface
{
    public function groupContext(int $groupId): array
    {
        $group = TeachingGroup::query()->with('academicTerm')->findOrFail($groupId);

        return [
            'course_id' => $group->course_id,
            'term_start' => new DateTimeImmutable($group->academicTerm->starts_at->format('Y-m-d')),
        ];
    }

    public function record(
        int $groupId,
        int $teacherId,
        EligibilityDecision $decision,
        ?int $catalogId,
        ?int $actorId,
    ): int {
        return DB::transaction(function () use ($groupId, $teacherId, $decision, $catalogId, $actorId): int {
            $assignment = TeachingAssignment::query()
                ->where('teaching_group_id', $groupId)
                ->where('teacher_id', $teacherId)
                ->lockForUpdate()
                ->first();

            if ($assignment !== null && (
                $assignment->technicalNote()->exists()
                || in_array($assignment->status, [
                    'approved',
                    'rejected',
                    'technical_note_pending',
                    'technical_note_expired',
                    'technical_note_ratified',
                    'technical_note_rejected',
                ], true)
            )) {
                throw new AssignmentCannotBeReverifiedException;
            }

            $status = match ($decision->result) {
                EligibilityResult::Eligible => 'confirmed',
                EligibilityResult::NotEligible => 'blocked',
                EligibilityResult::NoCatalog => 'pending_manual_approval',
                EligibilityResult::TechnicalNote => 'technical_note_pending',
            };

            $assignment ??= new TeachingAssignment([
                'teaching_group_id' => $groupId,
                'teacher_id' => $teacherId,
            ]);
            $assignment->fill([
                'status' => $status,
                'decided_by' => null,
                'decided_at' => null,
                'decision_reason' => null,
            ])->save();

            $check = $assignment->checks()->create([
                'eligibility_catalog_id' => $catalogId,
                'executed_by' => $actorId,
                'result' => $decision->result->value,
                'provisional' => $decision->provisional,
                'reason' => $decision->reason,
            ]);

            $this->audit($actorId, 'teaching_assignment', $assignment->id, 'eligibility_checked', [
                'check_id' => $check->id,
                'result' => $decision->result->value,
                'catalog_id' => $catalogId,
                'provisional' => $decision->provisional,
            ]);

            return $assignment->id;
        });
    }

    public function history(DateTimeImmutable $now): array
    {
        $this->expireOverdue($now);

        $checks = EligibilityCheck::query()
            ->with([
                'catalog.specializations',
                'assignment.teacher.credentials',
                'assignment.group.course.career',
                'assignment.group.academicTerm',
                'assignment.technicalNote.resolver',
            ])
            ->whereIn('id', EligibilityCheck::query()
                ->selectRaw('MAX(id)')
                ->groupBy('teaching_assignment_id'))
            ->latest()
            ->latest('id')
            ->get();

        $history = [];

        foreach ($checks as $check) {
            $assignment = $check->assignment;
            $note = $assignment->technicalNote;
            $noteStatus = $note?->status;

            $eligibleSpecializations = $check->catalog?->specializations->pluck('name')->all() ?? [];
            $normalizedEligible = array_map($this->normalize(...), $eligibleSpecializations);
            $career = $assignment->group->course->career->name;
            $course = $assignment->group->course->name;
            $catalogReference = $check->catalog
                ? $career.' · '.$course.' · v'.$check->catalog->version.' · '.$check->catalog->university_council_agreement.' · '.$check->catalog->gazette_number
                : null;
            $credentialResults = [];
            foreach ($assignment->teacher->credentials as $credential) {
                $credentialResults[] = [
                    'degree' => $credential->degree_level,
                    'specialization' => $credential->specialization,
                    'institution' => $credential->institution,
                    'year' => $credential->graduation_year,
                    'result' => $check->catalog === null
                        ? 'no_catalog'
                        : (in_array($this->normalize($credential->specialization), $normalizedEligible, true) ? 'eligible' : 'not_eligible'),
                    'catalog_reference' => $catalogReference,
                ];
            }

            $history[] = [
                'id' => $check->id,
                'assignment_id' => $assignment->id,
                'teacher' => $assignment->teacher->fullName(),
                'national_id' => $assignment->teacher->national_id,
                'career' => $career,
                'course' => $course,
                'group' => $assignment->group->group_number,
                'term' => $assignment->group->academicTerm->term_number.'-'.$assignment->group->academicTerm->year,
                'result' => $check->result,
                'assignment_status' => $assignment->status,
                'provisional' => (bool) $check->provisional,
                'reason' => $check->reason,
                'catalog_reference' => $catalogReference,
                'catalog_version' => $check->catalog?->version,
                'catalog_agreement' => $check->catalog?->university_council_agreement,
                'catalog_gazette' => $check->catalog?->gazette_number,
                'technical_note_status' => $noteStatus,
                'ratification_deadline' => $note?->ratification_deadline->format('Y-m-d'),
                'technical_note_path' => $note?->document_path,
                'technical_note_resolved_by' => $note?->resolver?->name,
                'technical_note_resolved_at' => $note?->resolved_at?->format('Y-m-d H:i'),
                'technical_note_resolution_reason' => $note?->resolution_reason,
                'checked_at' => $check->created_at?->format('Y-m-d H:i'),
                'credential_results' => $credentialResults,
            ];
        }

        return $history;
    }

    public function expireOverdue(DateTimeImmutable $now): int
    {
        $notes = TechnicalNote::query()
            ->with('assignment')
            ->where('status', 'pending')
            ->whereDate('ratification_deadline', '<', $now->format('Y-m-d'))
            ->get();

        foreach ($notes as $note) {
            DB::transaction(function () use ($note): void {
                $note->update(['status' => 'expired']);
                $note->assignment->update(['status' => 'technical_note_expired']);
                $this->audit(null, 'teaching_assignment', $note->teaching_assignment_id, 'technical_note_expired', [
                    'ratification_deadline' => $note->ratification_deadline->format('Y-m-d'),
                ]);
            });
        }

        return $notes->count();
    }

    public function options(): array
    {
        $teachers = Teacher::query()
            ->where('active', true)
            ->whereHas('credentials')
            ->orderBy('last_name')
            ->get()
            ->map(static fn (Teacher $teacher): array => ['id' => $teacher->id, 'label' => $teacher->national_id.' - '.$teacher->fullName()])
            ->all();

        $groups = TeachingGroup::query()
            ->with(['course.career', 'academicTerm'])
            ->get()
            ->sortBy(static fn (TeachingGroup $group): string => $group->course->career->name.'|'.$group->course->name.'|'.$group->group_number)
            ->unique(static fn (TeachingGroup $group): string => $group->course->career_id.'|'.mb_strtolower(trim($group->course->name), 'UTF-8'))
            ->map(static fn (TeachingGroup $group): array => [
                'id' => $group->id,
                'label' => $group->course->name
                    .' · '.$group->course->career->name,
            ])
            ->values()
            ->all();

        return ['teachers' => $teachers, 'groups' => $groups];
    }

    public function findAssignment(int $assignmentId): ?array
    {
        $assignment = TeachingAssignment::query()->with(['checks' => fn ($query) => $query->latest('id')])->find($assignmentId);
        $latest = $assignment?->checks->first();

        return $assignment && $latest ? [
            'id' => $assignment->id,
            'result' => $latest->result,
            'catalog_id' => $latest->eligibility_catalog_id,
        ] : null;
    }

    public function startTechnicalNote(
        int $assignmentId,
        string $documentPath,
        DateTimeImmutable $deadline,
        ?int $actorId,
    ): void {
        DB::transaction(function () use ($assignmentId, $documentPath, $deadline, $actorId): void {
            $assignment = TeachingAssignment::query()->with(['checks' => fn ($query) => $query->latest('id')])->findOrFail($assignmentId);
            $latest = $assignment->checks->firstOrFail();

            TechnicalNote::query()->updateOrCreate(
                ['teaching_assignment_id' => $assignmentId],
                [
                    'created_by' => $actorId,
                    'document_path' => $documentPath,
                    'ratification_deadline' => $deadline->format('Y-m-d'),
                    'status' => 'pending',
                    'resolved_by' => null,
                    'resolved_at' => null,
                    'resolution_reason' => null,
                ],
            );

            $assignment->update(['status' => 'technical_note_pending']);
            $previousResult = $latest->result;
            $latest->update([
                'executed_by' => $actorId,
                'result' => 'technical_note',
                'provisional' => (bool) $latest->provisional,
                'reason' => 'Provisional assignment supported by a signed technical criterion.',
            ]);

            $this->audit($actorId, 'teaching_assignment', $assignmentId, 'technical_note_created', [
                'ratification_deadline' => $deadline->format('Y-m-d'),
                'document_path' => $documentPath,
                'result' => ['previous' => $previousResult, 'new' => 'technical_note'],
            ]);
        });
    }

    public function decideManual(int $assignmentId, bool $approved, string $reason, ?int $actorId): void
    {
        DB::transaction(function () use ($assignmentId, $approved, $reason, $actorId): void {
            $assignment = TeachingAssignment::query()->findOrFail($assignmentId);
            $assignment->update([
                'status' => $approved ? 'approved' : 'rejected',
                'decided_by' => $actorId,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ]);

            $this->audit($actorId, 'teaching_assignment', $assignmentId, 'manual_decision', [
                'result' => $approved ? 'approved' : 'rejected',
                'reason' => $reason,
            ]);
        });
    }

    public function resolveTechnicalNote(
        int $assignmentId,
        string $outcome,
        string $reason,
        ?int $actorId,
    ): void {
        DB::transaction(function () use ($assignmentId, $outcome, $reason, $actorId): void {
            $note = TechnicalNote::query()
                ->with('assignment')
                ->where('teaching_assignment_id', $assignmentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($note->status !== 'pending') {
                throw new \DomainException('Only a pending technical note can be resolved.');
            }

            $resolvedAt = now();
            $note->update([
                'status' => $outcome,
                'resolved_by' => $actorId,
                'resolved_at' => $resolvedAt,
                'resolution_reason' => $reason,
            ]);
            $note->assignment->update([
                'status' => $outcome === 'ratified' ? 'technical_note_ratified' : 'technical_note_rejected',
                'decided_by' => $actorId,
                'decided_at' => $resolvedAt,
                'decision_reason' => $reason,
            ]);

            $this->audit($actorId, 'teaching_assignment', $assignmentId, 'technical_note_resolved', [
                'result' => $outcome,
                'reason' => $reason,
            ]);
        });
    }

    /** @param array<string, mixed> $changes */
    private function audit(?int $userId, string $type, int $id, string $event, array $changes): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'event' => $event,
            'changes' => $changes,
        ]);
    }

    private function normalize(string $value): string
    {
        $lowercase = mb_strtolower($value, 'UTF-8');
        $ascii = strtr($lowercase, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? $ascii;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }
}
