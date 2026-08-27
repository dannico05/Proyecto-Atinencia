<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read Collection<int, Credential> $credentials */
class Teacher extends Model
{
    protected $fillable = ['user_id', 'national_id', 'first_name', 'last_name', 'second_last_name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<Credential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name, $this->second_last_name])));
    }
}
