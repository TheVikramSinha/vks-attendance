#!/usr/bin/env php
<?php
/**
 * VKS ATTENDANCE SYSTEM - QUOTA RESET CRON JOB
 * 
 * Runs on December 31st to reset all annual leave quotas
 * 
 * CRON Schedule: 0 0 31 12 *  (Midnight on Dec 31)
 * 
 * Usage: php /path/to/vks/cron/reset-quotas.php
 */

// Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Leave.php';

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

echo "========================================\n";
echo "VKS Attendance - Quota Reset CRON Job\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    // Check if today is December 31st
    if (date('m-d') !== '12-31') {
        echo "⚠ Today is not December 31st. Quota reset skipped.\n";
        echo "Current date: " . date('Y-m-d') . "\n";
        exit(0);
    }
    
    // Reset quotas
    $result = Leave::resetAnnualQuotas();
    
    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        
        // Log to file
        $logMessage = date('Y-m-d H:i:s') . " - Annual quotas reset successfully\n";
        file_put_contents(__DIR__ . '/../logs/cron.log', $logMessage, FILE_APPEND);
        
        // Send notification to all admins
        $admins = fetchAll("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        
        foreach ($admins as $admin) {
            $sql = "INSERT INTO notifications (user_id, type, title, message) 
                    VALUES (?, 'general', 'Annual Quota Reset', 'Leave quotas have been successfully reset for the new year.')";
            executeQuery($sql, [$admin['id']]);
        }
        
        echo "✓ Notifications sent to administrators\n";
    } else {
        echo "✗ " . $result['message'] . "\n";
    }
    
    echo "\n========================================\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    
    // Log error
    error_log("Quota Reset CRON Error: " . $e->getMessage());
    
    exit(1);
}
