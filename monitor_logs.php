<?php

// Script untuk memonitor log secara real-time
// Jalankan dengan: php monitor_logs.php

$host = 'localhost';
$username = 'hyperf';
$password = 'hyperf';
$database = 'hyperf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 Monitoring system logs...\n";
    echo "Press Ctrl+C to stop\n\n";
    
    $lastAlertCount = 0;
    $lastCronTime = null;
    
    while (true) {
        // Check alert logs
        $result = $pdo->query("SELECT COUNT(*) as count FROM alert_logs");
        $alertCount = $result->fetch()['count'];
        
        if ($alertCount > $lastAlertCount) {
            echo "🚨 NEW ALERT! Total alerts: {$alertCount}\n";
            
            // Show latest alert
            $result = $pdo->query("SELECT * FROM alert_logs ORDER BY created_at DESC LIMIT 1");
            $alert = $result->fetch();
            if ($alert) {
                echo "  ├─ App: {$alert['app_name']}\n";
                echo "  ├─ Duration: {$alert['duration_ms']}ms\n";
                echo "  ├─ Threshold: {$alert['threshold_ms']}ms\n";
                echo "  ├─ Status: {$alert['alert_status']}\n";
                echo "  └─ Time: {$alert['created_at']}\n\n";
            }
            $lastAlertCount = $alertCount;
        }
        
        // Check cron execution
        $result = $pdo->query("SELECT started_at, status, logs_processed, alerts_triggered, alerts_sent FROM cron_execution_logs ORDER BY started_at DESC LIMIT 1");
        $cron = $result->fetch();
        
        if ($cron && $cron['started_at'] !== $lastCronTime) {
            echo "⏰ Cron executed: {$cron['started_at']}\n";
            echo "  ├─ Status: {$cron['status']}\n";
            echo "  ├─ Logs processed: {$cron['logs_processed']}\n";
            echo "  ├─ Alerts triggered: {$cron['alerts_triggered']}\n";
            echo "  └─ Alerts sent: {$cron['alerts_sent']}\n\n";
            $lastCronTime = $cron['started_at'];
        }
        
        sleep(10); // Check every 10 seconds
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\n👋 Monitoring stopped.\n";
}
