<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedQueryRole extends Model
{
    protected $fillable = [
        'saved_query_id',
        'role_id',
    ];

    public function savedQuery(): BelongsTo
    {
        return $this->belongsTo(SavedQuery::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}