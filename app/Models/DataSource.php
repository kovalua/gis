<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = [
        'code',
        'name',
        'driver',
        'connection_name',
        'schema_name',
        'table_name',
        'geometry_column',
        'primary_key',
        'srid',
        'geometry_type',
        'title_column',
        'is_active',
        'metadata_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata_json' => 'array',
    ];

    public function layers(): HasMany
    {
        return $this->hasMany(Layer::class);
    }
}