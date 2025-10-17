<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class CheckRulesCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('check:rules');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Check active rules in database');
    }

    public function handle()
    {
        $this->info('Checking active rules...');
        
        // Check App Rules
        $appRules = \App\Model\AppRule::where('is_active', true)->get();
        $this->info("\n📋 App Rules (" . $appRules->count() . " active):");
        
        if ($appRules->count() > 0) {
            $this->table(
                ['App Name', 'Max Duration (ms)', 'Alert Channels', 'Cooldown (min)'],
                $appRules->map(function ($rule) {
                    return [
                        $rule->app_name,
                        $rule->max_duration,
                        implode(', ', $rule->alert_channels),
                        $rule->cooldown_minutes
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No active app rules found!');
        }
        
        // Check Message Rules
        $messageRules = \App\Model\MessageRule::where('is_active', true)->get();
        $this->info("\n📋 Message Rules (" . $messageRules->count() . " active):");
        
        if ($messageRules->count() > 0) {
            $this->table(
                ['App Name', 'Message Key', 'Max Duration (ms)', 'Priority', 'Alert Channels'],
                $messageRules->map(function ($rule) {
                    return [
                        $rule->app_name,
                        $rule->message_key,
                        $rule->max_duration,
                        $rule->priority,
                        implode(', ', $rule->alert_channels)
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No active message rules found!');
        }
        
        if ($appRules->count() === 0 && $messageRules->count() === 0) {
            $this->error("\n❌ No active rules found! This is why no alerts are being triggered.");
            $this->info("Run 'php bin/hyperf.php seed:rules' to create test rules.");
        }
    }
}
