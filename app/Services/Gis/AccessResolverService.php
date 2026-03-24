<?php

namespace App\Services\Gis;

use App\Models\Layer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AccessResolverService
{
    public function assertLayerAbility(?User $user, Layer $layer, string $ability): void
    {
        if (!$user) {
            throw new AuthorizationException('Authentication required.');
        }

        if (!$user->hasLayerAbility($layer, $ability)) {
            throw new AuthorizationException("No [{$ability}] access to layer [{$layer->code}].");
        }
    }

    public function applyRegionFilter(array &$where, array &$bindings, Layer $layer, ?User $user): void
    {
        if (!$user) {
            return;
        }

        if ($user->is_super_admin) {
            return;
        }

        $ds = $layer->dataSource;
        $allowedColumns = $this->getAllowedColumns($ds);

        if (!in_array('region_id', $allowedColumns, true)) {
            return;
        }

        $regionIds = $user->regionIds();

        if (empty($regionIds)) {
            $where[] = '1 = 0';
            return;
        }

        if (in_array('*', $regionIds, true)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($regionIds), '?'));
        $where[] = '"region_id" IN (' . $placeholders . ')';
        $bindings = array_merge($bindings, $regionIds);
    }

    public function assertRegionAllowedForProperties(Layer $layer, ?User $user, array $properties): void
    {
        if (!$user) {
            throw new AuthorizationException('Authentication required.');
        }

        if ($user->is_super_admin) {
            return;
        }

        $ds = $layer->dataSource;
        $allowedColumns = $this->getAllowedColumns($ds);

        if (!in_array('region_id', $allowedColumns, true)) {
            return;
        }

        if (!array_key_exists('region_id', $properties)) {
            return;
        }

        $regionId = (int) $properties['region_id'];
        $allowedRegionIds = $user->regionIds();

        if (empty($allowedRegionIds)) {
            throw new AuthorizationException("No access to region [{$regionId}].");
        }

        if (in_array('*', $allowedRegionIds, true)) {
            return;
        }

        if (!in_array($regionId, $allowedRegionIds, true)) {
            throw new AuthorizationException("No access to region [{$regionId}].");
        }
    }

    public function assertFeatureRegionAllowed(Layer $layer, ?User $user, array $feature): void
    {
        if (!$user) {
            throw new AuthorizationException('Authentication required.');
        }

        if ($user->is_super_admin) {
            return;
        }

        $properties = $feature['properties'] ?? [];
        if (!is_array($properties)) {
            return;
        }

        if (!array_key_exists('region_id', $properties)) {
            return;
        }

        $regionId = (int) $properties['region_id'];
        $allowedRegionIds = $user->regionIds();

        if (empty($allowedRegionIds)) {
            throw new AuthorizationException("No access to region [{$regionId}].");
        }

        if (in_array('*', $allowedRegionIds, true)) {
            return;
        }

        if (!in_array($regionId, $allowedRegionIds, true)) {
            throw new AuthorizationException("No access to region [{$regionId}].");
        }
    }

    protected function getAllowedColumns($dataSource): array
    {
        $rows = DB::connection($dataSource->connection_name)->select(
            "
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = ?
              AND table_name = ?
            ORDER BY ordinal_position
            ",
            [$dataSource->schema_name, $dataSource->table_name]
        );

        return array_map(fn ($row) => $row->column_name, $rows);
    }
}