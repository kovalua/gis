<?php

namespace App\Services\Gis\Export;

use App\Exports\ArrayExport;
use App\Models\ExportJob;
use App\Models\Layer;
use App\Models\ResultSnapshot;
use App\Models\SavedQuery;
use App\Models\User;
use App\Services\Gis\Analytics\FeatureAnalyticsService;
use App\Services\Gis\FeatureQueryService;
use App\Services\Gis\Presets\SavedQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class ExportService
{
    public function __construct(
        protected FeatureQueryService $featureQueryService,
        protected FeatureAnalyticsService $featureAnalyticsService,
        protected SavedQueryService $savedQueryService
    ) {
    }

    public function createQueryExportJob(User $user, array $payload): ExportJob
    {
        $layerCode = (string) ($payload['layer_code'] ?? '');
        $queryType = (string) ($payload['query_type'] ?? 'feature_query');
        $format = strtolower((string) ($payload['format'] ?? 'json'));
        $requestPayload = $payload['payload'] ?? [];

        if ($layerCode === '') {
            throw new InvalidArgumentException('layer_code is required.');
        }

        if (!in_array($queryType, ['feature_query', 'feature_aggregate', 'feature_statistics', 'feature_count'], true)) {
            throw new InvalidArgumentException("Unsupported query_type [{$queryType}].");
        }

        $this->assertFormatAllowed($format, $queryType);

        $layer = Layer::query()->where('code', $layerCode)->firstOrFail();

        return ExportJob::create([
            'user_id' => $user->id,
            'layer_id' => $layer->id,
            'saved_query_id' => null,
            'result_snapshot_id' => null,
            'export_type' => $queryType,
            'format' => $format,
            'status' => 'pending',
            'request_payload_json' => is_array($requestPayload) ? $requestPayload : [],
        ]);
    }

    public function createSavedQueryExportJob(User $user, SavedQuery $savedQuery, string $format = 'json'): ExportJob
    {
        $this->savedQueryService->showVisible($user, $savedQuery->id);

        $format = strtolower($format);
        $this->assertFormatAllowed($format, $savedQuery->query_type);

        return ExportJob::create([
            'user_id' => $user->id,
            'layer_id' => $savedQuery->layer_id,
            'saved_query_id' => $savedQuery->id,
            'result_snapshot_id' => null,
            'export_type' => $savedQuery->query_type,
            'format' => $format,
            'status' => 'pending',
            'request_payload_json' => $savedQuery->payload_json ?? [],
        ]);
    }

    public function runJob(ExportJob $job, Request|null $request = null): ExportJob
    {
        if ($job->status === 'running') {
            throw new InvalidArgumentException("Export job [{$job->id}] is already running.");
        }

        $job->update([
            'status' => 'running',
            'started_at' => now(),
            'error_json' => null,
        ]);

        $startedAt = microtime(true);

        try {
            $user = $job->user()->firstOrFail();

            [$data, $meta, $snapshot] = $this->resolveJobData($job, $user);

            $fileInfo = $this->writeExportFile(
                job: $job,
                data: $data,
                meta: $meta
            );

            $durationMs = round((microtime(true) - $startedAt) * 1000, 3);

            $job->update([
                'result_snapshot_id' => $snapshot?->id,
                'status' => 'completed',
                'response_meta_json' => $meta,
                'disk' => $fileInfo['disk'],
                'file_path' => $fileInfo['file_path'],
                'file_name' => $fileInfo['file_name'],
                'file_size' => $fileInfo['file_size'],
                'mime_type' => $fileInfo['mime_type'],
                'result_count' => $this->detectResultCount($job->export_type, $data),
                'duration_ms' => $durationMs,
                'finished_at' => now(),
            ]);

            return $job->fresh(['user', 'layer', 'savedQuery', 'resultSnapshot']);
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startedAt) * 1000, 3);

            $job->update([
                'status' => 'failed',
                'error_json' => [
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                ],
                'duration_ms' => $durationMs,
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    public function listJobs(User $user, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $query = ExportJob::query()
            ->with(['user', 'layer', 'savedQuery', 'resultSnapshot'])
            ->orderByDesc('id');

        if (!$user->is_super_admin) {
            $query->where('user_id', $user->id);
        }

        return $query->limit($limit)->get()->map(fn (ExportJob $job) => $this->mapJob($job))->values()->all();
    }

    public function getJob(User $user, int $id): ExportJob
    {
        $job = ExportJob::query()
            ->with(['user', 'layer', 'savedQuery', 'resultSnapshot'])
            ->findOrFail($id);

        if (!$user->is_super_admin && $job->user_id !== $user->id) {
            throw new AuthorizationException("No access to export job [{$job->id}].");
        }

        return $job;
    }

    public function downloadInfo(User $user, int $id): array
    {
        $job = $this->getJob($user, $id);

        if ($job->status !== 'completed' || !$job->file_path || !$job->disk) {
            throw new InvalidArgumentException("Export job [{$job->id}] has no downloadable file.");
        }

        if (!Storage::disk($job->disk)->exists($job->file_path)) {
            throw new InvalidArgumentException("Export file for job [{$job->id}] not found.");
        }

        return [
            'disk' => $job->disk,
            'file_path' => $job->file_path,
            'file_name' => $job->file_name,
            'mime_type' => $job->mime_type,
        ];
    }

    protected function resolveJobData(ExportJob $job, User $user): array
    {
        if ($job->saved_query_id) {
            $savedQuery = $job->savedQuery()->firstOrFail();
            $executed = $this->savedQueryService->execute($user, $savedQuery->id, request());

            $data = $executed['execution']['data'] ?? [];
            $meta = $executed['execution']['meta'] ?? [];
            $snapshot = $this->createSnapshotFromExecutedSavedQuery($job, $savedQuery, $data, $meta, $user);

            return [$data, $meta, $snapshot];
        }

        $layer = $job->layer()->with('dataSource')->firstOrFail();
        $payload = $job->request_payload_json ?? [];

        [$data, $meta] = match ($job->export_type) {
            'feature_query' => [
                $this->featureQueryService->query($layer, $payload, $user),
                [],
            ],
            'feature_count' => [
                ['count' => $this->featureQueryService->count($layer, $payload, $user)],
                [],
            ],
            'feature_aggregate' => (function () use ($layer, $payload, $user) {
                $result = $this->featureAnalyticsService->aggregate($layer, $payload, $user);
                return [$result['rows'] ?? [], $result['meta'] ?? []];
            })(),
            'feature_statistics' => (function () use ($layer, $payload, $user) {
                $result = $this->featureAnalyticsService->statistics($layer, $payload, $user);
                return [$result['stats'] ?? [], $result['meta'] ?? []];
            })(),
            default => throw new InvalidArgumentException("Unsupported export type [{$job->export_type}]."),
        };

        $snapshot = $this->createSnapshotFromAdhoc($job, $layer, $data, $meta, $user);

        return [$data, $meta, $snapshot];
    }

    protected function createSnapshotFromExecutedSavedQuery(
        ExportJob $job,
        SavedQuery $savedQuery,
        mixed $data,
        array $meta,
        User $user
    ): ResultSnapshot {
        return ResultSnapshot::create([
            'user_id' => $user->id,
            'layer_id' => $savedQuery->layer_id,
            'saved_query_id' => $savedQuery->id,
            'snapshot_type' => $savedQuery->query_type,
            'name' => 'Snapshot: ' . $savedQuery->name . ' #' . $job->id,
            'description' => 'Auto-created from export job #' . $job->id,
            'request_payload_json' => $savedQuery->payload_json ?? [],
            'result_meta_json' => $meta,
            'preview_json' => $this->buildPreview($data),
            'result_count' => $this->detectResultCount($savedQuery->query_type, $data),
            'is_public' => false,
        ]);
    }

    protected function createSnapshotFromAdhoc(
        ExportJob $job,
        Layer $layer,
        mixed $data,
        array $meta,
        User $user
    ): ResultSnapshot {
        return ResultSnapshot::create([
            'user_id' => $user->id,
            'layer_id' => $layer->id,
            'saved_query_id' => null,
            'snapshot_type' => $job->export_type,
            'name' => 'Snapshot: ' . $layer->name . ' #' . $job->id,
            'description' => 'Auto-created from export job #' . $job->id,
            'request_payload_json' => $job->request_payload_json ?? [],
            'result_meta_json' => $meta,
            'preview_json' => $this->buildPreview($data),
            'result_count' => $this->detectResultCount($job->export_type, $data),
            'is_public' => false,
        ]);
    }

    protected function writeExportFile(ExportJob $job, mixed $data, array $meta): array
    {
        $disk = 'local';
        $dir = 'exports/' . now()->format('Y/m/d');
        $extension = $job->format;
        $fileName = 'export-job-' . $job->id . '-' . Str::uuid() . '.' . $extension;
        $filePath = $dir . '/' . $fileName;

        return match ($job->format) {
            'json' => $this->writeJsonFile($disk, $filePath, $fileName, $data, $meta),
            'geojson' => $this->writeGeoJsonFile($disk, $filePath, $fileName, $data),
            'csv' => $this->writeCsvFile($disk, $filePath, $fileName, $data),
            'xlsx' => $this->writeXlsxFile($disk, $filePath, $fileName, $data),
            default => throw new InvalidArgumentException("Unsupported export format [{$job->format}]."),
        };
    }

    protected function writeJsonFile(string $disk, string $filePath, string $fileName, mixed $data, array $meta): array
    {
        $content = json_encode([
            'meta' => $meta,
            'data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::disk($disk)->put($filePath, $content);

        return [
            'disk' => $disk,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => Storage::disk($disk)->size($filePath),
            'mime_type' => 'application/json',
        ];
    }

    protected function writeGeoJsonFile(string $disk, string $filePath, string $fileName, mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('GeoJSON export requires array feature data.');
        }

        $features = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'id' => $item['id'] ?? null,
                'geometry' => $item['geometry'] ?? null,
                'properties' => $item['properties'] ?? [],
            ];
        }

        $content = json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::disk($disk)->put($filePath, $content);

        return [
            'disk' => $disk,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => Storage::disk($disk)->size($filePath),
            'mime_type' => 'application/geo+json',
        ];
    }

    protected function writeCsvFile(string $disk, string $filePath, string $fileName, mixed $data): array
    {
        $rows = $this->normalizeRowsForTabularExport($data);

        $tmp = fopen('php://temp', 'r+');

        $headers = [];
        foreach ($rows as $row) {
            $headers = array_values(array_unique(array_merge($headers, array_keys($row))));
        }

        if (!empty($headers)) {
            fputcsv($tmp, $headers);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? null;
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $line[] = $value;
                }
                fputcsv($tmp, $line);
            }
        }

        rewind($tmp);
        $content = stream_get_contents($tmp);
        fclose($tmp);

        Storage::disk($disk)->put($filePath, $content);

        return [
            'disk' => $disk,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => Storage::disk($disk)->size($filePath),
            'mime_type' => 'text/csv',
        ];
    }

    protected function writeXlsxFile(string $disk, string $filePath, string $fileName, mixed $data): array
    {
        $rows = $this->normalizeRowsForTabularExport($data);

        Excel::store(new ArrayExport($rows), $filePath, $disk);

        return [
            'disk' => $disk,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => Storage::disk($disk)->size($filePath),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    protected function normalizeRowsForTabularExport(mixed $data): array
    {
        if (!is_array($data)) {
            if (is_scalar($data) || $data === null) {
                return [['value' => $data]];
            }
            return [];
        }

        if ($this->isAssoc($data)) {
            return [$data];
        }

        $rows = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                if (isset($item['properties']) && is_array($item['properties'])) {
                    $row = $item['properties'];
                    if (array_key_exists('id', $item)) {
                        $row = ['id' => $item['id']] + $row;
                    }
                    if (array_key_exists('geometry', $item)) {
                        $row['geometry'] = $item['geometry'];
                    }
                    $rows[] = $row;
                } else {
                    $rows[] = $item;
                }
            } else {
                $rows[] = ['value' => $item];
            }
        }

        return $rows;
    }

    protected function buildPreview(mixed $data): array
    {
        if (!is_array($data)) {
            return [['value' => $data]];
        }

        if ($this->isAssoc($data)) {
            return [$data];
        }

        return array_slice($data, 0, 20);
    }

    protected function detectResultCount(string $type, mixed $data): int
    {
        return match ($type) {
            'feature_query', 'feature_aggregate', 'saved_query' => is_array($data) ? count($data) : 0,
            'feature_count' => (int) ($data['count'] ?? 0),
            'feature_statistics' => is_array($data) ? count($data) : 0,
            default => is_array($data) ? count($data) : 0,
        };
    }

    protected function assertFormatAllowed(string $format, string $queryType): void
    {
        $allowed = match ($queryType) {
            'feature_query' => ['json', 'geojson', 'csv', 'xlsx'],
            'feature_count' => ['json', 'csv', 'xlsx'],
            'feature_aggregate' => ['json', 'csv', 'xlsx'],
            'feature_statistics' => ['json', 'csv', 'xlsx'],
            default => ['json'],
        };

        if (!in_array($format, $allowed, true)) {
            throw new InvalidArgumentException("Format [{$format}] is not allowed for [{$queryType}].");
        }
    }

    protected function mapJob(ExportJob $job): array
    {
        return [
            'id' => $job->id,
            'export_type' => $job->export_type,
            'format' => $job->format,
            'status' => $job->status,
            'file_name' => $job->file_name,
            'file_size' => $job->file_size,
            'mime_type' => $job->mime_type,
            'result_count' => $job->result_count,
            'duration_ms' => $job->duration_ms,
            'layer' => $job->layer ? [
                'id' => $job->layer->id,
                'code' => $job->layer->code,
                'name' => $job->layer->name,
            ] : null,
            'saved_query' => $job->savedQuery ? [
                'id' => $job->savedQuery->id,
                'code' => $job->savedQuery->code,
                'name' => $job->savedQuery->name,
            ] : null,
            'result_snapshot' => $job->resultSnapshot ? [
                'id' => $job->resultSnapshot->id,
                'name' => $job->resultSnapshot->name,
            ] : null,
            'error' => $job->error_json,
            'links' => [
                'self' => url("/api/v1/exports/jobs/{$job->id}"),
                'download' => url("/api/v1/exports/jobs/{$job->id}/download"),
                'run' => url("/api/v1/exports/jobs/{$job->id}/run"),
            ],
            'started_at' => optional($job->started_at)?->toISOString(),
            'finished_at' => optional($job->finished_at)?->toISOString(),
            'created_at' => optional($job->created_at)?->toISOString(),
            'updated_at' => optional($job->updated_at)?->toISOString(),
        ];
    }

    protected function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}