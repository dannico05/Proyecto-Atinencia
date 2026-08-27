<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Presentation\Livewire\Forms;

use App\Models\Teacher as TeacherModel;
use Closure;
use Illuminate\Support\Str;
use Livewire\Form;
use Src\TeachingEligibility\Teacher\Application\DTOs\TeacherDTO;
use Src\TeachingEligibility\Teacher\Domain\Entities\Teacher;

final class TeacherForm extends Form
{
    public string $nationalId = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $secondLastName = '';

    public bool $active = true;

    public ?int $editingId = null;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'nationalId' => [
                'required',
                'string',
                'size:9',
                'regex:/^[1-9]\d{8}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = TeacherModel::query()
                        ->where('national_id', $this->formatNationalId((string) $value))
                        ->when($this->editingId !== null, fn ($query) => $query->where('id', '!=', $this->editingId))
                        ->exists();

                    if ($exists) {
                        $fail(__('A teacher with this ID already exists.'));
                    }
                },
            ],
            'firstName' => ['required', 'string', 'min:2', 'max:80', "regex:/^\\pL+(?:[\\s'’-]\\pL+)*$/u"],
            'lastName' => ['required', 'string', 'min:2', 'max:80', "regex:/^\\pL+(?:[\\s'’-]\\pL+)*$/u"],
            'secondLastName' => ['nullable', 'string', 'min:2', 'max:80', "regex:/^\\pL+(?:[\\s'’-]\\pL+)*$/u"],
            'active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nationalId.size' => __('Enter the 9 digits of the Costa Rican national ID.'),
            'nationalId.regex' => __('Enter a structurally valid Costa Rican national ID using only 9 digits.'),
            'firstName.regex' => __('The name may only contain letters, spaces, apostrophes, and hyphens.'),
            'lastName.regex' => __('The surname may only contain letters, spaces, apostrophes, and hyphens.'),
            'secondLastName.regex' => __('The surname may only contain letters, spaces, apostrophes, and hyphens.'),
        ];
    }

    public function fromEntity(Teacher $teacher): void
    {
        $this->nationalId = preg_replace('/\D+/', '', $teacher->nationalId()) ?? '';
        $this->firstName = $teacher->firstName();
        $this->lastName = $teacher->lastName();
        $this->secondLastName = $teacher->secondLastName() ?? '';
        $this->active = $teacher->active();
    }

    public function toDto(): TeacherDTO
    {
        return new TeacherDTO(
            $this->formatNationalId($this->nationalId),
            Str::squish($this->firstName),
            Str::squish($this->lastName),
            Str::squish($this->secondLastName) !== '' ? Str::squish($this->secondLastName) : null,
            $this->active,
        );
    }

    private function formatNationalId(string $digits): string
    {
        return substr($digits, 0, 1).'-'.substr($digits, 1, 4).'-'.substr($digits, 5, 4);
    }
}
