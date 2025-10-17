<?php

declare(strict_types=1);

return [
    'email_recipients' => explode(',', \Hyperf\Support\env('ALERT_EMAIL_RECIPIENTS', 'admin@example.com')),
];
