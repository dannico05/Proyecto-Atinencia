<?php

declare(strict_types=1);

namespace Tests\Unit\TeachingEligibility;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog;
use Src\TeachingEligibility\Catalog\Domain\ValueObjects\CatalogSelection;
use Src\TeachingEligibility\Teacher\Domain\Entities\Credential;
use Src\TeachingEligibility\Verification\Domain\Enums\EligibilityResult;
use Src\TeachingEligibility\Verification\Domain\Services\EligibilityEngine;

final class EligibilityEngineTest extends TestCase
{
    #[Test]
    public function it_returns_eligible_when_a_specialization_matches_after_normalization(): void
    {
        $catalog = $this->catalog(['Ingeniería del Software']);
        $credential = new Credential(1, 1, 'Licenciatura', 'UTN', 2024, 'ingenieria del software');

        $decision = (new EligibilityEngine)->verify([$credential], new CatalogSelection($catalog, false));

        self::assertSame(EligibilityResult::Eligible, $decision->result);
        self::assertSame('ingenieria del software', $decision->matchedSpecialization);
        self::assertFalse($decision->provisional);
    }

    #[Test]
    public function it_returns_not_eligible_when_no_credential_matches(): void
    {
        $credential = new Credential(1, 1, 'Licenciatura', 'UTN', 2024, 'Administración de Empresas');

        $decision = (new EligibilityEngine)->verify([$credential], new CatalogSelection($this->catalog(['Computación']), false));

        self::assertSame(EligibilityResult::NotEligible, $decision->result);
    }

    #[Test]
    public function it_returns_no_catalog_when_no_version_exists(): void
    {
        $decision = (new EligibilityEngine)->verify([], new CatalogSelection(null, false));

        self::assertSame(EligibilityResult::NoCatalog, $decision->result);
    }

    #[Test]
    public function it_preserves_the_provisional_future_validity_flag(): void
    {
        $credential = new Credential(1, 1, 'Licenciatura', 'UTN', 2024, 'Computación');

        $decision = (new EligibilityEngine)->verify([$credential], new CatalogSelection($this->catalog(['Computación']), true));

        self::assertTrue($decision->provisional);
    }

    /** @param array<int, string> $specializations */
    private function catalog(array $specializations): EligibilityCatalog
    {
        return new EligibilityCatalog(
            1,
            1,
            1,
            'Acuerdo 14',
            'La Gaceta 55',
            new DateTimeImmutable('2026-03-20'),
            null,
            $specializations,
        );
    }
}
