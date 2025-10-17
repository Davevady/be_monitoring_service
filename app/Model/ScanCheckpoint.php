<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class ScanCheckpoint extends Model
{
    protected ?string $table = 'scan_checkpoints';

    protected array $fillable = [
        'index_name',
        'last_scanned_timestamp',
        'last_scanned_id',
        'last_scan_at',
        'total_logs_scanned',
        'total_alerts_sent',
    ];

    protected array $casts = [
        'last_scanned_timestamp' => 'datetime',
        'last_scan_at' => 'datetime',
        'total_logs_scanned' => 'integer',
        'total_alerts_sent' => 'integer',
    ];
}
