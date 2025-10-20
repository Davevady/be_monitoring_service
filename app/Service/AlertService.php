<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\AlertLog;
use Hyperf\Di\Annotation\Inject;
use Carbon\Carbon;
use Hyperf\Contract\ConfigInterface;

class AlertService
{
    #[Inject]
    protected TelegramService $telegram;

    #[Inject]
    protected EmailService $email;

    #[Inject]
    protected ConfigInterface $config;

    /**
     * Cek apakah sudah pernah mengirim alert untuk kombinasi app_name + correlation_id
     * dengan status sent.
     */
    public function hasAlertSentForCorrelation(string $appName, ?string $correlationId): bool
    {
        if ($correlationId === null || $correlationId === '') {
            return false; // tanpa correlation id, tidak di-skip
        }

        return AlertLog::where('app_name', $appName)
            ->where('correlation_id', $correlationId)
            ->where('alert_status', 'sent')
            ->exists();
    }

    /**
     * Send alert via configured channels
     */
    public function sendAlert(array $violation): bool
    {
        $log = $violation['log'];
        $rule = $violation['rule'];

        // Build alert message
        $message = $this->buildAlertMessage($violation);

        // Parse alert channels (legacy) & dynamic targets from DB
        $channels = $this->parseAlertChannels($rule['alert_channels'] ?? null);

        $sentTo = [];
        $success = false;

        // 1) Dynamic targets via DB relations
        $dynamicTargets = [];
        if ($violation['rule_type'] === 'app') {
            $appRule = \App\Model\AppRule::find($violation['rule_id']);
            if ($appRule) {
                $dynamicTargets = $appRule->alertTargets()->get(['type', 'external_id'])->toArray();
            }
        } else {
            $msgRule = \App\Model\MessageRule::find($violation['rule_id']);
            if ($msgRule) {
                $dynamicTargets = $msgRule->alertTargets()->get(['type', 'external_id'])->toArray();
            }
        }

        // Kumpulkan semua target pengiriman, deduplikasi per kanal
        $telegramIds = [];
        $emailIds = [];

        // Dynamic telegram/email targets dari DB
        foreach ($dynamicTargets as $target) {
            if (str_starts_with((string) $target['type'], 'telegram')) {
                $telegramIds[] = (string) $target['external_id'];
            } elseif ($target['type'] === 'email') {
                $emailIds[] = (string) $target['external_id'];
            }
        }

        // Legacy channels fallback (config) - bisa menyebabkan duplikat jika sama, maka dedup
        if (in_array('telegram_chat', $channels)) {
            $chatId = (string) $this->config->get('telegram.chat_id');
            if ($chatId !== '') {
                $telegramIds[] = $chatId;
            }
        }
        if (in_array('telegram_group', $channels)) {
            $groupId = (string) $this->config->get('telegram.group_id');
            if ($groupId !== '') {
                $telegramIds[] = $groupId;
            }
        }
        if (in_array('telegram', $channels)) { // backward compatible
            // tipe ini tidak punya external_id, langsung gunakan sendAlert sekali saja
            if ($this->telegram->sendAlert($message)) {
                $sentTo[] = 'telegram';
                $success = true;
            }
        }

        // Dedup dan kirim telegram unik per external_id
        $telegramIds = array_values(array_unique($telegramIds, SORT_STRING));
        foreach ($telegramIds as $externalId) {
            if ($this->telegram->sendTo($externalId, $message)) {
                $sentTo[] = 'telegram:' . $externalId;
                $success = true;
            }
        }

        // Email targets
        if (!empty($emailIds)) {
            $emailIds = array_values(array_unique($emailIds, SORT_STRING));
            foreach ($emailIds as $email) {
                if ($this->email->sendAlert($log['app_name'], $message, $log)) {
                    $sentTo[] = 'email:' . $email;
                    $success = true;
                }
            }
        } elseif (in_array('email', $channels)) {
            if ($this->email->sendAlert($log['app_name'], $message, $log)) {
                $sentTo[] = 'email';
                $success = true;
            }
        }

        // Save to alert_logs
        $this->saveAlertLog($violation, $sentTo, $success);

        return $success;
    }

    /**
     * Build alert message based on rule type
     */
    private function buildAlertMessage(array $violation): string
    {
        $log = $violation['log'];
        $rule = $violation['rule'];
        $type = $violation['rule_type'];

        // Siapkan context (JSON) yang sudah di-decode oleh ElasticsearchScanService
        $contextPreview = '';
        if (!empty($log['context']) && is_array($log['context'])) {
            $ctx = $log['context'];
            // ringkas jika terlalu panjang
            $json = json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            // batasi agar aman untuk Telegram (<= 4096 char)
            $maxLen = 1200; // sisakan ruang untuk field lain
            if (strlen($json) > $maxLen) {
                $json = substr($json, 0, $maxLen) . "... (truncated)";
            }
            $contextPreview = "\n📦 Context:\n```
" . $json . "\n```";
        }

        if ($type === 'app') {
            return sprintf(
                "⚠️ *Slow App Alert*\n\n" .
                    "🔴 App: `%s`\n" .
                    "⏱ Duration: %dms (threshold: %dms)\n" .
                    "📊 Exceeded by: %dms\n" .
                    "📝 Message: `%s`\n" .
                    "🔗 Correlation ID: `%s`\n" .
                    "🕐 Time: %s%s\n\n" .
                    "_Copy correlation ID untuk trace logs_",
                $log['app_name'],
                $log['duration_ms'],
                $violation['threshold_ms'],
                $violation['exceeded_by_ms'],
                $log['message'],
                $log['correlation_id'] ?? 'N/A',
                $log['timestamp'],
                $contextPreview
            );
        } else {
            return sprintf(
                "🚨 *Process Alert*\n\n" .
                    "📝 Process: `%s`\n" .
                    "🏢 App: `%s`\n" .
                    "⏱ Duration: %dms (threshold: %dms)\n" .
                    "📊 Exceeded by: %dms\n" .
                    "🔗 Correlation ID: `%s`\n" .
                    "🕐 Time: %s%s\n\n" .
                    "_Copy correlation ID untuk trace logs_",
                $log['message'],
                $log['app_name'],
                $log['duration_ms'],
                $violation['threshold_ms'],
                $violation['exceeded_by_ms'],
                $log['correlation_id'] ?? 'N/A',
                $log['timestamp'],
                $contextPreview
            );
        }
    }

    /**
     * Parse alert channels dari JSON
     */
    private function parseAlertChannels($channels): array
    {
        if (is_string($channels)) {
            $decoded = json_decode($channels, true);
            return $decoded ?? [];
        }

        return (array) $channels;
    }

    /**
     * Save alert log to database
     */
    private function saveAlertLog(array $violation, array $sentTo, bool $success): void
    {
        $log = $violation['log'];

        // Idempotensi berbasis (rule_type, rule_id, message_hash, correlation_id)
        $messageHash = hash('sha256', (string) $log['message']);
        AlertLog::updateOrCreate(
            [
                // tetap gunakan key idempoten berbasis message+correlation untuk menjamin single write
                'rule_type' => $violation['rule_type'],
                'rule_id' => $violation['rule_id'],
                'message_hash' => $messageHash,
                'correlation_id' => (string) ($log['correlation_id'] ?? ''),
            ],
            [
                // referensi log ES tetap disimpan untuk audit
                'log_index' => $log['index'],
                'log_id' => $log['id'],
                'app_name' => $log['app_name'],
                'message' => $log['message'],
                'duration_ms' => $log['duration_ms'],
                'log_timestamp' => $log['timestamp'],
                'threshold_ms' => $violation['threshold_ms'],
                'exceeded_by_ms' => $violation['exceeded_by_ms'],
                'alert_sent_to' => json_encode($sentTo),
                'alert_status' => $success ? 'sent' : 'failed',
                'sent_at' => Carbon::now(),
            ]
        );
    }
}
