<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunExportJob;
use App\Models\ExportJob;
use App\Models\SavedQuery;
use App\Services\Gis\Export\ExportService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {
    }

    public function createQueryExport(Request $request): JsonResponse
    {
        try {
            $job = $this->exportService->createQueryExportJob(
                $request->user('sanctum'),
                $request->all()
            );

            return response()->json(ApiResponse::success($job->toArray(), [], 'Export job created.'), 201);
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_EXPORT_REQUEST', $e->getMessage()), 422);
        }
    }

    public function createSavedQueryExport(Request $request, int $id): JsonResponse
    {
        try {
            $savedQuery = SavedQuery::query()->findOrFail($id);

            $job = $this->exportService->createSavedQueryExportJob(
                $request->user('sanctum'),
                $savedQuery,
                (string) $request->input('format', 'json')
            );

            return response()->json(ApiResponse::success($job->toArray(), [], 'Saved query export job created.'), 201);
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_EXPORT_REQUEST', $e->getMessage()), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Saved query not found.'), 404);
        }
    }

    public function jobs(Request $request): JsonResponse
    {
        try {
            $rows = $this->exportService->listJobs(
                $request->user('sanctum'),
                (int) $request->input('limit', 50)
            );

            return response()->json(ApiResponse::success($rows));
        } catch (\Throwable $e) {
            return response()->json(ApiResponse::error('EXPORT_JOB_LIST_ERROR', $e->getMessage()), 500);
        }
    }

    public function showJob(Request $request, int $id): JsonResponse
    {
        try {
            $job = $this->exportService->getJob($request->user('sanctum'), $id);

            return response()->json(ApiResponse::success($job->toArray()));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Export job not found.'), 404);
        }
    }

    public function runJob(Request $request, int $id): JsonResponse
    {
        try {
            $job = $this->exportService->getJob($request->user('sanctum'), $id);

            if ((bool) $request->input('async', true)) {
                RunExportJob::dispatch($job->id);

                return response()->json(ApiResponse::success(
                    ['job_id' => $job->id],
                    [],
                    'Export job dispatched.'
                ));
            }

            $job = $this->exportService->runJob($job, $request);

            return response()->json(ApiResponse::success($job->toArray(), [], 'Export job completed.'));
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_EXPORT_RUN', $e->getMessage()), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Export job not found.'), 404);
        }
    }

    public function download(Request $request, int $id)
    {
        try {
            $info = $this->exportService->downloadInfo($request->user('sanctum'), $id);

            return Storage::disk($info['disk'])->download(
                $info['file_path'],
                $info['file_name'],
                ['Content-Type' => $info['mime_type']]
            );
        } catch (AuthorizationException $e) {
            return response()->json(ApiResponse::error('FORBIDDEN', $e->getMessage()), 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(ApiResponse::error('INVALID_EXPORT_DOWNLOAD', $e->getMessage()), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('NOT_FOUND', 'Export job not found.'), 404);
        }
    }
}