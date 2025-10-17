<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class SeedRulesCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('seed:rules');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Seed app rules and message rules for testing');
    }

    public function handle()
    {
        $this->info('Seeding app rules...');
        
        // Clear existing rules
        \App\Model\AppRule::truncate();
        \App\Model\MessageRule::truncate();
        
        // Seed app rules
        \App\Model\AppRule::create([
            'app_name' => 'core',
            'max_duration' => 1000, // 1 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'cooldown_minutes' => 5,
        ]);

        \App\Model\AppRule::create([
            'app_name' => 'merchant',
            'max_duration' => 2000, // 2 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'cooldown_minutes' => 5,
        ]);

        \App\Model\AppRule::create([
            'app_name' => 'transaction',
            'max_duration' => 1500, // 1.5 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'cooldown_minutes' => 5,
        ]);

        \App\Model\AppRule::create([
            'app_name' => 'vendor',
            'max_duration' => 3000, // 3 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'cooldown_minutes' => 5,
        ]);

        $this->info('Seeding message rules...');
        
        // Seed message rules
        \App\Model\MessageRule::create([
            'app_name' => 'core',
            'message_key' => 'user.login',
            'max_duration' => 500, // 0.5 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'priority' => 1,
            'cooldown_minutes' => 5,
        ]);

        \App\Model\MessageRule::create([
            'app_name' => 'merchant',
            'message_key' => 'payment.process',
            'max_duration' => 1000, // 1 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'priority' => 1,
            'cooldown_minutes' => 5,
        ]);

        \App\Model\MessageRule::create([
            'app_name' => 'transaction',
            'message_key' => 'order.create',
            'max_duration' => 800, // 0.8 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'priority' => 1,
            'cooldown_minutes' => 5,
        ]);

        $this->info('✅ Rules seeded successfully!');
        $this->info('App Rules: 4 rules created');
        $this->info('Message Rules: 3 rules created');
    }
}
