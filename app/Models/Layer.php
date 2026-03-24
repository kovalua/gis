<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Layer extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'data_source_id',
        'layer_type',
        'geometry_type',
        'title_field',
        'description_field',
        'group_code',
        'is_active',
        'is_public',
        'is_queryable',
        'is_editable',
        'is_exportable',
        'min_zoom',
        'max_zoom',
        'default_visibility',
        'catalog_order',
        'filter_definition_json',
        'metadata_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_queryable' => 'boolean',
        'is_editable' => 'boolean',
        'is_exportable' => 'boolean',
        'default_visibility' => 'boolean',
        'filter_definition_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            GisService::class,
            'service_layers',
            'layer_id',
            'service_id'
        )->withPivot('sort_order')
         ->withTimestamps();
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(LayerPermission::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(LayerField::class)->orderBy('sort_order');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(LayerOperation::class);
    }

    public function styles(): HasMany
    {
        return $this->hasMany(LayerStyle::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}