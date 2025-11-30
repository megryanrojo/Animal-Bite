<?php
require_once 'src/conn/conn.php';
require_once 'src/admin/includes/logging_helper.php';

try {
    // Check if table exists
    $stmt = $pdo->query('SHOW TABLES LIKE "activity_logs"');
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "✅ Activity logs table exists\n";

        // Check record count
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM activity_logs');
        $count = $stmt->fetch()['count'];
        echo "📊 Records in table: $count\n";

        if ($count > 0) {
            // Show recent logs
            $stmt = $pdo->query('SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT 5');
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "\n📋 Recent logs:\n";
            foreach ($logs as $log) {
                echo "- " . $log['action'] . " | " . $log['entity_type'] . " | " . $log['timestamp'] . "\n";
            }
        } else {
            echo "⚠️  No records found in activity_logs table\n";
        }
    } else {
        echo "❌ Activity logs table does NOT exist\n";

        // Try to create it
        echo "🔧 Attempting to create table...\n";
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
        echo "✅ Table created successfully\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
