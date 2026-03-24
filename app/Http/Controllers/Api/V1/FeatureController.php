<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use App\Services\Gis\Analytics\FeatureAnalyticsService;
use App\Services\Gis\FeatureQueryService;
use App\Services\Gis\FeatureWriteService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FeatureController extends Controller
{
    public function __construct(
        protected FeatureQueryService $featureQueryService,
        protected FeatureWriteService $featureWriteService,
        protected FeatureAnalyticsService $featureAnalyticsService
    ) {
    }

    public function index(Request $request, string $layerCode): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        $limit = (int) $request->get('limit', 50);

        try {
            $features = $this->featureQueryService->list(
                $layer,
                $limit,
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success($features));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_QUERY', $e->getMessage()), 422);
        }
    }

    public function show(Request $request, string $layerCode, $id): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $feature = $this->featureWriteService->show(
                $layer,
                $id,
                $request->user('sanctum')
            );

            if (!$feature) {
                return response()->json(
                    ApiResponse::error('NOT_FOUND', 'Feature not found.'),
                    404
                );
            }

            return response()->json(ApiResponse::success($feature));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_FEATURE_SHOW', $e->getMessage()), 422);
        }
    }

    public function query(Request $request, string $layerCode): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $features = $this->featureQueryService->query(
                $layer,
                $request->all(),
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success($features));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_QUERY', $e->getMessage()), 422);
        }
    }

    public function count(Request $request, string $layerCode): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $total = $this->featureQueryService->count(
                $layer,
                $request->all(),
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success([
                'count' => $total,
            ]));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_QUERY', $e->getMessage()), 422);
        }
    }

    public function aggregate(Request $request, string $layerCode): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $result = $this->featureAnalyticsService->aggregate(
                $layer,
                $request->all(),
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success(
                $result['rows'] ?? [],
                $result['meta'] ?? [],
                'Aggregate query executed.'
            ));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_AGGREGATE_QUERY', $e->getMessage()), 422);
        }
    }

    public function statistics(Request $request, string $layerCode): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $result = $this->featureAnalyticsService->statistics(
                $layer,
                $request->all(),
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success(
                $result['stats'] ?? [],
                $result['meta'] ?? [],
                'Statistics query executed.'
            ));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_STATISTICS_QUERY', $e->getMessage()), 422);
        }
    }

    public function store(Request $request, string $layerCode): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $feature = $this->featureWriteService->create(
                $layer,
                $request->all(),
                $request,
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success($feature), 201);
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_FEATURE_CREATE', $e->getMessage()), 422);
        }
    }

    public function update(Request $request, string $layerCode, $id): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $feature = $this->featureWriteService->update(
                $layer,
                $id,
                $request->all(),
                $request,
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success($feature));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            $status = $e->getMessage() === 'Feature not found.' ? 404 : 422;

            return response()->json(
                ApiResponse::error('INVALID_FEATURE_UPDATE', $e->getMessage()),
                $status
            );
        }
    }

    public function destroy(Request $request, string $layerCode, $id): JsonResponse
    {
        $layer = Layer::query()
            ->with('dataSource')
            ->where('code', $layerCode)
            ->firstOrFail();

        try {
            $this->featureWriteService->delete(
                $layer,
                $id,
                $request,
                $request->user('sanctum')
            );

            return response()->json(ApiResponse::success(null, [], 'Feature deleted.'));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            $status = $e->getMessage() === 'Feature not found.' ? 404 : 422;

            return response()->json(
                ApiResponse::error('INVALID_FEATURE_DELETE', $e->getMessage()),
                $status
            );
        }
    }
}