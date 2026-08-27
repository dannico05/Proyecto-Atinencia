<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Presentation\Livewire\Forms;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Form;
use Src\TeachingEligibility\Verification\Application\DTOs\EligibilityCheckDTO;

final class EligibilityCheckForm extends Form
{
    public int $teacherId = 0;

    public int $groupId = 0;

    /** @return array<string, array<int, string|Exists>> */
    public function rules(): array
    {
        return [
            'teacherId' => ['required', 'integer', Rule::exists('teachers', 'id')->where(
                fn ($query) => $query
                    ->where('active', true)
                    ->whereExists(fn ($credentials) => $credentials
                        ->selectRaw('1')
                        ->from('credentials')
                        ->whereColumn('credentials.teacher_id', 'teachers.id')),
            )],
            'groupId' => ['required', 'integer', 'exists:teaching_groups,id'],
        ];
    }

    public function toDto(): EligibilityCheckDTO
    {
        return new EligibilityCheckDTO($this->groupId, $this->teacherId);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'teacherId.exists' => __('Select an active teacher with at least one registered academic credential.'),
        ];
    }
}
