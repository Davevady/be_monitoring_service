<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\{Blueprint, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Rule reference
            $table->enum('rule_type', ['app', 'message']);
            $table->unsignedBigInteger('rule_id');

            // Log details dari Elasticsearch
            $table->string('log_index');
            $table->string('log_id');
            $table->string('correlation_id', 100)->nullable()->index();

            // Log data
            $table->string('app_name', 100)->index();
            $table->text('message');
            $table->unsignedInteger('duration_ms');
            $table->timestamp('log_timestamp')->index();

            // Alert details
            $table->unsignedInteger('threshold_ms');
            $table->unsignedInteger('exceeded_by_ms');
            $table->json('alert_sent_to')->nullable();
            $table->enum('alert_status', ['pending', 'sent', 'failed'])->default('pending')->index();

            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            // Indexes
            $table->index(['log_index', 'log_id'], 'idx_log_ref');
            $table->unique(['log_index', 'log_id'], 'unique_alert');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
    }
};
