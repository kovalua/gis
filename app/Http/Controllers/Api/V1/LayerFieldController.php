<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use App\Models\LayerField;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LayerFieldController extends Controller
{
    public function index(Layer $layer): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $layer->fields()->orderBy('sort_order')->get()
        ));
    }

    public function store(Request $request, Layer $layer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('layer_fields', 'name')->where('layer_id', $layer->id)],
            'title' => ['required', 'string', 'max:255'],
            'data_type' => ['required', 'string', 'max:100'],
            'db_column' => ['nullable', 'string', 'max:255'],
            'is_nullable' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_sortable' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'is_editable' => ['nullable', 'boolean'],
            'visible_in_list' => ['nullable', 'boolean'],
            'visible_in_popup' => ['nullable', 'boolean'],
            'visible_in_form' => ['nullable', 'boolean'],
            'operators_json' => ['nullable', 'array'],
            'domain_json' => ['nullable', 'array'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $item = $layer->fields()->create($validated);

        return response()->json(ApiResponse::success($item), 201);
    }

    public function show(Layer $layer, int $fieldId): JsonResponse
    {
        $field = $layer->fields()->findOrFail($fieldId);

        return response()->json(ApiResponse::success($field));
    }

    public function update(Request $request, Layer $layer, int $fieldId): JsonResponse
    {
        $field = $layer->fields()->findOrFail($fieldId);

        $validated = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('layer_fields', 'name')
                    ->where('layer_id', $layer->id)
                    ->ignore($field->id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'data_type' => ['sometimes', 'required', 'string', 'max:100'],
            'db_column' => ['nullable', 'string', 'max:255'],
            'is_nullable' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_sortable' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'is_editable' => ['nullable', 'boolean'],
            'visible_in_list' => ['nullable', 'boolean'],
            'visible_in_popup' => ['nullable', 'boolean'],
            'visible_in_form' => ['nullable', 'boolean'],
            'operators_json' => ['nullable', 'array'],
            'domain_json' => ['nullable', 'array'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $field->update($validated);

        return response()->json(ApiResponse::success($field->fresh()));
    }

    public function destroy(Layer $layer, int $fieldId): JsonResponse
    {
        $field = $layer->fields()->findOrFail($fieldId);
        $field->delete();

        return response()->json(ApiResponse::success(null, [], 'Layer field deleted.'));
    }

    public function syncFromDataSource(Layer $layer): JsonResponse
    {
        $layer->load('dataSource');

        $ds = $layer->dataSource;
        if (!$ds) {
            return response()->json(ApiResponse::error('LAYER_HAS_NO_DATA_SOURCE', 'Layer has no data source.'), 422);
        }

        $rows = DB::connection($ds->connection_name)->select(
            "
            SELECT column_name, data_type, is_nullable
            FROM information_schema.columns
            WHERE table_schema = ?
              AND table_name = ?
            ORDER BY ordinal_position
            ",
            [$ds->schema_name, $ds->table_name]
        );

        $synced = [];

        foreach ($rows as $index => $row) {
            $column = (string) $row->column_name;

            if ($column === $ds->geometry_column) {
                continue;
            }

            $field = $layer->fields()->updateOrCreate(
                ['name' => $column],
                [
                    'title' => $this->makeTitle($column),
                    'data_type' => (string) $row->data_type,
                    'db_column' => $column,
                    'is_nullable' => strtoupper((string) $row->is_nullable) === 'YES',
                    'is_visible' => true,
                    'is_filterable' => true,
                    'is_sortable' => true,
                    'is_searchable' => $this->isSearchableType((string) $row->data_type),
                    'is_editable' => false,
                    'visible_in_list' => true,
                    'visible_in_popup' => true,
                    'visible_in_form' => false,
                    'operators_json' => $this->defaultOperators((string) $row->data_type),
                    'sort_order' => ($index + 1) * 10,
                ]
            );

            $synced[] = $field;
        }

        return response()->json(ApiResponse::success([
            'layer_id' => $layer->id,
            'synced_count' => count($synced),
            'fields' => $synced,
        ], [], 'Fields synced from data source.'));
    }

    protected function makeTitle(string $column): string
    {
        return ucwords(str_replace('_', ' ', $column));
    }

    protected function isSearchableType(string $dataType): bool
    {
        $type = strtolower($dataType);

        return in_array($type, [
            'character varying',
            'character',
            'text',
            'varchar',
            'char',
        ], true);
    }

    protected function defaultOperators(string $dataType): array
    {
        $type = strtolower($dataType);

        if (in_array($type, ['smallint', 'integer', 'bigint', 'numeric', 'decimal', 'real', 'double precision'], true)) {
            return ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'in'];
        }

        if (in_array($type, ['date', 'timestamp without time zone', 'timestamp with time zone'], true)) {
            return ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between'];
        }

        if (in_array($type, ['boolean'], true)) {
            return ['eq', 'neq'];
        }

        return ['eq', 'neq', 'like', 'ilike', 'in'];
    }
}