<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\{Blueprint, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_rules', function (Blueprint $table) {
            // Tambah kolom baru aja, kolom lama tetap
            $table->boolean('is_active')->default(true)->after('max_duration');
            $table->json('alert_channels')->nullable()->after('is_active')
                ->comment('["telegram","email"]');
            $table->integer('cooldown_minutes')->default(5)->after('alert_channels')
                ->comment('Jeda antar alert yang sama (menit)');

            // Add unique constraint untuk app_name
            $table->unique('app_name');
        });
    }

    public function down(): void
    {
        Schema::table('app_rules', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'alert_channels', 'cooldown_minutes']);
            $table->dropUnique(['app_name']);
        });
    }
};
