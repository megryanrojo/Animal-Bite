<?php
// API endpoint to get workload for a given date
header('Content-Type: application/json');

require_once '../conn/conn.php';
require_once 'includes/vaccination_helper.php';

$date = isset($_GET['date']) ? $_GET['date'] : null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

try {
    $workload = getWorkloadLevel($pdo, $date);
    echo json_encode($workload);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
