<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Domain\Enums;

enum EligibilityResult: string
{
    case Eligible = 'eligible';
    case NotEligible = 'not_eligible';
    case TechnicalNote = 'technical_note';
    case NoCatalog = 'no_catalog';
}
