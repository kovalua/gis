<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LayerPermission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LayerPermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(
            LayerPermission::query()
                ->with(['layer', 'role'])
                ->latest()
                ->get()
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'layer_id' => ['required', 'exists:layers,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'can_view' => ['nullable', 'boolean'],
            'can_query' => ['nullable', 'boolean'],
            'can_create' => ['nullable', 'boolean'],
            'can_update' => ['nullable', 'boolean'],
            'can_delete' => ['nullable', 'boolean'],
            'can_export' => ['nullable', 'boolean'],
        ]);

        $permission = LayerPermission::updateOrCreate(
            [
                'layer_id' => $validated['layer_id'],
                'role_id' => $validated['role_id'],
            ],
            [
                'can_view' => $validated['can_view'] ?? false,
                'can_query' => $validated['can_query'] ?? false,
                'can_create' => $validated['can_create'] ?? false,
                'can_update' => $validated['can_update'] ?? false,
                'can_delete' => $validated['can_delete'] ?? false,
                'can_export' => $validated['can_export'] ?? false,
            ]
        );

        return response()->json(ApiResponse::success(
            $permission->load('layer', 'role')
        ), 201);
    }

    public function show(LayerPermission $layerPermission): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $layerPermission->load('layer', 'role')
        ));
    }

    public function update(Request $request, LayerPermission $layerPermission): JsonResponse
    {
        $validated = $request->validate([
            'can_view' => ['nullable', 'boolean'],
            'can_query' => ['nullable', 'boolean'],
            'can_create' => ['nullable', 'boolean'],
            'can_update' => ['nullable', 'boolean'],
            'can_delete' => ['nullable', 'boolean'],
            'can_export' => ['nullable', 'boolean'],
        ]);

        $layerPermission->update($validated);

        return response()->json(ApiResponse::success(
            $layerPermission->fresh()->load('layer', 'role')
        ));
    }

    public function destroy(LayerPermission $layerPermission): JsonResponse
    {
        $layerPermission->delete();

        return response()->json(ApiResponse::success(null, [], 'Layer permission deleted.'));
    }
}