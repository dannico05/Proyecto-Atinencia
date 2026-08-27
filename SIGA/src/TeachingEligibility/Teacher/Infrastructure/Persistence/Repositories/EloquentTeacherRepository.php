<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Infrastructure\Persistence\Repositories;

use App\Models\AcademicSpecialization;
use App\Models\AuditLog;
use App\Models\Credential as CredentialModel;
use App\Models\Teacher as TeacherModel;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;
use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\TeachingEligibility\Teacher\Domain\Entities\Credential;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;
use Src\TeachingEligibility\Teacher\Domain\Exceptions\TeacherHasAssignmentsException;

final class EloquentTeacherRepository implements TeacherRepositoryInterface
{
    public function find(int $id): ?Teacher
    {
        $model = TeacherModel::query()->with('credentials')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null): array
    {
        $models = TeacherModel::query()
            ->with('credentials')
            ->when(filled($search), function ($query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('national_id', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('second_last_name', 'like', $term);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $models->map($this->toDomain(...))->all();
    }

    public function save(Teacher $teacher, ?int $actorId = null): Teacher
    {
        return DB::transaction(function () use ($teacher, $actorId): Teacher {
            $model = $teacher->id()
                ? TeacherModel::query()->findOrFail($teacher->id())
                : new TeacherModel;
            $before = $model->exists ? $model->only($this->profileFields()) : [];

            $model->fill([
                'national_id' => $teacher->nationalId(),
                'first_name' => $teacher->firstName(),
                'last_name' => $teacher->lastName(),
                'second_last_name' => $teacher->secondLastName(),
                'active' => $teacher->active(),
            ])->save();

            $after = $model->only($this->profileFields());
            $this->audit(
                $actorId,
                'teacher',
                $model->id,
                $before === [] ? 'created' : 'updated',
                ['fields' => $this->fieldChanges($before, $after)],
            );

            return $this->toDomain($model->load('credentials'));
        });
    }

    public function delete(int $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            $model = TeacherModel::query()->findOrFail($id);
            if (TeachingAssignment::query()->where('teacher_id', $id)->exists()) {
                throw new TeacherHasAssignmentsException;
            }

            $before = $model->only($this->profileFields());
            $model->delete();
            $this->audit($actorId, 'teacher', $id, 'deleted', ['fields' => $this->fieldChanges($before, [])]);
        });
    }

    public function saveCredential(Credential $credential, ?int $actorId = null): Credential
    {
        return DB::transaction(function () use ($credential, $actorId): Credential {
            $model = $credential->id()
                ? CredentialModel::query()->findOrFail($credential->id())
                : new CredentialModel;
            $before = $model->exists ? $model->only($this->credentialFields()) : [];

            $model->fill([
                'teacher_id' => $credential->teacherId(),
                'degree_level' => $credential->degreeLevel(),
                'institution' => $credential->institution(),
                'graduation_year' => $credential->graduationYear(),
                'specialization' => $credential->specialization(),
            ])->save();

            $after = $model->only($this->credentialFields());
            $this->audit(
                $actorId,
                'credential',
                $model->id,
                $before === [] ? 'created' : 'updated',
                ['fields' => $this->fieldChanges($before, $after)],
            );

            return $this->credentialToDomain($model);
        });
    }

    public function deleteCredential(int $credentialId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($credentialId, $actorId): void {
            $model = CredentialModel::query()->findOrFail($credentialId);
            $before = $model->only($this->credentialFields());
            $model->delete();
            $this->audit($actorId, 'credential', $credentialId, 'deleted', ['fields' => $this->fieldChanges($before, [])]);
        });
    }

    public function specializationOptions(): array
    {
        return AcademicSpecialization::query()
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function toDomain(TeacherModel $model): Teacher
    {
        return Teacher::reconstitute(
            $model->id,
            $model->national_id,
            $model->first_name,
            $model->last_name,
            $model->second_last_name,
            (bool) $model->active,
            $model->credentials->map($this->credentialToDomain(...))->all(),
        );
    }

    private function credentialToDomain(CredentialModel $model): Credential
    {
        return new Credential(
            $model->id,
            $model->teacher_id,
            $model->degree_level,
            $model->institution,
            (int) $model->graduation_year,
            $model->specialization,
        );
    }

    /** @return array<int, string> */
    private function profileFields(): array
    {
        return ['national_id', 'first_name', 'last_name', 'second_last_name', 'active'];
    }

    /** @return array<int, string> */
    private function credentialFields(): array
    {
        return ['teacher_id', 'degree_level', 'institution', 'graduation_year', 'specialization'];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{previous: mixed, new: mixed}>
     */
    private function fieldChanges(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            $previous = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($previous !== $new) {
                $changes[$field] = ['previous' => $previous, 'new' => $new];
            }
        }

        return $changes;
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
}
