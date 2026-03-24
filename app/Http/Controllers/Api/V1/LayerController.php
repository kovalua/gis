<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\ApiResponse;

class LayerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Layer::query()->with('dataSource')->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:layers,code'],
            'name' => ['required', 'string', 'max:255'],
            'data_source_id' => ['required', 'integer', 'exists:data_sources,id'],
            'layer_type' => ['required', 'string', 'max:50'],
            'geometry_type' => ['nullable', 'string', 'max:100'],
            'title_field' => ['nullable', 'string', 'max:255'],
            'description_field' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_queryable' => ['nullable', 'boolean'],
            'is_editable' => ['nullable', 'boolean'],
            'is_exportable' => ['nullable', 'boolean'],
            'min_zoom' => ['nullable', 'integer'],
            'max_zoom' => ['nullable', 'integer'],
            'filter_definition_json' => ['nullable', 'array'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $item = Layer::create($validated);

        return response()->json([
            'success' => true,
            'data' => $item->load('dataSource'),
        ], 201);
    }

    public function show(Layer $layer): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $layer->load('dataSource'),
        ]);
    }

    public function update(Request $request, Layer $layer): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('layers', 'code')->ignore($layer->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'data_source_id' => ['sometimes', 'required', 'integer', 'exists:data_sources,id'],
            'layer_type' => ['sometimes', 'required', 'string', 'max:50'],
            'geometry_type' => ['nullable', 'string', 'max:100'],
            'title_field' => ['nullable', 'string', 'max:255'],
            'description_field' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_queryable' => ['nullable', 'boolean'],
            'is_editable' => ['nullable', 'boolean'],
            'is_exportable' => ['nullable', 'boolean'],
            'min_zoom' => ['nullable', 'integer'],
            'max_zoom' => ['nullable', 'integer'],
            'filter_definition_json' => ['nullable', 'array'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $layer->update($validated);

        return response()->json([
            'success' => true,
            'data' => $layer->fresh()->load('dataSource'),
        ]);
    }

    public function destroy(Layer $layer): JsonResponse
    {
        $layer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Layer deleted.',
        ]);
    }
}