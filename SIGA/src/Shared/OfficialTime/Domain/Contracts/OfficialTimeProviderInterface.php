<?php

declare(strict_types=1);

namespace Src\Shared\OfficialTime\Domain\Contracts;

use DateTimeImmutable;

interface OfficialTimeProviderInterface
{
    public function now(): DateTimeImmutable;
}
