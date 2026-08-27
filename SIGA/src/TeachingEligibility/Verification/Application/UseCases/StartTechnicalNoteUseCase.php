<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Application\UseCases;

use DateTimeImmutable;
use DomainException;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;

final readonly class StartTechnicalNoteUseCase
{
    public function __construct(private EligibilityCheckRepositoryInterface $repository) {}

    public function handle(int $assignmentId, string $documentPath, string $deadline, ?int $actorId): void
    {
        $assignment = $this->repository->findAssignment($assignmentId);

        if ($assignment === null || $assignment['result'] !== 'not_eligible') {
            throw new DomainException('A technical note can only be created for a non-eligible result.');
        }

        $this->repository->startTechnicalNote(
            $assignmentId,
            $documentPath,
            new DateTimeImmutable($deadline),
            $actorId,
        );
    }
}
