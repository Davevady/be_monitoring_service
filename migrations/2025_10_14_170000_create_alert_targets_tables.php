<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;

class CreateAlertTargetsTables extends Migration
{
    public function up(): void
    {
        Schema::create('alert_targets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type'); // e.g. telegram_chat, telegram_group, email, etc.
            $table->string('external_id'); // chat_id / group_id / email
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('app_rule_alert_target', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('app_rule_id');
            $table->unsignedBigInteger('alert_target_id');
            $table->timestamps();
            $table->unique(['app_rule_id', 'alert_target_id']);
            $table->index(['alert_target_id']);
        });

        Schema::create('message_rule_alert_target', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_rule_id');
            $table->unsignedBigInteger('alert_target_id');
            $table->timestamps();
            $table->unique(['message_rule_id', 'alert_target_id']);
            $table->index(['alert_target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_rule_alert_target');
        Schema::dropIfExists('app_rule_alert_target');
        Schema::dropIfExists('alert_targets');
    }
}


