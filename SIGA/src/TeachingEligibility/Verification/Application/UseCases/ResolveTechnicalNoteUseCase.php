<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Application\UseCases;

use DomainException;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;

final readonly class ResolveTechnicalNoteUseCase
{
    public function __construct(private EligibilityCheckRepositoryInterface $repository) {}

    public function handle(
        int $assignmentId,
        string $outcome,
        string $reason,
        ?int $actorId,
    ): void {
        $assignment = $this->repository->findAssignment($assignmentId);

        if ($assignment === null || $assignment['result'] !== 'technical_note') {
            throw new DomainException('Only an assignment supported by a technical note can be resolved.');
        }

        if (! in_array($outcome, ['ratified', 'rejected'], true)) {
            throw new DomainException('The technical note resolution must be ratified or rejected.');
        }

        $this->repository->resolveTechnicalNote(
            $assignmentId,
            $outcome,
            trim($reason),
            $actorId,
        );
    }
}
