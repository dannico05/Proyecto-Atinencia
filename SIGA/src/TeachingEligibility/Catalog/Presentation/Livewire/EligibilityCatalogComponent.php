<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Src\TeachingEligibility\Catalog\Application\UseCases\CreateCatalogVersionUseCase;
use Src\TeachingEligibility\Catalog\Application\UseCases\ListCatalogsUseCase;
use Src\TeachingEligibility\Catalog\Domain\Entities\EligibilityCatalog;
use Src\TeachingEligibility\Catalog\Domain\Exceptions\OverlappingCatalogValidityException;
use Src\TeachingEligibility\Catalog\Presentation\Livewire\Forms\EligibilityCatalogForm;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EligibilityCatalogComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    protected string $tableMode = 'client';

    public bool $showModal = false;

    public int $selectedCareerId = 0;

    public int $modalCareerId = 0;

    public EligibilityCatalogForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', EligibilityCatalog::class);
        $this->sortKey = 'course';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', EligibilityCatalog::class);
        $this->form->reset();
        $this->modalCareerId = $this->selectedCareerId;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function updatedModalCareerId(): void
    {
        $this->form->courseId = 0;
        $this->form->specializations = [];
        $this->resetValidation();
    }

    public function updatedFormCourseId(): void
    {
        $this->form->specializations = [];
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateCatalogVersionUseCase $useCase, ListCatalogsUseCase $listUseCase): void
    {
        $this->authorize('create', EligibilityCatalog::class);
        $this->form->validate();
        try {
            $useCase->handle($this->form->toDto(), Auth::id() === null ? null : (int) Auth::id());
        } catch (OverlappingCatalogValidityException) {
            $this->addError('form.validFrom', __('The validity period overlaps an existing version for this course.'));

            return;
        }
        $this->showModal = false;
        $this->refreshTable($listUseCase->versions($this->selectedCareerId === 0 ? null : $this->selectedCareerId));
        $this->dispatch('toast', variant: 'success', text: __('Catalog version created.'));
    }

    public function exportPdf(PdfExporterInterface $exporter, ListCatalogsUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', EligibilityCatalog::class);

        return $this->streamPdf(
            __('Eligibility catalog'),
            $this->pdfHeaders(),
            $this->exportableRows($useCase, $search, false),
            Str::slug(__('Eligibility catalog')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListCatalogsUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', EligibilityCatalog::class);

        return $this->streamExcel(
            $this->excelHeaders(),
            $this->exportableRows($useCase, $search, true),
            Str::slug(__('Eligibility catalog')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListCatalogsUseCase $useCase): View
    {
        return view('teaching-eligibility.catalog.livewire.eligibility-catalog-component', [
            'tableMode' => 'client',
            'rows' => $useCase->versions($this->selectedCareerId === 0 ? null : $this->selectedCareerId),
            'careers' => $useCase->careers(),
            'courses' => $this->modalCareerId === 0 ? [] : $useCase->courses($this->modalCareerId),
            'specializationOptions' => $useCase->specializations($this->form->courseId),
        ])->layout('components.layouts.dashboard', [
            'title' => __('Eligibility catalog'),
            'subtitle' => __('Versioned eligibility rules by career and course'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function exportableRows(ListCatalogsUseCase $useCase, ?string $search, bool $withSpecializations): array
    {
        $careerId = $this->selectedCareerId === 0 ? null : $this->selectedCareerId;
        $rows = array_map(static function (array $row): array {
            $row['specializations_export'] = implode('; ', $row['specializations']);
            $row['specializations_summary'] = $row['has_catalog'] ? $row['specializations_count'].' '.__('registered') : '—';
            $row['official_reference'] = $row['has_catalog'] ? __('Agreement').': '.$row['agreement'].' · '.__('Gazette').': '.$row['gazette'] : '—';
            $row['validity_summary'] = $row['has_catalog'] ? $row['valid_from'].' - '.$row['valid_until'] : '—';
            $row['version_export'] = $row['has_catalog'] ? 'v'.$row['version'] : '—';

            return $row;
        }, $useCase->versions($careerId, $withSpecializations));
        $term = trim((string) $search);

        if ($term === '' || ! Auth::user()->can('search', EligibilityCatalog::class)) {
            return $rows;
        }

        $normalizedTerm = Str::lower(Str::ascii($term));

        return array_values(array_filter($rows, static fn (array $row): bool => Str::contains(
            Str::lower(Str::ascii(implode(' ', [$row['career'], $row['course'], $row['catalog_status'], $row['agreement'] ?? '', $row['gazette'] ?? '']))),
            $normalizedTerm,
        )));
    }

    /** @return array<int, array{key: string, label: string, width: int}> */
    private function pdfHeaders(): array
    {
        return [
            ['key' => 'course', 'label' => __('Course'), 'width' => 23],
            ['key' => 'career', 'label' => __('Career'), 'width' => 21],
            ['key' => 'catalog_status', 'label' => __('Catalog status'), 'width' => 15],
            ['key' => 'version_export', 'label' => __('Version'), 'width' => 7],
            ['key' => 'official_reference', 'label' => __('Official reference'), 'width' => 18],
            ['key' => 'validity_summary', 'label' => __('Validity'), 'width' => 13],
        ];
    }

    /** @return array<int, array{key: string, label: string, format?: callable}> */
    private function excelHeaders(): array
    {
        return [
            ['key' => 'course', 'label' => __('Course')],
            ['key' => 'career', 'label' => __('Career')],
            ['key' => 'catalog_status', 'label' => __('Catalog status')],
            ['key' => 'version_export', 'label' => __('Version')],
            ['key' => 'agreement', 'label' => __('University Council agreement')],
            ['key' => 'gazette', 'label' => __('Gazette number')],
            ['key' => 'valid_from', 'label' => __('Valid from')],
            ['key' => 'valid_until', 'label' => __('Valid until')],
            ['key' => 'specializations_export', 'label' => __('Eligible degrees or specializations')],
        ];
    }
}
