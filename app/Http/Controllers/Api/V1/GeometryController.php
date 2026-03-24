<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Gis\GeometryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\ApiResponse;

class GeometryController extends Controller
{
    public function __construct(
        protected GeometryService $geometryService
    ) {
    }

    public function buffer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'srid' => ['required', 'integer'],
            'distance' => ['required', 'numeric'],
        ]);

        $result = $this->geometryService->buffer(
            $validated['geometry'],
            $validated['srid'],
            (float) $validated['distance']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'geometry' => $result,
                'srid' => $validated['srid'],
            ],
        ]);
    }

    public function intersection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'geometry_a' => ['required', 'array'],
            'geometry_b' => ['required', 'array'],
            'srid' => ['required', 'integer'],
        ]);

        $result = $this->geometryService->intersection(
            $validated['geometry_a'],
            $validated['geometry_b'],
            $validated['srid']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'geometry' => $result,
                'srid' => $validated['srid'],
            ],
        ]);
    }

    public function area(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'srid' => ['required', 'integer'],
        ]);

        $area = $this->geometryService->area(
            $validated['geometry'],
            $validated['srid']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'area' => $area,
                'unit' => 'square_meters',
            ],
        ]);
    }

    public function centroid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'srid' => ['required', 'integer'],
        ]);

        $result = $this->geometryService->centroid(
            $validated['geometry'],
            $validated['srid']
        );

        return response()->json(ApiResponse::success($result));
    }

    public function validateGeometry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'srid' => ['required', 'integer'],
        ]);

        $result = $this->geometryService->validate(
            $validated['geometry'],
            $validated['srid']
        );

        return response()->json(ApiResponse::success($result));
    }
}