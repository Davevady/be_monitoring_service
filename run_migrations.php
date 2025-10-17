<?php

// Script untuk menjalankan migrasi secara manual
// Jalankan dengan: php run_migrations.php

$host = 'localhost';
$username = 'hyperf';
$password = 'hyperf';
$database = 'hyperf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to database\n\n";
    
    // 1. Create message_rules table
    echo "📋 Creating message_rules table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `message_rules` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `app_name` varchar(255) NOT NULL,
        `message_key` varchar(255) NOT NULL,
        `max_duration` int(11) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT '1',
        `alert_channels` json DEFAULT NULL,
        `priority` int(11) NOT NULL DEFAULT '1',
        `cooldown_minutes` int(11) NOT NULL DEFAULT '5',
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `message_rules_app_name_index` (`app_name`),
        KEY `message_rules_message_key_index` (`message_key`),
        KEY `message_rules_is_active_index` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ message_rules table created\n";
    
    // 2. Create app_rules table
    echo "📋 Creating app_rules table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `app_rules` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `app_name` varchar(255) NOT NULL,
        `max_duration` int(11) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT '1',
        `alert_channels` json DEFAULT NULL,
        `cooldown_minutes` int(11) NOT NULL DEFAULT '5',
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `app_rules_app_name_unique` (`app_name`),
        KEY `app_rules_is_active_index` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ app_rules table created\n";
    
    // 3. Create alert_logs table
    echo "📋 Creating alert_logs table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `alert_logs` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `rule_type` varchar(255) NOT NULL,
        `rule_id` bigint(20) unsigned NOT NULL,
        `log_index` varchar(255) NOT NULL,
        `log_id` varchar(255) NOT NULL,
        `correlation_id` varchar(255) DEFAULT NULL,
        `app_name` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `duration_ms` decimal(10,2) NOT NULL,
        `log_timestamp` timestamp NULL DEFAULT NULL,
        `threshold_ms` int(11) NOT NULL,
        `exceeded_by_ms` decimal(10,2) NOT NULL,
        `alert_sent_to` json DEFAULT NULL,
        `alert_status` enum('sent','failed') NOT NULL,
        `sent_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `alert_logs_log_index_log_id_unique` (`log_index`,`log_id`),
        KEY `alert_logs_rule_type_index` (`rule_type`),
        KEY `alert_logs_rule_id_index` (`rule_id`),
        KEY `alert_logs_app_name_index` (`app_name`),
        KEY `alert_logs_created_at_index` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ alert_logs table created\n";
    
    // 4. Create scan_checkpoints table
    echo "📋 Creating scan_checkpoints table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `scan_checkpoints` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `index_name` varchar(255) NOT NULL,
        `last_scanned_timestamp` timestamp NULL DEFAULT NULL,
        `last_scanned_id` varchar(255) DEFAULT NULL,
        `last_scan_at` timestamp NULL DEFAULT NULL,
        `total_logs_scanned` bigint(20) unsigned NOT NULL DEFAULT '0',
        `total_alerts_triggered` bigint(20) unsigned NOT NULL DEFAULT '0',
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `scan_checkpoints_index_name_unique` (`index_name`),
        KEY `scan_checkpoints_last_scan_at_index` (`last_scan_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ scan_checkpoints table created\n";
    
    // 5. Create cron_execution_logs table
    echo "📋 Creating cron_execution_logs table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `cron_execution_logs` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `job_name` varchar(255) NOT NULL,
        `started_at` timestamp NULL DEFAULT NULL,
        `finished_at` timestamp NULL DEFAULT NULL,
        `status` enum('running','success','failed') NOT NULL DEFAULT 'running',
        `indices_scanned` int(11) DEFAULT NULL,
        `logs_processed` int(11) DEFAULT NULL,
        `alerts_triggered` int(11) DEFAULT NULL,
        `alerts_sent` int(11) DEFAULT NULL,
        `execution_time_ms` int(11) DEFAULT NULL,
        `memory_usage_mb` decimal(8,2) DEFAULT NULL,
        `error_message` text DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `cron_execution_logs_job_name_index` (`job_name`),
        KEY `cron_execution_logs_started_at_index` (`started_at`),
        KEY `cron_execution_logs_status_index` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ cron_execution_logs table created\n";
    
    // 6. Create alert_rate_limits table
    echo "📋 Creating alert_rate_limits table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `alert_rate_limits` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `app_name` varchar(255) NOT NULL,
        `rule_type` varchar(255) NOT NULL,
        `rule_id` bigint(20) unsigned NOT NULL,
        `cooldown_until` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `alert_rate_limits_app_name_rule_type_rule_id_unique` (`app_name`,`rule_type`,`rule_id`),
        KEY `alert_rate_limits_cooldown_until_index` (`cooldown_until`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ alert_rate_limits table created\n";
    
    echo "\n🎉 All migrations completed successfully!\n";
    echo "💡 Now run 'php create_rules.php' to create test rules.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
