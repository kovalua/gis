<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayerField extends Model
{
    protected $fillable = [
        'layer_id',
        'name',
        'title',
        'data_type',
        'db_column',
        'is_nullable',
        'is_visible',
        'is_filterable',
        'is_sortable',
        'is_searchable',
        'is_editable',
        'visible_in_list',
        'visible_in_popup',
        'visible_in_form',
        'operators_json',
        'domain_json',
        'default_value',
        'sort_order',
        'metadata_json',
    ];

    protected $casts = [
        'is_nullable' => 'boolean',
        'is_visible' => 'boolean',
        'is_filterable' => 'boolean',
        'is_sortable' => 'boolean',
        'is_searchable' => 'boolean',
        'is_editable' => 'boolean',
        'visible_in_list' => 'boolean',
        'visible_in_popup' => 'boolean',
        'visible_in_form' => 'boolean',
        'operators_json' => 'array',
        'domain_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }
}