<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Application\UseCases;

use RuntimeException;
use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;

final readonly class FindTeacherUseCase
{
    public function __construct(private TeacherRepositoryInterface $repository) {}

    public function handle(int $id): Teacher
    {
        return $this->repository->find($id) ?? throw new RuntimeException('Teacher not found.');
    }
}
