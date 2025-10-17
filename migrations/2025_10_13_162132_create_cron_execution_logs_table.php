<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\{Blueprint, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_execution_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('job_name', 100)->default('log_alert_scanner')->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->enum('status', ['running', 'success', 'failed'])->default('running')->index();

            // Metrics
            $table->unsignedInteger('indices_scanned')->default(0);
            $table->unsignedInteger('logs_processed')->default(0);
            $table->unsignedInteger('alerts_triggered')->default(0);
            $table->unsignedInteger('alerts_sent')->default(0);

            // Performance
            $table->unsignedInteger('execution_time_ms')->default(0);
            $table->decimal('memory_usage_mb', 10, 2)->default(0);

            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_execution_logs');
    }
};
