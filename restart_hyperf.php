<?php

// Script untuk restart Hyperf server
// Jalankan dengan: php restart_hyperf.php

echo "🔄 Restarting Hyperf server...\n";

// Stop server
echo "⏹️  Stopping server...\n";
$output = shell_exec('php bin/hyperf.php stop 2>&1');
echo $output;

// Wait a moment
sleep(2);

// Start server
echo "▶️  Starting server...\n";
$output = shell_exec('php bin/hyperf.php start 2>&1');
echo $output;

echo "\n✅ Hyperf server restarted!\n";
echo "⏰ Cron job will run every 5 minutes.\n";
echo "📱 Check your Telegram chat for alerts.\n";
