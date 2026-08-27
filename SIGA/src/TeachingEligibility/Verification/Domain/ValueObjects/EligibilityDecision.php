<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Domain\ValueObjects;

use Src\TeachingEligibility\Verification\Domain\Enums\EligibilityResult;

final readonly class EligibilityDecision
{
    public function __construct(
        public EligibilityResult $result,
        public bool $provisional,
        public string $reason,
        public ?string $matchedSpecialization = null,
    ) {}
}
