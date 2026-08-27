<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Domain\Contracts;

use DateTimeImmutable;
use Src\TeachingEligibility\Verification\Domain\ValueObjects\EligibilityDecision;

interface EligibilityCheckRepositoryInterface
{
    /** @return array{course_id:int,term_start:DateTimeImmutable} */
    public function groupContext(int $groupId): array;

    public function record(
        int $groupId,
        int $teacherId,
        EligibilityDecision $decision,
        ?int $catalogId,
        ?int $actorId,
    ): int;

    /** @return array<int, array<string, mixed>> */
    public function history(DateTimeImmutable $now): array;

    public function expireOverdue(DateTimeImmutable $now): int;

    /** @return array{teachers:array<int,array{id:int,label:string}>,groups:array<int,array{id:int,label:string}>} */
    public function options(): array;

    /** @return array<string, mixed>|null */
    public function findAssignment(int $assignmentId): ?array;

    public function startTechnicalNote(
        int $assignmentId,
        string $documentPath,
        DateTimeImmutable $deadline,
        ?int $actorId,
    ): void;

    public function decideManual(
        int $assignmentId,
        bool $approved,
        string $reason,
        ?int $actorId,
    ): void;

    public function resolveTechnicalNote(
        int $assignmentId,
        string $outcome,
        string $reason,
        ?int $actorId,
    ): void;
}
