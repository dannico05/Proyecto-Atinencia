<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Domain\Contracts;

use Src\TeachingEligibility\Teacher\Domain\Entities\Credential;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;

interface TeacherRepositoryInterface
{
    public function find(int $id): ?Teacher;

    /** @return array<int, Teacher> */
    public function all(?string $search = null): array;

    public function save(Teacher $teacher, ?int $actorId = null): Teacher;

    public function delete(int $id, ?int $actorId = null): void;

    public function saveCredential(Credential $credential, ?int $actorId = null): Credential;

    public function deleteCredential(int $credentialId, ?int $actorId = null): void;

    /** @return array<int, string> */
    public function specializationOptions(): array;
}
