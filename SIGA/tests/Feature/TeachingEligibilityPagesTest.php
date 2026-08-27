<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeachingEligibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Src\IdentityAccess\Permission\Presentation\Livewire\PermissionComponent;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Src\TeachingEligibility\Catalog\Presentation\Livewire\EligibilityCatalogComponent;
use Src\TeachingEligibility\Teacher\Presentation\Livewire\TeacherComponent;
use Src\TeachingEligibility\Verification\Presentation\Livewire\EligibilityVerificationComponent;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class TeachingEligibilityPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            TeachingEligibilitySeeder::class,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'Administrador')->firstOrFail());
        $this->actingAs($user);

        Http::fake([
            '*' => Http::response(['datetime' => '2026-08-23T12:00:00-06:00']),
        ]);
    }

    public function test_teaching_eligibility_pages_render_in_spanish(): void
    {
        $this->get(route('teaching-eligibility.teachers.index'))
            ->assertOk()
            ->assertSeeText('Docentes y atestados')
            ->assertSeeText('Exportar a PDF')
            ->assertSeeText('Exportar a Excel');

        $this->get(route('teaching-eligibility.catalogs.index'))
            ->assertOk()
            ->assertSeeText('Catálogo de cursos')
            ->assertDontSeeText('¿De dónde se obtienen el acuerdo y el número de La Gaceta?')
            ->assertSeeText('Filtrar cursos por carrera')
            ->assertSeeText('Seleccione una carrera');

        $this->get(route('teaching-eligibility.verifications.index'))
            ->assertOk()
            ->assertSeeText('Verificación de atinencia')
            ->assertSeeText('Proponer y verificar automáticamente')
            ->assertSeeText('Exportar a Excel');
    }

    public function test_every_pdf_export_action_returns_a_download(): void
    {
        $pdfExporter = new class implements PdfExporterInterface
        {
            /** @var array<string, string> */
            public array $documents = [];

            public function fromHtml(string $html, string $filename, string $paperSize = 'a4'): StreamedResponse
            {
                $this->documents[$filename] = $html;

                return response()->streamDownload(
                    static fn () => print "%PDF-1.4\n%%EOF",
                    $filename,
                    ['Content-Type' => 'application/pdf'],
                );
            }
        };
        $this->app->instance(PdfExporterInterface::class, $pdfExporter);

        foreach ([
            RoleComponent::class,
            PermissionComponent::class,
            TeacherComponent::class,
            EligibilityCatalogComponent::class,
            EligibilityVerificationComponent::class,
        ] as $component) {
            $test = Livewire::test($component);

            $test
                ->call('exportPdf')
                ->assertFileDownloaded();
        }

        $catalogHtml = $pdfExporter->documents['catalogo-de-cursos.pdf'];
        self::assertStringContainsString('Estado del catálogo', $catalogHtml);
        self::assertStringContainsString('Sin catálogo', $catalogHtml);
        self::assertStringNotContainsString('Administración de las Tecnologías de Información;', $catalogHtml);

        $historyHtml = $pdfExporter->documents['historial-de-verificaciones.pdf'];
        self::assertStringContainsString('Docente y cédula', $historyHtml);
        self::assertStringContainsString('<th>Fecha</th>', $historyHtml);
        self::assertStringNotContainsString('<th>Fecha de verificación</th>', $historyHtml);
    }

    public function test_technical_note_download_rejects_path_traversal(): void
    {
        Livewire::test(EligibilityVerificationComponent::class)
            ->call('downloadTechnicalNote', 'technical-notes/../.env')
            ->assertNotFound();
    }
}
