<?php
/**
 * Check Thresholds Cron Job
 * 
 * This script is meant to be run as a scheduled task (cron job) to check for threshold breaches
 * and create notifications when thresholds are exceeded.
 * 
 * Recommended schedule: Once per day
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../../includes/db_connect.php';
require_once '../includes/notification_helper.php';
require_once '../includes/report_notification.php';

// Log file
$log_file = __DIR__ . '/threshold_check.log';

// Function to log messages
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Log start
log_message("Starting threshold check");

try {
    // Check thresholds
    $results = checkThresholds($conn);
    
    // Log results
    if (empty($results)) {
        log_message("No thresholds exceeded");
    } else {
        foreach ($results as $key => $value) {
            log_message("Threshold exceeded: $key");
        }
    }
    
    // Log success
    log_message("Threshold check completed successfully");
} catch (Exception $e) {
    // Log error
    log_message("Error: " . $e->getMessage());
}

// Close database connection
$conn->close();
