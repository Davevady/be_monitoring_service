<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class AppRule extends Model
{
    protected ?string $table = 'app_rules';

    protected array $fillable = [
        'app_name',
        'max_duration',
        'is_active',
        'alert_channels',
        'cooldown_minutes',
    ];

    protected array $casts = [
        'max_duration' => 'integer',
        'is_active' => 'boolean',
        'alert_channels' => 'array',
        'cooldown_minutes' => 'integer',
    ];

    public function alertTargets()
    {
        return $this->belongsToMany(AlertTarget::class, 'app_rule_alert_target', 'app_rule_id', 'alert_target_id')->where('is_active', true);
    }
}
