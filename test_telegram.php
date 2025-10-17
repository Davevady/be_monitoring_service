<?php

// Script untuk test Telegram bot
// Jalankan dengan: php test_telegram.php

$botToken = '8486179566:AAF6joSXh_sQcJESVxxF40rVmmtpdJ5sq7M';
$chatId = '5263413073';

$testMessage = "🚨 **TEST ALERT** 🚨\n\n" .
              "**App:** core\n" .
              "**Message:** INQUIRY_ACCEPTANCE_FAILED\n" .
              "**Duration:** 1115ms\n" .
              "**Threshold:** 1000ms\n" .
              "**Exceeded by:** 115ms\n" .
              "**Timestamp:** " . date('Y-m-d H:i:s') . "\n" .
              "**Correlation ID:** test-" . uniqid();

$url = "https://api.telegram.org/bot{$botToken}/sendMessage";

$data = [
    'chat_id' => $chatId,
    'text' => $testMessage,
    'parse_mode' => 'Markdown'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

echo "📱 Testing Telegram bot...\n";
echo "📤 Sending message to chat ID: {$chatId}\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if ($result['ok']) {
        echo "✅ Message sent successfully!\n";
        echo "📨 Message ID: " . $result['result']['message_id'] . "\n";
        echo "👤 Check your Telegram chat for the test message.\n";
    } else {
        echo "❌ Telegram API error: " . $result['description'] . "\n";
    }
} else {
    echo "❌ HTTP error: {$httpCode}\n";
    echo "Response: {$response}\n";
}
