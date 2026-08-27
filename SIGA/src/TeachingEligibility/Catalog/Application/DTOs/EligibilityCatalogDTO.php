<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Application\DTOs;

final readonly class EligibilityCatalogDTO
{
    /** @param array<int, string> $specializations */
    public function __construct(
        public int $courseId,
        public string $agreement,
        public string $gazetteNumber,
        public string $validFrom,
        public ?string $validUntil,
        public array $specializations,
    ) {}
}
