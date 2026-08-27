<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Domain\Entities;

use DateTimeImmutable;

final readonly class TechnicalNote
{
    public function __construct(
        private int $assignmentId,
        private string $documentPath,
        private DateTimeImmutable $ratificationDeadline,
        private string $status,
    ) {}

    public function effectiveStatus(DateTimeImmutable $now): string
    {
        if ($this->status === 'pending' && $this->ratificationDeadline < $now->setTime(0, 0)) {
            return 'expired';
        }

        return $this->status;
    }

    public function assignmentId(): int
    {
        return $this->assignmentId;
    }

    public function documentPath(): string
    {
        return $this->documentPath;
    }

    public function ratificationDeadline(): DateTimeImmutable
    {
        return $this->ratificationDeadline;
    }
}
