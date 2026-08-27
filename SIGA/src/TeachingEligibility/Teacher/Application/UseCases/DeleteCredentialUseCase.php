<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Application\UseCases;

use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;

final readonly class DeleteCredentialUseCase
{
    public function __construct(private TeacherRepositoryInterface $repository) {}

    public function handle(int $id, ?int $actorId): void
    {
        $this->repository->deleteCredential($id, $actorId);
    }
}
