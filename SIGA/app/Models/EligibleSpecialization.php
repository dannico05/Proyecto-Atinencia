<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read EligibilityCatalog $catalog */
class EligibleSpecialization extends Model
{
    protected $fillable = ['eligibility_catalog_id', 'name'];

    /** @return BelongsTo<EligibilityCatalog, $this> */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(EligibilityCatalog::class, 'eligibility_catalog_id');
    }
}
