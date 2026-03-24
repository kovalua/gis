<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayerPermission extends Model
{
    protected $fillable = [
        'layer_id',
        'role_id',
        'can_view',
        'can_query',
        'can_create',
        'can_update',
        'can_delete',
        'can_export',
        'can_use_tiles',
        'can_identify',
        'can_attributes',
        'can_aggregate',
        'can_statistics',
        'can_read_style',
        'allowed_field_names_json',
        'denied_field_names_json',
        'allowed_style_codes_json',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_query' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_export' => 'boolean',
        'can_use_tiles' => 'boolean',
        'can_identify' => 'boolean',
        'can_attributes' => 'boolean',
        'can_aggregate' => 'boolean',
        'can_statistics' => 'boolean',
        'can_read_style' => 'boolean',
        'allowed_field_names_json' => 'array',
        'denied_field_names_json' => 'array',
        'allowed_style_codes_json' => 'array',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}