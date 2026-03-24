<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedQuery extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'layer_id',
        'owner_user_id',
        'query_type',
        'visibility',
        'is_active',
        'payload_json',
        'metadata_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payload_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'saved_query_roles',
            'saved_query_id',
            'role_id'
        )->withTimestamps();
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AnalyticsExecution::class);
    }
}