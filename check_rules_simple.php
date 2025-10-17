<?php

// Simple script to check rules without Hyperf dependencies
$host = 'localhost';
$username = 'hyperf';
$password = 'hyperf';
$database = 'hyperf';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "🔍 Checking Rules...\n\n";

// Check App Rules
$result = $mysqli->query("SELECT * FROM app_rules WHERE is_active = 1");
echo "📋 Active App Rules:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['app_name']}: max {$row['max_duration']}ms\n";
    }
} else {
    echo "  ❌ No active app rules found!\n";
}

// Check Message Rules
$result = $mysqli->query("SELECT * FROM message_rules WHERE is_active = 1");
echo "\n📋 Active Message Rules:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['app_name']}.{$row['message_key']}: max {$row['max_duration']}ms\n";
    }
} else {
    echo "  ❌ No active message rules found!\n";
}

// Check recent checkpoints
$result = $mysqli->query("SELECT * FROM scan_checkpoints ORDER BY last_scan_at DESC LIMIT 3");
echo "\n📊 Recent Checkpoints:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['index_name']}: last scan {$row['last_scan_at']}, total logs {$row['total_logs_scanned']}\n";
    }
} else {
    echo "  ❌ No checkpoints found!\n";
}

$mysqli->close();
