<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;
use Hyperf\Database\Model\Relations\BelongsToMany;

/**
 * Permission Model
 * 
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property string $resource
 * @property string $permission_type
 * @property string $group
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property \Hyperf\Database\Model\Collection|Role[] $roles
 */
class Permission extends Model
{
    protected ?string $table = 'permissions';

    protected array $fillable = [
        'name',
        'display_name',
        'description',
        'resource',
        'permission_type',
        'group',
        'is_active',
    ];

    protected array $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ✅ Permission types - HANYA 2 TYPE
    public const TYPE_MENU = 'menu';
    public const TYPE_ACTION = 'action';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->where('roles.is_active', true)
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByResource($query, string $resource)
    {
        return $query->where('resource', $resource);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('permission_type', $type);
    }

    public static function getGroups(): array
    {
        return self::select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->toArray();
    }

    public static function getResources(): array
    {
        return self::select('resource')
            ->distinct()
            ->orderBy('resource')
            ->pluck('resource')
            ->toArray();
    }

    /**
     * Get permissions grouped by group name
     */
    public static function getGroupedPermissions(): array
    {
        $permissions = self::active()
            ->orderBy('group')
            ->orderBy('display_name')
            ->get();

        $grouped = [];
        foreach ($permissions as $permission) {
            $group = $permission->group;
            
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'description' => $permission->description,
                'resource' => $permission->resource,
                'permission_type' => $permission->permission_type,
                'group' => $permission->group,
            ];
        }

        return $grouped;
    }

    /**
     * Get permissions grouped by resource for frontend consumption
     */
    public static function getGroupedByResource(): array
    {
        $permissions = self::active()
            ->orderBy('resource')
            ->orderBy('permission_type')
            ->get();

        $grouped = [];
        foreach ($permissions as $permission) {
            $resource = $permission->resource;
            
            if (!isset($grouped[$resource])) {
                $grouped[$resource] = [
                    'resource' => $resource,
                    'group' => $permission->group,
                    'menu' => null,
                    'actions' => []
                ];
            }

            if ($permission->permission_type === self::TYPE_MENU) {
                $grouped[$resource]['menu'] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                ];
            } else {
                $grouped[$resource]['actions'][] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                ];
            }
        }

        return array_values($grouped);
    }

    /**
     * Get permissions structured for frontend (grouped by group, then resource)
     */
    public static function getStructuredPermissions(): array
    {
        $permissions = self::active()
            ->orderBy('group')
            ->orderBy('resource')
            ->orderBy('permission_type')
            ->get();

        $structured = [];
        foreach ($permissions as $permission) {
            $group = $permission->group;
            $resource = $permission->resource;
            
            if (!isset($structured[$group])) {
                $structured[$group] = [
                    'group' => $group,
                    'resources' => []
                ];
            }

            if (!isset($structured[$group]['resources'][$resource])) {
                $structured[$group]['resources'][$resource] = [
                    'resource' => $resource,
                    'menu' => null,
                    'actions' => []
                ];
            }

            if ($permission->permission_type === self::TYPE_MENU) {
                $structured[$group]['resources'][$resource]['menu'] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                ];
            } else {
                $structured[$group]['resources'][$resource]['actions'][] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                ];
            }
        }

        // Convert to array values
        foreach ($structured as $groupKey => $groupData) {
            $structured[$groupKey]['resources'] = array_values($groupData['resources']);
        }

        return array_values($structured);
    }
}