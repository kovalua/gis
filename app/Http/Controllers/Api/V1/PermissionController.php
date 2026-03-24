<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(
            Permission::query()->latest()->get()
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:permissions,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $permission = Permission::create($validated);

        return response()->json(ApiResponse::success($permission), 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $permission->load('roles')
        ));
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('permissions', 'code')->ignore($permission->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $permission->update($validated);

        return response()->json(ApiResponse::success($permission->fresh()));
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json(ApiResponse::success(null, [], 'Permission deleted.'));
    }
}