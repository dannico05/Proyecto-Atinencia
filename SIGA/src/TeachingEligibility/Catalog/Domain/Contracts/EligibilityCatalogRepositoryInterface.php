<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Domain\Contracts;

use DateTimeImmutable;
use Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog;
use Src\TeachingEligibility\Catalog\Domain\ValueObjects\CatalogSelection;

interface EligibilityCatalogRepositoryInterface
{
    public function selectForCourse(int $courseId, DateTimeImmutable $termStart): CatalogSelection;

    public function createVersion(EligibilityCatalog $catalog, ?int $actorId = null): EligibilityCatalog;

    /** @return array<int, array<string, mixed>> */
    public function listVersions(?int $careerId = null, bool $withSpecializations = true): array;

    /** @return array<int, array{id:int,label:string}> */
    public function careerOptions(): array;

    /** @return array<int, array{id:int,label:string}> */
    public function courseOptions(?int $careerId = null): array;

    /** @return array<int, string> */
    public function specializationOptions(?int $courseId = null): array;

    public function hasOverlappingValidity(int $courseId, DateTimeImmutable $from, DateTimeImmutable $until): bool;
}
