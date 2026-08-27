<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Career $career
 * @property-read Collection<int, TeachingGroup> $groups
 * @property-read Collection<int, EligibilityCatalog> $eligibilityCatalogs
 */
class Course extends Model
{
    protected $fillable = ['career_id', 'code', 'name'];

    /** @return BelongsTo<Career, $this> */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /** @return HasMany<TeachingGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }

    /** @return HasMany<EligibilityCatalog, $this> */
    public function eligibilityCatalogs(): HasMany
    {
        return $this->hasMany(EligibilityCatalog::class);
    }
}
