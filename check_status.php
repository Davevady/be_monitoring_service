<?php

// Script untuk mengecek status sistem monitoring
// Jalankan dengan: php check_status.php

$host = 'localhost';
$username = 'hyperf';
$password = 'hyperf';
$database = 'hyperf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 Monitoring System Status\n";
    echo "==========================\n\n";
    
    // Check App Rules
    $result = $pdo->query("SELECT COUNT(*) as count FROM app_rules WHERE is_active = 1");
    $appRulesCount = $result->fetch()['count'];
    echo "📋 Active App Rules: {$appRulesCount}\n";
    
    if ($appRulesCount > 0) {
        $result = $pdo->query("SELECT app_name, max_duration FROM app_rules WHERE is_active = 1");
        while ($row = $result->fetch()) {
            echo "  ├─ {$row['app_name']}: max {$row['max_duration']}ms\n";
        }
    }
    
    // Check Message Rules
    $result = $pdo->query("SELECT COUNT(*) as count FROM message_rules WHERE is_active = 1");
    $messageRulesCount = $result->fetch()['count'];
    echo "\n📋 Active Message Rules: {$messageRulesCount}\n";
    
    if ($messageRulesCount > 0) {
        $result = $pdo->query("SELECT app_name, message_key, max_duration FROM message_rules WHERE is_active = 1");
        while ($row = $result->fetch()) {
            echo "  ├─ {$row['app_name']}.{$row['message_key']}: max {$row['max_duration']}ms\n";
        }
    }
    
    // Check Alert Logs
    $result = $pdo->query("SELECT COUNT(*) as count FROM alert_logs");
    $alertLogsCount = $result->fetch()['count'];
    echo "\n🚨 Total Alert Logs: {$alertLogsCount}\n";
    
    // Check Recent Alerts
    if ($alertLogsCount > 0) {
        $result = $pdo->query("SELECT app_name, rule_type, duration_ms, threshold_ms, alert_status, created_at FROM alert_logs ORDER BY created_at DESC LIMIT 5");
        echo "📊 Recent Alerts:\n";
        while ($row = $result->fetch()) {
            echo "  ├─ {$row['app_name']} ({$row['rule_type']}): {$row['duration_ms']}ms > {$row['threshold_ms']}ms [{$row['alert_status']}] - {$row['created_at']}\n";
        }
    }
    
    // Check Rate Limits
    $result = $pdo->query("SELECT COUNT(*) as count FROM alert_rate_limits");
    $rateLimitsCount = $result->fetch()['count'];
    echo "\n⏰ Rate Limited Alerts: {$rateLimitsCount}\n";
    
    // Check Checkpoints
    $result = $pdo->query("SELECT COUNT(*) as count FROM scan_checkpoints");
    $checkpointsCount = $result->fetch()['count'];
    echo "\n📊 Scan Checkpoints: {$checkpointsCount}\n";
    
    if ($checkpointsCount > 0) {
        $result = $pdo->query("SELECT index_name, last_scan_at, total_logs_scanned, total_alerts_triggered FROM scan_checkpoints ORDER BY last_scan_at DESC LIMIT 5");
        echo "📈 Recent Checkpoints:\n";
        while ($row = $result->fetch()) {
            echo "  ├─ {$row['index_name']}: {$row['total_logs_scanned']} logs, {$row['total_alerts_triggered']} alerts - {$row['last_scan_at']}\n";
        }
    }
    
    // Check Cron Logs
    $result = $pdo->query("SELECT COUNT(*) as count FROM cron_execution_logs");
    $cronLogsCount = $result->fetch()['count'];
    echo "\n⏰ Cron Execution Logs: {$cronLogsCount}\n";
    
    if ($cronLogsCount > 0) {
        $result = $pdo->query("SELECT started_at, status, logs_processed, alerts_triggered, alerts_sent FROM cron_execution_logs ORDER BY started_at DESC LIMIT 3");
        echo "🔄 Recent Cron Executions:\n";
        while ($row = $result->fetch()) {
            echo "  ├─ {$row['started_at']}: {$row['status']} - {$row['logs_processed']} logs, {$row['alerts_triggered']} triggered, {$row['alerts_sent']} sent\n";
        }
    }
    
    // Summary
    echo "\n📝 Summary:\n";
    if ($appRulesCount === 0 && $messageRulesCount === 0) {
        echo "  ❌ No active rules - no alerts will be triggered\n";
        echo "  💡 Run 'php create_rules.php' to create test rules\n";
    } elseif ($alertLogsCount === 0) {
        echo "  ⚠️  Rules exist but no alerts triggered - check if logs exceed thresholds\n";
        echo "  💡 Run 'php reset_checkpoints.php' to force re-scan\n";
    } else {
        echo "  ✅ System appears to be working\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure:\n";
    echo "  ├─ Database server is running\n";
    echo "  ├─ Database credentials are correct\n";
    echo "  └─ Database '{$database}' exists\n";
}
