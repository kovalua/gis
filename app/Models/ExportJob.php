<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    protected $fillable = [
        'user_id',
        'layer_id',
        'saved_query_id',
        'result_snapshot_id',
        'export_type',
        'format',
        'status',
        'request_payload_json',
        'response_meta_json',
        'error_json',
        'disk',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'result_count',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'request_payload_json' => 'array',
        'response_meta_json' => 'array',
        'error_json' => 'array',
        'result_count' => 'integer',
        'file_size' => 'integer',
        'duration_ms' => 'decimal:3',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
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

    public function resultSnapshot(): BelongsTo
    {
        return $this->belongsTo(ResultSnapshot::class);
    }
}