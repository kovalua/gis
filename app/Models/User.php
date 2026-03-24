<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    public function regionAssignments(): HasMany
    {
        return $this->hasMany(UserRegion::class);
    }

    public function regionIds(): array
    {
        if ($this->is_super_admin) {
            return ['*'];
        }

        return $this->regionAssignments()
            ->pluck('region_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->contains(fn ($permission) => $permission->code === $permissionCode);
    }

    public function hasLayerAbility(Layer $layer, string $ability): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $abilityMap = [
            'view' => 'can_view',
            'query' => 'can_query',
            'create' => 'can_create',
            'update' => 'can_update',
            'delete' => 'can_delete',
            'export' => 'can_export',
            'tiles' => 'can_use_tiles',
            'identify' => 'can_identify',
            'attributes' => 'can_attributes',
            'aggregate' => 'can_aggregate',
            'statistics' => 'can_statistics',
            'style_read' => 'can_read_style',
        ];

        $column = $abilityMap[$ability] ?? null;

        if (!$column) {
            return false;
        }

        $roleIds = $this->roles()->pluck('roles.id')->all();

        if (empty($roleIds)) {
            return false;
        }

        return \App\Models\LayerPermission::query()
            ->where('layer_id', $layer->id)
            ->whereIn('role_id', $roleIds)
            ->where($column, true)
            ->exists();
    }
}