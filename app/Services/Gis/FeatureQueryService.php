<?php

namespace App\Services\Gis;

use App\Models\Layer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeatureQueryService
{
    public function __construct(
        protected AccessResolverService $accessResolverService,
        protected LayerRuntimeMetadataService $layerRuntimeMetadataService
    ) {
    }

    public function list(Layer $layer, int $limit = 50, ?User $user = null): array
    {
        $layer->loadMissing(['dataSource', 'fields', 'operations', 'styles', 'permissions']);

        $ds = $layer->dataSource;

        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;
        $primaryKey = $ds->primary_key;

        $this->accessResolverService->assertLayerAbility($user, $layer, 'view');

        if ($limit < 1) {
            $limit = 1;
        }

        if ($limit > 1000) {
            $limit = 1000;
        }

        $selectedColumns = $this->layerRuntimeMetadataService->resolveSelectedColumns(
            $layer,
            $user,
            []
        );

        $selectSql = implode(', ', array_map([$this, 'wrapIdentifier'], $selectedColumns));

        $where = [];
        $bindings = [];

        $this->accessResolverService->applyRegionFilter($where, $bindings, $layer, $user);

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "
            SELECT {$selectSql},
                   ST_AsGeoJSON({$this->wrapIdentifier($geomColumn)}) as geometry_geojson
            FROM {$this->wrapTable($schema, $table)}
            {$whereSql}
            ORDER BY {$this->wrapIdentifier($primaryKey)} ASC
            LIMIT ?
        ";

        $bindings[] = $limit;

        $rows = DB::connection($connection)->select($sql, $bindings);

        return $this->mapRowsToFeatures($rows, $geomColumn, $primaryKey);
    }

    public function query(Layer $layer, array $payload, ?User $user = null): array
    {
        $layer->loadMissing(['dataSource', 'fields', 'operations', 'styles', 'permissions']);

        $ds = $layer->dataSource;

        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;
        $primaryKey = $ds->primary_key;

        $this->accessResolverService->assertLayerAbility($user, $layer, 'query');

        $filter = $payload['filter'] ?? [];
        $spatial = $payload['spatial_filter'] ?? null;
        $select = $payload['select'] ?? [];
        $orderBy = $payload['order_by'] ?? [];
        $limit = (int) ($payload['limit'] ?? 50);
        $offset = (int) ($payload['offset'] ?? 0);

        if ($limit < 1) {
            $limit = 1;
        }

        if ($limit > 1000) {
            $limit = 1000;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $visibleColumns = $this->layerRuntimeMetadataService->selectableColumns($layer, $user);
        $filterableColumns = $this->layerRuntimeMetadataService->filterableColumns($layer, $user);
        $sortableColumns = $this->layerRuntimeMetadataService->sortableColumns($layer, $user);

        $selectedColumns = $this->layerRuntimeMetadataService->resolveSelectedColumns(
            $layer,
            $user,
            is_array($select) ? $select : []
        );

        $where = [];
        $bindings = [];

        $this->accessResolverService->applyRegionFilter($where, $bindings, $layer, $user);

        foreach ($filter as $field => $condition) {
            if (!in_array($field, $filterableColumns, true)) {
                throw new InvalidArgumentException("Filtering by field [{$field}] is not allowed.");
            }

            $wrappedField = $this->wrapIdentifier($field);
            $this->applyFieldConditions($where, $bindings, $wrappedField, $condition, $field);
        }

        if ($spatial && isset($spatial['geometry'])) {
            $geojson = json_encode($spatial['geometry'], JSON_UNESCAPED_UNICODE);
            $srid = (int) ($spatial['srid'] ?? 4326);
            $type = $spatial['type'] ?? 'intersects';

            $wrappedGeom = $this->wrapIdentifier($geomColumn);

            if ($type === 'intersects') {
                $where[] = "ST_Intersects(
                    {$wrappedGeom},
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                )";
                $bindings[] = $geojson;
                $bindings[] = $srid;
            } elseif (
                $type === 'bbox'
                && isset($spatial['bbox'])
                && is_array($spatial['bbox'])
                && count($spatial['bbox']) === 4
            ) {
                $where[] = "{$wrappedGeom} && ST_MakeEnvelope(?, ?, ?, ?, ?)";
                $bindings[] = $spatial['bbox'][0];
                $bindings[] = $spatial['bbox'][1];
                $bindings[] = $spatial['bbox'][2];
                $bindings[] = $spatial['bbox'][3];
                $bindings[] = $srid;
            } else {
                throw new InvalidArgumentException("Spatial filter type [{$type}] is not supported.");
            }
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $orderSql = $this->buildOrderBy($orderBy, $sortableColumns, $primaryKey);
        $selectSql = implode(', ', array_map([$this, 'wrapIdentifier'], $selectedColumns));

        $sql = "
            SELECT {$selectSql},
                   ST_AsGeoJSON({$this->wrapIdentifier($geomColumn)}) as geometry_geojson
            FROM {$this->wrapTable($schema, $table)}
            {$whereSql}
            {$orderSql}
            LIMIT ?
            OFFSET ?
        ";

        $bindings[] = $limit;
        $bindings[] = $offset;

        $rows = DB::connection($connection)->select($sql, $bindings);

        return $this->mapRowsToFeatures(
            $rows,
            $geomColumn,
            $primaryKey,
            $visibleColumns
        );
    }

    public function count(Layer $layer, array $payload, ?User $user = null): int
    {
        $layer->loadMissing(['dataSource', 'fields', 'operations', 'styles', 'permissions']);

        $ds = $layer->dataSource;

        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;

        $this->accessResolverService->assertLayerAbility($user, $layer, 'query');

        $filter = $payload['filter'] ?? [];
        $spatial = $payload['spatial_filter'] ?? null;
        $filterableColumns = $this->layerRuntimeMetadataService->filterableColumns($layer, $user);

        $where = [];
        $bindings = [];

        $this->accessResolverService->applyRegionFilter($where, $bindings, $layer, $user);

        foreach ($filter as $field => $condition) {
            if (!in_array($field, $filterableColumns, true)) {
                throw new InvalidArgumentException("Filtering by field [{$field}] is not allowed.");
            }

            $wrappedField = $this->wrapIdentifier($field);
            $this->applyFieldConditions($where, $bindings, $wrappedField, $condition, $field);
        }

        if ($spatial && isset($spatial['geometry'])) {
            $geojson = json_encode($spatial['geometry'], JSON_UNESCAPED_UNICODE);
            $srid = (int) ($spatial['srid'] ?? 4326);
            $type = $spatial['type'] ?? 'intersects';

            $wrappedGeom = $this->wrapIdentifier($geomColumn);

            if ($type === 'intersects') {
                $where[] = "ST_Intersects(
                    {$wrappedGeom},
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                )";
                $bindings[] = $geojson;
                $bindings[] = $srid;
            } elseif (
                $type === 'bbox'
                && isset($spatial['bbox'])
                && is_array($spatial['bbox'])
                && count($spatial['bbox']) === 4
            ) {
                $where[] = "{$wrappedGeom} && ST_MakeEnvelope(?, ?, ?, ?, ?)";
                $bindings[] = $spatial['bbox'][0];
                $bindings[] = $spatial['bbox'][1];
                $bindings[] = $spatial['bbox'][2];
                $bindings[] = $spatial['bbox'][3];
                $bindings[] = $srid;
            } else {
                throw new InvalidArgumentException("Spatial filter type [{$type}] is not supported.");
            }
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "
            SELECT COUNT(*) as total
            FROM {$this->wrapTable($schema, $table)}
            {$whereSql}
        ";

        $row = DB::connection($connection)->selectOne($sql, $bindings);

        return (int) ($row->total ?? 0);
    }

    protected function applyFieldConditions(
        array &$where,
        array &$bindings,
        string $wrappedField,
        mixed $condition,
        string $field
    ): void {
        if (!is_array($condition)) {
            throw new InvalidArgumentException("Condition for field [{$field}] must be an object.");
        }

        if (array_key_exists('eq', $condition)) {
            $where[] = "{$wrappedField} = ?";
            $bindings[] = $condition['eq'];
        }

        if (array_key_exists('neq', $condition)) {
            $where[] = "{$wrappedField} <> ?";
            $bindings[] = $condition['neq'];
        }

        if (array_key_exists('gt', $condition)) {
            $where[] = "{$wrappedField} > ?";
            $bindings[] = $condition['gt'];
        }

        if (array_key_exists('gte', $condition)) {
            $where[] = "{$wrappedField} >= ?";
            $bindings[] = $condition['gte'];
        }

        if (array_key_exists('lt', $condition)) {
            $where[] = "{$wrappedField} < ?";
            $bindings[] = $condition['lt'];
        }

        if (array_key_exists('lte', $condition)) {
            $where[] = "{$wrappedField} <= ?";
            $bindings[] = $condition['lte'];
        }

        if (array_key_exists('like', $condition)) {
            $where[] = "{$wrappedField} LIKE ?";
            $bindings[] = $condition['like'];
        }

        if (array_key_exists('ilike', $condition)) {
            $where[] = "{$wrappedField} ILIKE ?";
            $bindings[] = $condition['ilike'];
        }

        if (
            array_key_exists('in', $condition)
            && is_array($condition['in'])
            && count($condition['in']) > 0
        ) {
            $placeholders = implode(',', array_fill(0, count($condition['in']), '?'));
            $where[] = "{$wrappedField} IN ({$placeholders})";
            $bindings = array_merge($bindings, array_values($condition['in']));
        }

        if (
            array_key_exists('between', $condition)
            && is_array($condition['between'])
            && count($condition['between']) === 2
        ) {
            $where[] = "{$wrappedField} BETWEEN ? AND ?";
            $bindings[] = $condition['between'][0];
            $bindings[] = $condition['between'][1];
        }
    }

    protected function buildOrderBy(array $orderBy, array $sortableColumns, string $primaryKey): string
    {
        if (empty($orderBy)) {
            return 'ORDER BY ' . $this->wrapIdentifier($primaryKey) . ' ASC';
        }

        $parts = [];

        foreach ($orderBy as $item) {
            $field = $item['field'] ?? null;
            $direction = strtoupper($item['direction'] ?? 'ASC');

            if (!$field || !in_array($field, $sortableColumns, true)) {
                throw new InvalidArgumentException("Ordering by field [{$field}] is not allowed.");
            }

            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }

            $parts[] = $this->wrapIdentifier($field) . ' ' . $direction;
        }

        if (empty($parts)) {
            return 'ORDER BY ' . $this->wrapIdentifier($primaryKey) . ' ASC';
        }

        return 'ORDER BY ' . implode(', ', $parts);
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

    protected function mapRowsToFeatures(
        array $rows,
        string $geomColumn,
        string $primaryKey,
        array $visibleColumns = []
    ): array {
        $features = [];

        foreach ($rows as $row) {
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

            $features[] = [
                'id' => $rowArray[$primaryKey] ?? null,
                'properties' => $rowArray,
                'geometry' => $geometry,
            ];
        }

        return $features;
    }
}