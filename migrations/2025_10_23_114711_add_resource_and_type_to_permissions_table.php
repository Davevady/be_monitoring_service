<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class AddResourceAndTypeToPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Add new columns after 'description'
            $table->string('resource', 50)->after('description')->nullable();
            $table->enum('permission_type', ['menu', 'view', 'create', 'update', 'delete', 'action'])
                  ->default('action')
                  ->after('resource');
            
            // Add indexes
            $table->index(['resource']);
            $table->index(['permission_type']);
        });

        // Update existing data - set resource from name prefix
        $this->updateExistingPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['resource']);
            $table->dropIndex(['permission_type']);
            $table->dropColumn(['resource', 'permission_type']);
        });
    }

    /**
     * Update existing permissions with resource and type
     */
    private function updateExistingPermissions(): void
    {
        $permissions = \Hyperf\DbConnection\Db::table('permissions')->get();

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $resource = $parts[0] ?? 'general';
            $action = $parts[1] ?? 'index';

            // Determine permission type based on action
            $type = $this->determinePermissionType($action);

            \Hyperf\DbConnection\Db::table('permissions')
                ->where('id', $permission->id)
                ->update([
                    'resource' => $resource,
                    'permission_type' => $type,
                ]);
        }
    }

    private function determinePermissionType(string $action): string
    {
        $actionMap = [
            'index' => 'view',
            'show' => 'view',
            'store' => 'create',
            'update' => 'update',
            'destroy' => 'delete',
            'delete' => 'delete',
        ];

        return $actionMap[$action] ?? 'action';
    }
}