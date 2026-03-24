<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\ApiResponse;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => GisService::query()->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:gis_services,code'],
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'string', 'max:100'],
            'endpoint_path' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'publish_status' => ['nullable', 'string', 'max:100'],
            'config_json' => ['nullable', 'array'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $item = GisService::create($validated);

        return response()->json(ApiResponse::success($item));
    }

    public function show(GisService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    public function update(Request $request, GisService $service): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('gis_services', 'code')->ignore($service->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'service_type' => ['sometimes', 'required', 'string', 'max:100'],
            'endpoint_path' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'publish_status' => ['nullable', 'string', 'max:100'],
            'config_json' => ['nullable', 'array'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $service->update($validated);

        return response()->json([
            'success' => true,
            'data' => $service->fresh(),
        ]);
    }

    public function destroy(GisService $service): JsonResponse
    {
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'GIS service deleted.',
        ]);
    }

    public function attachLayers(Request $request, GisService $service): JsonResponse
    {
        $validated = $request->validate([
            'layers' => ['required', 'array'],
            'layers.*.layer_id' => ['required', 'exists:layers,id'],
            'layers.*.sort_order' => ['nullable', 'integer'],
        ]);

        $syncData = [];

        foreach ($validated['layers'] as $layer) {
            $syncData[$layer['layer_id']] = [
                'sort_order' => $layer['sort_order'] ?? 0,
            ];
        }

        $service->layers()->sync($syncData);

        return response()->json(ApiResponse::success(
            $service->load('layers')
        ));
    }

    public function publish(GisService $service): JsonResponse
    {
        $service->update([
            'publish_status' => 'published',
            'endpoint_path' => '/tiles/'.$service->code,
        ]);

        return response()->json(ApiResponse::success([
            'status' => 'published',
            'endpoint' => $service->endpoint_path,
        ]));
    }

    public function status(GisService $service): JsonResponse
    {
        return response()->json(ApiResponse::success([
            'status' => $service->publish_status,
            'endpoint' => $service->endpoint_path,
        ]));
    }
}