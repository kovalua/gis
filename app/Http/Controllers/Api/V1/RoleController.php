<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(
            Role::query()->with('permissions')->latest()->get()
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $role = Role::create($validated);

        return response()->json(ApiResponse::success($role), 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $role->load('permissions', 'users')
        ));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('roles', 'code')->ignore($role->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $role->update($validated);

        return response()->json(ApiResponse::success($role->fresh()));
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json(ApiResponse::success(null, [], 'Role deleted.'));
    }
}