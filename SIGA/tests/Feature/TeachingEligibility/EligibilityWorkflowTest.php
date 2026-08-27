<?php

declare(strict_types=1);

namespace Tests\Feature\TeachingEligibility;

use App\Models\AcademicTerm;
use App\Models\Career;
use App\Models\Course;
use App\Models\EligibilityCheck;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TeachingGroup;
use Database\Seeders\TeachingEligibilitySeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Src\TeachingEligibility\Teacher\Application\UseCases\DeleteTeacherUseCase;
use Src\TeachingEligibility\Teacher\Domain\Exceptions\TeacherHasAssignmentsException;
use Src\TeachingEligibility\Verification\Application\DTOs\EligibilityCheckDTO;
use Src\TeachingEligibility\Verification\Application\UseCases\DecideManualAssignmentUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\StartTechnicalNoteUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\VerifyAssignmentUseCase;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;
use Src\TeachingEligibility\Verification\Domain\Enums\EligibilityResult;
use Src\TeachingEligibility\Verification\Domain\Exceptions\AssignmentCannotBeReverifiedException;
use Tests\TestCase;

final class EligibilityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TeachingEligibilitySeeder::class);
    }

    #[Test]
    public function an_eligible_teacher_is_confirmed_with_the_catalog_version_snapshot(): void
    {
        $teacher = Teacher::query()->where('national_id', '1-1111-1111')->firstOrFail();
        $group = TeachingGroup::query()->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))->firstOrFail();

        $decision = app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);

        self::assertSame(EligibilityResult::Eligible, $decision->result);
        $this->assertDatabaseHas('teaching_assignments', ['teacher_id' => $teacher->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('eligibility_checks', ['result' => 'eligible', 'provisional' => false]);
        self::assertNotNull(EligibilityCheck::query()->firstOrFail()->eligibility_catalog_id);

        $history = app(EligibilityCheckRepositoryInterface::class)->history(new DateTimeImmutable('2026-08-25'));
        $reference = $history[0]['credential_results'][0]['catalog_reference'];
        self::assertStringContainsString('INGENIERÍA EN TECNOLOGÍAS DE INFORMACIÓN', $reference);
        self::assertStringContainsString('Programación II', $reference);
        self::assertStringNotContainsString('ITI-321', $reference);
        self::assertStringContainsString('v1', $reference);
        self::assertStringContainsString('Acuerdo 10 - Sesión Ordinaria 22-2025', $reference);
    }

    #[Test]
    public function history_uses_the_check_id_to_break_creation_time_ties(): void
    {
        $teacher = Teacher::query()->where('national_id', '1-1111-1111')->firstOrFail();
        $group = TeachingGroup::query()->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))->firstOrFail();

        Carbon::setTestNow('2026-08-25 10:00:00');

        try {
            $useCase = app(VerifyAssignmentUseCase::class);
            $useCase->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
            $useCase->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);

            $latestCheckId = EligibilityCheck::query()->max('id');
            $history = app(EligibilityCheckRepositoryInterface::class)->history(new DateTimeImmutable('2026-08-25'));

            self::assertSame($latestCheckId, $history[0]['id']);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function a_teacher_referenced_by_assignment_history_cannot_be_deleted(): void
    {
        $teacher = Teacher::query()->where('national_id', '1-1111-1111')->firstOrFail();
        $group = TeachingGroup::query()->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))->firstOrFail();
        app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);

        try {
            app(DeleteTeacherUseCase::class)->handle($teacher->id, null);
            self::fail('The teacher deletion should preserve referenced assignment history.');
        } catch (TeacherHasAssignmentsException) {
            $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        }
    }

    #[Test]
    public function a_non_eligible_teacher_is_blocked_and_can_enter_the_technical_note_path(): void
    {
        $teacher = Teacher::query()->where('national_id', '2-2222-2222')->firstOrFail();
        $group = TeachingGroup::query()->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))->firstOrFail();
        app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
        $assignment = TeachingAssignment::query()->firstOrFail();

        app(StartTechnicalNoteUseCase::class)->handle(
            $assignment->id,
            'technical-notes/signed.pdf',
            '2026-12-31',
            null,
        );

        $this->assertDatabaseHas('technical_notes', [
            'teaching_assignment_id' => $assignment->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('eligibility_checks', ['result' => 'technical_note']);
        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignment->id, 'status' => 'technical_note_pending']);
    }

    #[Test]
    public function an_assignment_with_a_technical_note_cannot_be_reverified(): void
    {
        $teacher = Teacher::query()->where('national_id', '2-2222-2222')->firstOrFail();
        $group = TeachingGroup::query()->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))->firstOrFail();
        $useCase = app(VerifyAssignmentUseCase::class);
        $useCase->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
        $assignment = TeachingAssignment::query()->firstOrFail();

        app(StartTechnicalNoteUseCase::class)->handle(
            $assignment->id,
            'technical-notes/signed.pdf',
            '2026-12-31',
            null,
        );

        try {
            $useCase->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
            self::fail('An assignment with an active technical note must not be verified again.');
        } catch (AssignmentCannotBeReverifiedException) {
            $this->assertDatabaseHas('technical_notes', [
                'teaching_assignment_id' => $assignment->id,
                'status' => 'pending',
            ]);
            $this->assertDatabaseHas('teaching_assignments', [
                'id' => $assignment->id,
                'status' => 'technical_note_pending',
            ]);
            self::assertSame(1, EligibilityCheck::query()->where('teaching_assignment_id', $assignment->id)->count());
        }
    }

    #[Test]
    public function an_unratified_technical_note_expires_with_an_audited_assignment_status(): void
    {
        $teacher = Teacher::query()->where('national_id', '2-2222-2222')->firstOrFail();
        $group = TeachingGroup::query()->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))->firstOrFail();
        app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
        $assignment = TeachingAssignment::query()->firstOrFail();

        app(StartTechnicalNoteUseCase::class)->handle(
            $assignment->id,
            'technical-notes/signed.pdf',
            '2026-08-20',
            null,
        );

        app(EligibilityCheckRepositoryInterface::class)->history(new DateTimeImmutable('2026-08-21'));

        $this->assertDatabaseHas('technical_notes', ['teaching_assignment_id' => $assignment->id, 'status' => 'expired']);
        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignment->id, 'status' => 'technical_note_expired']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $assignment->id, 'event' => 'technical_note_expired']);
    }

    #[Test]
    public function a_course_without_catalog_requires_a_traced_manual_decision(): void
    {
        $teacher = Teacher::query()->where('national_id', '1-1111-1111')->firstOrFail();
        $career = Career::query()->where('code', 'IGA')->firstOrFail();
        $course = Course::query()->create([
            'career_id' => $career->id,
            'code' => 'IGA-SIN-CAT',
            'name' => 'Curso sin publicación en el Manual',
        ]);
        $term = AcademicTerm::query()->firstOrFail();
        $group = TeachingGroup::query()->create([
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'group_number' => 99,
        ]);
        $decision = app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
        $assignment = TeachingAssignment::query()->firstOrFail();

        self::assertSame(EligibilityResult::NoCatalog, $decision->result);
        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignment->id, 'status' => 'pending_manual_approval']);

        try {
            app(StartTechnicalNoteUseCase::class)->handle(
                $assignment->id,
                'technical-notes/not-allowed.pdf',
                '2026-12-31',
                null,
            );
            self::fail('An assignment without a catalog must use manual approval only.');
        } catch (\DomainException) {
            $this->assertDatabaseMissing('technical_notes', ['teaching_assignment_id' => $assignment->id]);
        }

        app(DecideManualAssignmentUseCase::class)->handle($assignment->id, true, 'Manual review completed.', null);

        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignment->id, 'status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $assignment->id, 'event' => 'manual_decision']);
    }
}
