<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayerStyle extends Model
{
    protected $fillable = [
        'layer_id',
        'code',
        'name',
        'style_type',
        'style_json',
        'legend_json',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'style_json' => 'array',
        'legend_json' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }
}