<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GisService extends Model
{
    protected $table = 'gis_services';

    protected $fillable = [
        'code',
        'name',
        'service_type',
        'endpoint_path',
        'is_public',
        'is_active',
        'publish_status',
        'config_json',
        'metadata_json',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'config_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function layers(): BelongsToMany
    {
        return $this->belongsToMany(
            Layer::class,
            'service_layers',
            'service_id',
            'layer_id'
        )->withPivot('sort_order')
         ->withTimestamps();
    }
}