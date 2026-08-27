<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Application\UseCases;

use Src\Shared\OfficialTime\Domain\Contracts\OfficialTimeProviderInterface;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;

final readonly class ListEligibilityChecksUseCase
{
    public function __construct(
        private EligibilityCheckRepositoryInterface $repository,
        private OfficialTimeProviderInterface $officialTime,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function history(): array
    {
        $this->refreshExpiredTechnicalNotes();

        return $this->repository->history($this->officialTime->now());
    }

    public function refreshExpiredTechnicalNotes(): int
    {
        return $this->repository->expireOverdue($this->officialTime->now());
    }

    /** @return array{teachers:array<int,array{id:int,label:string}>,groups:array<int,array{id:int,label:string}>} */
    public function options(): array
    {
        return $this->repository->options();
    }
}
