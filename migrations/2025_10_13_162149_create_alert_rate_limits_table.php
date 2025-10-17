<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\{Blueprint, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rate_limits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('rule_type', ['app', 'message']);
            $table->unsignedBigInteger('rule_id');
            $table->string('app_name', 100)->index();
            $table->string('message_hash', 64);

            $table->timestamp('last_alert_sent_at');
            $table->timestamp('cooldown_until')->index();
            $table->unsignedInteger('alert_count')->default(1);

            $table->timestamps();

            $table->unique(['rule_type', 'rule_id', 'app_name', 'message_hash'], 'unique_rate_limit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rate_limits');
    }
};
