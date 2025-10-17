<?php

declare(strict_types=1);

return [
    'bot_token' => \Hyperf\Support\env('TELEGRAM_BOT_TOKEN', '8486179566:AAF6joSXh_sQcJESVxxF40rVmmtpdJ5sq7M'),
    'chat_id' => \Hyperf\Support\env('TELEGRAM_CHAT_ID', '5263413073'),
    'group_id' => \Hyperf\Support\env('TELEGRAM_GROUP_ID', null),
];
