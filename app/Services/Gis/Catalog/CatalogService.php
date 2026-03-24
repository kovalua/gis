<?php

namespace App\Services\Gis\Catalog;

use App\Models\GisService;
use App\Models\Layer;
use App\Models\LayerPermission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CatalogService
{
    public function fullCatalog(User $user): array
    {
        $layers = Layer::query()
            ->with([
                'dataSource',
                'fields',
                'operations',
                'styles',
                'services',
                'permissions',
            ])
            ->where('is_active', true)
            ->orderBy('catalog_order')
            ->orderBy('name')
            ->get();

        $resultLayers = [];
        foreach ($layers as $layer) {
            if (!$this->canViewLayer($user, $layer)) {
                continue;
            }

            $capabilities = $this->resolveCapabilities($user, $layer);

            $resultLayers[] = [
                'id' => $layer->id,
                'code' => $layer->code,
                'name' => $layer->name,
                'description' => $layer->description,
                'group' => $layer->group_code,
                'layer_type' => $layer->layer_type,
                'geometry_type' => $layer->geometry_type,
                'default_visibility' => (bool) $layer->default_visibility,
                'service_types' => $layer->services->pluck('service_type')->unique()->values()->all(),
                'links' => $this->buildLayerLinks($layer, $capabilities),
            ];
        }

        $services = GisService::query()
            ->with([
                'layers.permissions',
                'layers.fields',
                'layers.operations',
                'layers.styles',
            ])
            ->where('is_active', true)
            ->where('publish_status', 'published')
            ->orderBy('name')
            ->get();

        $resultServices = [];
        foreach ($services as $service) {
            $visible = false;

            foreach ($service->layers as $layer) {
                if ($this->canViewLayer($user, $layer)) {
                    $visible = true;
                    break;
                }
            }

            if (!$visible) {
                continue;
            }

            $resultServices[] = [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
                'service_type' => $service->service_type,
                'endpoint_path' => $service->endpoint_path,
                'is_public' => (bool) $service->is_public,
                'links' => [
                    'self' => url("/api/v1/catalog/services/{$service->code}"),
                    'service' => $this->buildServiceHref($service),
                ],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_super_admin' => (bool) $user->is_super_admin,
                    'regions' => $user->regionIds(),
                ],
                'services' => $resultServices,
                'layers' => $resultLayers,
            ],
        ];
    }

    public function layers(User $user): array
    {
        return [
            'success' => true,
            'data' => $this->fullCatalog($user)['data']['layers'],
        ];
    }

    public function layer(User $user, string $layerCode): array
    {
        $layer = $this->findLayerForCatalog($layerCode);

        if (!$this->canViewLayer($user, $layer)) {
            throw new AuthorizationException("No [view] access to layer [{$layer->code}].");
        }

        $capabilities = $this->resolveCapabilities($user, $layer);
        $fields = $this->resolveVisibleFields($user, $layer);
        $styles = $this->resolveVisibleStyles($user, $layer);

        return [
            'success' => true,
            'data' => [
                'id' => $layer->id,
                'code' => $layer->code,
                'name' => $layer->name,
                'description' => $layer->description,
                'group' => $layer->group_code,
                'layer_type' => $layer->layer_type,
                'geometry_type' => $layer->geometry_type,
                'min_zoom' => $layer->min_zoom,
                'max_zoom' => $layer->max_zoom,
                'default_visibility' => (bool) $layer->default_visibility,
                'title_field' => $layer->title_field,
                'description_field' => $layer->description_field,
                'capabilities' => $capabilities,
                'scope' => $this->resolveScope($user, $layer),
                'fields' => $fields,
                'operations' => $this->buildOperationLinks($layer, $capabilities),
                'styles' => $styles,
                'services' => $this->buildLayerServices($layer, $capabilities),
                'metadata' => $layer->metadata_json ?? [],
                'links' => $this->buildLayerLinks($layer, $capabilities),
            ],
        ];
    }

    public function layerFields(User $user, string $layerCode): array
    {
        $layer = $this->findLayerForCatalog($layerCode);

        if (!$this->canViewLayer($user, $layer)) {
            throw new AuthorizationException("No [view] access to layer [{$layer->code}].");
        }

        return [
            'success' => true,
            'data' => [
                'layer' => $layer->code,
                'fields' => $this->resolveVisibleFields($user, $layer),
            ],
        ];
    }

    public function layerCapabilities(User $user, string $layerCode): array
    {
        $layer = $this->findLayerForCatalog($layerCode);

        if (!$this->canViewLayer($user, $layer)) {
            throw new AuthorizationException("No [view] access to layer [{$layer->code}].");
        }

        return [
            'success' => true,
            'data' => [
                'layer' => $layer->code,
                'capabilities' => $this->resolveCapabilities($user, $layer),
                'scope' => $this->resolveScope($user, $layer),
            ],
        ];
    }

    public function layerStyle(User $user, string $layerCode): array
    {
        $layer = $this->findLayerForCatalog($layerCode);
        $capabilities = $this->resolveCapabilities($user, $layer);

        if (!$this->canViewLayer($user, $layer)) {
            throw new AuthorizationException("No [view] access to layer [{$layer->code}].");
        }

        if (!($capabilities['style_read'] ?? false)) {
            throw new AuthorizationException("No [style_read] access to layer [{$layer->code}].");
        }

        return [
            'success' => true,
            'data' => [
                'layer' => $layer->code,
                'styles' => $this->resolveVisibleStyles($user, $layer),
            ],
        ];
    }

    public function layerLegend(User $user, string $layerCode): array
    {
        $layer = $this->findLayerForCatalog($layerCode);
        $capabilities = $this->resolveCapabilities($user, $layer);

        if (!$this->canViewLayer($user, $layer)) {
            throw new AuthorizationException("No [view] access to layer [{$layer->code}].");
        }

        if (!($capabilities['style_read'] ?? false)) {
            throw new AuthorizationException("No [style_read] access to layer [{$layer->code}].");
        }

        $styles = [];
        foreach ($layer->styles as $style) {
            if (!$style->is_active) {
                continue;
            }

            $styles[] = [
                'code' => $style->code,
                'name' => $style->name,
                'is_default' => (bool) $style->is_default,
                'legend' => $style->legend_json,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'layer' => $layer->code,
                'legend' => $styles,
            ],
        ];
    }

    public function services(User $user): array
    {
        $services = GisService::query()
            ->with([
                'layers.permissions',
                'layers.fields',
                'layers.operations',
                'layers.styles',
            ])
            ->where('is_active', true)
            ->where('publish_status', 'published')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($services as $service) {
            $layers = [];

            foreach ($service->layers as $layer) {
                if (!$this->canViewLayer($user, $layer)) {
                    continue;
                }

                $layers[] = [
                    'id' => $layer->id,
                    'code' => $layer->code,
                    'name' => $layer->name,
                    'layer_type' => $layer->layer_type,
                    'geometry_type' => $layer->geometry_type,
                ];
            }

            if (empty($layers)) {
                continue;
            }

            $result[] = [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
                'service_type' => $service->service_type,
                'endpoint_path' => $service->endpoint_path,
                'publish_status' => $service->publish_status,
                'is_public' => (bool) $service->is_public,
                'config' => $service->config_json ?? [],
                'metadata' => $service->metadata_json ?? [],
                'layers' => $layers,
                'links' => [
                    'self' => url("/api/v1/catalog/services/{$service->code}"),
                    'service' => $this->buildServiceHref($service),
                ],
            ];
        }

        return [
            'success' => true,
            'data' => $result,
        ];
    }

    public function service(User $user, string $serviceCode): array
    {
        $service = GisService::query()
            ->with([
                'layers.permissions',
                'layers.fields',
                'layers.operations',
                'layers.styles',
            ])
            ->where('code', $serviceCode)
            ->firstOrFail();

/** 
*
*   $service = GisService::query()
*    ->with([
*        'layers.permissions',
*        'layers.fields',
*        'layers.operations',
*        'layers.styles',
*    ])
*    ->where('code', $serviceCode)
*    ->first();
*
*if (!$service) {
*    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
*        "Service [{$serviceCode}] not found."
*    );
*}
*            
*
*/

        $layers = [];

        foreach ($service->layers as $layer) {
            if (!$this->canViewLayer($user, $layer)) {
                continue;
            }

            $capabilities = $this->resolveCapabilities($user, $layer);

            $layers[] = [
                'id' => $layer->id,
                'code' => $layer->code,
                'name' => $layer->name,
                'layer_type' => $layer->layer_type,
                'geometry_type' => $layer->geometry_type,
                'capabilities' => $capabilities,
                'links' => $this->buildLayerLinks($layer, $capabilities),
            ];
        }

        if (empty($layers)) {
            throw new AuthorizationException("No visible layers in service [{$service->code}].");
        }

        return [
            'success' => true,
            'data' => [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
                'service_type' => $service->service_type,
                'endpoint_path' => $service->endpoint_path,
                'publish_status' => $service->publish_status,
                'is_public' => (bool) $service->is_public,
                'config' => $service->config_json ?? [],
                'metadata' => $service->metadata_json ?? [],
                'layers' => $layers,
                'links' => [
                    'self' => url("/api/v1/catalog/services/{$service->code}"),
                    'service' => $this->buildServiceHref($service),
                ],
            ],
        ];
    }

    protected function findLayerForCatalog(string $layerCode): Layer
    {
        return Layer::query()
            ->with([
                'dataSource',
                'fields',
                'operations',
                'styles',
                'services',
                'permissions',
            ])
            ->where('code', $layerCode)
            ->where('is_active', true)
            ->firstOrFail();
    }

    protected function canViewLayer(User $user, Layer $layer): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if ($layer->is_public) {
            return true;
        }

        return $user->hasLayerAbility($layer, 'view');
    }

    protected function resolveCapabilities(User $user, Layer $layer): array
    {
        $capabilities = [
            'view' => false,
            'query' => false,
            'create' => false,
            'update' => false,
            'delete' => false,
            'export' => false,
            'tiles' => false,
            'identify' => false,
            'attributes' => false,
            'aggregate' => false,
            'statistics' => false,
            'style_read' => false,
        ];

        if (!$this->canViewLayer($user, $layer)) {
            return $capabilities;
        }

        if ($user->is_super_admin) {
            $capabilities = [
                'view' => true,
                'query' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'export' => true,
                'tiles' => true,
                'identify' => true,
                'attributes' => true,
                'aggregate' => true,
                'statistics' => true,
                'style_read' => true,
            ];
        } else {
            $capabilities['view'] = true;
            $capabilities['query'] = $user->hasLayerAbility($layer, 'query') && (bool) $layer->is_queryable;
            $capabilities['create'] = $user->hasLayerAbility($layer, 'create') && (bool) $layer->is_editable;
            $capabilities['update'] = $user->hasLayerAbility($layer, 'update') && (bool) $layer->is_editable;
            $capabilities['delete'] = $user->hasLayerAbility($layer, 'delete') && (bool) $layer->is_editable;
            $capabilities['export'] = $user->hasLayerAbility($layer, 'export') && (bool) $layer->is_exportable;
            $capabilities['tiles'] = $user->hasLayerAbility($layer, 'tiles');
            $capabilities['identify'] = $user->hasLayerAbility($layer, 'identify');
            $capabilities['attributes'] = $user->hasLayerAbility($layer, 'attributes');
            $capabilities['aggregate'] = $user->hasLayerAbility($layer, 'aggregate');
            $capabilities['statistics'] = $user->hasLayerAbility($layer, 'statistics');
            $capabilities['style_read'] = $user->hasLayerAbility($layer, 'style_read');
        }

        $enabledOps = $layer->operations
            ->where('is_enabled', true)
            ->pluck('operation_code')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        if (!empty($enabledOps)) {
            $map = [
                'view' => 'view',
                'query' => 'query',
                'create' => 'create',
                'update' => 'update',
                'delete' => 'delete',
                'export' => 'export',
                'tiles' => 'tiles',
                'identify' => 'identify',
                'attributes' => 'attributes',
                'aggregate' => 'aggregate',
                'statistics' => 'statistics',
                'style_read' => 'style_read',
            ];

            foreach ($map as $key => $operationCode) {
                if (!in_array($operationCode, $enabledOps, true)) {
                    $capabilities[$key] = false;
                }
            }
        }

        return $capabilities;
    }

    protected function resolveVisibleFields(User $user, Layer $layer): array
    {
        $fields = $layer->fields->where('is_visible', true)->values();

        if ($user->is_super_admin) {
            return $fields->map(fn ($field) => $this->mapField($field))->values()->all();
        }

        $roleIds = $user->roles()->pluck('roles.id')->all();

        $permissionRows = LayerPermission::query()
            ->where('layer_id', $layer->id)
            ->whereIn('role_id', $roleIds)
            ->get();

        $allowed = [];
        $denied = [];

        foreach ($permissionRows as $permission) {
            foreach ((array) $permission->allowed_field_names_json as $name) {
                $allowed[(string) $name] = true;
            }

            foreach ((array) $permission->denied_field_names_json as $name) {
                $denied[(string) $name] = true;
            }
        }

        $hasAllowed = !empty($allowed);

        return $fields
            ->filter(function ($field) use ($allowed, $denied, $hasAllowed) {
                $name = (string) $field->name;

                if (isset($denied[$name])) {
                    return false;
                }

                if ($hasAllowed && !isset($allowed[$name])) {
                    return false;
                }

                return true;
            })
            ->map(fn ($field) => $this->mapField($field))
            ->values()
            ->all();
    }

    protected function resolveVisibleStyles(User $user, Layer $layer): array
    {
        $styles = $layer->styles->where('is_active', true)->values();

        if ($user->is_super_admin) {
            return $styles->map(fn ($style) => [
                'code' => $style->code,
                'name' => $style->name,
                'style_type' => $style->style_type,
                'is_default' => (bool) $style->is_default,
                'style' => $style->style_json,
                'legend' => $style->legend_json,
            ])->values()->all();
        }

        $roleIds = $user->roles()->pluck('roles.id')->all();

        $permissionRows = LayerPermission::query()
            ->where('layer_id', $layer->id)
            ->whereIn('role_id', $roleIds)
            ->get();

        $allowedCodes = [];
        foreach ($permissionRows as $permission) {
            foreach ((array) $permission->allowed_style_codes_json as $code) {
                $allowedCodes[(string) $code] = true;
            }
        }

        return $styles
            ->filter(function ($style) use ($allowedCodes) {
                if (empty($allowedCodes)) {
                    return true;
                }

                return isset($allowedCodes[$style->code]);
            })
            ->map(fn ($style) => [
                'code' => $style->code,
                'name' => $style->name,
                'style_type' => $style->style_type,
                'is_default' => (bool) $style->is_default,
                'style' => $style->style_json,
                'legend' => $style->legend_json,
            ])
            ->values()
            ->all();
    }

    protected function resolveScope(User $user, Layer $layer): array
    {
        if ($user->is_super_admin) {
            return [
                'access_mode' => 'all',
                'regions' => ['*'],
                'layer_filter' => $layer->filter_definition_json ?? null,
            ];
        }

        $regions = $user->regionIds();

        return [
            'access_mode' => empty($regions) ? 'none' : 'filtered',
            'regions' => $regions,
            'layer_filter' => $layer->filter_definition_json ?? null,
        ];
    }

    protected function buildLayerLinks(Layer $layer, array $capabilities): array
    {
        $links = [
            'self' => url("/api/v1/catalog/layers/{$layer->code}"),
            'fields' => url("/api/v1/catalog/layers/{$layer->code}/fields"),
            'capabilities' => url("/api/v1/catalog/layers/{$layer->code}/capabilities"),
        ];

        if ($capabilities['style_read'] ?? false) {
            $links['style'] = url("/api/v1/catalog/layers/{$layer->code}/style");
            $links['legend'] = url("/api/v1/catalog/layers/{$layer->code}/legend");
        }

        if ($capabilities['query'] ?? false) {
            $links['query'] = url("/api/v1/features/{$layer->code}/query");
            $links['count'] = url("/api/v1/features/{$layer->code}/count");
        }

        if ($capabilities['create'] ?? false) {
            $links['create'] = url("/api/v1/features/{$layer->code}");
        }

        if ($capabilities['update'] ?? false) {
            $links['update'] = url("/api/v1/features/{$layer->code}/{id}");
        }

        if ($capabilities['delete'] ?? false) {
            $links['delete'] = url("/api/v1/features/{$layer->code}/{id}");
        }

        if ($capabilities['export'] ?? false) {
            $links['export'] = url("/api/v1/features/{$layer->code}/query");
        }

        return $links;
    }

    protected function buildOperationLinks(Layer $layer, array $capabilities): array
    {
        $operations = [];

        if ($capabilities['query'] ?? false) {
            $operations[] = [
                'code' => 'query',
                'method' => 'POST',
                'href' => url("/api/v1/features/{$layer->code}/query"),
            ];
            $operations[] = [
                'code' => 'count',
                'method' => 'POST',
                'href' => url("/api/v1/features/{$layer->code}/count"),
            ];
        }

        if ($capabilities['create'] ?? false) {
            $operations[] = [
                'code' => 'create',
                'method' => 'POST',
                'href' => url("/api/v1/features/{$layer->code}"),
            ];
        }

        if ($capabilities['update'] ?? false) {
            $operations[] = [
                'code' => 'update',
                'method' => 'PUT',
                'href' => url("/api/v1/features/{$layer->code}/{id}"),
            ];
        }

        if ($capabilities['delete'] ?? false) {
            $operations[] = [
                'code' => 'delete',
                'method' => 'DELETE',
                'href' => url("/api/v1/features/{$layer->code}/{id}"),
            ];
        }

        return $operations;
    }

    protected function buildLayerServices(Layer $layer, array $capabilities): array
    {
        $services = [];

        foreach ($layer->services as $service) {
            if ($service->publish_status !== 'published') {
                continue;
            }

            if ($service->service_type === 'vector_tiles' && !($capabilities['tiles'] ?? false)) {
                continue;
            }

            $services[] = [
                'code' => $service->code,
                'name' => $service->name,
                'service_type' => $service->service_type,
                'href' => $this->buildServiceHref($service),
            ];
        }

        return $services;
    }

    protected function buildServiceHref(GisService $service): ?string
    {
        if (!empty($service->endpoint_path)) {
            return url($service->endpoint_path);
        }

        return match ($service->service_type) {
            'vector_tiles' => url("/api/v1/tiles/services/{$service->code}/{z}/{x}/{y}.pbf"),
            'features' => url("/api/v1/features/services/{$service->code}"),
            default => null,
        };
    }

    protected function mapField($field): array
    {
        return [
            'name' => $field->name,
            'title' => $field->title,
            'type' => $field->data_type,
            'db_column' => $field->db_column,
            'nullable' => (bool) $field->is_nullable,
            'visible' => (bool) $field->is_visible,
            'filterable' => (bool) $field->is_filterable,
            'sortable' => (bool) $field->is_sortable,
            'searchable' => (bool) $field->is_searchable,
            'editable' => (bool) $field->is_editable,
            'visible_in_list' => (bool) $field->visible_in_list,
            'visible_in_popup' => (bool) $field->visible_in_popup,
            'visible_in_form' => (bool) $field->visible_in_form,
            'operators' => $field->operators_json ?? [],
            'domain' => $field->domain_json,
            'default_value' => $field->default_value,
            'metadata' => $field->metadata_json ?? [],
        ];
    }
}