<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Application\DTOs;

final readonly class TeacherDTO
{
    public function __construct(
        public string $nationalId,
        public string $firstName,
        public string $lastName,
        public ?string $secondLastName,
        public bool $active,
    ) {}
}
