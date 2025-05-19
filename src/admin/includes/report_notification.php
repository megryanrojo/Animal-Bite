<?php
/**
 * Report Notification Integration
 * 
 * This file contains functions to integrate notifications with the report submission process.
 */

// Include notification helper if not already included
if (!function_exists('addNotification')) {
    include_once 'notification_helper.php';
}

/**
 * Process a new report submission and create a notification
 * 
 * @param mysqli $conn Database connection
 * @param int $report_id Report ID
 * @param array $report_data Report data
 * @return bool True if successful, false otherwise
 */
function processReportSubmission($conn, $report_id, $report_data) {
    // Extract patient name from report data
    $patient_name = isset($report_data['patient_name']) ? $report_data['patient_name'] : 
                   (isset($report_data['first_name']) && isset($report_data['last_name']) ? 
                   $report_data['first_name'] . ' ' . $report_data['last_name'] : 'Unknown');
    
    // Create notification
    return createReportNotification($conn, $report_id, $patient_name);
}

/**
 * Check for threshold breaches and create notifications
 * 
 * @param mysqli $conn Database connection
 * @return array Results of threshold checks
 */
function checkThresholds($conn) {
    $results = [];
    
    // Check daily report threshold
    $daily_threshold = 10; // This should come from settings
    $sql = "SELECT COUNT(*) as count FROM reports WHERE DATE(date_reported) = CURDATE()";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $daily_count = intval($row['count']);
        
        if ($daily_count >= $daily_threshold) {
            createThresholdNotification($conn, 'Daily Reports', $daily_count, $daily_threshold);
            $results['daily'] = true;
        }
    }
    
    // Check weekly report threshold
    $weekly_threshold = 50; // This should come from settings
    $sql = "SELECT COUNT(*) as count FROM reports WHERE date_reported >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $weekly_count = intval($row['count']);
        
        if ($weekly_count >= $weekly_threshold) {
            createThresholdNotification($conn, 'Weekly Reports', $weekly_count, $weekly_threshold);
            $results['weekly'] = true;
        }
    }
    
    // Check barangay-specific thresholds
    $barangay_threshold = 5; // This should come from settings
    $sql = "SELECT barangay, COUNT(*) as count FROM reports 
            WHERE DATE(date_reported) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
            GROUP BY barangay 
            HAVING count >= $barangay_threshold";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $barangay = $row['barangay'];
            $count = intval($row['count']);
            
            createThresholdNotification($conn, "Barangay $barangay Reports", $count, $barangay_threshold);
            $results['barangay_' . $barangay] = true;
        }
    }
    
    return $results;
}
