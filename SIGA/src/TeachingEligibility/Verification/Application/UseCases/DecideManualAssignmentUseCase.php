<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Application\UseCases;

use DomainException;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;

final readonly class DecideManualAssignmentUseCase
{
    public function __construct(private EligibilityCheckRepositoryInterface $repository) {}

    public function handle(int $assignmentId, bool $approved, string $reason, ?int $actorId): void
    {
        $assignment = $this->repository->findAssignment($assignmentId);

        if ($assignment === null || $assignment['result'] !== 'no_catalog') {
            throw new DomainException('Manual approval is only available for assignments without a catalog.');
        }

        $this->repository->decideManual($assignmentId, $approved, trim($reason), $actorId);
    }
}
