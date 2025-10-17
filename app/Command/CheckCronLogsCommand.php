<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class CheckCronLogsCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('check:cron-logs');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Check cron execution logs');
    }

    public function handle()
    {
        $this->info('Checking cron execution logs...');
        
        $cronLogs = \App\Model\CronExecutionLog::orderBy('started_at', 'desc')->limit(10)->get();
        
        if ($cronLogs->count() > 0) {
            $this->info("\n⏰ Recent Cron Executions (" . $cronLogs->count() . " shown):");
            
            $this->table(
                ['Job Name', 'Status', 'Started At', 'Finished At', 'Logs Processed', 'Alerts Triggered', 'Alerts Sent'],
                $cronLogs->map(function ($log) {
                    return [
                        $log->job_name,
                        $log->status,
                        $log->started_at ? $log->started_at->format('Y-m-d H:i:s') : 'N/A',
                        $log->finished_at ? $log->finished_at->format('Y-m-d H:i:s') : 'N/A',
                        $log->logs_processed ?? 0,
                        $log->alerts_triggered ?? 0,
                        $log->alerts_sent ?? 0
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No cron execution logs found.');
        }
    }
}
