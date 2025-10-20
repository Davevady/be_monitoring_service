<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class UpdateAlertLogsUniqueKey extends Migration
{
    public function up(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            // Drop old unique (log_index, log_id) if exists
            try {
                $table->dropUnique('unique_alert');
            } catch (\Throwable $e) {
                // ignore if not exists
            }

            // Add message_hash for unique key (text cannot be used for unique index efficiently)
            if (!Schema::hasColumn('alert_logs', 'message_hash')) {
                $table->string('message_hash', 64)->after('message')->nullable()->index();
            }

            // New unique key based on business idempotency
            $table->unique(['rule_type', 'rule_id', 'message_hash', 'correlation_id'], 'unique_alert_by_msg_corr');
        });
    }

    public function down(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            try {
                $table->dropUnique('unique_alert_by_msg_corr');
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('alert_logs', 'message_hash')) {
                $table->dropColumn('message_hash');
            }
            // restore old unique if needed
            try {
                $table->unique(['log_index', 'log_id'], 'unique_alert');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
}


