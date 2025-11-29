<?php
/**
 * ====================================================================
 * VACCINATION SUGGESTED DATES API
 * ====================================================================
 * 
 * Endpoint: GET /src/admin/api/get_suggested_doses.php
 * Parameters:
 *   - report_id: Report ID
 *   - bite_date: Bite date (YYYY-MM-DD)
 *   - exposure_type: 'PEP' or 'PrEP' (default: 'PEP')
 *   - max_doses: Maximum doses (default: 3 for PEP)
 * 
 * Returns: JSON with suggested dose dates
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../conn/conn.php';
require_once '../includes/vaccination_helper_v2.php';

header('Content-Type: application/json');

$reportId = $_GET['report_id'] ?? null;
$biteDate = $_GET['bite_date'] ?? null;
$exposureType = $_GET['exposure_type'] ?? 'PEP';
$maxDoses = (int)($_GET['max_doses'] ?? 3);

if (!$reportId || !is_numeric($reportId) || !$biteDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid parameters']);
    exit;
}

try {
    // Get existing doses
    $stmt = $pdo->prepare("
        SELECT doseNumber, dateGiven 
        FROM vaccination_records 
        WHERE reportId = ? AND exposureType = ?
        ORDER BY doseNumber ASC
    ");
    $stmt->execute([$reportId, $exposureType]);
    $existingDoses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingDoseNumbers = array_column($existingDoses, 'doseNumber');
    
    $suggestedDoses = [];
    
    for ($i = 1; $i <= $maxDoses; $i++) {
        if (!in_array($i, $existingDoseNumbers)) {
            // Calculate suggested date
            if ($exposureType === 'PEP') {
                $suggestedDate = calculateSuggestedDoseDate($biteDate, $i, 'PEP');
            } else {
                $suggestedDate = calculateSuggestedDoseDate($biteDate, $i, 'PrEP');
            }
            
            $status = 'NotScheduled';
            $statusInfo = getDoseStatusInfo($status);
            
            // Get workload for suggested date if available
            $workload = null;
            if ($suggestedDate) {
                $workload = getWorkloadLevel($pdo, $suggestedDate, $exposureType);
            }
            
            $suggestedDoses[] = [
                'dose_number' => $i,
                'suggested_date' => $suggestedDate,
                'status' => $status,
                'status_info' => $statusInfo,
                'workload' => $workload
            ];
        }
    }
    
    // Count completed doses
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_count 
        FROM vaccination_records 
        WHERE reportId = ? AND exposureType = ? AND dateGiven IS NOT NULL
    ");
    $stmt->execute([$reportId, $exposureType]);
    $completedResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $completedCount = (int)($completedResult['completed_count'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'report_id' => $reportId,
        'exposure_type' => $exposureType,
        'bite_date' => $biteDate,
        'completed_doses' => $completedCount,
        'max_doses' => $maxDoses,
        'suggested_doses' => $suggestedDoses
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
