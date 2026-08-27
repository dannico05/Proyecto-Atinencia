<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Application\UseCases;

use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;

final readonly class ListTeachersUseCase
{
    public function __construct(private TeacherRepositoryInterface $repository) {}

    /** @return array<int, Teacher> */
    public function handle(?string $search = null): array
    {
        return $this->repository->all($search);
    }

    /** @return array<int, string> */
    public function specializations(): array
    {
        return $this->repository->specializationOptions();
    }
}
