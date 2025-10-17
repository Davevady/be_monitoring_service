<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\TelegramService;

#[Command]
class TestTelegramCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('test:telegram');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Test Telegram bot functionality');
    }

    public function handle()
    {
        $this->info('Testing Telegram bot...');
        
        $telegramService = $this->container->get(TelegramService::class);
        
        $testMessage = "🚨 **TEST ALERT** 🚨\n\n" .
                      "**App:** core\n" .
                      "**Message:** user.login\n" .
                      "**Duration:** 1500ms\n" .
                      "**Threshold:** 500ms\n" .
                      "**Exceeded by:** 1000ms\n" .
                      "**Timestamp:** " . date('Y-m-d H:i:s') . "\n" .
                      "**Correlation ID:** test-12345";
        
        try {
            $result = $telegramService->sendMessage($testMessage);
            
            if ($result) {
                $this->info('✅ Test message sent successfully!');
                $this->info('Check your Telegram chat for the test message.');
            } else {
                $this->error('❌ Failed to send test message');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error sending test message: ' . $e->getMessage());
        }
    }
}
