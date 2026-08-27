<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read TeachingAssignment $assignment
 * @property-read EligibilityCatalog|null $catalog
 */
class EligibilityCheck extends Model
{
    protected $fillable = [
        'teaching_assignment_id', 'eligibility_catalog_id', 'executed_by',
        'result', 'provisional', 'reason',
    ];

    protected function casts(): array
    {
        return ['provisional' => 'boolean'];
    }

    /** @return BelongsTo<TeachingAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    /** @return BelongsTo<EligibilityCatalog, $this> */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(EligibilityCatalog::class, 'eligibility_catalog_id');
    }
}
