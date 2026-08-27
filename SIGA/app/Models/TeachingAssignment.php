<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read TeachingGroup $group
 * @property-read Teacher $teacher
 * @property-read Collection<int, EligibilityCheck> $checks
 * @property-read TechnicalNote|null $technicalNote
 */
class TeachingAssignment extends Model
{
    protected $fillable = [
        'teaching_group_id', 'teacher_id', 'status', 'decided_by',
        'decided_at', 'decision_reason',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** @return BelongsTo<TeachingGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return HasMany<EligibilityCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(EligibilityCheck::class);
    }

    /** @return HasOne<TechnicalNote, $this> */
    public function technicalNote(): HasOne
    {
        return $this->hasOne(TechnicalNote::class);
    }
}
