<?php

use App\Http\Controllers\Api\V1\AnalyticsExecutionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DataSourceController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Controllers\Api\V1\GeometryController;
use App\Http\Controllers\Api\V1\LayerController;
use App\Http\Controllers\Api\V1\LayerPermissionController;
use App\Http\Controllers\Api\V1\LayerFieldController;
use App\Http\Controllers\Api\V1\LayerOperationController;
use App\Http\Controllers\Api\V1\LayerStyleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ResultSnapshotController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SavedQueryController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\UserAccessController;
use App\Http\Controllers\Api\V1\UserRegionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    Route::prefix('geometry')->group(function () {
        Route::post('buffer', [GeometryController::class, 'buffer']);
        Route::post('intersection', [GeometryController::class, 'intersection']);
        Route::post('area', [GeometryController::class, 'area']);
        Route::post('centroid', [GeometryController::class, 'centroid']);
        Route::post('validate', [GeometryController::class, 'validateGeometry']);
    });

    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('catalog')->group(function () {
            Route::get('/', [CatalogController::class, 'index']);
            Route::get('/layers', [CatalogController::class, 'layers']);
            Route::get('/layers/{layerCode}', [CatalogController::class, 'showLayer']);
            Route::get('/layers/{layerCode}/fields', [CatalogController::class, 'layerFields']);
            Route::get('/layers/{layerCode}/capabilities', [CatalogController::class, 'layerCapabilities']);
            Route::get('/layers/{layerCode}/style', [CatalogController::class, 'layerStyle']);
            Route::get('/layers/{layerCode}/legend', [CatalogController::class, 'layerLegend']);
            Route::get('/services', [CatalogController::class, 'services']);
            Route::get('/services/{serviceCode}', [CatalogController::class, 'showService']);
        });

        Route::apiResource('data-sources', DataSourceController::class);
        Route::post('data-sources/{dataSource}/inspect', [DataSourceController::class, 'inspect']);

        Route::apiResource('layers', LayerController::class);

        Route::apiResource('services', ServiceController::class);
        Route::post('services/{service}/layers', [ServiceController::class, 'attachLayers']);
        Route::post('services/{service}/publish', [ServiceController::class, 'publish']);
        Route::get('services/{service}/status', [ServiceController::class, 'status']);

        Route::get('features/{layerCode}', [FeatureController::class, 'index']);
        Route::get('features/{layerCode}/{id}', [FeatureController::class, 'show']);
        Route::post('features/{layerCode}/query', [FeatureController::class, 'query']);
        Route::post('features/{layerCode}/count', [FeatureController::class, 'count']);
        Route::post('features/{layerCode}/aggregate', [FeatureController::class, 'aggregate']);
        Route::post('features/{layerCode}/statistics', [FeatureController::class, 'statistics']);
        Route::post('features/{layerCode}', [FeatureController::class, 'store']);
        Route::put('features/{layerCode}/{id}', [FeatureController::class, 'update']);
        Route::delete('features/{layerCode}/{id}', [FeatureController::class, 'destroy']);

        Route::get('saved-queries', [SavedQueryController::class, 'index']);
        Route::post('saved-queries', [SavedQueryController::class, 'store']);
        Route::get('saved-queries/{id}', [SavedQueryController::class, 'show']);
        Route::put('saved-queries/{id}', [SavedQueryController::class, 'update']);
        Route::delete('saved-queries/{id}', [SavedQueryController::class, 'destroy']);
        Route::post('saved-queries/{id}/execute', [SavedQueryController::class, 'execute']);
        Route::post('saved-queries/{id}/roles/sync', [SavedQueryController::class, 'syncRoles']);

        Route::get('analytics-executions', [AnalyticsExecutionController::class, 'index']);
        Route::get('analytics-executions/{id}', [AnalyticsExecutionController::class, 'show']);

        /*
        |--------------------------------------------------------------------------
        | Export API / Async Jobs / Result Snapshots
        |--------------------------------------------------------------------------
        */
        Route::post('exports/query', [ExportController::class, 'createQueryExport']);
        Route::post('exports/saved-query/{id}', [ExportController::class, 'createSavedQueryExport']);
        Route::get('exports/jobs', [ExportController::class, 'jobs']);
        Route::get('exports/jobs/{id}', [ExportController::class, 'showJob']);
        Route::post('exports/jobs/{id}/run', [ExportController::class, 'runJob']);
        Route::get('exports/jobs/{id}/download', [ExportController::class, 'download']);

        Route::get('result-snapshots', [ResultSnapshotController::class, 'index']);
        Route::get('result-snapshots/{id}', [ResultSnapshotController::class, 'show']);
        Route::delete('result-snapshots/{id}', [ResultSnapshotController::class, 'destroy']);

        Route::middleware('access.manage')->group(function () {
            Route::apiResource('roles', RoleController::class);
            Route::apiResource('permissions', PermissionController::class);
            Route::apiResource('layer-permissions', LayerPermissionController::class);
            Route::apiResource('user-regions', UserRegionController::class)->only(['index', 'store', 'show', 'destroy']);

            Route::get('users', [UserAccessController::class, 'indexUsers']);
            Route::get('users/{user}', [UserAccessController::class, 'showUser']);
            Route::put('users/{user}', [UserAccessController::class, 'updateUser']);

            Route::post('users/{user}/roles', [UserAccessController::class, 'attachRoles']);
            Route::post('users/{user}/regions', [UserAccessController::class, 'syncRegions']);
            Route::post('roles/{role}/permissions', [UserAccessController::class, 'attachPermissionsToRole']);
    
                        /*
            |--------------------------------------------------------------------------
            | Layer Metadata Admin API
            |--------------------------------------------------------------------------
            */
            Route::get('layers/{layer}/fields', [LayerFieldController::class, 'index']);
            Route::post('layers/{layer}/fields', [LayerFieldController::class, 'store']);
            Route::get('layers/{layer}/fields/{fieldId}', [LayerFieldController::class, 'show']);
            Route::put('layers/{layer}/fields/{fieldId}', [LayerFieldController::class, 'update']);
            Route::delete('layers/{layer}/fields/{fieldId}', [LayerFieldController::class, 'destroy']);
            Route::post('layers/{layer}/fields/sync-from-data-source', [LayerFieldController::class, 'syncFromDataSource']);

            Route::get('layers/{layer}/styles', [LayerStyleController::class, 'index']);
            Route::post('layers/{layer}/styles', [LayerStyleController::class, 'store']);
            Route::get('layers/{layer}/styles/{styleId}', [LayerStyleController::class, 'show']);
            Route::put('layers/{layer}/styles/{styleId}', [LayerStyleController::class, 'update']);
            Route::delete('layers/{layer}/styles/{styleId}', [LayerStyleController::class, 'destroy']);

            Route::get('layers/{layer}/operations', [LayerOperationController::class, 'index']);
            Route::post('layers/{layer}/operations', [LayerOperationController::class, 'store']);
            Route::get('layers/{layer}/operations/{operationId}', [LayerOperationController::class, 'show']);
            Route::put('layers/{layer}/operations/{operationId}', [LayerOperationController::class, 'update']);
            Route::delete('layers/{layer}/operations/{operationId}', [LayerOperationController::class, 'destroy']);



        });
    });
});