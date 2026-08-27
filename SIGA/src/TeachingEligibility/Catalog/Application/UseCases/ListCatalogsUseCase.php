<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Application\UseCases;

use Src\TeachingEligibility\Catalog\Domain\Contracts\EligibilityCatalogRepositoryInterface;

final readonly class ListCatalogsUseCase
{
    public function __construct(private EligibilityCatalogRepositoryInterface $repository) {}

    /** @return array<int, array<string, mixed>> */
    public function versions(?int $careerId = null, bool $withSpecializations = true): array
    {
        return $this->repository->listVersions($careerId, $withSpecializations);
    }

    /** @return array<int, array{id:int,label:string}> */
    public function careers(): array
    {
        return $this->repository->careerOptions();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function courses(?int $careerId = null): array
    {
        return $this->repository->courseOptions($careerId);
    }

    /** @return array<int, string> */
    public function specializations(?int $courseId = null): array
    {
        return $this->repository->specializationOptions($courseId);
    }
}
