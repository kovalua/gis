<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Gis\Catalog\CatalogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->fullCatalog($request->user('sanctum'))
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_ERROR');
        }
    }

    public function layers(Request $request): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->layers($request->user('sanctum'))
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_LAYERS_ERROR');
        }
    }

    public function showLayer(Request $request, string $layerCode): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->layer($request->user('sanctum'), $layerCode)
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_LAYER_ERROR');
        }
    }

    public function layerFields(Request $request, string $layerCode): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->layerFields($request->user('sanctum'), $layerCode)
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_LAYER_FIELDS_ERROR');
        }
    }

    public function layerCapabilities(Request $request, string $layerCode): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->layerCapabilities($request->user('sanctum'), $layerCode)
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_LAYER_CAPABILITIES_ERROR');
        }
    }

    public function layerStyle(Request $request, string $layerCode): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->layerStyle($request->user('sanctum'), $layerCode)
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_LAYER_STYLE_ERROR');
        }
    }

    public function layerLegend(Request $request, string $layerCode): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->layerLegend($request->user('sanctum'), $layerCode)
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_LAYER_LEGEND_ERROR');
        }
    }

    public function services(Request $request): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->services($request->user('sanctum'))
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_SERVICES_ERROR');
        }
    }

    public function showService(Request $request, string $serviceCode): JsonResponse
    {
        try {
            return response()->json(
                $this->catalogService->service($request->user('sanctum'), $serviceCode)
            );
        } catch (\Throwable $e) {
            return $this->handleError($e, 'CATALOG_SERVICE_ERROR');
        }
    }

    protected function handleError(\Throwable $e, string $defaultCode): JsonResponse
    {
        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => $e->getMessage(),
                    'details' => [],
                ],
            ], 403);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => $e->getMessage() ?: 'Resource not found.',
                    'details' => [],
                ],
            ], 404);
        }

        if ($e instanceof InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $defaultCode,
                    'message' => $e->getMessage(),
                    'details' => [],
                ],
            ], 422);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $defaultCode,
                'message' => $e->getMessage(),
                'details' => [],
            ],
        ], 500);
    }
}
