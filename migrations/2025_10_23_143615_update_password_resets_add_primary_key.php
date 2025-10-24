<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class UpdatePasswordResetsAddPrimaryKey extends Migration
{
    public function up(): void
    {
        Schema::table('password_resets', function (Blueprint $table) {
            // Drop existing index if exists
            $table->dropIndex(['email']);
            
            // Add primary key
            $table->primary('email');
        });
    }

    public function down(): void
    {
        Schema::table('password_resets', function (Blueprint $table) {
            $table->dropPrimary(['email']);
            $table->index(['email']);
        });
    }
}