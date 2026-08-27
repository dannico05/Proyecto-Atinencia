<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Application\UseCases;

use RuntimeException;
use Src\TeachingEligibility\Catalog\Domain\Contracts\EligibilityCatalogRepositoryInterface;
use Src\TeachingEligibility\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\TeachingEligibility\Verification\Application\DTOs\EligibilityCheckDTO;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;
use Src\TeachingEligibility\Verification\Domain\Services\EligibilityEngine;
use Src\TeachingEligibility\Verification\Domain\ValueObjects\EligibilityDecision;

final readonly class VerifyAssignmentUseCase
{
    public function __construct(
        private TeacherRepositoryInterface $teachers,
        private EligibilityCatalogRepositoryInterface $catalogs,
        private EligibilityCheckRepositoryInterface $checks,
        private EligibilityEngine $engine,
    ) {}

    public function handle(EligibilityCheckDTO $dto, ?int $actorId): EligibilityDecision
    {
        $teacher = $this->teachers->find($dto->teacherId) ?? throw new RuntimeException('Teacher not found.');
        $group = $this->checks->groupContext($dto->groupId);
        $selection = $this->catalogs->selectForCourse($group['course_id'], $group['term_start']);
        $decision = $this->engine->verify($teacher->credentials(), $selection);

        $this->checks->record(
            $dto->groupId,
            $dto->teacherId,
            $decision,
            $selection->catalog?->id(),
            $actorId,
        );

        return $decision;
    }
}
