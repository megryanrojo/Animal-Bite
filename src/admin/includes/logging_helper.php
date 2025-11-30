<?php
/**
 * Activity Logging Helper
 * Logs all system actions for audit trail and compliance
 */

/**
 * Log an activity to the activity_logs table
 * @param PDO $pdo - Database connection
 * @param string $action - Action type (CREATE, UPDATE, DELETE, VIEW, LOGIN, etc.)
 * @param string $entity_type - Entity type (report, patient, staff, vaccination, etc.)
 * @param int|null $entity_id - ID of the entity being acted upon
 * @param int|null $admin_id - Admin/Staff ID performing action
 * @param string|null $details - Additional details (JSON or plain text)
 * @param string|null $ip_address - IP address of the user
 * @return bool - Success status
 */
function logActivity($pdo, $action, $entity_type, $entity_id = null, $admin_id = null, $details = null, $ip_address = null) {
    try {
        // Get user ID from session if not provided
        if ($admin_id === null && isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
        } elseif ($admin_id === null && isset($_SESSION['staffId'])) {
            $admin_id = $_SESSION['staffId'];
        }

        // Get IP address if not provided
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        // Ensure table exists (for safety)
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS activity_logs (
                logId INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT,
                user_id INT,
                details LONGTEXT,
                ip_address VARCHAR(45),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_user (user_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($createTableSQL);

        // Insert log entry
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (action, entity_type, entity_id, user_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $action,
            $entity_type,
            $entity_id,
            $admin_id,
            $details,
            $ip_address
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get activity logs with optional filters
 * @param PDO $pdo - Database connection
 * @param array $filters - Filters: ['action' => '...', 'entity_type' => '...', 'user_id' => ..., 'date_from' => '...', 'date_to' => '...']
 * @param int $limit - Number of records to fetch
 * @param int $offset - Pagination offset
 * @return array - Array of log records
 */
function getActivityLogs($pdo, $filters = [], $limit = 100, $offset = 0) {
    try {
        $query = "SELECT * FROM activity_logs WHERE 1=1";
        $params = [];

        if (!empty($filters['action'])) {
            $query .= " AND action = ?";
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $query .= " AND entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['user_id'])) {
            $query .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }

        $query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get logs error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count of activity logs
 * @param PDO $pdo - Database connection
 * @param array $filters - Same filters as getActivityLogs
 * @return int - Total count
 */
function getActivityLogCount($pdo, $filters = []) {
    try {
        $query = "SELECT COUNT(*) as total FROM activity_logs WHERE 1=1";
        $params = [];

        if (!empty($filters['action'])) {
            $query .= " AND action = ?";
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $query .= " AND entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['user_id'])) {
            $query .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Count logs error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get user/admin name by ID
 * @param PDO $pdo - Database connection
 * @param int $user_id - User ID (admin or staff)
 * @return string - User's full name or 'Unknown'
 */
function getUserName($pdo, $user_id) {
    try {
        // Try admin first
        $stmt = $pdo->prepare("SELECT name FROM admin WHERE adminId = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) return $result['name'];

        // Try staff
        $stmt = $pdo->prepare("SELECT CONCAT(firstName, ' ', lastName) as name FROM staff WHERE staffId = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) return $result['name'];

        return 'Unknown (ID: ' . $user_id . ')';
    } catch (PDOException $e) {
        return 'Unknown';
    }
}

/**
 * Clear old logs (retention policy: keep logs for N days)
 * @param PDO $pdo - Database connection
 * @param int $days - Number of days to keep (default 365)
 * @return bool - Success status
 */
function clearOldLogs($pdo, $days = 365) {
    try {
        $cutoffDate = date('Y-m-d', strtotime("-$days days"));
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE DATE(timestamp) < ?");
        $stmt->execute([$cutoffDate]);
        return true;
    } catch (PDOException $e) {
        error_log("Clear logs error: " . $e->getMessage());
        return false;
    }
}
?>
