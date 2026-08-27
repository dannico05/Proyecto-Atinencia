<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Infrastructure\Persistence\Repositories;

use App\Models\AcademicSpecialization;
use App\Models\AuditLog;
use App\Models\Career;
use App\Models\Course;
use App\Models\EligibilityCatalog as CatalogModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\TeachingEligibility\Catalog\Domain\Contracts\EligibilityCatalogRepositoryInterface;
use Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog;
use Src\TeachingEligibility\Catalog\Domain\ValueObjects\CatalogSelection;

final class EloquentEligibilityCatalogRepository implements EligibilityCatalogRepositoryInterface
{
    public function selectForCourse(int $courseId, DateTimeImmutable $termStart): CatalogSelection
    {
        $date = $termStart->format('Y-m-d');
        $model = CatalogModel::query()
            ->with('specializations')
            ->where('course_id', $courseId)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('version')
            ->first();

        if ($model !== null) {
            return new CatalogSelection($this->toDomain($model), false);
        }

        $fallback = CatalogModel::query()
            ->with('specializations')
            ->where('course_id', $courseId)
            ->orderByDesc('valid_from')
            ->orderByDesc('version')
            ->first();

        return new CatalogSelection($fallback ? $this->toDomain($fallback) : null, $fallback !== null);
    }

    public function createVersion(EligibilityCatalog $catalog, ?int $actorId = null): EligibilityCatalog
    {
        return DB::transaction(function () use ($catalog, $actorId): EligibilityCatalog {
            $openVersion = CatalogModel::query()
                ->where('course_id', $catalog->courseId())
                ->whereNull('valid_until')
                ->whereDate('valid_from', '<', $catalog->validFrom()->format('Y-m-d'))
                ->lockForUpdate()
                ->orderByDesc('valid_from')
                ->first();

            if ($openVersion !== null) {
                $previousEnd = $catalog->validFrom()->modify('-1 day')->format('Y-m-d');
                $openVersion->update(['valid_until' => $previousEnd]);

                AuditLog::query()->create([
                    'user_id' => $actorId,
                    'auditable_type' => 'eligibility_catalog',
                    'auditable_id' => $openVersion->id,
                    'event' => 'validity_closed_by_new_version',
                    'changes' => ['valid_until' => ['previous' => null, 'new' => $previousEnd]],
                ]);
            }

            $lastVersion = (int) CatalogModel::query()
                ->where('course_id', $catalog->courseId())
                ->lockForUpdate()
                ->max('version');

            $model = CatalogModel::query()->create([
                'course_id' => $catalog->courseId(),
                'created_by' => $actorId,
                'version' => $lastVersion + 1,
                'university_council_agreement' => $catalog->agreement(),
                'gazette_number' => $catalog->gazetteNumber(),
                'valid_from' => $catalog->validFrom()->format('Y-m-d'),
                'valid_until' => $catalog->validUntil()?->format('Y-m-d'),
            ]);

            $model->specializations()->createMany(array_map(
                static fn (string $name): array => ['name' => $name],
                $catalog->specializations(),
            ));

            AuditLog::query()->create([
                'user_id' => $actorId,
                'auditable_type' => 'eligibility_catalog',
                'auditable_id' => $model->id,
                'event' => 'version_created',
                'changes' => ['version' => $model->version, 'course_id' => $model->course_id],
            ]);

            return $this->toDomain($model->load('specializations'));
        });
    }

    public function listVersions(?int $careerId = null, bool $withSpecializations = true): array
    {
        $query = CatalogModel::query()
            ->with('course.career')
            ->when($withSpecializations, fn ($builder) => $builder->with('specializations'))
            ->when(! $withSpecializations, fn ($builder) => $builder->withCount('specializations'))
            ->when($careerId !== null, fn ($builder) => $builder->whereHas(
                'course',
                fn ($courseQuery) => $courseQuery->where('career_id', $careerId),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $catalogRows = $query->get()->map(fn (CatalogModel $model): array => [
            'id' => $model->id,
            'career_id' => $model->course->career->id,
            'career' => $model->course->career->name,
            'course' => $this->courseLabel($model->course),
            'version' => $model->version,
            'agreement' => $model->university_council_agreement,
            'gazette' => $model->gazette_number,
            'valid_from' => $model->valid_from->format('Y-m-d'),
            'valid_until' => $model->valid_until?->format('Y-m-d') ?? __('No end date'),
            'specializations' => $withSpecializations ? $model->specializations->pluck('name')->all() : [],
            'specializations_count' => $withSpecializations
                ? $model->specializations->count()
                : (int) $model->specializations_count,
            'has_catalog' => true,
            'catalog_status' => __('Catalog available'),
        ])
            ->unique(static fn (array $row): string => implode('|', [
                $row['career_id'],
                Str::lower($row['course']),
                $row['version'],
            ]))
            ->values();

        $coursesWithoutCatalog = Course::query()
            ->with('career')
            ->whereDoesntHave('eligibilityCatalogs')
            ->when($careerId !== null, fn ($builder) => $builder->where('career_id', $careerId))
            ->orderBy('name')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => -$course->id,
                'career_id' => $course->career->id,
                'career' => $course->career->name,
                'course' => $this->courseLabel($course),
                'version' => null,
                'agreement' => null,
                'gazette' => null,
                'valid_from' => null,
                'valid_until' => null,
                'specializations' => [],
                'specializations_count' => 0,
                'has_catalog' => false,
                'catalog_status' => __('No catalog'),
            ])
            ->unique(static fn (array $row): string => $row['career_id'].'|'.Str::lower($row['course']));

        return $catalogRows
            ->concat($coursesWithoutCatalog)
            ->sortBy(static fn (array $row): string => Str::lower($row['course'].'|'.$row['career'].'|'.($row['version'] ?? 0)))
            ->values()
            ->all();
    }

    public function careerOptions(): array
    {
        return Career::query()
            ->where('active', true)
            ->whereHas('courses')
            ->orderBy('name')
            ->get()
            ->map(static fn (Career $career): array => [
                'id' => $career->id,
                'label' => $career->name,
            ])
            ->all();
    }

    public function courseOptions(?int $careerId = null): array
    {
        return Course::query()
            ->with('career')
            ->when($careerId !== null, fn ($query) => $query->where('career_id', $careerId))
            ->orderBy('name')
            ->orderBy('code')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'label' => $this->courseLabel($course),
            ])
            ->unique(static fn (array $course): string => Str::lower($course['label']))
            ->values()
            ->all();
    }

    public function specializationOptions(?int $courseId = null): array
    {
        if ($courseId === null || $courseId === 0) {
            return [];
        }

        return AcademicSpecialization::query()
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function hasOverlappingValidity(int $courseId, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        $overlaps = CatalogModel::query()
            ->where('course_id', $courseId)
            ->whereDate('valid_from', '<=', $until->format('Y-m-d'))
            ->where(function ($query) use ($from): void {
                $query->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $from->format('Y-m-d'));
            })
            ->orderByDesc('valid_from')
            ->get(['valid_from', 'valid_until']);

        if ($overlaps->count() !== 1) {
            return $overlaps->isNotEmpty();
        }

        $overlap = $overlaps->first();

        return $overlap->valid_until !== null
            || $from->format('Y-m-d') <= $overlap->valid_from->format('Y-m-d');
    }

    private function toDomain(CatalogModel $model): EligibilityCatalog
    {
        return new EligibilityCatalog(
            $model->id,
            $model->course_id,
            $model->version,
            $model->university_council_agreement,
            $model->gazette_number,
            new DateTimeImmutable($model->valid_from->format('Y-m-d')),
            $model->valid_until ? new DateTimeImmutable($model->valid_until->format('Y-m-d')) : null,
            $model->specializations->pluck('name')->all(),
        );
    }

    private function courseLabel(Course $course): string
    {
        return trim($course->name);
    }
}
