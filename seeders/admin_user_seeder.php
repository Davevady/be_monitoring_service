<?php

declare(strict_types=1);

namespace App\Seeder;

use App\Model\{Role, Permission, User};
use Hyperf\Database\Seeders\Seeder;

/**
 * Admin User Seeder
 * 
 * Creates default admin user, roles, and permissions
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Full system administrator with all permissions',
                'is_active' => true,
            ]
        );

        $userRole = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'User',
                'description' => 'Regular user with limited permissions',
                'is_active' => true,
            ]
        );

        // Create default permissions
        $permissions = [
            // Authentication permissions
            ['name' => 'auth.login', 'display_name' => 'Login', 'group' => 'auth'],
            ['name' => 'auth.logout', 'display_name' => 'Logout', 'group' => 'auth'],
            ['name' => 'auth.refresh', 'display_name' => 'Refresh Token', 'group' => 'auth'],
            ['name' => 'auth.profile', 'display_name' => 'View Profile', 'group' => 'auth'],
            ['name' => 'auth.update-profile', 'display_name' => 'Update Profile', 'group' => 'auth'],
            ['name' => 'auth.change-password', 'display_name' => 'Change Password', 'group' => 'auth'],
            ['name' => 'auth.forgot-password', 'display_name' => 'Forgot Password', 'group' => 'auth'],
            ['name' => 'auth.reset-password', 'display_name' => 'Reset Password', 'group' => 'auth'],

            // Dashboard permissions
            ['name' => 'dashboard.overview', 'display_name' => 'Dashboard Overview', 'group' => 'dashboard'],
            ['name' => 'dashboard.log-trends', 'display_name' => 'Log Trends', 'group' => 'dashboard'],
            ['name' => 'dashboard.app-performance', 'display_name' => 'App Performance', 'group' => 'dashboard'],

            // Monitor permissions
            ['name' => 'monitor.server', 'display_name' => 'Server Monitor', 'group' => 'monitor'],
            ['name' => 'monitor.trace', 'display_name' => 'Trace Logs', 'group' => 'monitor'],
            ['name' => 'monitor.violations-by-app', 'display_name' => 'Violations by App', 'group' => 'monitor'],
            ['name' => 'monitor.violations-by-message', 'display_name' => 'Violations by Message', 'group' => 'monitor'],

            // Rules permissions
            ['name' => 'rules.app.index', 'display_name' => 'List App Rules', 'group' => 'rules'],
            ['name' => 'rules.app.store', 'display_name' => 'Create App Rule', 'group' => 'rules'],
            ['name' => 'rules.app.show', 'display_name' => 'View App Rule', 'group' => 'rules'],
            ['name' => 'rules.app.update', 'display_name' => 'Update App Rule', 'group' => 'rules'],
            ['name' => 'rules.app.destroy', 'display_name' => 'Delete App Rule', 'group' => 'rules'],
            ['name' => 'rules.message.index', 'display_name' => 'List Message Rules', 'group' => 'rules'],
            ['name' => 'rules.message.store', 'display_name' => 'Create Message Rule', 'group' => 'rules'],
            ['name' => 'rules.message.show', 'display_name' => 'View Message Rule', 'group' => 'rules'],
            ['name' => 'rules.message.update', 'display_name' => 'Update Message Rule', 'group' => 'rules'],
            ['name' => 'rules.message.destroy', 'display_name' => 'Delete Message Rule', 'group' => 'rules'],

            // Role permissions
            ['name' => 'roles.index', 'display_name' => 'List Roles', 'group' => 'roles'],
            ['name' => 'roles.store', 'display_name' => 'Create Role', 'group' => 'roles'],
            ['name' => 'roles.show', 'display_name' => 'View Role', 'group' => 'roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Role', 'group' => 'roles'],
            ['name' => 'roles.destroy', 'display_name' => 'Delete Role', 'group' => 'roles'],
            ['name' => 'roles.assign-permissions', 'display_name' => 'Assign Permissions', 'group' => 'roles'],
            ['name' => 'roles.permissions', 'display_name' => 'View Role Permissions', 'group' => 'roles'],

            // Permission permissions
            ['name' => 'permissions.index', 'display_name' => 'List Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.store', 'display_name' => 'Create Permission', 'group' => 'permissions'],
            ['name' => 'permissions.show', 'display_name' => 'View Permission', 'group' => 'permissions'],
            ['name' => 'permissions.update', 'display_name' => 'Update Permission', 'group' => 'permissions'],
            ['name' => 'permissions.destroy', 'display_name' => 'Delete Permission', 'group' => 'permissions'],
            ['name' => 'permissions.groups', 'display_name' => 'Permission Groups', 'group' => 'permissions'],
            ['name' => 'permissions.grouped', 'display_name' => 'Grouped Permissions', 'group' => 'permissions'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                array_merge($permissionData, ['is_active' => true])
            );
        }

        // Assign all permissions to admin role
        $allPermissions = Permission::where('is_active', true)->pluck('id')->toArray();
        $adminRole->syncPermissions($allPermissions);

        // Assign basic permissions to user role
        $userPermissions = Permission::whereIn('name', [
            'auth.login',
            'auth.logout',
            'auth.refresh',
            'auth.profile',
            'auth.update-profile',
            'auth.change-password',
            'dashboard.overview',
            'dashboard.log-trends',
            'dashboard.app-performance',
            'monitor.server',
            'monitor.trace',
        ])->pluck('id')->toArray();
        
        $userRole->syncPermissions($userPermissions);

        // Create admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'is_active' => true,
                'role_id' => $adminRole->id,
                'email_verified_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]
        );

        // Create regular user
        $regularUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'email' => 'user@example.com',
                'password' => password_hash('user123', PASSWORD_BCRYPT),
                'is_active' => true,
                'role_id' => $userRole->id,
                'email_verified_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]
        );

        echo "Admin user created:\n";
        echo "Email: admin@example.com\n";
        echo "Password: admin123\n\n";
        
        echo "Regular user created:\n";
        echo "Email: user@example.com\n";
        echo "Password: user123\n\n";
        
        echo "Roles and permissions seeded successfully!\n";
    }
}
