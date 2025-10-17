<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\{Blueprint, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_rules', function (Blueprint $table) {
            // Tambah kolom baru aja
            $table->boolean('is_active')->default(true)->after('max_duration');
            $table->json('alert_channels')->nullable()->after('is_active')
                ->comment('["telegram","email"]');
            $table->tinyInteger('priority')->default(1)->after('alert_channels')
                ->comment('Priority level (1=low, 3=high)');
            $table->integer('cooldown_minutes')->default(5)->after('priority')
                ->comment('Jeda antar alert yang sama (menit)');

            // Add index untuk query cepat
            $table->index(['app_name', 'message_key'], 'idx_app_message');
        });
    }

    public function down(): void
    {
        Schema::table('message_rules', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'alert_channels', 'priority', 'cooldown_minutes']);
            $table->dropIndex('idx_app_message');
        });
    }
};
