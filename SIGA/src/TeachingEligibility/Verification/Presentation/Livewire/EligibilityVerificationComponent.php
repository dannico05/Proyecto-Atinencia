<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithExports;
use DateInterval;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Src\Shared\OfficialTime\Domain\Contracts\OfficialTimeProviderInterface;
use Src\TeachingEligibility\Verification\Application\UseCases\DecideManualAssignmentUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\ListEligibilityChecksUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\ResolveTechnicalNoteUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\StartTechnicalNoteUseCase;
use Src\TeachingEligibility\Verification\Application\UseCases\VerifyAssignmentUseCase;
use Src\TeachingEligibility\Verification\Domain\Entities\EligibilityVerification;
use Src\TeachingEligibility\Verification\Domain\Enums\EligibilityResult;
use Src\TeachingEligibility\Verification\Domain\Exceptions\AssignmentCannotBeReverifiedException;
use Src\TeachingEligibility\Verification\Presentation\Livewire\Forms\EligibilityCheckForm;
use Src\TeachingEligibility\Verification\Presentation\Livewire\Forms\TechnicalNoteForm;
use Src\TeachingEligibility\Verification\Presentation\Livewire\Forms\TechnicalNoteResolutionForm;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EligibilityVerificationComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithExports;
    use WithFileUploads;

    public bool $showModal = false;

    public bool $showResolutionModal = false;

    public EligibilityCheckForm $form;

    public TechnicalNoteForm $technicalNoteForm;

    public TechnicalNoteResolutionForm $resolutionForm;

    public function mount(): void
    {
        $this->authorize('viewAny', EligibilityVerification::class);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function openResolutionModal(int $assignmentId, string $outcome): void
    {
        $this->authorize('resolveTechnicalNote', EligibilityVerification::class);
        abort_unless(in_array($outcome, ['ratified', 'rejected'], true), 404);

        $this->resolutionForm->reset();
        $this->resolutionForm->assignmentId = $assignmentId;
        $this->resolutionForm->outcome = $outcome;
        $this->resetValidation();
        $this->showResolutionModal = true;
    }

    public function closeResolutionModal(): void
    {
        $this->showResolutionModal = false;
    }

    public function saveResolution(ResolveTechnicalNoteUseCase $useCase): void
    {
        $this->authorize('resolveTechnicalNote', EligibilityVerification::class);
        $this->resolutionForm->validate();

        $useCase->handle(
            $this->resolutionForm->assignmentId,
            $this->resolutionForm->outcome,
            $this->resolutionForm->reason,
            $this->actorId(),
        );

        $ratified = $this->resolutionForm->outcome === 'ratified';
        $this->showResolutionModal = false;
        $this->dispatch('toast', variant: $ratified ? 'success' : 'danger', text: $ratified
            ? __('The Technical Note was ratified by the University Council.')
            : __('The Technical Note was rejected by the University Council.'));
    }

    public function verify(VerifyAssignmentUseCase $useCase): void
    {
        $this->authorize('create', EligibilityVerification::class);
        $this->form->validate();
        try {
            $decision = $useCase->handle($this->form->toDto(), $this->actorId());
        } catch (AssignmentCannotBeReverifiedException $exception) {
            $this->dispatch('toast', variant: 'danger', text: __($exception->getMessage()));

            return;
        }

        $message = match ($decision->result) {
            EligibilityResult::Eligible => __('Eligible assignment. The teacher meets the required eligibility.'),
            EligibilityResult::NotEligible => __('Assignment blocked: the teacher does not meet the eligibility required for this course.'),
            EligibilityResult::NoCatalog => __('No catalog - pending manual approval.'),
            EligibilityResult::TechnicalNote => __('Technical note - ratification pending.'),
        };

        $this->dispatch('toast', variant: $decision->result === EligibilityResult::Eligible ? 'success' : 'warning', text: $message);
        $this->dispatch('eligibility:announce', message: $message, result: $decision->result->value);
    }

    public function openTechnicalNoteModal(int $assignmentId, OfficialTimeProviderInterface $officialTime): void
    {
        $this->authorize('createTechnicalNote', EligibilityVerification::class);
        $this->technicalNoteForm->reset();
        $this->technicalNoteForm->assignmentId = $assignmentId;
        $days = max(0, (int) config('teaching_eligibility.technical_note_sla_days', 0));
        if ($days > 0) {
            $this->technicalNoteForm->ratificationDeadline = $officialTime->now()
                ->add(new DateInterval('P'.$days.'D'))
                ->format('Y-m-d');
        }
        $this->resetValidation();
        $this->showModal = true;
    }

    public function saveTechnicalNote(StartTechnicalNoteUseCase $useCase): void
    {
        $this->authorize('createTechnicalNote', EligibilityVerification::class);
        $this->technicalNoteForm->validate();
        $document = $this->technicalNoteForm->document;
        $path = $document?->store('technical-notes', 'local');

        if (! is_string($path)) {
            $this->addError('technicalNoteForm.document', __('The signed technical criterion could not be stored.'));

            return;
        }

        try {
            $useCase->handle(
                $this->technicalNoteForm->assignmentId,
                $path,
                $this->technicalNoteForm->ratificationDeadline,
                $this->actorId(),
            );
        } catch (DomainException $exception) {
            Storage::disk('local')->delete($path);
            $this->addError('technicalNoteForm.document', __($exception->getMessage()));

            return;
        }

        $this->showModal = false;
        $this->dispatch('toast', variant: 'success', text: __('Technical note - ratification pending before the University Council.'));
    }

    public function decideManual(int $assignmentId, bool $approved, DecideManualAssignmentUseCase $useCase): void
    {
        $this->authorize('approveManual', EligibilityVerification::class);
        $useCase->handle(
            $assignmentId,
            $approved,
            $approved ? 'Manual approval by Academic Coordination.' : 'Manual rejection by Academic Coordination.',
            $this->actorId(),
        );
        $this->dispatch('toast', variant: $approved ? 'success' : 'danger', text: $approved ? __('Assignment approved.') : __('Assignment rejected.'));
    }

    public function downloadTechnicalNote(string $path): StreamedResponse
    {
        $this->authorize('viewAny', EligibilityVerification::class);
        abort_unless(preg_match('/\Atechnical-notes\/[A-Za-z0-9._-]+\.pdf\z/', $path) === 1, 404);
        abort_if(Storage::disk('local')->missing($path), 404);

        return Storage::disk('local')->download($path);
    }

    public function exportPdf(PdfExporterInterface $exporter, ListEligibilityChecksUseCase $useCase): StreamedResponse
    {
        $this->authorize('exportPdf', EligibilityVerification::class);

        return $this->streamPdf(
            __('Verification history'),
            $this->pdfHeaders(),
            $this->exportableRows($useCase),
            Str::slug(__('Verification history')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListEligibilityChecksUseCase $useCase): StreamedResponse
    {
        $this->authorize('exportExcel', EligibilityVerification::class);

        return $this->streamExcel(
            $this->excelHeaders(),
            $this->exportableRows($useCase),
            Str::slug(__('Verification history')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListEligibilityChecksUseCase $useCase): View
    {
        $history = $useCase->history();

        return view('teaching-eligibility.verification.livewire.eligibility-verification-component', [
            'history' => $history,
            'options' => $useCase->options(),
            'summary' => [
                'manual' => count(array_filter($history, static fn (array $row): bool => $row['assignment_status'] === 'pending_manual_approval')),
                'technical' => count(array_filter($history, static fn (array $row): bool => $row['assignment_status'] === 'technical_note_pending')),
                'expired' => count(array_filter($history, static fn (array $row): bool => $row['assignment_status'] === 'technical_note_expired')),
            ],
        ])->layout('components.layouts.dashboard', [
            'title' => __('Eligibility verification'),
            'subtitle' => __('Automatic, traceable teacher assignment decisions'),
        ]);
    }

    private function actorId(): ?int
    {
        return Auth::id() === null ? null : (int) Auth::id();
    }

    /** @return array<int, array<string, mixed>> */
    private function exportableRows(ListEligibilityChecksUseCase $useCase): array
    {
        $resultLabels = [
            'eligible' => __('Eligible'),
            'not_eligible' => __('Not eligible'),
            'technical_note' => __('Technical note'),
            'no_catalog' => __('No catalog'),
        ];
        $statusLabels = [
            'confirmed' => __('Confirmed'),
            'blocked' => __('Blocked'),
            'pending_manual_approval' => __('Pending manual approval'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'technical_note_pending' => __('Technical note - ratification pending'),
            'technical_note_expired' => __('Technical note expired'),
            'technical_note_ratified' => __('Technical note ratified'),
            'technical_note_rejected' => __('Technical note rejected'),
        ];

        return array_map(static function (array $row) use ($resultLabels, $statusLabels): array {
            $row['result_label'] = $resultLabels[$row['result']] ?? $row['result'];
            $row['status_label'] = $statusLabels[$row['assignment_status']] ?? $row['assignment_status'];
            $row['catalog_reference_export'] = $row['catalog_reference'] ?? __('No published catalog');
            $row['teacher_identity'] = $row['teacher'].' ('.$row['national_id'].')';
            $row['academic_context'] = $row['career'].' · '.$row['course'].' · '.__('Group').' '.$row['group'].' · '.$row['term'];
            $row['decision_summary'] = $row['result_label'].' · '.$row['status_label'];
            $row['catalog_summary'] = $row['catalog_version'] === null
                ? __('No published catalog')
                : 'v'.$row['catalog_version'].' · '.$row['catalog_agreement'].' · '.$row['catalog_gazette'];
            $timestamp = is_string($row['checked_at']) ? strtotime($row['checked_at']) : false;
            $row['checked_at_display'] = $timestamp === false ? '' : date('d/m/Y H:i', $timestamp);

            return $row;
        }, $useCase->history());
    }

    /** @return array<int, array{key: string, label: string, width: int}> */
    private function pdfHeaders(): array
    {
        return [
            ['key' => 'teacher_identity', 'label' => __('Teacher and national ID'), 'width' => 18],
            ['key' => 'academic_context', 'label' => __('Academic assignment'), 'width' => 29],
            ['key' => 'decision_summary', 'label' => __('Result and status'), 'width' => 15],
            ['key' => 'catalog_summary', 'label' => __('Applied catalog'), 'width' => 21],
            ['key' => 'checked_at_display', 'label' => __('Date'), 'width' => 12],
        ];
    }

    /** @return array<int, array{key: string, label: string, format?: callable}> */
    private function excelHeaders(): array
    {
        return [
            ['key' => 'teacher', 'label' => __('Teacher')],
            ['key' => 'national_id', 'label' => __('National ID')],
            ['key' => 'career', 'label' => __('Career')],
            ['key' => 'course', 'label' => __('Course')],
            ['key' => 'group', 'label' => __('Group')],
            ['key' => 'term', 'label' => __('Academic term')],
            ['key' => 'result_label', 'label' => __('Result')],
            ['key' => 'status_label', 'label' => __('Status')],
            ['key' => 'catalog_reference_export', 'label' => __('Applied catalog')],
            ['key' => 'checked_at', 'label' => __('Verification date')],
        ];
    }
}
