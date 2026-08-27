<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Catalog\Presentation\Livewire\Forms;

use Livewire\Form;
use Src\TeachingEligibility\Catalog\Application\DTOs\EligibilityCatalogDTO;

final class EligibilityCatalogForm extends Form
{
    public int $courseId = 0;

    public string $agreement = '';

    public string $gazetteNumber = '';

    public string $validFrom = '';

    public string $validUntil = '';

    /** @var array<int, string> */
    public array $specializations = [];

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'courseId' => ['required', 'integer', 'exists:courses,id'],
            'agreement' => ['required', 'string', 'min:5', 'max:160'],
            'gazetteNumber' => ['required', 'string', 'min:3', 'max:80'],
            'validFrom' => ['required', 'date'],
            'validUntil' => ['required', 'date', 'after_or_equal:validFrom'],
            'specializations' => ['required', 'array', 'min:1'],
            'specializations.*' => ['required', 'string', 'distinct', 'exists:academic_specializations,name'],
        ];
    }

    public function toDto(): EligibilityCatalogDTO
    {
        return new EligibilityCatalogDTO(
            $this->courseId,
            trim($this->agreement),
            trim($this->gazetteNumber),
            $this->validFrom,
            $this->validUntil,
            $this->specializations,
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'validUntil.required' => __('The end of the validity period is required.'),
            'validUntil.after_or_equal' => __('The end date must be equal to or after the start date.'),
            'specializations.required' => __('Select at least one eligible specialization.'),
            'specializations.min' => __('Select at least one eligible specialization.'),
        ];
    }
}
