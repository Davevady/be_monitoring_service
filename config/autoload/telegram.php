<?php

declare(strict_types=1);

return [
    // bot_token diabaikan, gunakan database (lihat TelegramService::getActiveBotToken)
    'bot_token' => null,
    'chat_id' => \Hyperf\Support\env('TELEGRAM_CHAT_ID', '5263413073'),
    'group_id' => \Hyperf\Support\env('TELEGRAM_GROUP_ID', null),
];
