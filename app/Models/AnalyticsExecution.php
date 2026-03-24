<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsExecution extends Model
{
    protected $fillable = [
        'saved_query_id',
        'layer_id',
        'user_id',
        'execution_type',
        'status',
        'request_payload_json',
        'response_meta_json',
        'error_json',
        'result_count',
        'duration_ms',
        'ip_address',
        'request_url',
    ];

    protected $casts = [
        'request_payload_json' => 'array',
        'response_meta_json' => 'array',
        'error_json' => 'array',
        'result_count' => 'integer',
        'duration_ms' => 'decimal:3',
    ];

    public function savedQuery(): BelongsTo
    {
        return $this->belongsTo(SavedQuery::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}