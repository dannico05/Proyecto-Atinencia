<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $ratification_deadline
 * @property Carbon|null $resolved_at
 * @property-read TeachingAssignment $assignment
 * @property-read User|null $resolver
 */
class TechnicalNote extends Model
{
    protected $fillable = [
        'teaching_assignment_id', 'created_by', 'document_path',
        'ratification_deadline', 'status', 'resolved_by', 'resolved_at',
        'resolution_reason',
    ];

    protected function casts(): array
    {
        return ['ratification_deadline' => 'date', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<TeachingAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
