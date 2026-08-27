<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Domain\Entities;

final readonly class Credential
{
    public function __construct(
        private ?int $id,
        private int $teacherId,
        private string $degreeLevel,
        private string $institution,
        private int $graduationYear,
        private string $specialization,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function teacherId(): int
    {
        return $this->teacherId;
    }

    public function degreeLevel(): string
    {
        return $this->degreeLevel;
    }

    public function institution(): string
    {
        return $this->institution;
    }

    public function graduationYear(): int
    {
        return $this->graduationYear;
    }

    public function specialization(): string
    {
        return $this->specialization;
    }
}
