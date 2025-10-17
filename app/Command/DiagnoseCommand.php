<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class DiagnoseCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('diagnose');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Diagnose the monitoring system');
    }

    public function handle()
    {
        $this->info('🔍 Diagnosing monitoring system...');
        
        // Check rules
        $appRules = \App\Model\AppRule::where('is_active', true)->count();
        $messageRules = \App\Model\MessageRule::where('is_active', true)->count();
        
        $this->info("\n📋 Rules Status:");
        $this->info("  ├─ Active App Rules: {$appRules}");
        $this->info("  └─ Active Message Rules: {$messageRules}");
        
        if ($appRules === 0 && $messageRules === 0) {
            $this->error("  ❌ No active rules found! This is why no alerts are being triggered.");
            $this->info("  💡 Run 'php bin/hyperf.php seed:rules' to create test rules.");
        }
        
        // Check checkpoints
        $checkpoints = \App\Model\ScanCheckpoint::count();
        $this->info("\n📊 Scan Checkpoints: {$checkpoints} indices");
        
        // Check alert logs
        $alertLogs = \App\Model\AlertLog::count();
        $this->info("\n🚨 Alert Logs: {$alertLogs} total alerts");
        
        // Check rate limits
        $rateLimits = \App\Model\AlertRateLimit::count();
        $this->info("\n⏰ Rate Limits: {$rateLimits} rate limited alerts");
        
        // Check cron logs
        $cronLogs = \App\Model\CronExecutionLog::orderBy('started_at', 'desc')->limit(1)->first();
        if ($cronLogs) {
            $this->info("\n⏰ Last Cron Execution:");
            $this->info("  ├─ Job: {$cronLogs->job_name}");
            $this->info("  ├─ Status: {$cronLogs->status}");
            $this->info("  ├─ Started: " . ($cronLogs->started_at ? $cronLogs->started_at->format('Y-m-d H:i:s') : 'N/A'));
            $this->info("  ├─ Logs Processed: " . ($cronLogs->logs_processed ?? 0));
            $this->info("  ├─ Alerts Triggered: " . ($cronLogs->alerts_triggered ?? 0));
            $this->info("  └─ Alerts Sent: " . ($cronLogs->alerts_sent ?? 0));
        }
        
        // Check Telegram config
        $telegramConfig = $this->container->get(\Hyperf\Contract\ConfigInterface::class)->get('telegram');
        $this->info("\n📱 Telegram Config:");
        $this->info("  ├─ Bot Token: " . (isset($telegramConfig['bot_token']) ? 'Set' : 'Not set'));
        $this->info("  └─ Chat ID: " . (isset($telegramConfig['chat_id']) ? 'Set' : 'Not set'));
        
        // Summary
        $this->info("\n📝 Summary:");
        if ($appRules === 0 && $messageRules === 0) {
            $this->error("  ❌ No active rules - no alerts will be triggered");
        } elseif ($alertLogs === 0) {
            $this->warn("  ⚠️  Rules exist but no alerts triggered - check if logs exceed thresholds");
        } else {
            $this->info("  ✅ System appears to be working");
        }
        
        $this->info("\n💡 Useful commands:");
        $this->info("  ├─ php bin/hyperf.php seed:rules - Create test rules");
        $this->info("  ├─ php bin/hyperf.php test:telegram - Test Telegram bot");
        $this->info("  ├─ php bin/hyperf.php check:logs - Check sample logs");
        $this->info("  └─ php bin/hyperf.php check:rules - View active rules");
    }
}