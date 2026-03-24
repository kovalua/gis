<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use App\Models\LayerOperation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LayerOperationController extends Controller
{
    public function index(Layer $layer): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $layer->operations()->orderBy('operation_code')->get()
        ));
    }

    public function store(Request $request, Layer $layer): JsonResponse
    {
        $validated = $request->validate([
            'operation_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('layer_operations', 'operation_code')->where('layer_id', $layer->id),
            ],
            'is_enabled' => ['nullable', 'boolean'],
            'config_json' => ['nullable', 'array'],
        ]);

        $item = $layer->operations()->create($validated);

        return response()->json(ApiResponse::success($item), 201);
    }

    public function show(Layer $layer, int $operationId): JsonResponse
    {
        $operation = $layer->operations()->findOrFail($operationId);

        return response()->json(ApiResponse::success($operation));
    }

    public function update(Request $request, Layer $layer, int $operationId): JsonResponse
    {
        $operation = $layer->operations()->findOrFail($operationId);

        $validated = $request->validate([
            'operation_code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('layer_operations', 'operation_code')
                    ->where('layer_id', $layer->id)
                    ->ignore($operation->id),
            ],
            'is_enabled' => ['nullable', 'boolean'],
            'config_json' => ['nullable', 'array'],
        ]);

        $operation->update($validated);

        return response()->json(ApiResponse::success($operation->fresh()));
    }

    public function destroy(Layer $layer, int $operationId): JsonResponse
    {
        $operation = $layer->operations()->findOrFail($operationId);
        $operation->delete();

        return response()->json(ApiResponse::success(null, [], 'Layer operation deleted.'));
    }
}