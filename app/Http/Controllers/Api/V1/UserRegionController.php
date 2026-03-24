<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserRegion;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRegionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(
            UserRegion::query()
                ->with('user')
                ->latest()
                ->get()
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'region_id' => ['required', 'integer'],
        ]);

        $item = UserRegion::firstOrCreate([
            'user_id' => $validated['user_id'],
            'region_id' => $validated['region_id'],
        ]);

        return response()->json(ApiResponse::success(
            $item->load('user')
        ), 201);
    }

    public function show(UserRegion $userRegion): JsonResponse
    {
        return response()->json(ApiResponse::success(
            $userRegion->load('user')
        ));
    }

    public function destroy(UserRegion $userRegion): JsonResponse
    {
        $userRegion->delete();

        return response()->json(ApiResponse::success(null, [], 'User region deleted.'));
    }
}