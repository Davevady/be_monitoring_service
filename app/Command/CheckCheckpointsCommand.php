<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class CheckCheckpointsCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('check:checkpoints');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Check scan checkpoints');
    }

    public function handle()
    {
        $this->info('Checking scan checkpoints...');
        
        $checkpoints = \App\Model\ScanCheckpoint::orderBy('last_scan_at', 'desc')->get();
        
        if ($checkpoints->count() > 0) {
            $this->info("\n📊 Scan Checkpoints (" . $checkpoints->count() . " total):");
            
            $this->table(
                ['Index Name', 'Last Scan', 'Total Logs', 'Last ID', 'Alerts'],
                $checkpoints->map(function ($checkpoint) {
                    return [
                        $checkpoint->index_name,
                        $checkpoint->last_scan_at ? $checkpoint->last_scan_at->format('Y-m-d H:i:s') : 'Never',
                        $checkpoint->total_logs_scanned,
                        $checkpoint->last_scanned_id ? substr($checkpoint->last_scanned_id, 0, 20) . '...' : 'N/A',
                        $checkpoint->total_alerts_triggered
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No scan checkpoints found.');
        }
    }
}
