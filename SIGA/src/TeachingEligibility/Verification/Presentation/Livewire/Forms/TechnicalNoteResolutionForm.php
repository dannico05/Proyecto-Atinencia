<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Presentation\Livewire\Forms;

use Livewire\Form;

final class TechnicalNoteResolutionForm extends Form
{
    public int $assignmentId = 0;

    public string $outcome = '';

    public string $reason = '';

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'assignmentId' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'outcome' => ['required', 'in:ratified,rejected'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $ratified = $this->outcome === 'ratified';

        return [
            'reason.required' => $ratified
                ? __('Enter the Council agreement or resolution that ratifies the Technical Note.')
                : __('Enter the reason why the Council rejected the Technical Note.'),
            'reason.min' => $ratified
                ? __('The Council agreement or resolution must contain at least 5 characters.')
                : __('The rejection reason must contain at least 5 characters.'),
        ];
    }
}
