<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'layer_code',
        'old_values',
        'new_values',
        'meta',
        'user_id',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];
}