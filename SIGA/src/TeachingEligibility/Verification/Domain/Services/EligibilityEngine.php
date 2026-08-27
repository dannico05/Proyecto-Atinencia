<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Domain\Services;

use Src\TeachingEligibility\Catalog\Domain\ValueObjects\CatalogSelection;
use Src\TeachingEligibility\Teacher\Domain\Entities\Credential;
use Src\TeachingEligibility\Verification\Domain\Enums\EligibilityResult;
use Src\TeachingEligibility\Verification\Domain\ValueObjects\EligibilityDecision;

final class EligibilityEngine
{
    /** @param array<int, Credential> $credentials */
    public function verify(array $credentials, CatalogSelection $selection): EligibilityDecision
    {
        if ($selection->catalog === null) {
            return new EligibilityDecision(
                EligibilityResult::NoCatalog,
                false,
                'No published eligibility catalog exists for this course.',
            );
        }

        $eligible = array_map($this->normalize(...), $selection->catalog->specializations());

        foreach ($credentials as $credential) {
            if (in_array($this->normalize($credential->specialization()), $eligible, true)) {
                return new EligibilityDecision(
                    EligibilityResult::Eligible,
                    $selection->provisional,
                    'The teacher has a credential included in the applicable catalog.',
                    $credential->specialization(),
                );
            }
        }

        return new EligibilityDecision(
            EligibilityResult::NotEligible,
            $selection->provisional,
            'The teacher does not hold a credential included in the applicable catalog.',
        );
    }

    private function normalize(string $value): string
    {
        $lowercase = mb_strtolower($value, 'UTF-8');
        $ascii = strtr($lowercase, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? $ascii;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }
}
