<?php

// Script untuk reset checkpoints agar memaksa re-scan
// Jalankan dengan: php reset_checkpoints.php

$host = 'localhost';
$username = 'hyperf';
$password = 'hyperf';
$database = 'hyperf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to database\n\n";
    
    // Reset checkpoints
    $stmt = $pdo->prepare("UPDATE scan_checkpoints SET 
        last_scanned_timestamp = '2025-10-01T00:00:00Z',
        last_scanned_id = NULL,
        total_logs_scanned = 0,
        total_alerts_triggered = 0,
        updated_at = NOW()
    ");
    
    $stmt->execute();
    $affectedRows = $stmt->rowCount();
    
    echo "🔄 Reset {$affectedRows} checkpoints\n";
    echo "📅 Reset timestamp to: 2025-10-01T00:00:00Z\n";
    echo "🆔 Reset last_scanned_id to NULL\n";
    echo "📊 Reset counters to 0\n\n";
    
    // Show current checkpoints
    $result = $pdo->query("SELECT index_name, last_scanned_timestamp, total_logs_scanned FROM scan_checkpoints ORDER BY index_name");
    
    echo "📋 Current checkpoints:\n";
    while ($row = $result->fetch()) {
        echo "  ├─ {$row['index_name']}: {$row['last_scanned_timestamp']} ({$row['total_logs_scanned']} logs)\n";
    }
    
    echo "\n🎉 Checkpoints reset successfully!\n";
    echo "⏰ Next cron execution will scan from the beginning.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
