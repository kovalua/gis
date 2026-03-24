<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'layer_id',
        'saved_query_id',
        'snapshot_type',
        'name',
        'description',
        'request_payload_json',
        'result_meta_json',
        'preview_json',
        'result_count',
        'is_public',
    ];

    protected $casts = [
        'request_payload_json' => 'array',
        'result_meta_json' => 'array',
        'preview_json' => 'array',
        'result_count' => 'integer',
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }

    public function savedQuery(): BelongsTo
    {
        return $this->belongsTo(SavedQuery::class);
    }

    public function exportJobs(): HasMany
    {
        return $this->hasMany(ExportJob::class);
    }
}