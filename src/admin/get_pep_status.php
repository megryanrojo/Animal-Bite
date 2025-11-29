<?php
// API endpoint to get PEP vaccination status (lock status, dose count)
header('Content-Type: application/json');

require_once '../conn/conn.php';
require_once 'includes/vaccination_helper.php';

$reportId = isset($_GET['reportId']) ? (int)$_GET['reportId'] : 0;

if ($reportId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid report ID']);
    exit;
}

try {
    $status = getLockStatus($pdo, $reportId);
    echo json_encode($status);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
