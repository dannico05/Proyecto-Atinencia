<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSpecialization;
use App\Models\AuditLog;
use App\Models\Career;
use App\Models\Course;
use App\Models\EligibilityCatalog;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TeachingGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeachingEligibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\TeachingEligibility\Catalog\Presentation\Livewire\EligibilityCatalogComponent;
use Src\TeachingEligibility\Teacher\Presentation\Livewire\TeacherComponent;
use Src\TeachingEligibility\Verification\Application\DTOs\EligibilityCheckDTO;
use Src\TeachingEligibility\Verification\Application\UseCases\ResolveTechnicalNoteUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\StartTechnicalNoteUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\VerifyAssignmentUseCase;
use Src\TeachingEligibility\Verification\Domain\Contracts\EligibilityCheckRepositoryInterface;
use Src\TeachingEligibility\Verification\Presentation\Livewire\EligibilityVerificationComponent;
use Tests\TestCase;

final class TeachingEligibilityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class, TeachingEligibilitySeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'Administrador')->firstOrFail());
        $this->actingAs($user);

        Http::fake(['*' => Http::response(['datetime' => '2026-08-25T12:00:00-06:00'])]);
    }

    public function test_teacher_fields_and_specialization_are_validated(): void
    {
        $teacher = Teacher::query()->firstOrFail();

        Livewire::test(TeacherComponent::class)
            ->set('form.nationalId', '012345678')
            ->set('form.firstName', 'Ana2')
            ->set('form.lastName', 'Mora')
            ->call('save')
            ->assertHasErrors(['form.nationalId' => 'regex', 'form.firstName' => 'regex']);

        Livewire::test(TeacherComponent::class)
            ->set('form.nationalId', '311112222')
            ->set('form.firstName', 'Ana')
            ->set('form.lastName', 'Mora')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showCredentialModal', true);

        $this->assertDatabaseHas('teachers', ['national_id' => '3-1111-2222']);

        Livewire::test(TeacherComponent::class)
            ->set('form.nationalId', '111111111')
            ->set('form.firstName', 'Duplicada')
            ->set('form.lastName', 'Prueba')
            ->call('save')
            ->assertHasErrors(['form.nationalId']);

        Livewire::test(TeacherComponent::class)
            ->set('credentialForm.teacherId', $teacher->id)
            ->set('credentialForm.degreeLevel', 'Licenciatura')
            ->set('credentialForm.institution', 'Universidad Técnica Nacional')
            ->set('credentialForm.graduationYear', now()->year + 1)
            ->set('credentialForm.specialization', 'Área inventada')
            ->call('saveCredential')
            ->assertHasErrors([
                'credentialForm.graduationYear' => 'max',
                'credentialForm.specialization' => 'exists',
            ]);

        Livewire::test(TeacherComponent::class)
            ->set('credentialForm.teacherId', $teacher->id)
            ->set('credentialForm.degreeLevel', 'Diplomado')
            ->set('credentialForm.institution', 'Universidad Técnica Nacional')
            ->set('credentialForm.graduationYear', 2020)
            ->set('credentialForm.specialization', 'Ingeniería del Software')
            ->call('saveCredential')
            ->assertHasErrors(['credentialForm.degreeLevel' => 'in']);

        Livewire::test(TeacherComponent::class)
            ->set('credentialForm.teacherId', $teacher->id)
            ->set('credentialForm.degreeLevel', 'Licenciatura')
            ->set('credentialForm.institution', '<script>alert(1)</script>')
            ->set('credentialForm.graduationYear', 2020)
            ->set('credentialForm.specialization', 'Ingeniería del Software')
            ->call('saveCredential')
            ->assertHasErrors(['credentialForm.institution' => 'regex']);
    }

    public function test_incomplete_teachers_cannot_be_proposed_until_they_have_a_credential(): void
    {
        $incomplete = Teacher::query()->create([
            'national_id' => '4-1111-2222',
            'first_name' => 'Perfil',
            'last_name' => 'Incompleto',
            'active' => true,
        ]);

        $options = app(EligibilityCheckRepositoryInterface::class)->options();

        self::assertNotContains($incomplete->id, array_column($options['teachers'], 'id'));
        self::assertNotEmpty($options['groups']);
        self::assertTrue(collect($options['groups'])->every(static fn (array $group): bool => preg_match('/ · G\d+ · \d-\d{4}$/', $group['label']) !== 1));
    }

    public function test_teacher_updates_audit_each_changed_field_with_previous_and_new_values(): void
    {
        $teacher = Teacher::query()->where('national_id', '1-1111-1111')->firstOrFail();

        Livewire::test(TeacherComponent::class)
            ->call('openEditModal', $teacher->id)
            ->set('form.firstName', 'Adriana')
            ->call('save')
            ->assertHasNoErrors();

        $audit = AuditLog::query()
            ->where('auditable_type', 'teacher')
            ->where('auditable_id', $teacher->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        self::assertSame('Andrea', $audit->changes['fields']['first_name']['previous']);
        self::assertSame('Adriana', $audit->changes['fields']['first_name']['new']);
        self::assertCount(1, $audit->changes['fields']);
    }

    public function test_only_permissions_backed_by_real_actions_are_seeded(): void
    {
        foreach (['teachers', 'eligibility_catalogs', 'eligibility_checks'] as $module) {
            $this->assertDatabaseHas('permissions', ['name' => $module.'.export_pdf']);
            $this->assertDatabaseHas('permissions', ['name' => $module.'.export_excel']);
        }

        $this->assertDatabaseHas('permissions', ['name' => 'eligibility_checks.resolve_technical_note']);
        $coordinator = Role::query()->where('name', 'Coordinadora de Docencia')->firstOrFail();
        self::assertFalse($coordinator->permissions()->where('name', 'eligibility_checks.resolve_technical_note')->exists());

        foreach (['eligibility_catalogs.edit', 'eligibility_catalogs.delete', 'eligibility_checks.edit', 'eligibility_checks.delete', 'eligibility_checks.search'] as $permission) {
            $this->assertDatabaseMissing('permissions', ['name' => $permission]);
        }
    }

    public function test_unreferenced_teacher_is_deleted(): void
    {
        $teacher = Teacher::query()->where('national_id', '2-2222-2222')->firstOrFail();

        Livewire::test(TeacherComponent::class)->call('delete', $teacher->id);

        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    }

    public function test_catalog_uses_official_specializations_and_rejects_overlapping_validity(): void
    {
        $catalog = EligibilityCatalog::query()
            ->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))
            ->with('specializations')
            ->firstOrFail();
        self::assertCount(53, $catalog->specializations);
        self::assertSame('Acuerdo 10 - Sesión Ordinaria 22-2025', $catalog->university_council_agreement);
        self::assertSame('La Gaceta 186 del 06/10/2025', $catalog->gazette_number);

        Livewire::test(EligibilityCatalogComponent::class)
            ->set('form.courseId', $catalog->course_id)
            ->set('form.agreement', 'Acuerdo oficial 2026')
            ->set('form.gazetteNumber', 'Gaceta 100')
            ->set('form.validFrom', '2025-10-01')
            ->set('form.validUntil', '2026-12-01')
            ->set('form.specializations', [$catalog->specializations->firstOrFail()->name])
            ->call('save')
            ->assertHasErrors(['form.validFrom']);
    }

    public function test_academic_offering_contains_twelve_manual_careers_and_two_without_catalog(): void
    {
        self::assertSame([
            'AA', 'AAI', 'AGRH', 'ASA', 'CE', 'CF', 'GCSC', 'IGA', 'ILE', 'IMAIS', 'ISOA', 'ISW', 'ITA', 'ITI',
        ], Career::query()->orderBy('code')->pluck('code')->all());
        self::assertSame(24, Career::query()->where('code', 'GCSC')->firstOrFail()->courses()->count());
        self::assertSame(56, Career::query()->where('code', 'IMAIS')->firstOrFail()->courses()->count());
        self::assertSame(0, EligibilityCatalog::query()->whereHas('course.career', fn ($query) => $query->whereIn('code', ['GCSC', 'IMAIS']))->count());
        self::assertSame(0, EligibilityCatalog::query()
            ->where('university_council_agreement', 'like', 'DEMO%')
            ->orWhere('gazette_number', 'like', 'DEMO%')
            ->count());
    }

    public function test_catalog_combobox_filters_courses_and_specializations_by_career(): void
    {
        $career = Career::query()->where('code', 'ITI')->firstOrFail();
        $course = Course::query()->where('code', 'ITI-321')->firstOrFail();

        Livewire::test(EligibilityCatalogComponent::class)
            ->set('selectedCareerId', $career->id)
            ->assertViewHas('rows', static fn (array $rows): bool => $rows !== []
                && collect($rows)->every(fn (array $row): bool => $row['career_id'] === $career->id))
            ->set('modalCareerId', $career->id)
            ->assertViewHas('courses', static fn (array $courses): bool => $courses !== []
                && collect($courses)->contains(fn (array $option): bool => $option['id'] === $course->id))
            ->set('form.courseId', $course->id)
            ->assertViewHas('specializationOptions', static fn (array $specializations): bool => in_array(
                'Ingeniería del Software',
                $specializations,
                true,
            ));
    }

    public function test_first_catalog_for_a_course_can_be_created_from_master_specializations(): void
    {
        $career = Career::query()->where('code', 'GCSC')->firstOrFail();
        $course = Course::query()->where('code', 'GCSC-111')->firstOrFail();
        $specialization = AcademicSpecialization::query()->where('active', true)->orderBy('name')->firstOrFail();

        Livewire::test(EligibilityCatalogComponent::class)
            ->set('modalCareerId', $career->id)
            ->set('form.courseId', $course->id)
            ->assertViewHas('specializationOptions', static fn (array $options): bool => in_array(
                $specialization->name,
                $options,
                true,
            ))
            ->set('form.agreement', 'Acuerdo oficial GCSC-2026')
            ->set('form.gazetteNumber', 'La Gaceta 210')
            ->set('form.validFrom', '2026-09-01')
            ->set('form.validUntil', '2027-08-31')
            ->set('form.specializations', [$specialization->name])
            ->call('save')
            ->assertHasNoErrors();

        $catalog = EligibilityCatalog::query()->where('course_id', $course->id)->firstOrFail();
        self::assertSame(1, $catalog->version);
        $this->assertDatabaseHas('eligible_specializations', [
            'eligibility_catalog_id' => $catalog->id,
            'name' => $specialization->name,
        ]);
    }

    public function test_catalog_initially_lists_all_careers_with_clean_individual_course_names(): void
    {
        Livewire::test(EligibilityCatalogComponent::class)
            ->assertViewHas('rows', static function (array $rows): bool {
                $careers = collect($rows)->pluck('career_id')->unique();
                $courseNames = collect($rows)->pluck('course');

                return $careers->count() === 14
                    && collect($rows)->where('has_catalog', false)->every(
                        static fn (array $row): bool => $row['catalog_status'] === __('No catalog'),
                    )
                    && $courseNames->contains('Cálculo y Álgebra Lineal I')
                    && $courseNames->contains('Inglés Integrado I')
                    && $courseNames->contains('Inglés Integrado IV')
                    && $courseNames->doesntContain(
                        'inglés integrado I, II, III y IV; expresión oral I y II; lectura básica, lectura I y lectura y composición, composición I y II, escritura creativa',
                    )
                    && $courseNames->every(static fn (string $name): bool => preg_match('/^[A-ZÁÉÍÓÚÑ]/u', $name) === 1
                        && preg_match('/^[A-Z]+-?[A-Z]*-?\d+\s+-/', $name) !== 1
                        && mb_strlen($name) <= 80
                        && strpbrk($name, "\"'“”‘’_") === false
                        && trim($name) === $name);
            })
            ->assertSee(__('All careers'))
            ->assertSee(__('Download'));
    }

    public function test_every_catalog_course_is_available_as_a_target_group(): void
    {
        $catalogCourses = Course::query()
            ->with('career')
            ->whereHas('eligibilityCatalogs')
            ->get()
            ->map(static fn (Course $course): string => $course->career->name.'|'.$course->name)
            ->unique();
        $targetCourses = TeachingGroup::query()
            ->with('course.career')
            ->get()
            ->map(static fn (TeachingGroup $group): string => $group->course->career->name.'|'.$group->course->name)
            ->unique();

        self::assertSame([], $catalogCourses->diff($targetCourses)->values()->all());
        self::assertTrue($targetCourses->contains(
            'GESTIÓN DE CENTROS DE SERVICIOS COMPARTIDOS|Administración de Centros de Servicios Compartidos I',
        ));
        self::assertTrue($targetCourses->contains(
            'INGENIERÍA EN MANTENIMIENTO AGROINDUSTRIAL SOSTENIBLE - MANTENIMIENTO AGROINDUSTRIAL SOSTENIBLE|Automatización Avanzada',
        ));
    }

    public function test_a_new_catalog_version_closes_the_previous_open_ended_version(): void
    {
        $catalog = EligibilityCatalog::query()
            ->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))
            ->with('specializations')
            ->firstOrFail();

        Livewire::test(EligibilityCatalogComponent::class)
            ->set('form.courseId', $catalog->course_id)
            ->set('form.agreement', 'Acuerdo oficial 2026')
            ->set('form.gazetteNumber', 'La Gaceta 200')
            ->set('form.validFrom', '2026-06-01')
            ->set('form.validUntil', '2027-12-31')
            ->set('form.specializations', [$catalog->specializations->firstOrFail()->name])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('eligibility_catalogs', [
            'course_id' => $catalog->course_id,
            'version' => 1,
            'valid_until' => '2026-05-31 00:00:00',
        ]);
        $this->assertDatabaseHas('eligibility_catalogs', [
            'course_id' => $catalog->course_id,
            'version' => 2,
            'valid_from' => '2026-06-01 00:00:00',
        ]);
    }

    public function test_technical_note_requires_a_signed_pdf_and_future_deadline(): void
    {
        $teacher = Teacher::query()->firstOrFail();
        $group = TeachingGroup::query()->firstOrFail();
        $assignment = TeachingAssignment::query()->create([
            'teaching_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'status' => 'blocked',
        ]);
        $assignment->checks()->create([
            'result' => 'not_eligible',
            'provisional' => false,
            'reason' => 'Validation test.',
        ]);

        Livewire::test(EligibilityVerificationComponent::class)
            ->call('openTechnicalNoteModal', $assignment->id)
            ->set('technicalNoteForm.ratificationDeadline', '2026-08-25')
            ->call('saveTechnicalNote')
            ->assertHasErrors([
                'technicalNoteForm.document' => 'required',
                'technicalNoteForm.ratificationDeadline' => 'after',
            ]);
    }

    public function test_technical_note_accepts_any_valid_pdf_instead_of_a_fixed_document(): void
    {
        Storage::fake('local');
        $teacher = Teacher::query()->firstOrFail();
        $group = TeachingGroup::query()->firstOrFail();
        $assignment = TeachingAssignment::query()->create([
            'teaching_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'status' => 'blocked',
        ]);
        $assignment->checks()->create([
            'result' => 'not_eligible',
            'provisional' => false,
            'reason' => 'Generic PDF test.',
        ]);

        Livewire::test(EligibilityVerificationComponent::class)
            ->call('openTechnicalNoteModal', $assignment->id)
            ->set('technicalNoteForm.document', UploadedFile::fake()->create(
                'criterio-firmado-cualquier-nombre.pdf',
                100,
                'application/pdf',
            ))
            ->set('technicalNoteForm.ratificationDeadline', '2026-09-30')
            ->call('saveTechnicalNote')
            ->assertHasNoErrors()
            ->assertViewHas('history', static fn (array $history): bool => count($history) === 1
                && $history[0]['result'] === 'technical_note');

        $note = $assignment->technicalNote()->firstOrFail();
        self::assertStringStartsWith('technical-notes/', $note->document_path);
        self::assertStringEndsWith('.pdf', $note->document_path);
        Storage::disk('local')->assertExists($note->document_path);
    }

    public function test_no_catalog_interface_only_offers_manual_approval(): void
    {
        $teacher = Teacher::query()->where('national_id', '1-1111-1111')->firstOrFail();
        $group = TeachingGroup::query()
            ->whereHas('course', fn ($query) => $query->where('code', 'GCSC-111'))
            ->firstOrFail();

        app(VerifyAssignmentUseCase::class)->handle(
            new EligibilityCheckDTO($group->id, $teacher->id),
            null,
        );

        Livewire::test(EligibilityVerificationComponent::class)
            ->assertSee(__('No catalog'))
            ->assertSee(__('Approve'))
            ->assertSee(__('Reject'))
            ->assertDontSee(__('Start technical note'));
    }

    public function test_technical_note_rejects_excel_word_and_files_disguised_as_pdf(): void
    {
        Storage::fake('local');
        $teacher = Teacher::query()->firstOrFail();
        $group = TeachingGroup::query()->firstOrFail();
        $assignment = TeachingAssignment::query()->create([
            'teaching_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'status' => 'blocked',
        ]);
        $assignment->checks()->create([
            'result' => 'not_eligible',
            'provisional' => false,
            'reason' => 'Invalid document validation test.',
        ]);

        foreach ([
            UploadedFile::fake()->create('criterio.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            UploadedFile::fake()->create('criterio.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('criterio-disfrazado.pdf', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ] as $invalidDocument) {
            Livewire::test(EligibilityVerificationComponent::class)
                ->call('openTechnicalNoteModal', $assignment->id)
                ->set('technicalNoteForm.document', $invalidDocument)
                ->set('technicalNoteForm.ratificationDeadline', '2026-09-30')
                ->call('saveTechnicalNote')
                ->assertHasErrors(['technicalNoteForm.document']);
        }

        self::assertSame(0, $assignment->technicalNote()->count());
    }

    public function test_overdue_technical_note_is_automatically_shown_as_expired_in_the_interface(): void
    {
        $teacher = Teacher::query()->where('national_id', '2-2222-2222')->firstOrFail();
        $group = TeachingGroup::query()
            ->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))
            ->firstOrFail();

        app(VerifyAssignmentUseCase::class)->handle(
            new EligibilityCheckDTO($group->id, $teacher->id),
            null,
        );
        $assignment = TeachingAssignment::query()->firstOrFail();

        app(StartTechnicalNoteUseCase::class)->handle(
            $assignment->id,
            'technical-notes/signed.pdf',
            '2026-08-20',
            null,
        );

        Livewire::test(EligibilityVerificationComponent::class)
            ->assertSee(__('Technical note expired'))
            ->assertSee('2026-08-20');

        $this->assertDatabaseHas('technical_notes', [
            'teaching_assignment_id' => $assignment->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
            'status' => 'technical_note_expired',
        ]);
    }

    public function test_administrator_can_ratify_a_pending_technical_note_from_the_interface(): void
    {
        $teacher = Teacher::query()->where('national_id', '2-2222-2222')->firstOrFail();
        $group = TeachingGroup::query()
            ->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))
            ->firstOrFail();

        app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
        $assignment = TeachingAssignment::query()->firstOrFail();
        app(StartTechnicalNoteUseCase::class)->handle(
            $assignment->id,
            'technical-notes/signed.pdf',
            '2026-09-30',
            null,
        );

        Livewire::test(EligibilityVerificationComponent::class)
            ->call('openResolutionModal', $assignment->id, 'ratified')
            ->assertSee(__('Council agreement or resolution'))
            ->assertDontSee(__('Rejection reason'))
            ->set('resolutionForm.reason', 'Acuerdo del Consejo CU-123-2026')
            ->call('saveResolution')
            ->assertHasNoErrors()
            ->assertSee(__('Technical note ratified'))
            ->assertSee(__('Ratified'))
            ->assertSee('Acuerdo del Consejo CU-123-2026')
            ->assertDontSee(__('Register Council decision'))
            ->assertDontSee(__('Provisional due to future validity'));

        $this->assertDatabaseHas('technical_notes', [
            'teaching_assignment_id' => $assignment->id,
            'status' => 'ratified',
            'resolution_reason' => 'Acuerdo del Consejo CU-123-2026',
        ]);
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
            'status' => 'technical_note_ratified',
        ]);
        $this->assertDatabaseHas('eligibility_checks', [
            'teaching_assignment_id' => $assignment->id,
            'result' => 'technical_note',
            'provisional' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $assignment->id,
            'event' => 'technical_note_resolved',
        ]);
    }

    public function test_coordinator_cannot_resolve_a_technical_note(): void
    {
        $coordinator = User::factory()->create();
        $coordinator->roles()->attach(Role::query()->where('name', 'Coordinadora de Docencia')->firstOrFail());
        $this->actingAs($coordinator);

        Livewire::test(EligibilityVerificationComponent::class)
            ->call('openResolutionModal', 1, 'ratified')
            ->assertForbidden();
    }

    public function test_pending_technical_note_can_be_rejected_with_an_audited_reason(): void
    {
        $teacher = Teacher::query()->where('national_id', '2-2222-2222')->firstOrFail();
        $group = TeachingGroup::query()
            ->whereHas('course', fn ($query) => $query->where('code', 'ITI-321'))
            ->firstOrFail();

        app(VerifyAssignmentUseCase::class)->handle(new EligibilityCheckDTO($group->id, $teacher->id), null);
        $assignment = TeachingAssignment::query()->firstOrFail();
        app(StartTechnicalNoteUseCase::class)->handle(
            $assignment->id,
            'technical-notes/signed.pdf',
            '2026-09-30',
            null,
        );

        app(ResolveTechnicalNoteUseCase::class)->handle(
            $assignment->id,
            'rejected',
            'El Consejo rechazó la excepción CU-124-2026.',
            null,
        );

        $this->assertDatabaseHas('technical_notes', [
            'teaching_assignment_id' => $assignment->id,
            'status' => 'rejected',
            'resolution_reason' => 'El Consejo rechazó la excepción CU-124-2026.',
        ]);
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
            'status' => 'technical_note_rejected',
        ]);
    }
}
