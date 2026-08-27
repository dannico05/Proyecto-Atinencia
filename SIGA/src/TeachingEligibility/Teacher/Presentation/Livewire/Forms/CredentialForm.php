<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Presentation\Livewire\Forms;

use Illuminate\Support\Str;
use Livewire\Form;
use Src\TeachingEligibility\Teacher\Application\DTOs\CredentialDTO;
use Src\TeachingEligibility\Teacher\Domain\Entities\Credential;

final class CredentialForm extends Form
{
    public int $teacherId = 0;

    public string $degreeLevel = '';

    public string $institution = '';

    public int $graduationYear = 2026;

    public string $specialization = '';

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'teacherId' => ['required', 'integer', 'exists:teachers,id'],
            'degreeLevel' => ['required', 'in:Bachillerato,Licenciatura,Maestría,Doctorado'],
            'institution' => ['required', 'string', 'min:3', 'max:180', "regex:/^[\\pL\\pN][\\pL\\pN\\s.,()&'’\\/-]*$/u"],
            'graduationYear' => ['required', 'integer', 'min:1950', 'max:'.now()->year],
            'specialization' => ['required', 'string', 'exists:academic_specializations,name'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'graduationYear.max' => __('The graduation year cannot be in the future.'),
            'institution.regex' => __('The institution contains invalid characters.'),
            'specialization.exists' => __('Select a specialization from the official list.'),
        ];
    }

    public function fromEntity(Credential $credential): void
    {
        $this->teacherId = $credential->teacherId();
        $this->degreeLevel = $credential->degreeLevel();
        $this->institution = $credential->institution();
        $this->graduationYear = $credential->graduationYear();
        $this->specialization = $credential->specialization();
    }

    public function toDto(?int $id): CredentialDTO
    {
        return new CredentialDTO(
            $this->teacherId,
            $this->degreeLevel,
            Str::squish($this->institution),
            $this->graduationYear,
            Str::squish($this->specialization),
            $id,
        );
    }
}
