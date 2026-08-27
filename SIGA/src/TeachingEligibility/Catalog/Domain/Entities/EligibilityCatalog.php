<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class EligibilityCatalog
{
    /** @param array<int, string> $specializations */
    public function __construct(
        private ?int $id,
        private int $courseId,
        private int $version,
        private string $agreement,
        private string $gazetteNumber,
        private DateTimeImmutable $validFrom,
        private ?DateTimeImmutable $validUntil,
        private array $specializations,
    ) {
        if (trim($agreement) === '' || trim($gazetteNumber) === '') {
            throw new InvalidArgumentException('Agreement and Gazette number are required.');
        }

        if ($validUntil !== null && $validUntil < $validFrom) {
            throw new InvalidArgumentException('The validity end date cannot precede the start date.');
        }

        if ($specializations === []) {
            throw new InvalidArgumentException('At least one eligible specialization is required.');
        }
    }

    public function appliesOn(DateTimeImmutable $date): bool
    {
        return $date >= $this->validFrom && ($this->validUntil === null || $date <= $this->validUntil);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function courseId(): int
    {
        return $this->courseId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function agreement(): string
    {
        return $this->agreement;
    }

    public function gazetteNumber(): string
    {
        return $this->gazetteNumber;
    }

    public function validFrom(): DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validUntil(): ?DateTimeImmutable
    {
        return $this->validUntil;
    }

    /** @return array<int, string> */
    public function specializations(): array
    {
        return $this->specializations;
    }
}
