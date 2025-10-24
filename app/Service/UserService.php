<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use App\Model\Role;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * User Service
 * 
 * Handles user management operations
 */
class UserService
{
    #[Inject]
    protected LoggerFactory $loggerFactory;

    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = $this->loggerFactory->get('user');
    }

    /**
     * Get all users with pagination
     */
    public function getAllUsers(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        try {
            $query = User::query()->with('role');

            // Apply filters
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if (isset($filters['is_active'])) {
                $query->where('is_active', (bool) $filters['is_active']);
            }

            if (!empty($filters['role_id'])) {
                $query->where('role_id', $filters['role_id']);
            }

            // Order by
            $query->orderBy('created_at', 'desc');

            // Paginate
            $users = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'last_page' => $users->lastPage(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get all users error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve users',
                'data' => null,
            ];
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): array
    {
        try {
            $user = User::with('role')->find($id);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'User retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'is_active' => $user->is_active,
                    'role_id' => $user->role_id,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name,
                    ] : null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get user by ID error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve user',
                'data' => null,
            ];
        }
    }

    /**
     * Create new user
     */
    public function createUser(array $data): array
    {
        try {
            // Check if email already exists
            if (User::where('email', $data['email'])->exists()) {
                return [
                    'success' => false,
                    'message' => 'Email already exists',
                    'data' => null,
                    'code' => 409, // Conflict
                ];
            }

            // Validate role exists
            if (!empty($data['role_id'])) {
                $roleExists = Role::where('id', $data['role_id'])
                    ->where('is_active', true)
                    ->exists();
                
                if (!$roleExists) {
                    return [
                        'success' => false,
                        'message' => 'Invalid role selected',
                        'data' => null,
                        'code' => 400,
                    ];
                }
            }

            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'role_id' => $data['role_id'] ?? null,
            ]);

            // Load role relationship
            $user->load('role');

            $this->logger->info('User created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role_id' => $user->role_id,
            ]);

            return [
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => $user->is_active,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name,
                    ] : null,
                    'created_at' => $user->created_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Create user error', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create user',
                'data' => null,
            ];
        }
    }

    /**
     * Update user
     */
    public function updateUser(int $id, array $data): array
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                    'code' => 404,
                ];
            }

            // Check if email already exists (excluding current user)
            if (isset($data['email']) && User::where('email', $data['email'])->where('id', '!=', $id)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Email already exists',
                    'data' => null,
                    'code' => 409,
                ];
            }

            // Validate role if provided
            if (isset($data['role_id'])) {
                $roleExists = Role::where('id', $data['role_id'])
                    ->where('is_active', true)
                    ->exists();
                
                if (!$roleExists) {
                    return [
                        'success' => false,
                        'message' => 'Invalid role selected',
                        'data' => null,
                        'code' => 400,
                    ];
                }
            }

            // Prepare update data
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
            if (isset($data['is_active'])) $updateData['is_active'] = (bool) $data['is_active'];
            if (isset($data['role_id'])) $updateData['role_id'] = $data['role_id'];
            
            // Update password if provided
            if (!empty($data['password'])) {
                $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Reload user with role
            $user->load('role');

            $this->logger->info('User updated', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData),
            ]);

            return [
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => $user->is_active,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name,
                    ] : null,
                    'updated_at' => $user->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Update user error', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update user',
                'data' => null,
            ];
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id): array
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                    'code' => 404,
                ];
            }

            // Prevent deleting own account (optional, based on your requirements)
            // You can add this check if you have authentication context
            // if ($user->id === auth()->id()) {
            //     return [
            //         'success' => false,
            //         'message' => 'You cannot delete your own account',
            //         'data' => null,
            //         'code' => 403,
            //     ];
            // }

            $userName = $user->name;
            $userEmail = $user->email;
            $user->delete();

            $this->logger->info('User deleted', [
                'user_id' => $id,
                'name' => $userName,
                'email' => $userEmail,
            ]);

            return [
                'success' => true,
                'message' => 'User deleted successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Delete user error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete user',
                'data' => null,
            ];
        }
    }

    /**
     * Get user's permissions (via role)
     */
    public function getUserPermissions(int $id): array
    {
        try {
            $user = User::with('role.permissions')->find($id);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ];
            }

            if (!$user->role) {
                return [
                    'success' => true,
                    'message' => 'User has no role assigned',
                    'data' => [],
                ];
            }

            // Get permissions from role
            $permissions = $user->role->permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'resource' => $permission->resource,
                    'permission_type' => $permission->permission_type,
                    'group' => $permission->group,
                ];
            });

            return [
                'success' => true,
                'message' => 'User permissions retrieved successfully',
                'data' => $permissions,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get user permissions error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve user permissions',
                'data' => null,
            ];
        }
    }
}