<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\{AlertRateLimit, AppRule, MessageRule};
use Carbon\Carbon;

class RuleViolationService
{
    /**
     * Get active rules
     */
    public function getActiveRules(): array
    {
        return [
            'app' => AppRule::where('is_active', true)->get()->keyBy('app_name')->toArray(),
            'message' => MessageRule::where('is_active', true)->get()->toArray(),
        ];
    }

    /**
     * Check violations untuk 1 log
     */
    public function checkViolations(array $log, array $rules): array
    {
        $violations = [];

        // Check app rules
        if (isset($rules['app'][$log['app_name']])) {
            $rule = $rules['app'][$log['app_name']];

            if ($log['duration_ms'] > $rule['max_duration']) {
                $violations[] = [
                    'rule_type' => 'app',
                    'rule_id' => $rule['id'],
                    'rule' => $rule,
                    'log' => $log,
                    'threshold_ms' => $rule['max_duration'],
                    'exceeded_by_ms' => $log['duration_ms'] - $rule['max_duration'],
                ];
            }
        }

        // Check message rules
        foreach ($rules['message'] as $rule) {
            // Skip jika app_name tidak match (kalau rule specific ke app tertentu)
            if ($rule['app_name'] && $rule['app_name'] !== $log['app_name']) {
                continue;
            }

            // Check message pattern match
            if ($this->matchMessagePattern($log['message'], $rule['message_key'])) {
                if ($log['duration_ms'] > $rule['max_duration']) {
                    $violations[] = [
                        'rule_type' => 'message',
                        'rule_id' => $rule['id'],
                        'rule' => $rule,
                        'log' => $log,
                        'threshold_ms' => $rule['max_duration'],
                        'exceeded_by_ms' => $log['duration_ms'] - $rule['max_duration'],
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * Check rate limit (cooldown)
     */
    public function isInCooldown(array $violation): bool
    {
        $messageHash = md5($violation['log']['message']);

        $rateLimit = AlertRateLimit::where('rule_type', $violation['rule_type'])
            ->where('rule_id', $violation['rule_id'])
            ->where('app_name', $violation['log']['app_name'])
            ->where('message_hash', $messageHash)
            ->where('cooldown_until', '>', Carbon::now())
            ->first();

        return $rateLimit !== null;
    }

    /**
     * Update rate limit setelah send alert
     */
    public function updateRateLimit(array $violation): void
    {
        $messageHash = md5($violation['log']['message']);
        $cooldownMinutes = $violation['rule']['cooldown_minutes'] ?? 5;

        $rateLimit = AlertRateLimit::where('rule_type', $violation['rule_type'])
            ->where('rule_id', $violation['rule_id'])
            ->where('app_name', $violation['log']['app_name'])
            ->where('message_hash', $messageHash)
            ->first();

        if ($rateLimit) {
            // Update existing
            $rateLimit->update([
                'last_alert_sent_at' => Carbon::now(),
                'cooldown_until' => Carbon::now()->addMinutes($cooldownMinutes),
                'alert_count' => $rateLimit->alert_count + 1,
            ]);
        } else {
            // Create new
            AlertRateLimit::create([
                'rule_type' => $violation['rule_type'],
                'rule_id' => $violation['rule_id'],
                'app_name' => $violation['log']['app_name'],
                'message_hash' => $messageHash,
                'last_alert_sent_at' => Carbon::now(),
                'cooldown_until' => Carbon::now()->addMinutes($cooldownMinutes),
                'alert_count' => 1,
            ]);
        }
    }

    /**
     * Match message pattern (support wildcard dengan %)
     */
    private function matchMessagePattern(string $message, string $pattern): bool
    {
        // Exact match
        if ($message === $pattern) {
            return true;
        }

        // Wildcard match (BILLING_TIMEOUT% match dengan BILLING_TIMEOUT_PROCESS)
        if (strpos($pattern, '%') !== false) {
            $regex = '/^' . str_replace('%', '.*', preg_quote($pattern, '/')) . '$/i';
            return preg_match($regex, $message) === 1;
        }

        return false;
    }
}
