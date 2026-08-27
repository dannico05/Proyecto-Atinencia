<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Application\UseCases;

use Src\TeachingEligibility\Teacher\Application\DTOs\CredentialDTO;
use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\TeachingEligibility\Teacher\Domain\Entities\Credential;

final readonly class SaveCredentialUseCase
{
    public function __construct(private TeacherRepositoryInterface $repository) {}

    public function handle(CredentialDTO $dto, ?int $actorId): Credential
    {
        return $this->repository->saveCredential(new Credential(
            $dto->id,
            $dto->teacherId,
            $dto->degreeLevel,
            $dto->institution,
            $dto->graduationYear,
            $dto->specialization,
        ), $actorId);
    }
}
