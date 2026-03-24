<?php

namespace App\Services\Gis;

use App\Models\Layer;
use App\Models\LayerPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LayerRuntimeMetadataService
{
    public function canReadAttributes(?User $user, Layer $layer): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        return $user->hasLayerAbility($layer, 'attributes');
    }

    public function visibleColumns(Layer $layer, ?User $user): array
    {
        $schemaColumns = $this->schemaColumns($layer);

        if (!$this->hasLayerFieldMetadata($layer)) {
            return $this->fallbackVisibleColumns($layer, $user, $schemaColumns);
        }

        $fieldRows = $layer->fields;
        $allowed = $this->allowedFieldSet($layer, $user);
        $denied = $this->deniedFieldSet($layer, $user);

        $result = [];

        foreach ($fieldRows as $field) {
            $column = (string) ($field->db_column ?: $field->name);

            if (!in_array($column, $schemaColumns, true)) {
                continue;
            }

            if (!$field->is_visible) {
                continue;
            }

            if (isset($denied[$field->name]) || isset($denied[$column])) {
                continue;
            }

            if (!empty($allowed) && !isset($allowed[$field->name]) && !isset($allowed[$column])) {
                continue;
            }

            $result[] = $column;
        }

        return $this->normalizeColumns($layer, $result, $schemaColumns);
    }

    public function filterableColumns(Layer $layer, ?User $user): array
    {
        $schemaColumns = $this->schemaColumns($layer);

        if (!$this->hasLayerFieldMetadata($layer)) {
            return $this->fallbackFilterableColumns($layer, $user, $schemaColumns);
        }

        $fieldRows = $layer->fields;
        $allowed = $this->allowedFieldSet($layer, $user);
        $denied = $this->deniedFieldSet($layer, $user);

        $result = [];

        foreach ($fieldRows as $field) {
            $column = (string) ($field->db_column ?: $field->name);

            if (!in_array($column, $schemaColumns, true)) {
                continue;
            }

            if (!$field->is_filterable) {
                continue;
            }

            if (isset($denied[$field->name]) || isset($denied[$column])) {
                continue;
            }

            if (!empty($allowed) && !isset($allowed[$field->name]) && !isset($allowed[$column])) {
                continue;
            }

            $result[] = $column;
        }

        return $this->normalizeColumns($layer, $result, $schemaColumns);
    }

    public function sortableColumns(Layer $layer, ?User $user): array
    {
        $schemaColumns = $this->schemaColumns($layer);

        if (!$this->hasLayerFieldMetadata($layer)) {
            return $this->fallbackSortableColumns($layer, $user, $schemaColumns);
        }

        $fieldRows = $layer->fields;
        $allowed = $this->allowedFieldSet($layer, $user);
        $denied = $this->deniedFieldSet($layer, $user);

        $result = [];

        foreach ($fieldRows as $field) {
            $column = (string) ($field->db_column ?: $field->name);

            if (!in_array($column, $schemaColumns, true)) {
                continue;
            }

            if (!$field->is_sortable) {
                continue;
            }

            if (isset($denied[$field->name]) || isset($denied[$column])) {
                continue;
            }

            if (!empty($allowed) && !isset($allowed[$field->name]) && !isset($allowed[$column])) {
                continue;
            }

            $result[] = $column;
        }

        return $this->normalizeColumns($layer, $result, $schemaColumns);
    }

    public function editableColumns(Layer $layer, ?User $user): array
    {
        $schemaColumns = $this->schemaColumns($layer);
        $primaryKey = $layer->dataSource->primary_key;
        $geomColumn = $layer->dataSource->geometry_column;

        if (!$this->hasLayerFieldMetadata($layer)) {
            return $this->fallbackEditableColumns($layer, $user, $schemaColumns);
        }

        $fieldRows = $layer->fields;
        $allowed = $this->allowedFieldSet($layer, $user);
        $denied = $this->deniedFieldSet($layer, $user);

        $result = [];

        foreach ($fieldRows as $field) {
            $column = (string) ($field->db_column ?: $field->name);

            if (!in_array($column, $schemaColumns, true)) {
                continue;
            }

            if (in_array($column, [$primaryKey, $geomColumn], true)) {
                continue;
            }

            if (!$field->is_editable) {
                continue;
            }

            if (isset($denied[$field->name]) || isset($denied[$column])) {
                continue;
            }

            if (!empty($allowed) && !isset($allowed[$field->name]) && !isset($allowed[$column])) {
                continue;
            }

            $result[] = $column;
        }

        return array_values(array_unique($result));
    }

    public function selectableColumns(Layer $layer, ?User $user): array
    {
        if (!$this->canReadAttributes($user, $layer)) {
            return [$layer->dataSource->primary_key];
        }

        return $this->visibleColumns($layer, $user);
    }

    public function resolveSelectedColumns(
        Layer $layer,
        ?User $user,
        array $requestedColumns
    ): array {
        $primaryKey = $layer->dataSource->primary_key;
        $geomColumn = $layer->dataSource->geometry_column;
        $visibleColumns = $this->selectableColumns($layer, $user);

        if (empty($requestedColumns)) {
            $columns = $visibleColumns;
        } else {
            $columns = [];

            foreach ($requestedColumns as $column) {
                if (!in_array($column, $visibleColumns, true)) {
                    continue;
                }

                if ($column === $geomColumn) {
                    continue;
                }

                $columns[] = $column;
            }
        }

        if (!in_array($primaryKey, $columns, true)) {
            array_unshift($columns, $primaryKey);
        }

        return array_values(array_unique($columns));
    }

    protected function hasLayerFieldMetadata(Layer $layer): bool
    {
        if (!$layer->relationLoaded('fields')) {
            $layer->load('fields');
        }

        return $layer->fields->isNotEmpty();
    }

    protected function schemaColumns(Layer $layer): array
    {
        $ds = $layer->dataSource;

        $rows = DB::connection($ds->connection_name)->select(
            "
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = ?
              AND table_name = ?
            ORDER BY ordinal_position
            ",
            [$ds->schema_name, $ds->table_name]
        );

        return array_map(fn ($row) => $row->column_name, $rows);
    }

    protected function fallbackVisibleColumns(Layer $layer, ?User $user, array $schemaColumns): array
    {
        $primaryKey = $layer->dataSource->primary_key;
        $geomColumn = $layer->dataSource->geometry_column;

        if (!$this->canReadAttributes($user, $layer)) {
            return [$primaryKey];
        }

        $allowed = $this->allowedFieldSet($layer, $user);
        $denied = $this->deniedFieldSet($layer, $user);

        $columns = array_values(array_filter(
            $schemaColumns,
            function ($column) use ($geomColumn, $allowed, $denied) {
                if ($column === $geomColumn) {
                    return false;
                }

                if (isset($denied[$column])) {
                    return false;
                }

                if (!empty($allowed) && !isset($allowed[$column])) {
                    return false;
                }

                return true;
            }
        ));

        if (!in_array($primaryKey, $columns, true)) {
            array_unshift($columns, $primaryKey);
        }

        return array_values(array_unique($columns));
    }

    protected function fallbackFilterableColumns(Layer $layer, ?User $user, array $schemaColumns): array
    {
        $primaryKey = $layer->dataSource->primary_key;
        $geomColumn = $layer->dataSource->geometry_column;

        if (!$user) {
            return [$primaryKey];
        }

        $allowed = $this->allowedFieldSet($layer, $user);
        $denied = $this->deniedFieldSet($layer, $user);

        $columns = array_values(array_filter(
            $schemaColumns,
            function ($column) use ($geomColumn, $allowed, $denied) {
                if ($column === $geomColumn) {
                    return false;
                }

                if (isset($denied[$column])) {
                    return false;
                }

                if (!empty($allowed) && !isset($allowed[$column])) {
                    return false;
                }

                return true;
            }
        ));

        if (!in_array($primaryKey, $columns, true)) {
            array_unshift($columns, $primaryKey);
        }

        return array_values(array_unique($columns));
    }

    protected function fallbackSortableColumns(Layer $layer, ?User $user, array $schemaColumns): array
    {
        return $this->fallbackFilterableColumns($layer, $user, $schemaColumns);
    }

    protected function fallbackEditableColumns(Layer $layer, ?User $user, array $schemaColumns): array
    {
        if (!$user) {
            return [];
        }

        $primaryKey = $layer->dataSource->primary_key;
        $geomColumn = $layer->dataSource->geometry_column;

        $allowed = $this->allowedFieldSet($layer, $user);
        $denied = $this->deniedFieldSet($layer, $user);

        return array_values(array_filter(
            $schemaColumns,
            function ($column) use ($primaryKey, $geomColumn, $allowed, $denied) {
                if (in_array($column, [$primaryKey, $geomColumn], true)) {
                    return false;
                }

                if (isset($denied[$column])) {
                    return false;
                }

                if (!empty($allowed) && !isset($allowed[$column])) {
                    return false;
                }

                return true;
            }
        ));
    }

    protected function allowedFieldSet(Layer $layer, ?User $user): array
    {
        if (!$user || $user->is_super_admin) {
            return [];
        }

        $permissions = $this->permissionRows($layer, $user);
        $allowed = [];

        foreach ($permissions as $permission) {
            foreach ((array) $permission->allowed_field_names_json as $name) {
                $allowed[(string) $name] = true;
            }
        }

        return $allowed;
    }

    protected function deniedFieldSet(Layer $layer, ?User $user): array
    {
        if (!$user || $user->is_super_admin) {
            return [];
        }

        $permissions = $this->permissionRows($layer, $user);
        $denied = [];

        foreach ($permissions as $permission) {
            foreach ((array) $permission->denied_field_names_json as $name) {
                $denied[(string) $name] = true;
            }
        }

        return $denied;
    }

    protected function permissionRows(Layer $layer, User $user)
    {
        $roleIds = $user->roles()->pluck('roles.id')->all();

        if (empty($roleIds)) {
            return collect();
        }

        return LayerPermission::query()
            ->where('layer_id', $layer->id)
            ->whereIn('role_id', $roleIds)
            ->get();
    }

    protected function normalizeColumns(Layer $layer, array $columns, array $schemaColumns): array
    {
        $primaryKey = $layer->dataSource->primary_key;
        $geomColumn = $layer->dataSource->geometry_column;

        $columns = array_values(array_filter(
            array_unique($columns),
            fn ($column) => $column !== $geomColumn && in_array($column, $schemaColumns, true)
        ));

        if (!in_array($primaryKey, $columns, true)) {
            array_unshift($columns, $primaryKey);
        }

        return array_values(array_unique($columns));
    }
}