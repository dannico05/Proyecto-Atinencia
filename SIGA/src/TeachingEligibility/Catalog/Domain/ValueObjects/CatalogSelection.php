<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Domain\ValueObjects;

use Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog;

final readonly class CatalogSelection
{
    public function __construct(
        public ?EligibilityCatalog $catalog,
        public bool $provisional,
    ) {}
}
