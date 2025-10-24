<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Permission;
use App\Model\Role;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Role Service
 * 
 * Handles role management operations
 */
class RoleService
{
    #[Inject]
    protected LoggerFactory $loggerFactory;

    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = $this->loggerFactory->get('role');
    }

    /**
     * Get all roles with pagination
     */
    public function getAllRoles(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        try {
            $query = Role::query();

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

            // Order by
            $query->orderBy('created_at', 'desc');

            // Paginate
            $roles = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $roles->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $roles->currentPage(),
                        'per_page' => $roles->perPage(),
                        'total' => $roles->total(),
                        'last_page' => $roles->lastPage(),
                        'from' => $roles->firstItem(),
                        'to' => $roles->lastItem(),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get all roles error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'data' => null,
            ];
        }
    }

    /**
     * Get all active roles for dropdown (lightweight)
     */
    public function getAllRolesForDropdown(): array
    {
        try {
            $roles = Role::where('is_active', true)
                ->orderBy('display_name')
                ->get(['id', 'name', 'display_name']);

            $data = $roles->map(function ($role) {
                return [
                    'value' => $role->id,
                    'label' => $role->display_name,
                    'name' => $role->name,
                ];
            })->toArray();

            return [
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get roles for dropdown error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'data' => [],
            ];
        }
    }

    /**
     * Get role by ID
     */
    public function getRoleById(int $id): array
    {
        try {
            $role = Role::with('permissions')->find($id);

            if (!$role) {
                return [
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Role retrieved successfully',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'display_name' => $permission->display_name,
                            'resource' => $permission->resource ?? null,
                            'permission_type' => $permission->permission_type ?? null,
                            'group' => $permission->group,
                        ];
                    }),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get role by ID error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve role',
                'data' => null,
            ];
        }
    }

    /**
     * Create new role with default permissions
     */
    public function createRole(array $data): array
    {
        try {
            // Check if role name already exists
            if (Role::where('name', $data['name'])->exists()) {
                return [
                    'success' => false,
                    'message' => 'Role name already exists',
                    'data' => null,
                ];
            }

            $role = Role::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // ✅ Auto-assign default permissions (auth & profile)
            $this->assignDefaultPermissions($role);

            // Load permissions relationship
            $role->load('permissions');

            $this->logger->info('Role created with default permissions', [
                'role_id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'default_permissions_count' => $role->permissions->count(),
            ]);

            return [
                'success' => true,
                'message' => 'Role created successfully with default permissions',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'display_name' => $permission->display_name,
                            'resource' => $permission->resource ?? null,
                            'permission_type' => $permission->permission_type ?? null,
                            'group' => $permission->group,
                        ];
                    }),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Create role error', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create role',
                'data' => null,
            ];
        }
    }

    /**
     * Assign default permissions to newly created role
     * Default: auth and profile resources
     */
    private function assignDefaultPermissions(Role $role): void
    {
        try {
            // Get permissions for auth & profile resources
            $defaultPermissions = Permission::active()
                ->whereIn('resource', ['auth', 'profile'])
                ->pluck('id')
                ->toArray();

            if (!empty($defaultPermissions)) {
                // Attach permissions to role
                $role->permissions()->attach($defaultPermissions);
                
                $this->logger->info('Default permissions assigned to role', [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'permission_count' => count($defaultPermissions),
                    'permission_ids' => $defaultPermissions,
                    'resources' => ['auth', 'profile'],
                ]);
            } else {
                $this->logger->warning('No default permissions found for role', [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'message' => 'auth or profile permissions might not exist yet',
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Assign default permissions error', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Don't throw exception - role creation should succeed even if default permissions fail
        }
    }

    /**
     * Update role
     */
    public function updateRole(int $id, array $data): array
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return [
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => null,
                ];
            }

            // Check if role name already exists (excluding current role)
            if (isset($data['name']) && Role::where('name', $data['name'])->where('id', '!=', $id)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Role name already exists',
                    'data' => null,
                ];
            }

            $role->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            $this->logger->info('Role updated', [
                'role_id' => $role->id,
                'name' => $role->name,
                'updated_fields' => array_keys($data),
            ]);

            return [
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'updated_at' => $role->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Update role error', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update role',
                'data' => null,
            ];
        }
    }

    /**
     * Delete role
     */
    public function deleteRole(int $id): array
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return [
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => null,
                ];
            }

            // Check if role has users
            if ($role->users()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete role that has users assigned',
                    'data' => null,
                ];
            }

            // Detach all permissions before deleting
            $role->permissions()->detach();

            $roleName = $role->name;
            $role->delete();

            $this->logger->info('Role deleted', [
                'role_id' => $id,
                'name' => $roleName,
            ]);

            return [
                'success' => true,
                'message' => 'Role deleted successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Delete role error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete role',
                'data' => null,
            ];
        }
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissions(int $roleId, array $permissionIds): array
    {
        try {
            $role = Role::find($roleId);

            if (!$role) {
                return [
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => null,
                ];
            }

            // Validate permission IDs
            $validPermissions = Permission::whereIn('id', $permissionIds)
                ->where('is_active', true)
                ->get();

            if ($validPermissions->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No valid permissions found',
                    'data' => null,
                ];
            }

            $validPermissionIds = $validPermissions->pluck('id')->toArray();

            // Sync permissions (replace all)
            $role->syncPermissions($validPermissionIds);

            // Reload relationship
            $role->load('permissions');

            $this->logger->info('Permissions assigned to role', [
                'role_id' => $roleId,
                'role_name' => $role->name,
                'permission_count' => count($validPermissionIds),
                'permission_ids' => $validPermissionIds,
            ]);

            return [
                'success' => true,
                'message' => 'Permissions assigned successfully',
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ],
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'display_name' => $permission->display_name,
                            'resource' => $permission->resource ?? null,
                            'permission_type' => $permission->permission_type ?? null,
                            'group' => $permission->group,
                        ];
                    }),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Assign permissions error', [
                'role_id' => $roleId,
                'permission_ids' => $permissionIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to assign permissions',
                'data' => null,
            ];
        }
    }

    /**
     * Get role permissions
     */
    public function getRolePermissions(int $roleId): array
    {
        try {
            $role = Role::with('permissions')->find($roleId);

            if (!$role) {
                return [
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Role permissions retrieved successfully',
                'data' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'resource' => $permission->resource ?? null,
                        'permission_type' => $permission->permission_type ?? null,
                        'group' => $permission->group,
                    ];
                }),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get role permissions error', [
                'role_id' => $roleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve role permissions',
                'data' => null,
            ];
        }
    }
}