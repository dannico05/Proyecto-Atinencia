<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Application\UseCases;

use DateTimeImmutable;
use Src\TeachingEligibility\Catalog\Application\DTOs\EligibilityCatalogDTO;
use Src\TeachingEligibility\Catalog\Domain\Contracts\EligibilityCatalogRepositoryInterface;
use Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog;
use Src\TeachingEligibility\Catalog\Domain\Exceptions\OverlappingCatalogValidityException;

final readonly class CreateCatalogVersionUseCase
{
    public function __construct(private EligibilityCatalogRepositoryInterface $repository) {}

    public function handle(EligibilityCatalogDTO $dto, ?int $actorId): EligibilityCatalog
    {
        $validFrom = new DateTimeImmutable($dto->validFrom);
        $validUntil = new DateTimeImmutable((string) $dto->validUntil);

        if ($this->repository->hasOverlappingValidity($dto->courseId, $validFrom, $validUntil)) {
            throw new OverlappingCatalogValidityException;
        }

        $catalog = new EligibilityCatalog(
            null,
            $dto->courseId,
            0,
            trim($dto->agreement),
            trim($dto->gazetteNumber),
            $validFrom,
            $validUntil,
            array_values(array_unique(array_filter(array_map('trim', $dto->specializations)))),
        );

        return $this->repository->createVersion($catalog, $actorId);
    }
}
