<?php

// Script untuk membuat rules secara otomatis
// Jalankan dengan: php create_rules.php

// Koneksi database sederhana
$host = 'localhost';
$username = 'hyperf';
$password = 'hyperf';
$database = 'hyperf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to database\n\n";
    
    // Hapus rules lama
    $pdo->exec("DELETE FROM app_rules");
    $pdo->exec("DELETE FROM message_rules");
    echo "🗑️  Cleared old rules\n";
    
    // Buat App Rules dengan threshold RENDAH untuk testing
    $appRules = [
        ['core', 1000, '["telegram"]'],
        ['merchant', 1000, '["telegram"]'],
        ['transaction', 1000, '["telegram"]'],
        ['vendor', 1000, '["telegram"]']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO app_rules (app_name, max_duration, is_active, alert_channels, cooldown_minutes, created_at, updated_at) VALUES (?, ?, 1, ?, 5, NOW(), NOW())");
    
    foreach ($appRules as $rule) {
        $stmt->execute($rule);
        echo "✅ Created app rule: {$rule[0]} (max {$rule[1]}ms)\n";
    }
    
    // Buat Message Rules dengan threshold SANGAT RENDAH untuk testing
    $messageRules = [
        ['core', 'INQUIRY_ACCEPTANCE_SUCCESS', 50, '["telegram"]'],
        ['core', 'INQUIRY_ACCEPTANCE_FAILED', 50, '["telegram"]'],
        ['core', '%', 100, '["telegram"]'],
        ['merchant', '%', 100, '["telegram"]'],
        ['transaction', '%', 100, '["telegram"]'],
        ['vendor', '%', 100, '["telegram"]']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO message_rules (app_name, message_key, max_duration, is_active, alert_channels, priority, cooldown_minutes, created_at, updated_at) VALUES (?, ?, ?, 1, ?, 1, 5, NOW(), NOW())");
    
    foreach ($messageRules as $rule) {
        $stmt->execute($rule);
        echo "✅ Created message rule: {$rule[0]}.{$rule[1]} (max {$rule[2]}ms)\n";
    }
    
    // Verify rules
    echo "\n📋 Verification:\n";
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM app_rules WHERE is_active = 1");
    $count = $result->fetch()['count'];
    echo "  ├─ Active App Rules: {$count}\n";
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM message_rules WHERE is_active = 1");
    $count = $result->fetch()['count'];
    echo "  └─ Active Message Rules: {$count}\n";
    
    echo "\n🎉 Rules created successfully!\n";
    echo "⏰ Wait 5 minutes for the next cron execution, or restart Hyperf server.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure:\n";
    echo "  ├─ Database server is running\n";
    echo "  ├─ Database credentials are correct\n";
    echo "  └─ Database '{$database}' exists\n";
}
