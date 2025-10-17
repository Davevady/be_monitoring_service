<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class AlertRateLimit extends Model
{
    protected ?string $table = 'alert_rate_limits';

    protected array $fillable = [
        'rule_type',
        'rule_id',
        'app_name',
        'message_hash',
        'last_alert_sent_at',
        'cooldown_until',
        'alert_count',
    ];

    protected array $casts = [
        'rule_id' => 'integer',
        'last_alert_sent_at' => 'datetime',
        'cooldown_until' => 'datetime',
        'alert_count' => 'integer',
    ];
}
