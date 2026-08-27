<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Presentation\Livewire\Forms;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

final class TechnicalNoteForm extends Form
{
    public int $assignmentId = 0;

    public ?TemporaryUploadedFile $document = null;

    public string $ratificationDeadline = '';

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'assignmentId' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'document' => ['required', 'file', 'extensions:pdf', 'mimes:pdf', 'mimetypes:application/pdf,application/x-pdf', 'max:10240'],
            'ratificationDeadline' => ['required', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document.required' => __('You must attach the signed technical criterion that supports the Technical Note.'),
            'document.extensions' => __('The technical criterion must have a PDF extension.'),
            'document.mimes' => __('The technical criterion must be a PDF file.'),
            'document.mimetypes' => __('The technical criterion must be a valid PDF file.'),
            'document.max' => __('The PDF may not be larger than 10 MB.'),
            'ratificationDeadline.after' => __('The ratification deadline must be after today.'),
        ];
    }
}
