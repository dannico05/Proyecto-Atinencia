<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Application\UseCases;

use RuntimeException;
use Src\TeachingEligibility\Teacher\Application\DTOs\TeacherDTO;
use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;

final readonly class SaveTeacherUseCase
{
    public function __construct(private TeacherRepositoryInterface $repository) {}

    public function handle(TeacherDTO $dto, ?int $id, ?int $actorId): Teacher
    {
        if ($id === null) {
            $teacher = Teacher::create(
                $dto->nationalId,
                $dto->firstName,
                $dto->lastName,
                $dto->secondLastName,
                $dto->active,
            );
        } else {
            $teacher = $this->repository->find($id) ?? throw new RuntimeException('Teacher not found.');
            $teacher->updateProfile(
                $dto->nationalId,
                $dto->firstName,
                $dto->lastName,
                $dto->secondLastName,
                $dto->active,
            );
        }

        return $this->repository->save($teacher, $actorId);
    }
}
