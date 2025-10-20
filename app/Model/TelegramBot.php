<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class TelegramBot extends Model
{
    protected ?string $table = 'telegram_bots';

    protected array $fillable = [
        'name',
        'bot_token',
        'is_active',
    ];

    protected array $casts = [
        'is_active' => 'boolean',
    ];
}


