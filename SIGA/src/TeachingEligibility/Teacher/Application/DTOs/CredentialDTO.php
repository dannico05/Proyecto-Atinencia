<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Application\DTOs;

final readonly class CredentialDTO
{
    public function __construct(
        public int $teacherId,
        public string $degreeLevel,
        public string $institution,
        public int $graduationYear,
        public string $specialization,
        public ?int $id = null,
    ) {}
}
