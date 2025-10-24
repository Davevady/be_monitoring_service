<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Permission;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Database\Model\Relations\BelongsTo;

use function Hyperf\Collection\collect;

/**
 * User Model
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property string|null $avatar
 * @property bool $is_active
 * @property int|null $role_id
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property Role|null $role
 */
class User extends Model
{
    protected ?string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'avatar',
        'is_active',
        'role_id',
        'remember_token',
    ];

    protected array $casts = [
        'id' => 'integer',
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'role_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected array $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the role that owns the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get all permissions for the user through their role.
     * This is NOT a relationship method - it's a helper method
     */
    public function getPermissions(): array
    {
        if (!$this->role) {
            return [];
        }

        return $this->role->permissions()
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    public function getMenuPermissions(): array
    {
        return collect($this->role->permissions)
            ->filter(fn($permission) => ($permission['permission_type'] ?? null) === Permission::TYPE_MENU)
            ->values()
            ->toArray();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions()
            ->where('name', $permission)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions()
            ->whereIn('name', $permissions)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if (!$this->role) {
            return false;
        }

        $userPermissions = $this->role->permissions()
            ->whereIn('name', $permissions)
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();

        return count($permissions) === count($userPermissions);
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get user's full name or fallback to email.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->email;
    }

    /**
     * Scope: Active users only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Users with specific role.
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('role', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }
}