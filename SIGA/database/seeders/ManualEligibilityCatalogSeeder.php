<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicSpecialization;
use App\Models\Career;
use App\Models\Course;
use App\Models\EligibilityCatalog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ManualEligibilityCatalogSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const OFFERING_COURSE_MAP = [
        'MAN-ITI-RULE-001' => ['ITI-622', 'ITIEL-13'],
        'MAN-ITI-RULE-002' => ['ITI-321', 'ITI-322', 'ITI-323', 'ITI-621', 'ITI-623', 'ITI-625', 'ITI-922'],
        'MAN-ITI-RULE-005' => ['ITI-325', 'ITI-921'],
        'MAN-ITI-RULE-006' => ['ITI-923'],
        'MAN-ITI-RULE-009' => ['ITI-224', 'ITI-324', 'ITI-624'],
        'MAN-IGA-RULE-001' => ['IGA-101'],
    ];

    public function run(): void
    {
        $payload = $this->catalogPayload();
        $actorId = User::query()->where('email', 'admin@gmail.com')->value('id');
        $allSpecializations = [];
        $manualCourseCodes = [];

        foreach ($payload['careers'] as $careerData) {
            foreach ($careerData['courses'] as $courseData) {
                $manualCourseCodes[] = $courseData['code'];
            }
        }

        DB::transaction(function () use ($payload, $actorId, $manualCourseCodes, &$allSpecializations): void {
            $processedOfferingRules = [];

            Course::query()
                ->where('code', 'like', 'MAN-%')
                ->whereNotIn('code', $manualCourseCodes)
                ->whereDoesntHave('groups')
                ->delete();

            foreach ($payload['careers'] as $careerData) {
                $career = Career::query()->updateOrCreate(
                    ['code' => $careerData['code']],
                    ['name' => $careerData['name'], 'active' => true],
                );
                foreach ($careerData['courses'] as $courseData) {
                    $offeringCourseCodes = [];

                    foreach ($courseData['source_rule_codes'] as $sourceRuleCode) {
                        if (isset($processedOfferingRules[$sourceRuleCode])) {
                            continue;
                        }

                        $offeringCourseCodes = [
                            ...$offeringCourseCodes,
                            ...(self::OFFERING_COURSE_MAP[$sourceRuleCode] ?? []),
                        ];
                        $processedOfferingRules[$sourceRuleCode] = true;
                    }

                    $courses = $this->coursesForCatalog($career, $courseData, $offeringCourseCodes);

                    foreach ($courses as $course) {
                        $catalog = EligibilityCatalog::query()->updateOrCreate(
                            ['course_id' => $course->id, 'version' => 1],
                            [
                                'created_by' => $actorId,
                                'university_council_agreement' => $careerData['agreement'],
                                'gazette_number' => $careerData['gazette'],
                                'valid_from' => $careerData['valid_from'],
                                'valid_until' => $careerData['valid_until'],
                            ],
                        );

                        $catalog->specializations()->delete();
                        $rows = [];

                        foreach ($courseData['specializations'] as $specialization) {
                            $name = trim((string) $specialization);
                            if ($name === '') {
                                continue;
                            }

                            $allSpecializations[$name] = true;
                            $rows[] = [
                                'eligibility_catalog_id' => $catalog->id,
                                'name' => $name,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        foreach (array_chunk($rows, 500) as $chunk) {
                            DB::table('eligible_specializations')->insert($chunk);
                        }
                    }
                }
            }

        });

        $specializationRows = array_map(
            static fn (string $name): array => [
                'name' => $name,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            array_keys($allSpecializations),
        );

        foreach (array_chunk($specializationRows, 500) as $chunk) {
            AcademicSpecialization::query()->insertOrIgnore($chunk);
        }
    }

    /** @param array<string, mixed> $courseData
     * @param  array<int, string>  $offeringCourseCodes
     * @return array<int, Course>
     */
    private function coursesForCatalog(Career $career, array $courseData, array $offeringCourseCodes): array
    {
        $manualCourse = Course::query()->updateOrCreate(
            ['code' => $courseData['code']],
            ['career_id' => $career->id, 'name' => $courseData['name']],
        );

        if ($offeringCourseCodes !== []) {
            $offeringCourses = Course::query()->whereIn('code', $offeringCourseCodes)->orderBy('code')->get()->all();
            if (count($offeringCourses) !== count($offeringCourseCodes)) {
                throw new RuntimeException('An academic-offering course mapped to the Manual is missing.');
            }

            return [$manualCourse, ...$offeringCourses];
        }

        return [$manualCourse];
    }

    /** @return array<string, mixed> */
    private function catalogPayload(): array
    {
        $path = database_path('data/san_carlos_manual_catalog.json');
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException('The normalized Manual catalog could not be read.');
        }

        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload) || count($payload['careers'] ?? []) !== 12) {
            throw new RuntimeException('The normalized Manual catalog must contain exactly 12 supported careers.');
        }

        return $payload;
    }
}
