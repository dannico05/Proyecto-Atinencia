<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property-read Course $course
 * @property-read Collection<int, EligibleSpecialization> $specializations
 */
class EligibilityCatalog extends Model
{
    protected $fillable = [
        'course_id', 'created_by', 'version', 'university_council_agreement',
        'gazette_number', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<EligibleSpecialization, $this> */
    public function specializations(): HasMany
    {
        return $this->hasMany(EligibleSpecialization::class);
    }
}
