<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Gis\Presets\SavedQueryService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SavedQueryController extends Controller
{
    public function __construct(
        protected SavedQueryService $savedQueryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->savedQueryService->listVisible($request->user('sanctum'));

            return response()->json(ApiResponse::success($result));
        } catch (\Throwable $e) {
            return response()->json(ApiResponse::error('SAVED_QUERY_LIST_ERROR', $e->getMessage()), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $savedQuery = $this->savedQueryService->create(
                $request->user('sanctum'),
                $request->all()
            );

            return response()->json(ApiResponse::success($savedQuery->toArray()), 201);
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_SAVED_QUERY_CREATE', $e->getMessage()), 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $savedQuery = $this->savedQueryService->showVisible(
                $request->user('sanctum'),
                $id
            );

            return response()->json(ApiResponse::success($savedQuery->toArray()));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Saved query not found.'), 404);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $savedQuery = $this->savedQueryService->update(
                $request->user('sanctum'),
                $id,
                $request->all()
            );

            return response()->json(ApiResponse::success($savedQuery->toArray()));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_SAVED_QUERY_UPDATE', $e->getMessage()), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Saved query not found.'), 404);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->savedQueryService->delete(
                $request->user('sanctum'),
                $id
            );

            return response()->json(ApiResponse::success(null, [], 'Saved query deleted.'));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Saved query not found.'), 404);
        }
    }

    public function execute(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->savedQueryService->execute(
                $request->user('sanctum'),
                $id,
                $request
            );

            return response()->json(ApiResponse::success(
                $result['execution']['data'] ?? [],
                $result['execution']['meta'] ?? [],
                'Saved query executed.'
            ));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_SAVED_QUERY_EXECUTION', $e->getMessage()), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Saved query not found.'), 404);
        }
    }

    public function syncRoles(Request $request, int $id): JsonResponse
    {
        try {
            $savedQuery = $this->savedQueryService->syncRoles(
                $request->user('sanctum'),
                $id,
                $request->input('role_ids', [])
            );

            return response()->json(ApiResponse::success($savedQuery->toArray(), [], 'Roles synced.'));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_SAVED_QUERY_ROLE_SYNC', $e->getMessage()), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Saved query not found.'), 404);
        }
    }
}