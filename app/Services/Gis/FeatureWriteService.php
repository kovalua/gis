<?php

namespace App\Services\Gis;

use App\Models\AuditLog;
use App\Models\Layer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeatureWriteService
{
    public function __construct(
        protected GeometryService $geometryService,
        protected AccessResolverService $accessResolverService,
        protected LayerRuntimeMetadataService $layerRuntimeMetadataService
    ) {
    }

    public function show(Layer $layer, $id, ?User $user = null): ?array
    {
        $layer->loadMissing(['dataSource', 'fields', 'permissions']);

        $ds = $layer->dataSource;
        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;
        $primaryKey = $ds->primary_key;

        $this->accessResolverService->assertLayerAbility($user, $layer, 'view');

        $selectedColumns = $this->layerRuntimeMetadataService->resolveSelectedColumns(
            $layer,
            $user,
            []
        );

        $selectSql = implode(', ', array_map([$this, 'wrapIdentifier'], $selectedColumns));

        $where = [];
        $bindings = [];

        $where[] = $this->wrapIdentifier($primaryKey) . ' = ?';
        $bindings[] = $id;

        $this->accessResolverService->applyRegionFilter($where, $bindings, $layer, $user);

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT {$selectSql},
                   ST_AsGeoJSON({$this->wrapIdentifier($geomColumn)}) as geometry_geojson
            FROM {$this->wrapTable($schema, $table)}
            {$whereSql}
            LIMIT 1
        ";

        $row = DB::connection($connection)->selectOne($sql, $bindings);

        if (!$row) {
            return null;
        }

        return $this->mapRowToFeature(
            $row,
            $geomColumn,
            $primaryKey,
            $this->layerRuntimeMetadataService->selectableColumns($layer, $user)
        );
    }

    public function create(Layer $layer, array $payload, Request $request, ?User $user = null): array
    {
        $layer->loadMissing(['dataSource', 'fields', 'permissions']);

        $ds = $layer->dataSource;
        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;
        $primaryKey = $ds->primary_key;

        $this->accessResolverService->assertLayerAbility($user, $layer, 'create');

        $properties = $payload['properties'] ?? [];
        $geometry = $payload['geometry'] ?? null;
        $srid = (int) ($payload['srid'] ?? $ds->srid ?? 4326);

        if (!is_array($properties)) {
            throw new InvalidArgumentException('Properties must be an object/array.');
        }

        if (!is_array($geometry)) {
            throw new InvalidArgumentException('Geometry is required and must be an object/array.');
        }

        $this->geometryService->validateOrFail($geometry, $srid);

        $editableColumns = $this->layerRuntimeMetadataService->editableColumns($layer, $user);

        $insertColumns = [];
        $placeholders = [];
        $bindings = [];

        foreach ($properties as $field => $value) {
            if (!in_array($field, $editableColumns, true)) {
                throw new InvalidArgumentException("Field [{$field}] is not allowed for insert.");
            }

            $insertColumns[] = $this->wrapIdentifier($field);
            $placeholders[] = '?';
            $bindings[] = $value;
        }

        $this->accessResolverService->assertRegionAllowedForProperties($layer, $user, $properties);

        $insertColumns[] = $this->wrapIdentifier($geomColumn);
        $placeholders[] = 'ST_SetSRID(ST_GeomFromGeoJSON(?), ?)';
        $bindings[] = json_encode($geometry, JSON_UNESCAPED_UNICODE);
        $bindings[] = $srid;

        $sql = "
            INSERT INTO {$this->wrapTable($schema, $table)}
            (" . implode(', ', $insertColumns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
            RETURNING {$this->wrapIdentifier($primaryKey)}
        ";

        $row = DB::connection($connection)->selectOne($sql, $bindings);
        $newId = $row->{$primaryKey} ?? null;

        $created = $this->show($layer, $newId, $user);

        $this->writeAudit(
            action: 'create',
            layerCode: $layer->code,
            entityId: (string) $newId,
            oldValues: null,
            newValues: $created,
            request: $request
        );

        return $created;
    }

    public function update(Layer $layer, $id, array $payload, Request $request, ?User $user = null): array
    {
        $layer->loadMissing(['dataSource', 'fields', 'permissions']);

        $ds = $layer->dataSource;
        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;
        $primaryKey = $ds->primary_key;

        $this->accessResolverService->assertLayerAbility($user, $layer, 'update');

        $existing = $this->show($layer, $id, $user);

        if (!$existing) {
            throw new InvalidArgumentException('Feature not found.');
        }

        $properties = $payload['properties'] ?? [];
        $geometry = $payload['geometry'] ?? null;
        $srid = (int) ($payload['srid'] ?? $ds->srid ?? 4326);

        if (!is_array($properties)) {
            $properties = [];
        }

        $editableColumns = $this->layerRuntimeMetadataService->editableColumns($layer, $user);

        $setParts = [];
        $bindings = [];

        foreach ($properties as $field => $value) {
            if (!in_array($field, $editableColumns, true)) {
                throw new InvalidArgumentException("Field [{$field}] is not allowed for update.");
            }

            $setParts[] = $this->wrapIdentifier($field) . ' = ?';
            $bindings[] = $value;
        }

        if (!empty($properties)) {
            $mergedProperties = array_merge($existing['properties'] ?? [], $properties);
            $this->accessResolverService->assertRegionAllowedForProperties($layer, $user, $mergedProperties);
        }

        if (is_array($geometry)) {
            $this->geometryService->validateOrFail($geometry, $srid);

            $setParts[] = $this->wrapIdentifier($geomColumn) . ' = ST_SetSRID(ST_GeomFromGeoJSON(?), ?)';
            $bindings[] = json_encode($geometry, JSON_UNESCAPED_UNICODE);
            $bindings[] = $srid;
        }

        if (empty($setParts)) {
            throw new InvalidArgumentException('Nothing to update.');
        }

        $where = [];
        $whereBindings = [];

        $where[] = $this->wrapIdentifier($primaryKey) . ' = ?';
        $whereBindings[] = $id;

        $this->accessResolverService->applyRegionFilter($where, $whereBindings, $layer, $user);

        $sql = "
            UPDATE {$this->wrapTable($schema, $table)}
            SET " . implode(', ', $setParts) . "
            WHERE " . implode(' AND ', $where) . "
        ";

        DB::connection($connection)->update($sql, array_merge($bindings, $whereBindings));

        $updated = $this->show($layer, $id, $user);

        if (!$updated) {
            throw new InvalidArgumentException('Feature not found after update or access denied.');
        }

        $this->writeAudit(
            action: 'update',
            layerCode: $layer->code,
            entityId: (string) $id,
            oldValues: $existing,
            newValues: $updated,
            request: $request
        );

        return $updated;
    }

    public function delete(Layer $layer, $id, Request $request, ?User $user = null): void
    {
        $layer->loadMissing(['dataSource', 'fields', 'permissions']);

        $ds = $layer->dataSource;
        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $primaryKey = $ds->primary_key;

        $this->accessResolverService->assertLayerAbility($user, $layer, 'delete');

        $existing = $this->show($layer, $id, $user);

        if (!$existing) {
            throw new InvalidArgumentException('Feature not found.');
        }

        $where = [];
        $bindings = [];

        $where[] = $this->wrapIdentifier($primaryKey) . ' = ?';
        $bindings[] = $id;

        $this->accessResolverService->applyRegionFilter($where, $bindings, $layer, $user);

        $sql = "
            DELETE FROM {$this->wrapTable($schema, $table)}
            WHERE " . implode(' AND ', $where) . "
        ";

        DB::connection($connection)->delete($sql, $bindings);

        $this->writeAudit(
            action: 'delete',
            layerCode: $layer->code,
            entityId: (string) $id,
            oldValues: $existing,
            newValues: null,
            request: $request
        );
    }

    protected function wrapIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Invalid identifier [{$identifier}].");
        }

        return '"' . $identifier . '"';
    }

    protected function wrapTable(string $schema, string $table): string
    {
        return $this->wrapIdentifier($schema) . '.' . $this->wrapIdentifier($table);
    }

    protected function mapRowToFeature(
        object $row,
        string $geomColumn,
        string $primaryKey,
        array $visibleColumns = []
    ): array {
        $rowArray = (array) $row;
        $geometry = json_decode($rowArray['geometry_geojson'] ?? 'null', true);

        unset($rowArray['geometry_geojson']);
        unset($rowArray[$geomColumn]);

        if (!empty($visibleColumns)) {
            $rowArray = array_filter(
                $rowArray,
                fn ($value, $key) => in_array($key, $visibleColumns, true),
                ARRAY_FILTER_USE_BOTH
            );
        }

        return [
            'id' => $rowArray[$primaryKey] ?? null,
            'properties' => $rowArray,
            'geometry' => $geometry,
        ];
    }

    protected function writeAudit(
        string $action,
        ?string $layerCode,
        ?string $entityId,
        $oldValues,
        $newValues,
        Request $request
    ): void {
        AuditLog::create([
            'entity_type' => 'feature',
            'entity_id' => $entityId,
            'action' => $action,
            'layer_code' => $layerCode,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'meta' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ],
            'user_id' => optional($request->user('sanctum'))->id,
            'ip_address' => $request->ip(),
        ]);
    }
}