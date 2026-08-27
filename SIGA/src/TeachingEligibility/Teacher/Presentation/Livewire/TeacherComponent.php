<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Src\TeachingEligibility\Teacher\Application\UseCases\DeleteCredentialUseCase;
use Src\TeachingEligibility\Teacher\Application\UseCases\DeleteTeacherUseCase;
use Src\TeachingEligibility\Teacher\Application\UseCases\FindTeacherUseCase;
use Src\TeachingEligibility\Teacher\Application\UseCases\ListTeachersUseCase;
use Src\TeachingEligibility\Teacher\Application\UseCases\SaveCredentialUseCase;
use Src\TeachingEligibility\Teacher\Application\UseCases\SaveTeacherUseCase;
use Src\TeachingEligibility\Teacher\Domain\Entities\Credential;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;
use Src\TeachingEligibility\Teacher\Domain\Exceptions\TeacherHasAssignmentsException;
use Src\TeachingEligibility\Teacher\Presentation\Livewire\Forms\CredentialForm;
use Src\TeachingEligibility\Teacher\Presentation\Livewire\Forms\TeacherForm;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TeacherComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    protected string $tableMode = 'client';

    public bool $showModal = false;

    public bool $showCredentialModal = false;

    public ?int $editingId = null;

    public ?int $editingCredentialId = null;

    public string $deleteError = '';

    public TeacherForm $form;

    public CredentialForm $credentialForm;

    public function mount(): void
    {
        $this->authorize('viewAny', Teacher::class);
        $this->sortKey = 'fullName';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Teacher::class);
        $this->editingId = null;
        $this->form->reset();
        $this->form->editingId = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindTeacherUseCase $useCase): void
    {
        $teacher = $useCase->handle($id);
        $this->authorize('update', $teacher);
        $this->editingId = $id;
        $this->form->editingId = $id;
        $this->form->fromEntity($teacher);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showCredentialModal = false;
    }

    public function updatedFormNationalId(mixed $value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        $this->form->nationalId = substr($digits, 0, 9);
    }

    public function save(SaveTeacherUseCase $useCase, ListTeachersUseCase $listUseCase): void
    {
        $this->form->validate();
        $this->authorize($this->editingId === null ? 'create' : 'update', Teacher::class);
        $created = $this->editingId === null;
        $savedTeacher = $useCase->handle($this->form->toDto(), $this->editingId, $this->actorId());
        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));

        if ($created) {
            $this->credentialForm->reset();
            $this->credentialForm->teacherId = (int) $savedTeacher->id();
            $this->editingCredentialId = null;
            $this->showCredentialModal = true;
        }

        $this->dispatch('toast', variant: 'success', text: $created
            ? __('Teacher created. Register the first academic credential to complete the profile.')
            : __('Teacher updated.'));
    }

    public function delete(int $id, DeleteTeacherUseCase $useCase, ListTeachersUseCase $listUseCase): bool
    {
        $this->authorize('delete', Teacher::class);
        $this->deleteError = '';

        try {
            $useCase->handle($id, $this->actorId());
        } catch (TeacherHasAssignmentsException) {
            $this->deleteError = __('Teacher cannot be deleted because teaching assignments reference the record. Deactivate it to preserve the verification history.');
            $this->dispatch('toast', variant: 'warning', text: __('Teacher cannot be deleted because teaching assignments reference the record.'));

            return false;
        }

        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Teacher deleted.'));

        return true;
    }

    public function openCredentialModal(int $teacherId, ?int $credentialId, FindTeacherUseCase $useCase): void
    {
        $teacher = $useCase->handle($teacherId);
        $this->authorize('update', $teacher);
        $this->credentialForm->reset();
        $this->credentialForm->teacherId = $teacherId;
        $this->editingCredentialId = $credentialId;

        if ($credentialId !== null) {
            $credential = collect($teacher->credentials())->first(fn (Credential $item): bool => $item->id() === $credentialId);
            if ($credential !== null) {
                $this->credentialForm->fromEntity($credential);
            }
        }

        $this->resetValidation();
        $this->showCredentialModal = true;
    }

    public function saveCredential(SaveCredentialUseCase $useCase, ListTeachersUseCase $listUseCase): void
    {
        $this->credentialForm->validate();
        $this->authorize('update', Teacher::class);
        $useCase->handle($this->credentialForm->toDto($this->editingCredentialId), $this->actorId());
        $this->showCredentialModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Credential saved.'));
    }

    public function deleteCredential(int $id, DeleteCredentialUseCase $useCase, ListTeachersUseCase $listUseCase): void
    {
        $this->authorize('update', Teacher::class);
        $useCase->handle($id, $this->actorId());
        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Credential deleted.'));
    }

    public function exportPdf(PdfExporterInterface $exporter, ListTeachersUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', Teacher::class);

        return $this->streamPdf(
            __('Teachers and academic credentials'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Teachers and academic credentials')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListTeachersUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Teacher::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Teachers and academic credentials')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListTeachersUseCase $useCase): View
    {
        return view('teaching-eligibility.teacher.livewire.teacher-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
            'specializations' => $useCase->specializations(),
        ])->layout('components.layouts.dashboard', [
            'title' => __('Teachers and credentials'),
            'subtitle' => __('Academic credentials used by the eligibility engine'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function freshRows(ListTeachersUseCase $useCase): array
    {
        return array_map(static fn (Teacher $teacher): array => [
            'id' => $teacher->id(),
            'nationalId' => $teacher->nationalId(),
            'nationalIdDigits' => preg_replace('/\D+/', '', $teacher->nationalId()) ?? '',
            'fullName' => $teacher->fullName(),
            'active' => $teacher->active(),
            'credentials' => array_map(static fn (Credential $credential): array => [
                'id' => $credential->id(),
                'degreeLevel' => $credential->degreeLevel(),
                'institution' => $credential->institution(),
                'graduationYear' => $credential->graduationYear(),
                'specialization' => $credential->specialization(),
            ], $teacher->credentials()),
            'credentialsCount' => count($teacher->credentials()),
            'profileComplete' => count($teacher->credentials()) > 0,
            'credentialsExport' => implode('; ', array_map(static fn (Credential $credential): string => sprintf(
                '%s - %s - %s (%d)',
                $credential->degreeLevel(),
                $credential->specialization(),
                $credential->institution(),
                $credential->graduationYear(),
            ), $teacher->credentials())),
        ], $useCase->handle());
    }

    /** @return array<int, array<string, mixed>> */
    private function exportableRows(ListTeachersUseCase $useCase, ?string $search): array
    {
        $rows = $this->freshRows($useCase);
        $term = trim((string) $search);

        if ($term === '' || ! Auth::user()->can('search', Teacher::class)) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $row): bool => Str::contains(
            Str::lower($row['nationalId'].' '.$row['nationalIdDigits'].' '.$row['fullName']),
            Str::lower($term),
        )));
    }

    /** @return array<int, array{key: string, label: string, format?: callable}> */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'nationalId', 'label' => __('National ID')],
            ['key' => 'fullName', 'label' => __('Full name')],
            ['key' => 'credentialsExport', 'label' => __('Credentials')],
            ['key' => 'active', 'label' => __('Status'), 'format' => static fn (bool $active): string => $active ? __('Active') : __('Inactive')],
        ];
    }

    private function actorId(): ?int
    {
        return Auth::id() === null ? null : (int) Auth::id();
    }
}
