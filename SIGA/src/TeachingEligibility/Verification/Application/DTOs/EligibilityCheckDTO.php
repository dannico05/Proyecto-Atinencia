<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Application\DTOs;

final readonly class EligibilityCheckDTO
{
    public function __construct(public int $groupId, public int $teacherId) {}
}
