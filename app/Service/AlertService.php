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
     * Check if already alerted
     */
    public function isAlreadyAlerted(string $logIndex, string $logId, string $ruleType, int $ruleId): bool
    {
        return AlertLog::where('log_index', $logIndex)
            ->where('log_id', $logId)
            ->where('rule_type', $ruleType)
            ->where('rule_id', $ruleId)
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

        // Kirim ke semua target dinamis
        foreach ($dynamicTargets as $target) {
            if (str_starts_with((string) $target['type'], 'telegram')) {
                if ($this->telegram->sendTo((string) $target['external_id'], $message)) {
                    $sentTo[] = (string) $target['type'];
                    $success = true;
                }
            } elseif ($target['type'] === 'email') {
                if ($this->email->sendAlert($log['app_name'], $message, $log)) {
                    $sentTo[] = 'email:' . (string) $target['external_id'];
                    $success = true;
                }
            }
        }

        // 2) Legacy channels fallback (using env config)
        if (in_array('telegram_chat', $channels)) {
            $chatId = $this->config->get('telegram.chat_id');
            if ($chatId && $this->telegram->sendTo((string) $chatId, $message)) {
                $sentTo[] = 'telegram_chat';
                $success = true;
            }
        }
        if (in_array('telegram_group', $channels)) {
            $groupId = $this->config->get('telegram.group_id');
            if ($groupId && $this->telegram->sendTo((string) $groupId, $message)) {
                $sentTo[] = 'telegram_group';
                $success = true;
            }
        }
        if (in_array('telegram', $channels)) { // backward compatible
            if ($this->telegram->sendAlert($message)) {
                $sentTo[] = 'telegram';
                $success = true;
            }
        }
        if (in_array('email', $channels)) {
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

        if ($type === 'app') {
            return sprintf(
                "⚠️ *Slow App Alert*\n\n" .
                    "🔴 App: `%s`\n" .
                    "⏱ Duration: %dms (threshold: %dms)\n" .
                    "📊 Exceeded by: %dms\n" .
                    "📝 Message: `%s`\n" .
                    "🔗 Correlation ID: `%s`\n" .
                    "🕐 Time: %s\n\n" .
                    "_Copy correlation ID untuk trace logs_",
                $log['app_name'],
                $log['duration_ms'],
                $violation['threshold_ms'],
                $violation['exceeded_by_ms'],
                $log['message'],
                $log['correlation_id'] ?? 'N/A',
                $log['timestamp']
            );
        } else {
            return sprintf(
                "🚨 *Process Alert*\n\n" .
                    "📝 Process: `%s`\n" .
                    "🏢 App: `%s`\n" .
                    "⏱ Duration: %dms (threshold: %dms)\n" .
                    "📊 Exceeded by: %dms\n" .
                    "🔗 Correlation ID: `%s`\n" .
                    "🕐 Time: %s\n\n" .
                    "_Copy correlation ID untuk trace logs_",
                $log['message'],
                $log['app_name'],
                $log['duration_ms'],
                $violation['threshold_ms'],
                $violation['exceeded_by_ms'],
                $log['correlation_id'] ?? 'N/A',
                $log['timestamp']
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

        // Hindari duplikasi berdasarkan constraint unique (log_index, log_id)
        // Gunakan updateOrCreate supaya upsert idempotent saat full-scan
        AlertLog::updateOrCreate(
            [
                'log_index' => $log['index'],
                'log_id' => $log['id'],
            ],
            [
                'rule_type' => $violation['rule_type'],
                'rule_id' => $violation['rule_id'],
                'correlation_id' => $log['correlation_id'],
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
