<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Guzzle\ClientFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;

class TelegramService
{
    #[Inject]
    protected ClientFactory $clientFactory;

    #[Inject]
    protected ConfigInterface $config;

    public function sendAlert(string $message): bool
    {
        $botToken = $this->config->get('telegram.bot_token');
        $chatId = $this->config->get('telegram.chat_id');

        if (!$botToken || !$chatId) {
            $logger = ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('alert');
            $logger->warning('Telegram not configured');
            return false;
        }

        try {
            $client = $this->clientFactory->create();
            $response = $client->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ],
                'timeout' => 10,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return $result['ok'] ?? false;
        } catch (\Exception $e) {
            $logger = ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('alert');
            $logger->error('Telegram send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Kirim ke target tertentu (chat/group) dengan id eksternal dinamis.
     */
    public function sendTo(string $externalId, string $message): bool
    {
        $botToken = $this->config->get('telegram.bot_token');
        if (!$botToken || !$externalId) {
            $logger = ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('alert');
            $logger->warning('Telegram not configured or externalId missing');
            return false;
        }

        try {
            $client = $this->clientFactory->create();
            $response = $client->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'json' => [
                    'chat_id' => $externalId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ],
                'timeout' => 10,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return $result['ok'] ?? false;
        } catch (\Exception $e) {
            $logger = ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('alert');
            $logger->error('Telegram send error: ' . $e->getMessage());
            return false;
        }
    }
}
