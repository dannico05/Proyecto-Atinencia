<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Domain\Entities;

final class Teacher
{
    /** @param array<int, Credential> $credentials */
    private function __construct(
        private readonly ?int $id,
        private string $nationalId,
        private string $firstName,
        private string $lastName,
        private ?string $secondLastName,
        private bool $active,
        private array $credentials,
    ) {}

    public static function create(
        string $nationalId,
        string $firstName,
        string $lastName,
        ?string $secondLastName,
        bool $active = true,
    ): self {
        return new self(null, $nationalId, $firstName, $lastName, $secondLastName, $active, []);
    }

    /** @param array<int, Credential> $credentials */
    public static function reconstitute(
        int $id,
        string $nationalId,
        string $firstName,
        string $lastName,
        ?string $secondLastName,
        bool $active,
        array $credentials,
    ): self {
        return new self($id, $nationalId, $firstName, $lastName, $secondLastName, $active, $credentials);
    }

    public function updateProfile(
        string $nationalId,
        string $firstName,
        string $lastName,
        ?string $secondLastName,
        bool $active,
    ): void {
        $this->nationalId = $nationalId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->secondLastName = $secondLastName;
        $this->active = $active;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function nationalId(): string
    {
        return $this->nationalId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function secondLastName(): ?string
    {
        return $this->secondLastName;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->firstName, $this->lastName, $this->secondLastName])));
    }

    /** @return array<int, Credential> */
    public function credentials(): array
    {
        return $this->credentials;
    }
}
