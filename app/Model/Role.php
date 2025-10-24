<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\Relations\HasMany;

/**
 * Role Model
 * 
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property \Hyperf\Database\Model\Collection|User[] $users
 * @property \Hyperf\Database\Model\Collection|Permission[] $permissions
 */
class Role extends Model
{
    protected ?string $table = 'roles';

    protected array $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
    ];

    protected array $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all users that belong to this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all permissions for this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->where('permissions.is_active', true)
            ->withTimestamps();
    }

    /**
     * Get all menu permissions for this role.
     */
    public function getMenuPermissionsAttribute()
    {
        return $this->permissions()
            ->where('permission_type', Permission::TYPE_MENU)
            ->get()
            ->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'resource' => $permission->resource,
                    'group' => $permission->group,
                ];
            })
            ->toArray();
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()
            ->where('name', $permission)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if role has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->permissions()
            ->whereIn('name', $permissions)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if role has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $rolePermissions = $this->permissions()
            ->whereIn('name', $permissions)
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();

        return count($permissions) === count($rolePermissions);
    }

    /**
     * Assign permission to role.
     */
    public function assignPermission(Permission $permission): void
    {
        if (!$this->hasPermission($permission->name)) {
            $this->permissions()->attach($permission->id);
        }
    }

    /**
     * Remove permission from role.
     */
    public function removePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    /**
     * Sync permissions for role (replace all existing permissions).
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Check if role is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope: Active roles only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Roles with specific permission.
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->whereHas('permissions', function ($q) use ($permission) {
            $q->where('name', $permission);
        });
    }
}
