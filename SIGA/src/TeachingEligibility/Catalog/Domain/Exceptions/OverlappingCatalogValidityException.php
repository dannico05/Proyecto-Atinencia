<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Domain\Exceptions;

use DomainException;

final class OverlappingCatalogValidityException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The validity period overlaps an existing catalog version for this course.');
    }
}
