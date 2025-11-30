<?php
require_once 'src/conn/conn.php';
require_once 'src/admin/includes/logging_helper.php';

echo "Testing activity logs functions...\n\n";

try {
    // Test getActivityLogCount
    $total = getActivityLogCount($pdo, []);
    echo "Total logs: $total\n";

    // Test getActivityLogs
    $logs = getActivityLogs($pdo, [], 5, 0);
    echo "Retrieved logs: " . count($logs) . "\n";

    if (!empty($logs)) {
        echo "\nFirst log:\n";
        print_r($logs[0]);

        echo "\nAll logs:\n";
        foreach ($logs as $log) {
            echo "- {$log['action']} | {$log['entity_type']} | {$log['timestamp']}\n";
        }
    } else {
        echo "No logs retrieved!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
