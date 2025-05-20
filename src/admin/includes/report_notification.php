<?php
/**
 * Report Notification Integration
 * 
 * This file contains functions to integrate notifications with the report submission process.
 */

// Include notification helper if not already included
if (!function_exists('add_notification')) {
    include_once 'notification_helper.php';
}

/**
 * Process a new report submission and create a notification
 * 
 * @param PDO $pdo Database connection
 * @param int $report_id Report ID
 * @param array $report_data Report data
 * @return bool True if successful, false otherwise
 */
function processReportSubmission(PDO $pdo, $report_id, $report_data) {
    try {
        // Extract patient name from report data
        $patient_name = isset($report_data['patient_name']) ? $report_data['patient_name'] : 
                       (isset($report_data['first_name']) && isset($report_data['last_name']) ? 
                       $report_data['first_name'] . ' ' . $report_data['last_name'] : 'Unknown');
        
        // Get bite type from report data
        $bite_type = isset($report_data['bite_type']) ? $report_data['bite_type'] : 'Unknown';
        
        // Create notification using the helper function
        return createReportNotification($pdo, $report_id, $patient_name, $bite_type);
    } catch (PDOException $e) {
        error_log("Error processing report submission: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for threshold breaches and create notifications
 * 
 * @param PDO $pdo Database connection
 * @return array Results of threshold checks
 */
function checkThresholds(PDO $pdo) {
    $results = [];
    
    try {
        // Check daily report threshold
        $daily_threshold = 10; // This should come from settings
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE DATE(reportDate) = CURDATE()");
        $daily_count = (int)$stmt->fetchColumn();
        
        if ($daily_count >= $daily_threshold) {
            createThresholdNotification($pdo, 'Daily Reports', $daily_count, $daily_threshold);
            $results['daily'] = true;
        }
        
        // Check weekly report threshold
        $weekly_threshold = 50; // This should come from settings
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE reportDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $weekly_count = (int)$stmt->fetchColumn();
        
        if ($weekly_count >= $weekly_threshold) {
            createThresholdNotification($pdo, 'Weekly Reports', $weekly_count, $weekly_threshold);
            $results['weekly'] = true;
        }
        
        // Check barangay-specific thresholds
        $barangay_threshold = 5; // This should come from settings
        $stmt = $pdo->query("
            SELECT p.barangay, COUNT(*) as count 
            FROM reports r
            JOIN patients p ON r.patientId = p.patientId
            WHERE r.reportDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
            GROUP BY p.barangay 
            HAVING count >= ?
        ");
        $stmt->execute([$barangay_threshold]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $barangay = $row['barangay'];
            $count = (int)$row['count'];
            
            createThresholdNotification($pdo, "Barangay $barangay Reports", $count, $barangay_threshold);
            $results['barangay_' . $barangay] = true;
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error checking thresholds: " . $e->getMessage());
        return $results;
    }
}

/**
 * Create a notification for a new report
 * 
 * @param PDO $pdo Database connection
 * @param int $report_id Report ID
 * @param string $patient_name Patient name
 * @return bool True if successful, false otherwise
 */
function createReportNotification(PDO $pdo, $report_id, $patient_name) {
    try {
        // Get report details
        $stmt = $pdo->prepare("
            SELECT r.biteType, r.urgency, r.reportDate
            FROM reports r
            WHERE r.reportId = ?
        ");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            return false;
        }
        
        // Determine notification type and message based on urgency and bite type
        $type = 'info';
        $icon = 'file-medical';
        $title = "New Animal Bite Report";
        $message = "A new animal bite report has been submitted for patient: $patient_name";
        
        if ($report['urgency'] === 'High' || $report['biteType'] === 'Category III') {
            $type = 'danger';
            $icon = 'exclamation-circle';
            $title = "Critical Case Alert";
            $message = "A critical animal bite case has been reported for patient: $patient_name";
        }
        
        // Add the notification
        return add_notification(
            $pdo,
            $title,
            $message,
            $type,
            $icon,
            "view_report.php?id=$report_id"
        );
    } catch (PDOException $e) {
        error_log("Error creating report notification: " . $e->getMessage());
        return false;
    }
}
