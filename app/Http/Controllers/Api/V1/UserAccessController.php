<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAccessController extends Controller
{
    public function indexUsers(): JsonResponse
    {
        return response()->json(ApiResponse::success(
            User::query()->with('roles', 'regionAssignments')->latest()->get()
        ));
    }

    public function showUser(User $user): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $user->load('roles', 'regionAssignments')
        ));
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'is_super_admin' => ['sometimes', 'boolean'],
        ]);

        $user->update($validated);

        return response()->json(ApiResponse::success($user->fresh()));
    }

    public function attachRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['required', 'exists:roles,id'],
        ]);

        $user->roles()->sync($validated['role_ids']);

        return response()->json(ApiResponse::success(
            $user->load('roles')
        ));
    }

    public function syncRegions(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'region_ids' => ['required', 'array'],
            'region_ids.*' => ['required', 'integer'],
        ]);

        $user->regionAssignments()->delete();

        $rows = [];
        foreach ($validated['region_ids'] as $regionId) {
            $rows[] = [
                'user_id' => $user->id,
                'region_id' => (int) $regionId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            \App\Models\UserRegion::insert($rows);
        }

        return response()->json(ApiResponse::success(
            $user->fresh()->load('regionAssignments')
        ));
    }

    public function attachPermissionsToRole(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['required', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return response()->json(ApiResponse::success(
            $role->load('permissions')
        ));
    }
}