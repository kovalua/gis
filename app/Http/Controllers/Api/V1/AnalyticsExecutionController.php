<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsExecution;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsExecutionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $query = AnalyticsExecution::query()
            ->with(['savedQuery', 'layer', 'user'])
            ->orderByDesc('id');

        if (!$user->is_super_admin) {
            $query->where('user_id', $user->id);
        }

        $limit = max(1, min((int) $request->get('limit', 50), 200));

        $rows = $query->limit($limit)->get();

        return response()->json(ApiResponse::success($rows->toArray()));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user('sanctum');

        $row = AnalyticsExecution::query()
            ->with(['savedQuery', 'layer', 'user'])
            ->findOrFail($id);

        if (!$user->is_super_admin && $row->user_id !== $user->id) {
            return response()->json(ApiResponse::error('FORBIDDEN', 'No access to analytics execution.'), 403);
        }

        return response()->json(ApiResponse::success($row->toArray()));
    }
}