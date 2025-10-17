<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class AlertLog extends Model
{
    protected ?string $table = 'alert_logs';

    protected array $fillable = [
        'rule_type',
        'rule_id',
        'log_index',
        'log_id',
        'correlation_id',
        'app_name',
        'message',
        'duration_ms',
        'log_timestamp',
        'threshold_ms',
        'exceeded_by_ms',
        'alert_sent_to',
        'alert_status',
        'sent_at',
    ];

    protected array $casts = [
        'rule_id' => 'integer',
        'duration_ms' => 'integer',
        'threshold_ms' => 'integer',
        'exceeded_by_ms' => 'integer',
        'alert_sent_to' => 'array',
        'log_timestamp' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
