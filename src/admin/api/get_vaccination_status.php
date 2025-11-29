<?php
/**
 * ====================================================================
 * VACCINATION STATUS API
 * ====================================================================
 * 
 * Endpoint: GET /src/admin/api/get_vaccination_status.php
 * Parameters:
 *   - report_id: Report ID (for PEP status)
 *   - exposure_type: 'PEP' or 'PrEP' (default: 'PEP')
 * 
 * Returns: JSON with lock status and progress
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../conn/conn.php';

header('Content-Type: application/json');

$reportId = $_GET['report_id'] ?? null;
$exposureType = $_GET['exposure_type'] ?? 'PEP';
$maxDoses = $_GET['max_doses'] ?? 5;

if (!$reportId || !is_numeric($reportId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid report_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as dose_count 
        FROM vaccination_records 
        WHERE reportId = ? AND exposureType = ?
    ");
    $stmt->execute([$reportId, $exposureType]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)($result['dose_count'] ?? 0);
    $maxDoses = (int)$maxDoses;
    
    $isLocked = $count >= $maxDoses;
    $message = $isLocked 
        ? "All $maxDoses doses completed - Record is locked"
        : "Progress: $count/$maxDoses doses";
    
    echo json_encode([
        'success' => true,
        'report_id' => $reportId,
        'exposure_type' => $exposureType,
        'completed_doses' => $count,
        'max_doses' => $maxDoses,
        'remaining_doses' => max(0, $maxDoses - $count),
        'is_locked' => $isLocked,
        'message' => $message,
        'progress_percent' => round(($count / $maxDoses) * 100)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
