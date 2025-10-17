<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MessageRule extends Model
{
    protected ?string $table = 'message_rules';

    protected array $fillable = [
        'app_name',
        'message_key',
        'max_duration',
        'is_active',
        'alert_channels',
        'priority',
        'cooldown_minutes',
    ];

    protected array $casts = [
        'max_duration' => 'integer',
        'is_active' => 'boolean',
        'alert_channels' => 'array',
        'priority' => 'integer',
        'cooldown_minutes' => 'integer',
    ];

    public function alertTargets()
    {
        return $this->belongsToMany(AlertTarget::class, 'message_rule_alert_target', 'message_rule_id', 'alert_target_id')->where('is_active', true);
    }
}
