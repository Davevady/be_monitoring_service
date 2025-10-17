<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\{AlertService, ElasticsearchScanService, RuleViolationService};
use App\Model\{CronExecutionLog, ScanCheckpoint};
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Carbon\Carbon;

#[Command]
class LogAlertScannerCommand extends HyperfCommand
{
    #[Inject]
    protected ElasticsearchScanService $esScan;

    #[Inject]
    protected RuleViolationService $ruleViolation;

    #[Inject]
    protected AlertService $alert;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct('log:alert-scan');
        $this->setDescription('Scan Elasticsearch logs and send alerts for violations');
    }

    public function handle()
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Create execution log
        $executionLog = CronExecutionLog::create([
            'job_name' => 'log_alert_scanner',
            'started_at' => Carbon::now(),
            'status' => 'running',
        ]);

        $this->info('🚀 Starting log alert scanner...');

        try {
            // Get active indices
            $indices = $this->esScan->getActiveIndices();
            $this->info('📂 Found ' . count($indices) . ' indices to scan');

            // Note: rules akan direfresh tiap batch agar perubahan langsung terdeteksi
            $rules = null;
            $appRulesCount = 0;
            $messageRulesCount = 0;

            $totalLogsProcessed = 0;
            $totalAlertsTriggered = 0;
            $totalAlertsSent = 0;

            // Process each index
            foreach ($indices as $indexName) {
                $this->line("📊 Scanning index: {$indexName}");

                try {
                    // Get checkpoint
                    $checkpoint = ScanCheckpoint::where('index_name', $indexName)->first();

                    // Full scan with batching using search_after
                    $alertsInIndex = 0;
                    $batch = 0;
                    $searchAfter = null;
                    // Pastikan fromTimestamp bertipe string/null, bukan Carbon
                    $fromTimestamp = null;
                    if ($checkpoint && $checkpoint->last_scanned_timestamp) {
                        $ts = $checkpoint->last_scanned_timestamp;
                        $fromTimestamp = $ts instanceof \DateTimeInterface ? $ts->format('Y-m-d H:i:s') : (string) $ts;
                    }

                    while (true) {
                        // Refresh rules setiap batch
                        $rules = $this->ruleViolation->getActiveRules();
                        $appRulesCount = is_array($rules['app']) ? count($rules['app']) : 0;
                        $messageRulesCount = is_array($rules['message']) ? count($rules['message']) : 0;
                        if ($batch === 1) {
                            $this->line("  ├─ Rules: {$appRulesCount} app, {$messageRulesCount} message (refreshed)");
                        }
                        $batch++;
                        $logs = $this->esScan->scanLogs($indexName, $checkpoint, 500, $searchAfter, $fromTimestamp);
                        $logsCount = count($logs);
                        if ($batch === 1) {
                            $this->info("  ├─ Batch #{$batch}: {$logsCount} logs");
                        } else {
                            $this->line("  │  Batch #{$batch}: {$logsCount} logs");
                        }

                        if (empty($logs)) {
                            break;
                        }

                        // Process each log
                        foreach ($logs as $log) {
                            $totalLogsProcessed++;

                            // Check violations
                            $violations = $this->ruleViolation->checkViolations($log, $rules);

                            foreach ($violations as $violation) {
                                $totalAlertsTriggered++;

                                // Check if already alerted
                                if ($this->alert->isAlreadyAlerted(
                                    $log['index'],
                                    $log['id'],
                                    $violation['rule_type'],
                                    $violation['rule_id']
                                )) {
                                    continue;
                                }

                                // Check rate limit
                                if ($this->ruleViolation->isInCooldown($violation)) {
                                    $this->line("  │  ⏸  Skipped (cooldown): {$log['message']}");
                                    continue;
                                }

                                // Send alert
                                if ($this->alert->sendAlert($violation)) {
                                    $totalAlertsSent++;
                                    $alertsInIndex++;
                                    $this->line("  │  ✅ Alert sent: {$violation['rule_type']} - {$log['message']}");

                                    // Update rate limit
                                    $this->ruleViolation->updateRateLimit($violation);
                                }
                            }
                        }

                        // Update checkpoint berdasarkan log terakhir batch ini
                        $lastLog = end($logs);
                        if (!isset($lastLog['timestamp']) || empty($lastLog['timestamp'])) {
                            $this->warn("  │  ⚠️  Warning: Last log has no timestamp, skipping checkpoint update for this batch");
                        } else {
                            $this->esScan->updateCheckpoint(
                                $indexName,
                                $lastLog['timestamp'],
                                $lastLog['id'],
                                count($logs),
                                $alertsInIndex
                            );
                            // Siapkan search_after untuk batch berikutnya
                            $searchAfter = [$lastLog['timestamp'], $lastLog['id']];
                            // Gunakan timestamp yang sama dengan search_after; ES akan lanjut ke id berikutnya berkat sort
                            $fromTimestamp = $lastLog['timestamp'];
                        }
                    }

                    $this->info("  └─ Alerts sent: {$alertsInIndex}");
                } catch (\Exception $e) {
                    $this->error("  │  ❌ Error scanning index {$indexName}: " . $e->getMessage());
                    $this->line("  │  Stack: " . $e->getTraceAsString());
                    continue; // Skip ke index berikutnya
                }
            }

            // Calculate metrics
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);
            $memoryUsageMb = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

            // Update execution log
            $executionLog->update([
                'finished_at' => Carbon::now(),
                'status' => 'success',
                'indices_scanned' => count($indices),
                'logs_processed' => $totalLogsProcessed,
                'alerts_triggered' => $totalAlertsTriggered,
                'alerts_sent' => $totalAlertsSent,
                'execution_time_ms' => $executionTimeMs,
                'memory_usage_mb' => round($memoryUsageMb, 2),
            ]);

            $this->info("\n✅ Scan completed successfully!");
            // Tampilkan tabel hanya jika output console tersedia (CLI interaktif). Crontab tidak menyediakan OutputInterface.
            if (property_exists($this, 'output') && $this->output instanceof \Symfony\Component\Console\Output\OutputInterface) {
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Indices Scanned', count($indices)],
                        ['Logs Processed', $totalLogsProcessed],
                        ['Alerts Triggered', $totalAlertsTriggered],
                        ['Alerts Sent', $totalAlertsSent],
                        ['Execution Time', $executionTimeMs . 'ms'],
                        ['Memory Usage', round($memoryUsageMb, 2) . 'MB'],
                    ]
                );
            } else {
                $this->line('Indices Scanned: ' . count($indices));
                $this->line('Logs Processed: ' . $totalLogsProcessed);
                $this->line('Alerts Triggered: ' . $totalAlertsTriggered);
                $this->line('Alerts Sent: ' . $totalAlertsSent);
                $this->line('Execution Time: ' . $executionTimeMs . 'ms');
                $this->line('Memory Usage: ' . round($memoryUsageMb, 2) . 'MB');
            }
        } catch (\Exception $e) {
            $executionLog->update([
                'finished_at' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->error('❌ Error: ' . $e->getMessage());
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());

            return 1;
        }

        return 0;
    }
}
