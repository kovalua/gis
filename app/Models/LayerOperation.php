<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayerOperation extends Model
{
    protected $fillable = [
        'layer_id',
        'operation_code',
        'is_enabled',
        'config_json',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config_json' => 'array',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }
}