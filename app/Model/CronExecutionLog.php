<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class CronExecutionLog extends Model
{
    protected ?string $table = 'cron_execution_logs';

    protected array $fillable = [
        'job_name',
        'started_at',
        'finished_at',
        'status',
        'indices_scanned',
        'logs_processed',
        'alerts_triggered',
        'alerts_sent',
        'execution_time_ms',
        'memory_usage_mb',
        'error_message',
    ];

    protected array $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'indices_scanned' => 'integer',
        'logs_processed' => 'integer',
        'alerts_triggered' => 'integer',
        'alerts_sent' => 'integer',
        'execution_time_ms' => 'integer',
        'memory_usage_mb' => 'float',
    ];
}
