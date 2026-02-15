#!/usr/bin/env php
<?php
/**
 * VKS ATTENDANCE SYSTEM - AUTO-LOGOUT CRON JOB
 * 
 * Runs every 15 minutes to auto-logout users who have been logged in > 10 hours
 * 
 * CRON Schedule: */15 * * * *
 * 
 * Usage: php /path/to/vks/cron/auto-logout.php
 */

// Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Attendance.php';

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

echo "========================================\n";
echo "VKS Attendance - Auto-Logout CRON Job\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    // Run auto-logout
    $count = Attendance::autoLogoutLongSessions();
    
    if ($count > 0) {
        echo "✓ Auto-logged out {$count} user(s)\n";
        
        // Log to file
        $logMessage = date('Y-m-d H:i:s') . " - Auto-logged out {$count} user(s)\n";
        file_put_contents(__DIR__ . '/../logs/cron.log', $logMessage, FILE_APPEND);
    } else {
        echo "✓ No users to auto-logout\n";
    }
    
    echo "\n========================================\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    
    // Log error
    error_log("Auto-Logout CRON Error: " . $e->getMessage());
    
    exit(1);
}
