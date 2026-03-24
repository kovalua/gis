<?php

namespace App\Services\Gis\Analytics;

use App\Models\Layer;
use App\Models\User;
use App\Services\Gis\AccessResolverService;
use App\Services\Gis\LayerRuntimeMetadataService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeatureAnalyticsService
{
    public function __construct(
        protected AccessResolverService $accessResolverService,
        protected LayerRuntimeMetadataService $layerRuntimeMetadataService
    ) {
    }

    public function aggregate(Layer $layer, array $payload, ?User $user = null): array
    {
        $layer->loadMissing(['dataSource', 'fields', 'permissions']);

        $this->accessResolverService->assertLayerAbility($user, $layer, 'aggregate');

        $ds = $layer->dataSource;
        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;
        $primaryKey = $ds->primary_key;
        $srid = (int) ($ds->srid ?? 4326);

        $filter = $payload['filter'] ?? [];
        $spatial = $payload['spatial_filter'] ?? null;
        $groupBy = $payload['group_by'] ?? [];
        $aggregates = $payload['aggregates'] ?? [];
        $orderBy = $payload['order_by'] ?? [];
        $limit = (int) ($payload['limit'] ?? 100);
        $offset = (int) ($payload['offset'] ?? 0);

        if (!is_array($groupBy)) {
            throw new InvalidArgumentException('group_by must be an array.');
        }

        if (!is_array($aggregates) || empty($aggregates)) {
            throw new InvalidArgumentException('aggregates must be a non-empty array.');
        }

        if ($limit < 1) {
            $limit = 1;
        }

        if ($limit > 5000) {
            $limit = 5000;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $visibleColumns = $this->layerRuntimeMetadataService->visibleColumns($layer, $user);
        $filterableColumns = $this->layerRuntimeMetadataService->filterableColumns($layer, $user);
        $sortableColumns = $this->layerRuntimeMetadataService->sortableColumns($layer, $user);
        $numericColumns = $this->numericColumns($layer, $user);

        $validatedGroupBy = [];
        foreach ($groupBy as $field) {
            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('Each group_by field must be a non-empty string.');
            }

            if (!in_array($field, $visibleColumns, true)) {
                throw new InvalidArgumentException("Grouping by field [{$field}] is not allowed.");
            }

            $validatedGroupBy[] = $field;
        }

        $aggregateSqlParts = [];
        $aggregateAliases = [];

        foreach ($aggregates as $index => $aggregate) {
            if (!is_array($aggregate)) {
                throw new InvalidArgumentException('Each aggregate definition must be an object.');
            }

            $func = strtolower((string) ($aggregate['func'] ?? ''));
            $field = $aggregate['field'] ?? null;
            $alias = (string) ($aggregate['as'] ?? ('agg_' . ($index + 1)));

            if (!$this->isSafeAlias($alias)) {
                throw new InvalidArgumentException("Invalid aggregate alias [{$alias}].");
            }

            if (isset($aggregateAliases[$alias])) {
                throw new InvalidArgumentException("Duplicate aggregate alias [{$alias}].");
            }

            $aggregateAliases[$alias] = true;
            $aggregateSqlParts[] = $this->buildAggregateSql(
                $func,
                $field,
                $alias,
                $primaryKey,
                $geomColumn,
                $srid,
                $visibleColumns,
                $numericColumns
            );
        }

        $where = [];
        $bindings = [];

        $this->accessResolverService->applyRegionFilter($where, $bindings, $layer, $user);
        $this->applyAttributeFilters($filter, $filterableColumns, $where, $bindings);
        $this->applySpatialFilter($spatial, $geomColumn, $where, $bindings);

        $groupSelectParts = [];
        foreach ($validatedGroupBy as $field) {
            $groupSelectParts[] = $this->wrapIdentifier($field) . ' as ' . $this->wrapIdentifier($field);
        }

        $selectParts = array_merge($groupSelectParts, $aggregateSqlParts);

        if (empty($selectParts)) {
            throw new InvalidArgumentException('Nothing to select for aggregate query.');
        }

        $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
        $groupSql = empty($validatedGroupBy)
            ? ''
            : ('GROUP BY ' . implode(', ', array_map([$this, 'wrapIdentifier'], $validatedGroupBy)));

        $orderSql = $this->buildAggregateOrderBy(
            $orderBy,
            $validatedGroupBy,
            array_keys($aggregateAliases),
            $sortableColumns
        );

        $sql = "
            SELECT " . implode(', ', $selectParts) . "
            FROM {$this->wrapTable($schema, $table)}
            {$whereSql}
            {$groupSql}
            {$orderSql}
            LIMIT ?
            OFFSET ?
        ";

        $bindings[] = $limit;
        $bindings[] = $offset;

        $rows = DB::connection($connection)->select($sql, $bindings);

        return [
            'rows' => array_map(fn ($row) => (array) $row, $rows),
            'meta' => [
                'group_by' => $validatedGroupBy,
                'aggregates' => $aggregates,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ];
    }

    public function statistics(Layer $layer, array $payload, ?User $user = null): array
    {
        $layer->loadMissing(['dataSource', 'fields', 'permissions']);

        $this->accessResolverService->assertLayerAbility($user, $layer, 'statistics');

        $ds = $layer->dataSource;
        $connection = $ds->connection_name;
        $schema = $ds->schema_name;
        $table = $ds->table_name;
        $geomColumn = $ds->geometry_column;
        $primaryKey = $ds->primary_key;
        $srid = (int) ($ds->srid ?? 4326);

        $filter = $payload['filter'] ?? [];
        $spatial = $payload['spatial_filter'] ?? null;
        $fields = $payload['fields'] ?? [];
        $stats = $payload['stats'] ?? ['count', 'sum', 'avg', 'min', 'max'];
        $includeGeometryStats = (bool) ($payload['include_geometry_stats'] ?? false);

        if (!is_array($fields) || empty($fields)) {
            throw new InvalidArgumentException('fields must be a non-empty array.');
        }

        if (!is_array($stats) || empty($stats)) {
            throw new InvalidArgumentException('stats must be a non-empty array.');
        }

        $filterableColumns = $this->layerRuntimeMetadataService->filterableColumns($layer, $user);
        $numericColumns = $this->numericColumns($layer, $user);

        $validatedFields = [];
        foreach ($fields as $field) {
            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('Each field in statistics.fields must be a non-empty string.');
            }

            if (!in_array($field, $numericColumns, true)) {
                throw new InvalidArgumentException("Statistics for field [{$field}] are not allowed.");
            }

            $validatedFields[] = $field;
        }

        $validatedStats = [];
        foreach ($stats as $stat) {
            $stat = strtolower((string) $stat);

            if (!in_array($stat, ['count', 'sum', 'avg', 'min', 'max'], true)) {
                throw new InvalidArgumentException("Statistic [{$stat}] is not supported.");
            }

            $validatedStats[] = $stat;
        }

        $selectParts = [];

        foreach ($validatedFields as $field) {
            foreach ($validatedStats as $stat) {
                $alias = $field . '_' . $stat;
                $wrappedField = $this->wrapIdentifier($field);

                $selectParts[] = match ($stat) {
                    'count' => "COUNT({$wrappedField}) as " . $this->wrapIdentifier($alias),
                    'sum' => "SUM({$wrappedField}) as " . $this->wrapIdentifier($alias),
                    'avg' => "AVG({$wrappedField}) as " . $this->wrapIdentifier($alias),
                    'min' => "MIN({$wrappedField}) as " . $this->wrapIdentifier($alias),
                    'max' => "MAX({$wrappedField}) as " . $this->wrapIdentifier($alias),
                };
            }
        }

        if ($includeGeometryStats) {
            $selectParts[] = "COUNT(*) as " . $this->wrapIdentifier('feature_count');
            $selectParts[] = "SUM(ST_Area(ST_Transform({$this->wrapIdentifier($geomColumn)}, 3857))) as " . $this->wrapIdentifier('geometry_area_sum_m2');
            $selectParts[] = "AVG(ST_Area(ST_Transform({$this->wrapIdentifier($geomColumn)}, 3857))) as " . $this->wrapIdentifier('geometry_area_avg_m2');
        } elseif (!in_array('count', $validatedStats, true)) {
            $selectParts[] = "COUNT({$this->wrapIdentifier($primaryKey)}) as " . $this->wrapIdentifier('feature_count');
        }

        $where = [];
        $bindings = [];

        $this->accessResolverService->applyRegionFilter($where, $bindings, $layer, $user);
        $this->applyAttributeFilters($filter, $filterableColumns, $where, $bindings);
        $this->applySpatialFilter($spatial, $geomColumn, $where, $bindings);

        $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

        $sql = "
            SELECT " . implode(', ', $selectParts) . "
            FROM {$this->wrapTable($schema, $table)}
            {$whereSql}
        ";

        $row = DB::connection($connection)->selectOne($sql, $bindings);

        return [
            'stats' => (array) $row,
            'meta' => [
                'fields' => $validatedFields,
                'stats' => $validatedStats,
                'include_geometry_stats' => $includeGeometryStats,
            ],
        ];
    }

    protected function buildAggregateSql(
        string $func,
        mixed $field,
        string $alias,
        string $primaryKey,
        string $geomColumn,
        int $srid,
        array $visibleColumns,
        array $numericColumns
    ): string {
        return match ($func) {
            'count' => $this->buildCountAggregateSql($field, $alias, $primaryKey, $visibleColumns),
            'sum', 'avg', 'min', 'max' => $this->buildNumericAggregateSql($func, $field, $alias, $numericColumns),
            'area_sum' => "SUM(ST_Area(ST_Transform({$this->wrapIdentifier($geomColumn)}, 3857))) as " . $this->wrapIdentifier($alias),
            'area_avg' => "AVG(ST_Area(ST_Transform({$this->wrapIdentifier($geomColumn)}, 3857))) as " . $this->wrapIdentifier($alias),
            default => throw new InvalidArgumentException("Aggregate function [{$func}] is not supported."),
        };
    }

    protected function buildCountAggregateSql(
        mixed $field,
        string $alias,
        string $primaryKey,
        array $visibleColumns
    ): string {
        if ($field === null || $field === '*' || $field === '') {
            return "COUNT(*) as " . $this->wrapIdentifier($alias);
        }

        $field = (string) $field;

        if ($field !== $primaryKey && !in_array($field, $visibleColumns, true)) {
            throw new InvalidArgumentException("COUNT by field [{$field}] is not allowed.");
        }

        return "COUNT(" . $this->wrapIdentifier($field) . ") as " . $this->wrapIdentifier($alias);
    }

    protected function buildNumericAggregateSql(
        string $func,
        mixed $field,
        string $alias,
        array $numericColumns
    ): string {
        $field = (string) $field;

        if ($field === '') {
            throw new InvalidArgumentException("Aggregate function [{$func}] requires field.");
        }

        if (!in_array($field, $numericColumns, true)) {
            throw new InvalidArgumentException("Aggregate [{$func}] for field [{$field}] is not allowed.");
        }

        $wrappedField = $this->wrapIdentifier($field);

        return match ($func) {
            'sum' => "SUM({$wrappedField}) as " . $this->wrapIdentifier($alias),
            'avg' => "AVG({$wrappedField}) as " . $this->wrapIdentifier($alias),
            'min' => "MIN({$wrappedField}) as " . $this->wrapIdentifier($alias),
            'max' => "MAX({$wrappedField}) as " . $this->wrapIdentifier($alias),
        };
    }

    protected function applyAttributeFilters(
        array $filter,
        array $filterableColumns,
        array &$where,
        array &$bindings
    ): void {
        foreach ($filter as $field => $condition) {
            if (!in_array($field, $filterableColumns, true)) {
                throw new InvalidArgumentException("Filtering by field [{$field}] is not allowed.");
            }

            if (!is_array($condition)) {
                throw new InvalidArgumentException("Condition for field [{$field}] must be an object.");
            }

            $wrappedField = $this->wrapIdentifier($field);

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
    }

    protected function applySpatialFilter(
        mixed $spatial,
        string $geomColumn,
        array &$where,
        array &$bindings
    ): void {
        if (!$spatial || !is_array($spatial)) {
            return;
        }

        $wrappedGeom = $this->wrapIdentifier($geomColumn);

        if (isset($spatial['geometry'])) {
            $geojson = json_encode($spatial['geometry'], JSON_UNESCAPED_UNICODE);
            $srid = (int) ($spatial['srid'] ?? 4326);
            $type = $spatial['type'] ?? 'intersects';

            if ($type === 'intersects') {
                $where[] = "ST_Intersects(
                    {$wrappedGeom},
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                )";
                $bindings[] = $geojson;
                $bindings[] = $srid;
                return;
            }

            if ($type === 'within') {
                $where[] = "ST_Within(
                    {$wrappedGeom},
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                )";
                $bindings[] = $geojson;
                $bindings[] = $srid;
                return;
            }

            throw new InvalidArgumentException("Spatial filter type [{$type}] is not supported.");
        }

        if (
            ($spatial['type'] ?? null) === 'bbox'
            && isset($spatial['bbox'])
            && is_array($spatial['bbox'])
            && count($spatial['bbox']) === 4
        ) {
            $srid = (int) ($spatial['srid'] ?? 4326);

            $where[] = "{$wrappedGeom} && ST_MakeEnvelope(?, ?, ?, ?, ?)";
            $bindings[] = $spatial['bbox'][0];
            $bindings[] = $spatial['bbox'][1];
            $bindings[] = $spatial['bbox'][2];
            $bindings[] = $spatial['bbox'][3];
            $bindings[] = $srid;
            return;
        }

        throw new InvalidArgumentException('Invalid spatial_filter payload.');
    }

    protected function buildAggregateOrderBy(
        array $orderBy,
        array $groupByColumns,
        array $aggregateAliases,
        array $sortableColumns
    ): string {
        if (empty($orderBy)) {
            return '';
        }

        $parts = [];

        foreach ($orderBy as $item) {
            $field = (string) ($item['field'] ?? '');
            $direction = strtoupper((string) ($item['direction'] ?? 'ASC'));

            if ($field === '') {
                throw new InvalidArgumentException('order_by.field is required.');
            }

            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }

            $isGroupField = in_array($field, $groupByColumns, true);
            $isAggregateAlias = in_array($field, $aggregateAliases, true);

            if (!$isGroupField && !$isAggregateAlias) {
                throw new InvalidArgumentException("Ordering by field [{$field}] is not allowed.");
            }

            if ($isGroupField && !in_array($field, $sortableColumns, true)) {
                throw new InvalidArgumentException("Ordering by grouped field [{$field}] is not allowed.");
            }

            $parts[] = $this->wrapIdentifier($field) . ' ' . $direction;
        }

        if (empty($parts)) {
            return '';
        }

        return 'ORDER BY ' . implode(', ', $parts);
    }

    protected function numericColumns(Layer $layer, ?User $user): array
    {
        $visibleColumns = $this->layerRuntimeMetadataService->visibleColumns($layer, $user);

        $columnTypes = $this->columnTypes($layer);

        $numericTypes = [
            'smallint',
            'integer',
            'bigint',
            'decimal',
            'numeric',
            'real',
            'double precision',
            'smallserial',
            'serial',
            'bigserial',
        ];

        return array_values(array_filter(
            $visibleColumns,
            function ($column) use ($columnTypes, $numericTypes) {
                $type = strtolower((string) ($columnTypes[$column] ?? ''));
                return in_array($type, $numericTypes, true);
            }
        ));
    }

    protected function columnTypes(Layer $layer): array
    {
        $ds = $layer->dataSource;

        $rows = DB::connection($ds->connection_name)->select(
            "
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = ?
              AND table_name = ?
            ",
            [$ds->schema_name, $ds->table_name]
        );

        $result = [];

        foreach ($rows as $row) {
            $result[$row->column_name] = $row->data_type;
        }

        return $result;
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

    protected function isSafeAlias(string $alias): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias);
    }
}