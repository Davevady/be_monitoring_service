<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\{Blueprint, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_checkpoints', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('index_name')->unique();
            $table->timestamp('last_scanned_timestamp');
            $table->string('last_scanned_id')->nullable();
            $table->timestamp('last_scan_at')->useCurrent();
            $table->unsignedInteger('total_logs_scanned')->default(0);
            $table->unsignedInteger('total_alerts_sent')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_checkpoints');
    }
};
