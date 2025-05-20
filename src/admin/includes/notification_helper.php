<?php
/**
 * Notification Helper Functions
 * 
 * This file contains functions for managing notifications in the Animal Bite Center system.
 */

// Initialize the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Add a new notification to the database
 * 
 * @param PDO $pdo Database connection
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (info, warning, danger, success)
 * @param string $icon The Bootstrap icon name
 * @param string|null $link Optional link to related content
 * @return int|bool The ID of the new notification or false on failure
 */
function add_notification(PDO $pdo, $title, $message, $type = 'info', $icon = 'bell', $link = null) {
    try {
        // Insert the notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, type, icon, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$title, $message, $type, $icon, $link]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all notifications, optionally filtered
 * 
 * @param PDO $pdo Database connection
 * @param array $filters Optional filters (type, is_read)
 * @param int $limit Optional limit on number of notifications
 * @param int $offset Optional offset for pagination
 * @return array Array of notifications
 */
function get_notifications(PDO $pdo, $filters = [], $limit = null, $offset = 0) {
    try {
        // Build the query
        $sql = "SELECT * FROM notifications";
        $params = [];
        $where_clauses = [];
        
        // Add filters
        if (!empty($filters)) {
            if (isset($filters['type'])) {
                $where_clauses[] = "type = ?";
                $params[] = $filters['type'];
            }
            
            if (isset($filters['is_read'])) {
                $where_clauses[] = "is_read = ?";
                $params[] = $filters['is_read'] ? 1 : 0;
            }
            
            if (isset($filters['search'])) {
                $where_clauses[] = "(title LIKE ? OR message LIKE ?)";
                $search_term = "%" . $filters['search'] . "%";
                $params[] = $search_term;
                $params[] = $search_term;
            }
        }
        
        // Add WHERE clause if needed
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        // Add ORDER BY
        $sql .= " ORDER BY created_at DESC";
        
        // Add LIMIT and OFFSET
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = (int)$limit;
            
            if ($offset > 0) {
                $sql .= " OFFSET ?";
                $params[] = (int)$offset;
            }
        }
        
        // Prepare and execute the query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all notifications
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Optional limit on number of notifications
 * @return array Array of notifications
 */
function getAllNotifications(PDO $pdo, $limit = null) {
    return get_notifications($pdo, [], $limit);
}

/**
 * Get unread notification count
 * 
 * @param PDO $pdo Database connection
 * @return int Number of unread notifications
 */
function getUnreadNotificationCount(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting unread notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark a notification as read
 * 
 * @param PDO $pdo Database connection
 * @param int $id The notification ID
 * @return bool Success or failure
 */
function markNotificationAsRead(PDO $pdo, $id) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read
 * 
 * @param PDO $pdo Database connection
 * @return bool Success or failure
 */
function mark_all_notifications_read(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a notification
 * 
 * @param PDO $pdo Database connection
 * @param int $id The notification ID
 * @return bool Success or failure
 */
function delete_notification(PDO $pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete all read notifications
 * 
 * @param PDO $pdo Database connection
 * @return bool Success or failure
 */
function delete_all_read_notifications(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE is_read = 1");
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        error_log("Error deleting read notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear all notifications
 * 
 * @param PDO $pdo Database connection
 * @return bool Success or failure
 */
function clear_all_notifications(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications");
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        error_log("Error clearing all notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification statistics
 * 
 * @param PDO $pdo Database connection
 * @return array Statistics about notifications
 */
function get_notification_stats(PDO $pdo) {
    try {
        // Get total count
        $total_stmt = $pdo->query("SELECT COUNT(*) FROM notifications");
        $total = $total_stmt->fetchColumn();
        
        // Get unread count
        $unread_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
        $unread = $unread_stmt->fetchColumn();
        
        // Get counts by type
        $info_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'info'");
        $info = $info_stmt->fetchColumn();
        
        $warning_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'warning'");
        $warning = $warning_stmt->fetchColumn();
        
        $danger_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'danger'");
        $danger = $danger_stmt->fetchColumn();
        
        $success_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'success'");
        $success = $success_stmt->fetchColumn();
        
        return [
            'total' => $total,
            'unread' => $unread,
            'info' => $info,
            'warning' => $warning,
            'danger' => $danger,
            'success' => $success
        ];
    } catch (PDOException $e) {
        error_log("Error getting notification stats: " . $e->getMessage());
        return [
            'total' => 0,
            'unread' => 0,
            'info' => 0,
            'warning' => 0,
            'danger' => 0,
            'success' => 0
        ];
    }
}

/**
 * Format notification date for display
 * 
 * @param string $date The date to format
 * @return string Formatted date string
 */
function format_notification_date($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $timestamp);
    }
}

/**
 * Get color for notification type
 * 
 * @param string $type Notification type
 * @return string CSS color
 */
function getNotificationColor($type) {
    switch ($type) {
        case 'info':
            return '#0dcaf0';
        case 'warning':
            return '#ffc107';
        case 'danger':
            return '#dc3545';
        case 'success':
            return '#198754';
        default:
            return '#0d6efd';
    }
}

/**
 * Get background color for notification type
 * 
 * @param string $type Notification type
 * @return string CSS background color
 */
function getNotificationBgColor($type) {
    switch ($type) {
        case 'info':
            return 'rgba(13, 202, 240, 0.2)';
        case 'warning':
            return 'rgba(255, 193, 7, 0.2)';
        case 'danger':
            return 'rgba(220, 53, 69, 0.2)';
        case 'success':
            return 'rgba(25, 135, 84, 0.2)';
        default:
            return 'rgba(13, 110, 253, 0.2)';
    }
}

/**
 * Create a notification for a new report submission
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @param string $patientName Patient name
 * @param string $biteType Bite type
 * @return bool True if successful, false otherwise
 */
function createReportNotification(PDO $pdo, $reportId, $patientName, $biteType = null) {
    try {
        // If bite type is not provided, fetch it from the database
        if ($biteType === null) {
            $stmt = $pdo->prepare("
                SELECT biteType, urgency 
                FROM reports 
                WHERE reportId = ?
            ");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($report) {
                $biteType = $report['biteType'];
                $urgency = $report['urgency'];
            }
        }
        
        $title = "New Animal Bite Report";
        $message = "A new animal bite report has been submitted for patient: $patientName";
        $type = "info";
        $icon = "file-medical";
        $link = "view_report.php?id=$reportId";
        
        // Check for critical cases
        if (($biteType === 'Category III') || (isset($urgency) && $urgency === 'High')) {
            $title = "Critical Case Alert";
            $message = "A critical animal bite case has been reported for patient: $patientName";
            $type = "danger";
            $icon = "exclamation-circle";
        }
        
        return add_notification($pdo, $title, $message, $type, $icon, $link);
    } catch (PDOException $e) {
        error_log("Error creating report notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for a status update
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @param string $patientName Patient name
 * @param string $status New status
 * @param string|null $staffName Staff member who updated the status
 * @return bool True if successful, false otherwise
 */
function createStatusUpdateNotification(PDO $pdo, $reportId, $patientName, $status, $staffName = null) {
    try {
        $title = "Report Status Updated";
        $message = "The status for $patientName's report has been updated to: $status";
        if ($staffName) {
            $message .= " by $staffName";
        }
        
        $type = "info";
        $icon = "arrow-repeat";
        $link = "view_report.php?id=$reportId";
        
        switch ($status) {
            case "completed":
                $type = "success";
                $icon = "check-circle";
                break;
            case "referred":
                $type = "warning";
                $icon = "arrow-right-circle";
                break;
            case "in_progress":
                $type = "info";
                $icon = "arrow-repeat";
                break;
            case "cancelled":
                $type = "danger";
                $icon = "x-circle";
                break;
        }
        
        return add_notification($pdo, $title, $message, $type, $icon, $link);
    } catch (PDOException $e) {
        error_log("Error creating status update notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for a threshold breach
 * 
 * @param PDO $pdo Database connection
 * @param string $thresholdType Type of threshold breached
 * @param int $currentValue Current value
 * @param int $thresholdValue Threshold value
 * @return bool True if successful, false otherwise
 */
function createThresholdNotification(PDO $pdo, $thresholdType, $currentValue, $thresholdValue) {
    try {
        $title = "Threshold Alert: $thresholdType";
        $message = "The $thresholdType threshold has been exceeded. Current value: $currentValue, Threshold: $thresholdValue";
        $type = "warning";
        $icon = "exclamation-triangle";
        $link = "decisionSupport.php";
        
        // If the current value is significantly higher than the threshold, mark as danger
        if ($currentValue >= ($thresholdValue * 1.5)) {
            $type = "danger";
            $icon = "exclamation-circle";
        }
        
        return add_notification($pdo, $title, $message, $type, $icon, $link);
    } catch (PDOException $e) {
        error_log("Error creating threshold notification: " . $e->getMessage());
        return false;
    }
}
?>
