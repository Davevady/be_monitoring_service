<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class AlertTarget extends Model
{
    protected ?string $table = 'alert_targets';

    protected array $fillable = [
        'type',
        'external_id',
        'label',
        'is_active',
    ];

    protected array $casts = [
        'is_active' => 'boolean',
    ];
}


