<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Career;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\TeachingGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class TeachingEligibilitySeeder extends Seeder
{
    public function run(): void
    {
        $informationTechnology = Career::query()->firstOrCreate(
            ['code' => 'ITI'],
            ['name' => 'INGENIERÍA EN TECNOLOGÍAS DE INFORMACIÓN - TECNOLOGÍAS DE INFORMACIÓN', 'active' => true],
        );
        $environmentalManagement = Career::query()->firstOrCreate(
            ['code' => 'IGA'],
            ['name' => 'INGENIERÍA EN GESTIÓN AMBIENTAL', 'active' => true],
        );

        foreach ([
            'AGRH' => 'ADMINISTRACIÓN Y GESTIÓN DE RECURSOS HUMANOS',
            'AA' => 'ADMINISTRACIÓN ADUANERA',
            'ISW' => 'INGENIERÍA DEL SOFTWARE - TECNOLOGÍAS INFORMÁTICAS',
            'CF' => 'CONTABILIDAD Y FINANZAS - CONTADURÍA PÚBLICA',
            'ASA' => 'ASISTENCIA ADMINISTRATIVA',
            'ILE' => 'INGLÉS COMO LENGUA EXTRANJERA',
            'AAI' => 'ADMINISTRACIÓN AGROINDUSTRIAL',
            'ISOA' => 'INGENIERÍA EN SALUD OCUPACIONAL Y AMBIENTE - SALUD OCUPACIONAL',
            'ITA' => 'INGENIERÍA EN TECNOLOGÍA DE ALIMENTOS - TECNOLOGÍA DE ALIMENTOS',
            'CE' => 'ADMINISTRACIÓN DEL COMERCIO EXTERIOR',
            'GCSC' => 'GESTIÓN DE CENTROS DE SERVICIOS COMPARTIDOS',
            'IMAIS' => 'INGENIERÍA EN MANTENIMIENTO AGROINDUSTRIAL SOSTENIBLE - MANTENIMIENTO AGROINDUSTRIAL SOSTENIBLE',
        ] as $code => $name) {
            Career::query()->firstOrCreate(['code' => $code], ['name' => $name, 'active' => true]);
        }

        $courses = [
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-224', 'name' => 'Matemática para Computación II', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-324', 'name' => 'Cálculo y Álgebra Lineal I', 'groups' => [1]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-321', 'name' => 'Programación II', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-322', 'name' => 'Diseño de Experiencias de Usuario', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-323', 'name' => 'Plataformas Tecnológicas II', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-325', 'name' => 'Fundamentos de las Organizaciones', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-621', 'name' => 'Tecnologías y Sistemas Web III', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-622', 'name' => 'Diseño de Redes', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-623', 'name' => 'Desarrollo de Aplicaciones para Dispositivos Móviles I', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-624', 'name' => 'Probabilidad y Estadística para Computación I', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-625', 'name' => 'Proyecto Integrador II: Infraestructura de TI', 'groups' => [1, 2]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-921', 'name' => 'Emprendedurismo de TI', 'groups' => [1]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-922', 'name' => 'Seguridad de TI II', 'groups' => [1]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITI-923', 'name' => 'Ética y Responsabilidad Social en TI', 'groups' => [1]],
            ['career_id' => $informationTechnology->id, 'code' => 'ITIEL-13', 'name' => 'Redes Programables', 'groups' => [1]],
            ['career_id' => $environmentalManagement->id, 'code' => 'IGA-101', 'name' => 'Introducción a la Gestión Ambiental', 'groups' => [1]],
        ];

        $term = AcademicTerm::query()->firstOrCreate(
            ['year' => 2026, 'term_number' => 2],
            ['starts_at' => '2026-05-11', 'ends_at' => '2026-08-29'],
        );

        foreach ($courses as $courseData) {
            $groups = $courseData['groups'];
            unset($courseData['groups']);
            $course = Course::query()->firstOrCreate(['code' => $courseData['code']], $courseData);

            foreach ($groups as $groupNumber) {
                TeachingGroup::query()->firstOrCreate([
                    'course_id' => $course->id,
                    'academic_term_id' => $term->id,
                    'group_number' => $groupNumber,
                ]);
            }
        }

        $this->seedNonCatalogCourses();

        $eligibleTeacher = Teacher::query()->firstOrCreate(
            ['national_id' => '1-1111-1111'],
            ['first_name' => 'Andrea', 'last_name' => 'Vargas', 'second_last_name' => 'Mora', 'active' => true],
        );
        $eligibleTeacher->credentials()->firstOrCreate([
            'degree_level' => 'Licenciatura',
            'specialization' => 'Ingeniería del Software',
        ], [
            'institution' => 'Universidad Técnica Nacional',
            'graduation_year' => 2022,
        ]);

        $nonEligibleTeacher = Teacher::query()->firstOrCreate(
            ['national_id' => '2-2222-2222'],
            ['first_name' => 'Carlos', 'last_name' => 'Rojas', 'second_last_name' => 'Solano', 'active' => true],
        );
        $nonEligibleTeacher->credentials()->firstOrCreate([
            'degree_level' => 'Licenciatura',
            'specialization' => 'Administración de Empresas',
        ], [
            'institution' => 'Universidad de Costa Rica',
            'graduation_year' => 2020,
        ]);

        $this->call(ManualEligibilityCatalogSeeder::class);
        $this->ensureEveryCourseHasATargetGroup($term);
    }

    private function seedNonCatalogCourses(): void
    {
        $contents = file_get_contents(database_path('data/san_carlos_non_catalog_courses.json'));

        if (! is_string($contents)) {
            throw new RuntimeException('The non-catalog San Carlos courses could not be read.');
        }

        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        foreach ($payload['careers'] ?? [] as $careerData) {
            $career = Career::query()->updateOrCreate(
                ['code' => $careerData['code']],
                ['name' => $careerData['name'], 'active' => true],
            );

            foreach ($careerData['courses'] as [$code, $name]) {
                Course::query()->updateOrCreate(
                    ['code' => $code],
                    ['career_id' => $career->id, 'name' => trim($name)],
                );
            }
        }
    }

    private function ensureEveryCourseHasATargetGroup(AcademicTerm $term): void
    {
        Course::query()
            ->with('career')
            ->get()
            ->sortBy(static fn (Course $course): string => $course->career->name.'|'.$course->name.'|'.$course->code)
            ->unique(static fn (Course $course): string => $course->career_id.'|'.Str::lower(trim($course->name)))
            ->each(static function (Course $course) use ($term): void {
                TeachingGroup::query()->firstOrCreate([
                    'course_id' => $course->id,
                    'academic_term_id' => $term->id,
                    'group_number' => 1,
                ]);
            });
    }
}
