<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Connectors\ConnectionFactory;
use Hyperf\Database\Connectors\MySqlConnector;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;

// Sederhana - langsung koneksi ke database
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3306;
$database = getenv('DB_DATABASE') ?: 'hyperf';
$username = getenv('DB_USERNAME') ?: 'hyperf';
$password = getenv('DB_PASSWORD') ?: 'hyperf';

$mysqli = new mysqli($host, $username, $password, $database, $port);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "✅ Connected to database\n\n";

// Check App Rules
$result = $mysqli->query("SELECT COUNT(*) as count FROM app_rules WHERE is_active = 1");
$row = $result->fetch_assoc();
$appRulesCount = $row['count'];

echo "📋 Active App Rules: {$appRulesCount}\n";

// Check Message Rules
$result = $mysqli->query("SELECT COUNT(*) as count FROM message_rules WHERE is_active = 1");
$row = $result->fetch_assoc();
$messageRulesCount = $row['count'];

echo "📋 Active Message Rules: {$messageRulesCount}\n\n";

if ($appRulesCount == 0 && $messageRulesCount == 0) {
    echo "❌ NO ACTIVE RULES FOUND!\n";
    echo "This is why no alerts are being triggered.\n\n";
    echo "Creating test rules...\n\n";
    
    // Clear existing rules
    $mysqli->query("TRUNCATE TABLE app_rules");
    $mysqli->query("TRUNCATE TABLE message_rules");
    
    // Insert App Rules with VERY LOW thresholds for testing
    $mysqli->query("INSERT INTO app_rules (app_name, max_duration, is_active, alert_channels, cooldown_minutes, created_at, updated_at) VALUES 
        ('core', 100, 1, '[\"telegram\"]', 5, NOW(), NOW()),
        ('merchant', 100, 1, '[\"telegram\"]', 5, NOW(), NOW()),
        ('transaction', 100, 1, '[\"telegram\"]', 5, NOW(), NOW()),
        ('vendor', 100, 1, '[\"telegram\"]', 5, NOW(), NOW())
    ");
    
    echo "✅ Created 4 app rules with 100ms threshold (very low for testing)\n";
    
    // Insert Message Rules with VERY LOW thresholds for testing
    $mysqli->query("INSERT INTO message_rules (app_name, message_key, max_duration, is_active, alert_channels, priority, cooldown_minutes, created_at, updated_at) VALUES 
        ('core', 'user.login', 50, 1, '[\"telegram\"]', 1, 5, NOW(), NOW()),
        ('merchant', 'payment.process', 50, 1, '[\"telegram\"]', 1, 5, NOW(), NOW()),
        ('transaction', 'order.create', 50, 1, '[\"telegram\"]', 1, 5, NOW(), NOW())
    ");
    
    echo "✅ Created 3 message rules with 50ms threshold (very low for testing)\n\n";
    
    echo "🔄 Now restart the scanner to trigger alerts:\n";
    echo "   php bin/hyperf.php log-alert-scanner\n\n";
} else {
    echo "ℹ️ Rules exist. Showing current rules:\n\n";
    
    // Show App Rules
    echo "App Rules:\n";
    $result = $mysqli->query("SELECT * FROM app_rules WHERE is_active = 1");
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['app_name']}: max {$row['max_duration']}ms\n";
    }
    
    echo "\nMessage Rules:\n";
    $result = $mysqli->query("SELECT * FROM message_rules WHERE is_active = 1");
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['app_name']}.{$row['message_key']}: max {$row['max_duration']}ms\n";
    }
    
    echo "\n💡 Thresholds mungkin terlalu tinggi. Ubah threshold menjadi lebih rendah (misal 100ms) untuk testing.\n";
}

$mysqli->close();

