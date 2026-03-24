<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ResultSnapshot;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultSnapshotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $query = ResultSnapshot::query()
            ->with(['user', 'layer', 'savedQuery'])
            ->orderByDesc('id');

        if (!$user->is_super_admin) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('is_public', true);
            });
        }

        $limit = max(1, min((int) $request->input('limit', 50), 200));

        $rows = $query->limit($limit)->get();

        return response()->json(ApiResponse::success($rows->toArray()));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user('sanctum');

        $row = ResultSnapshot::query()
            ->with(['user', 'layer', 'savedQuery'])
            ->findOrFail($id);

        if (
            !$user->is_super_admin &&
            !$row->is_public &&
            $row->user_id !== $user->id
        ) {
            return response()->json(ApiResponse::error('FORBIDDEN', 'No access to result snapshot.'), 403);
        }

        return response()->json(ApiResponse::success($row->toArray()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user('sanctum');

        $row = ResultSnapshot::query()->findOrFail($id);

        if (!$user->is_super_admin && $row->user_id !== $user->id) {
            return response()->json(ApiResponse::error('FORBIDDEN', 'No access to delete result snapshot.'), 403);
        }

        $row->delete();

        return response()->json(ApiResponse::success(null, [], 'Result snapshot deleted.'));
    }
}