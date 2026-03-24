<?php

namespace App\Services\Gis\Presets;

use App\Models\AnalyticsExecution;
use App\Models\Layer;
use App\Models\SavedQuery;
use App\Models\User;
use App\Services\Gis\Analytics\FeatureAnalyticsService;
use App\Services\Gis\FeatureQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SavedQueryService
{
    public function __construct(
        protected FeatureQueryService $featureQueryService,
        protected FeatureAnalyticsService $featureAnalyticsService
    ) {
    }

    public function listVisible(User $user): array
    {
        $roleIds = $user->roles()->pluck('roles.id')->all();

        $queries = SavedQuery::query()
            ->with(['layer', 'owner', 'roles'])
            ->where('is_active', true)
            ->where(function ($q) use ($user, $roleIds) {
                $q->where('visibility', 'public')
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'private')
                           ->where('owner_user_id', $user->id);
                    });

                if (!empty($roleIds)) {
                    $q->orWhere(function ($q3) use ($roleIds) {
                        $q3->where('visibility', 'role')
                           ->whereHas('roles', function ($sub) use ($roleIds) {
                               $sub->whereIn('roles.id', $roleIds);
                           });
                    });
                }
            })
            ->orderBy('name')
            ->get();

        return $queries->map(fn (SavedQuery $savedQuery) => $this->mapSavedQuery($savedQuery))->values()->all();
    }

    public function showVisible(User $user, int $id): SavedQuery
    {
        $savedQuery = SavedQuery::query()
            ->with(['layer', 'owner', 'roles'])
            ->findOrFail($id);

        if (!$this->canView($user, $savedQuery)) {
            throw new AuthorizationException("No access to saved query [{$savedQuery->id}].");
        }

        return $savedQuery;
    }

    public function create(User $user, array $payload): SavedQuery
    {
        $layerId = (int) ($payload['layer_id'] ?? 0);
        $name = trim((string) ($payload['name'] ?? ''));
        $description = $payload['description'] ?? null;
        $queryType = (string) ($payload['query_type'] ?? '');
        $visibility = (string) ($payload['visibility'] ?? 'private');
        $presetPayload = $payload['payload'] ?? null;
        $metadata = $payload['metadata'] ?? null;

        if ($layerId < 1) {
            throw new InvalidArgumentException('layer_id is required.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('name is required.');
        }

        if (!in_array($queryType, [
            'feature_query',
            'feature_count',
            'feature_aggregate',
            'feature_statistics',
        ], true)) {
            throw new InvalidArgumentException("Unsupported query_type [{$queryType}].");
        }

        if (!in_array($visibility, ['private', 'role', 'public'], true)) {
            throw new InvalidArgumentException("Unsupported visibility [{$visibility}].");
        }

        if (!is_array($presetPayload)) {
            throw new InvalidArgumentException('payload must be an object.');
        }

        $layer = Layer::query()->findOrFail($layerId);

        $this->validateExecutionAccess($user, $layer, $queryType);

        $savedQuery = SavedQuery::create([
            'code' => $this->generateUniqueCode($name),
            'name' => $name,
            'description' => $description,
            'layer_id' => $layer->id,
            'owner_user_id' => $user->id,
            'query_type' => $queryType,
            'visibility' => $visibility,
            'is_active' => true,
            'payload_json' => $presetPayload,
            'metadata_json' => is_array($metadata) ? $metadata : null,
        ]);

        if ($visibility === 'role' && !empty($payload['role_ids']) && is_array($payload['role_ids'])) {
            $savedQuery->roles()->sync(array_values(array_unique(array_map('intval', $payload['role_ids']))));
        }

        return $savedQuery->load(['layer', 'owner', 'roles']);
    }

    public function update(User $user, int $id, array $payload): SavedQuery
    {
        $savedQuery = SavedQuery::query()->with(['roles', 'layer', 'owner'])->findOrFail($id);

        if (!$this->canManage($user, $savedQuery)) {
            throw new AuthorizationException("No access to update saved query [{$savedQuery->id}].");
        }

        $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : $savedQuery->name;
        $description = array_key_exists('description', $payload) ? $payload['description'] : $savedQuery->description;
        $visibility = array_key_exists('visibility', $payload) ? (string) $payload['visibility'] : $savedQuery->visibility;
        $presetPayload = array_key_exists('payload', $payload) ? $payload['payload'] : $savedQuery->payload_json;
        $metadata = array_key_exists('metadata', $payload) ? $payload['metadata'] : $savedQuery->metadata_json;
        $isActive = array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : $savedQuery->is_active;

        if ($name === '') {
            throw new InvalidArgumentException('name cannot be empty.');
        }

        if (!in_array($visibility, ['private', 'role', 'public'], true)) {
            throw new InvalidArgumentException("Unsupported visibility [{$visibility}].");
        }

        if (!is_array($presetPayload)) {
            throw new InvalidArgumentException('payload must be an object.');
        }

        $this->validateExecutionAccess($user, $savedQuery->layer, $savedQuery->query_type);

        $savedQuery->update([
            'name' => $name,
            'description' => $description,
            'visibility' => $visibility,
            'is_active' => $isActive,
            'payload_json' => $presetPayload,
            'metadata_json' => is_array($metadata) ? $metadata : null,
        ]);

        if (array_key_exists('role_ids', $payload)) {
            $roleIds = is_array($payload['role_ids']) ? $payload['role_ids'] : [];
            $savedQuery->roles()->sync(array_values(array_unique(array_map('intval', $roleIds))));
        }

        return $savedQuery->fresh(['layer', 'owner', 'roles']);
    }

    public function delete(User $user, int $id): void
    {
        $savedQuery = SavedQuery::query()->findOrFail($id);

        if (!$this->canManage($user, $savedQuery)) {
            throw new AuthorizationException("No access to delete saved query [{$savedQuery->id}].");
        }

        $savedQuery->delete();
    }

    public function syncRoles(User $user, int $id, array $roleIds): SavedQuery
    {
        $savedQuery = SavedQuery::query()->with(['roles', 'layer', 'owner'])->findOrFail($id);

        if (!$this->canManage($user, $savedQuery)) {
            throw new AuthorizationException("No access to manage roles for saved query [{$savedQuery->id}].");
        }

        $savedQuery->roles()->sync(array_values(array_unique(array_map('intval', $roleIds))));
        $savedQuery->visibility = 'role';
        $savedQuery->save();

        return $savedQuery->fresh(['layer', 'owner', 'roles']);
    }

    public function execute(User $user, int $id, Request $request): array
    {
        $savedQuery = SavedQuery::query()
            ->with(['layer.dataSource', 'layer.fields', 'layer.permissions', 'roles', 'owner'])
            ->findOrFail($id);

        if (!$this->canView($user, $savedQuery)) {
            throw new AuthorizationException("No access to execute saved query [{$savedQuery->id}].");
        }

        if (!$savedQuery->is_active) {
            throw new InvalidArgumentException("Saved query [{$savedQuery->id}] is inactive.");
        }

        $layer = $savedQuery->layer;
        $payload = $savedQuery->payload_json ?? [];

        $startedAt = microtime(true);

        try {
            $result = match ($savedQuery->query_type) {
                'feature_query' => [
                    'type' => 'feature_query',
                    'data' => $this->featureQueryService->query($layer, $payload, $user),
                    'meta' => [],
                ],
                'feature_count' => [
                    'type' => 'feature_count',
                    'data' => ['count' => $this->featureQueryService->count($layer, $payload, $user)],
                    'meta' => [],
                ],
                'feature_aggregate' => $this->mapAggregateResult(
                    $this->featureAnalyticsService->aggregate($layer, $payload, $user)
                ),
                'feature_statistics' => $this->mapStatisticsResult(
                    $this->featureAnalyticsService->statistics($layer, $payload, $user)
                ),
                default => throw new InvalidArgumentException("Unsupported saved query type [{$savedQuery->query_type}]."),
            };

            $durationMs = round((microtime(true) - $startedAt) * 1000, 3);

            $this->writeExecutionAudit(
                savedQuery: $savedQuery,
                user: $user,
                request: $request,
                status: 'success',
                requestPayload: $payload,
                responseMeta: $result['meta'] ?? [],
                error: null,
                resultCount: $this->detectResultCount($savedQuery->query_type, $result['data']),
                durationMs: $durationMs
            );

            return [
                'saved_query' => $this->mapSavedQuery($savedQuery),
                'execution' => $result,
            ];
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startedAt) * 1000, 3);

            $this->writeExecutionAudit(
                savedQuery: $savedQuery,
                user: $user,
                request: $request,
                status: 'failed',
                requestPayload: $payload,
                responseMeta: [],
                error: [
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                ],
                resultCount: 0,
                durationMs: $durationMs
            );

            throw $e;
        }
    }

    protected function mapAggregateResult(array $result): array
    {
        return [
            'type' => 'feature_aggregate',
            'data' => $result['rows'] ?? [],
            'meta' => $result['meta'] ?? [],
        ];
    }

    protected function mapStatisticsResult(array $result): array
    {
        return [
            'type' => 'feature_statistics',
            'data' => $result['stats'] ?? [],
            'meta' => $result['meta'] ?? [],
        ];
    }

    protected function canView(User $user, SavedQuery $savedQuery): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if ($savedQuery->visibility === 'public') {
            return true;
        }

        if ($savedQuery->owner_user_id === $user->id) {
            return true;
        }

        if ($savedQuery->visibility === 'role') {
            $roleIds = $user->roles()->pluck('roles.id')->all();

            if (empty($roleIds)) {
                return false;
            }

            return $savedQuery->roles()->whereIn('roles.id', $roleIds)->exists();
        }

        return false;
    }

    protected function canManage(User $user, SavedQuery $savedQuery): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $savedQuery->owner_user_id === $user->id;
    }

    protected function validateExecutionAccess(User $user, Layer $layer, string $queryType): void
    {
        match ($queryType) {
            'feature_query', 'feature_count' => $this->assertLayerAbility($user, $layer, 'query'),
            'feature_aggregate' => $this->assertLayerAbility($user, $layer, 'aggregate'),
            'feature_statistics' => $this->assertLayerAbility($user, $layer, 'statistics'),
            default => throw new InvalidArgumentException("Unsupported query_type [{$queryType}]."),
        };
    }

    protected function assertLayerAbility(User $user, Layer $layer, string $ability): void
    {
        if ($user->is_super_admin) {
            return;
        }

        if (!$user->hasLayerAbility($layer, $ability)) {
            throw new AuthorizationException("No [{$ability}] access to layer [{$layer->code}].");
        }
    }

    protected function generateUniqueCode(string $name): string
    {
        $base = Str::slug($name, '-');

        if ($base === '') {
            $base = 'saved-query';
        }

        $code = $base;
        $i = 1;

        while (SavedQuery::query()->where('code', $code)->exists()) {
            $i++;
            $code = $base . '-' . $i;
        }

        return $code;
    }

    protected function writeExecutionAudit(
        SavedQuery $savedQuery,
        User $user,
        Request $request,
        string $status,
        ?array $requestPayload,
        ?array $responseMeta,
        ?array $error,
        int $resultCount,
        ?float $durationMs
    ): void {
        AnalyticsExecution::create([
            'saved_query_id' => $savedQuery->id,
            'layer_id' => $savedQuery->layer_id,
            'user_id' => $user->id,
            'execution_type' => $savedQuery->query_type,
            'status' => $status,
            'request_payload_json' => $requestPayload,
            'response_meta_json' => $responseMeta,
            'error_json' => $error,
            'result_count' => $resultCount,
            'duration_ms' => $durationMs,
            'ip_address' => $request->ip(),
            'request_url' => $request->fullUrl(),
        ]);
    }

    protected function detectResultCount(string $queryType, mixed $data): int
    {
        return match ($queryType) {
            'feature_query', 'feature_aggregate' => is_array($data) ? count($data) : 0,
            'feature_count' => (int) ($data['count'] ?? 0),
            'feature_statistics' => is_array($data) ? count($data) : 0,
            default => 0,
        };
    }

    protected function mapSavedQuery(SavedQuery $savedQuery): array
    {
        return [
            'id' => $savedQuery->id,
            'code' => $savedQuery->code,
            'name' => $savedQuery->name,
            'description' => $savedQuery->description,
            'query_type' => $savedQuery->query_type,
            'visibility' => $savedQuery->visibility,
            'is_active' => (bool) $savedQuery->is_active,
            'layer' => [
                'id' => $savedQuery->layer?->id,
                'code' => $savedQuery->layer?->code,
                'name' => $savedQuery->layer?->name,
            ],
            'owner' => [
                'id' => $savedQuery->owner?->id,
                'name' => $savedQuery->owner?->name,
            ],
            'roles' => $savedQuery->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
            ])->values()->all(),
            'payload' => $savedQuery->payload_json ?? [],
            'metadata' => $savedQuery->metadata_json ?? [],
            'links' => [
                'self' => url("/api/v1/saved-queries/{$savedQuery->id}"),
                'execute' => url("/api/v1/saved-queries/{$savedQuery->id}/execute"),
            ],
            'created_at' => optional($savedQuery->created_at)?->toISOString(),
            'updated_at' => optional($savedQuery->updated_at)?->toISOString(),
        ];
    }
}