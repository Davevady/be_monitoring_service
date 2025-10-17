<?php

declare(strict_types=1);

use Hyperf\Crontab\Crontab;

return [
    'enable' => true,
    'crontab' => [
        // Scan logs setiap 5 menit
        (new Crontab())
            ->setName('log-alert-scanner')
            ->setRule('*/2 * * * *')  // Setiap 5 menit
            ->setCallback([App\Command\LogAlertScannerCommand::class, 'handle'])
            ->setMemo('Scan logs and send alerts'),
    ],
];
