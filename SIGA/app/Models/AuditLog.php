<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'auditable_type', 'auditable_id', 'event', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }
}
