<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Permission;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Permission Service
 * 
 * Handles permission management operations
 */
class PermissionService
{
    #[Inject]
    protected LoggerFactory $loggerFactory;

    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = $this->loggerFactory->get('permission');
    }

    /**
     * Get all permissions with pagination
     */
    public function getAllPermissions(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        try {
            $query = Permission::query();

            // Apply filters
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('display_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (isset($filters['is_active'])) {
                $query->where('is_active', (bool) $filters['is_active']);
            }

            if (!empty($filters['group'])) {
                $query->where('group', $filters['group']);
            }

            // Order by
            $query->orderBy('group')->orderBy('display_name');

            // Paginate
            $permissions = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $permissions->currentPage(),
                        'per_page' => $permissions->perPage(),
                        'total' => $permissions->total(),
                        'last_page' => $permissions->lastPage(),
                        'from' => $permissions->firstItem(),
                        'to' => $permissions->lastItem(),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get all permissions error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'data' => null,
            ];
        }
    }

    /**
     * Get permission by ID
     */
    public function getPermissionById(int $id): array
    {
        try {
            $permission = Permission::with('roles')->find($id);

            if (!$permission) {
                return [
                    'success' => false,
                    'message' => 'Permission not found',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Permission retrieved successfully',
                'data' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'description' => $permission->description,
                    'group' => $permission->group,
                    'is_active' => $permission->is_active,
                    'roles' => $permission->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'display_name' => $role->display_name,
                        ];
                    }),
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get permission by ID error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve permission',
                'data' => null,
            ];
        }
    }

    /**
     * Create new permission
     */
    public function createPermission(array $data): array
    {
        try {
            // Check if permission name already exists
            if (Permission::where('name', $data['name'])->exists()) {
                return [
                    'success' => false,
                    'message' => 'Permission name already exists',
                    'data' => null,
                ];
            }

            $permission = Permission::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'group' => $data['group'] ?? 'general',
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->logger->info('Permission created', [
                'permission_id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'group' => $permission->group,
            ]);

            return [
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'description' => $permission->description,
                    'group' => $permission->group,
                    'is_active' => $permission->is_active,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Create permission error', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create permission',
                'data' => null,
            ];
        }
    }

    /**
     * Update permission
     */
    public function updatePermission(int $id, array $data): array
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return [
                    'success' => false,
                    'message' => 'Permission not found',
                    'data' => null,
                ];
            }

            // Check if permission name already exists (excluding current permission)
            if (isset($data['name']) && Permission::where('name', $data['name'])->where('id', '!=', $id)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Permission name already exists',
                    'data' => null,
                ];
            }

            $permission->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            $this->logger->info('Permission updated', [
                'permission_id' => $permission->id,
                'name' => $permission->name,
                'updated_fields' => array_keys($data),
            ]);

            return [
                'success' => true,
                'message' => 'Permission updated successfully',
                'data' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'description' => $permission->description,
                    'group' => $permission->group,
                    'is_active' => $permission->is_active,
                    'updated_at' => $permission->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Update permission error', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update permission',
                'data' => null,
            ];
        }
    }

    /**
     * Delete permission
     */
    public function deletePermission(int $id): array
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return [
                    'success' => false,
                    'message' => 'Permission not found',
                    'data' => null,
                ];
            }

            // Check if permission is assigned to any role
            if ($permission->roles()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete permission that is assigned to roles',
                    'data' => null,
                ];
            }

            $permissionName = $permission->name;
            $permission->delete();

            $this->logger->info('Permission deleted', [
                'permission_id' => $id,
                'name' => $permissionName,
            ]);

            return [
                'success' => true,
                'message' => 'Permission deleted successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Delete permission error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete permission',
                'data' => null,
            ];
        }
    }

    /**
     * Get permission groups
     */
    public function getPermissionGroups(): array
    {
        try {
            $groups = Permission::getGroups();

            return [
                'success' => true,
                'message' => 'Permission groups retrieved successfully',
                'data' => $groups,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get permission groups error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve permission groups',
                'data' => null,
            ];
        }
    }

    /**
     * Get permissions grouped by group
     */
    public function getGroupedPermissions(): array
    {
        try {
            $groupedPermissions = Permission::getGroupedPermissions();

            return [
                'success' => true,
                'message' => 'Grouped permissions retrieved successfully',
                'data' => $groupedPermissions,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get grouped permissions error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve grouped permissions',
                'data' => null,
            ];
        }
    }

    /**
     * Sync permissions from routes
     */
    public function syncPermissionsFromRoutes(array $routePermissions): array
    {
        try {
            $synced = 0;
            $updated = 0;

            foreach ($routePermissions as $permission) {
                $existing = Permission::where('name', $permission['name'])->first();
                
                if ($existing) {
                    // Update existing permission
                    $existing->update([
                        'display_name' => $permission['display_name'] ?? $existing->display_name,
                        'description' => $permission['description'] ?? $existing->description,
                        'resource' => $permission['resource'] ?? $existing->resource,
                        'permission_type' => $permission['permission_type'] ?? $existing->permission_type,
                        'group' => $permission['group'] ?? $existing->group,
                    ]);
                    $updated++;
                    continue;
                }

                // Create new permission
                Permission::create([
                    'name' => $permission['name'],
                    'display_name' => $permission['display_name'] ?? $permission['name'],
                    'description' => $permission['description'] ?? null,
                    'resource' => $permission['resource'],
                    'permission_type' => $permission['permission_type'],
                    'group' => $permission['group'] ?? 'general',
                    'is_active' => true,
                ]);

                $synced++;
            }

            $this->logger->info('Permissions synced from routes', [
                'synced' => $synced,
                'updated' => $updated,
                'total' => count($routePermissions),
            ]);

            return [
                'success' => true,
                'message' => 'Permissions synced successfully',
                'data' => [
                    'synced' => $synced,
                    'updated' => $updated,
                    'total' => count($routePermissions),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Sync permissions from routes error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to sync permissions from routes',
                'data' => null,
            ];
        }
    }
}
