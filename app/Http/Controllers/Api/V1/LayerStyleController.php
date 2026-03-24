<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use App\Models\LayerStyle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LayerStyleController extends Controller
{
    public function index(Layer $layer): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $layer->styles()->orderBy('sort_order')->get()
        ));
    }

    public function store(Request $request, Layer $layer): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('layer_styles', 'code')->where('layer_id', $layer->id)],
            'name' => ['required', 'string', 'max:255'],
            'style_type' => ['nullable', 'string', 'max:100'],
            'style_json' => ['nullable', 'array'],
            'legend_json' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($validated['is_default'])) {
            $layer->styles()->update(['is_default' => false]);
        }

        $item = $layer->styles()->create($validated);

        return response()->json(ApiResponse::success($item), 201);
    }

    public function show(Layer $layer, int $styleId): JsonResponse
    {
        $style = $layer->styles()->findOrFail($styleId);

        return response()->json(ApiResponse::success($style));
    }

    public function update(Request $request, Layer $layer, int $styleId): JsonResponse
    {
        $style = $layer->styles()->findOrFail($styleId);

        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('layer_styles', 'code')
                    ->where('layer_id', $layer->id)
                    ->ignore($style->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'style_type' => ['nullable', 'string', 'max:100'],
            'style_json' => ['nullable', 'array'],
            'legend_json' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($validated['is_default'])) {
            $layer->styles()->where('id', '<>', $style->id)->update(['is_default' => false]);
        }

        $style->update($validated);

        return response()->json(ApiResponse::success($style->fresh()));
    }

    public function destroy(Layer $layer, int $styleId): JsonResponse
    {
        $style = $layer->styles()->findOrFail($styleId);
        $style->delete();

        return response()->json(ApiResponse::success(null, [], 'Layer style deleted.'));
    }
}