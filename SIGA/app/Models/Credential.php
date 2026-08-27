<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read Teacher $teacher */
class Credential extends Model
{
    protected $fillable = ['teacher_id', 'degree_level', 'institution', 'graduation_year', 'specialization'];

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
