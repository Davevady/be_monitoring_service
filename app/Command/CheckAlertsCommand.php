<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class CheckAlertsCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('check:alerts');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Check alert logs in database');
    }

    public function handle()
    {
        $this->info('Checking alert logs...');
        
        $alertLogs = \App\Model\AlertLog::orderBy('created_at', 'desc')->limit(10)->get();
        
        if ($alertLogs->count() > 0) {
            $this->info("\n🚨 Recent Alert Logs (" . $alertLogs->count() . " shown):");
            
            $this->table(
                ['App', 'Rule Type', 'Duration', 'Threshold', 'Status', 'Created At'],
                $alertLogs->map(function ($alert) {
                    return [
                        $alert->app_name,
                        $alert->rule_type,
                        $alert->duration_ms . 'ms',
                        $alert->threshold_ms . 'ms',
                        $alert->alert_status,
                        $alert->created_at->format('Y-m-d H:i:s')
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No alert logs found in database.');
        }
        
        // Check rate limits
        $rateLimits = \App\Model\AlertRateLimit::orderBy('created_at', 'desc')->limit(5)->get();
        
        if ($rateLimits->count() > 0) {
            $this->info("\n⏰ Recent Rate Limits (" . $rateLimits->count() . " shown):");
            
            $this->table(
                ['App', 'Rule Type', 'Rule ID', 'Created At'],
                $rateLimits->map(function ($limit) {
                    return [
                        $limit->app_name,
                        $limit->rule_type,
                        $limit->rule_id,
                        $limit->created_at->format('Y-m-d H:i:s')
                    ];
                })->toArray()
            );
        } else {
            $this->info("\n✅ No rate limits found (good - means no alerts were rate limited).");
        }
    }
}
