<?php
/**
 * ====================================================================
 * VACCINATION WORKLOAD API
 * ====================================================================
 * 
 * Endpoint: GET /src/admin/api/get_vaccination_workload.php
 * Parameters:
 *   - date: YYYY-MM-DD format
 *   - exposure_type: 'PEP', 'PrEP', or 'all' (default: 'all')
 * 
 * Returns: JSON with workload data
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../conn/conn.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? null;
$exposureType = $_GET['exposure_type'] ?? 'all';

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

try {
    $query = "SELECT COUNT(*) as dose_count FROM vaccination_records WHERE DATE(dateGiven) = ?";
    $params = [$date];
    
    if ($exposureType !== 'all') {
        $query .= " AND exposureType = ?";
        $params[] = $exposureType;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)($result['dose_count'] ?? 0);
    
    // Workload classification
    $levels = [
        ['min' => 0, 'max' => 10, 'level' => 'LOW', 'color' => 'success', 'badge' => 'bg-success'],
        ['min' => 11, 'max' => 25, 'level' => 'MODERATE', 'color' => 'warning', 'badge' => 'bg-warning'],
        ['min' => 26, 'max' => 40, 'level' => 'HIGH', 'color' => 'danger', 'badge' => 'bg-danger'],
        ['min' => 41, 'max' => PHP_INT_MAX, 'level' => 'VERY HIGH', 'color' => 'dark', 'badge' => 'bg-dark']
    ];
    
    $classification = $levels[0];
    foreach ($levels as $level) {
        if ($count >= $level['min'] && $count <= $level['max']) {
            $classification = $level;
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'exposure_type' => $exposureType,
        'count' => $count,
        'level' => $classification['level'],
        'color' => $classification['color'],
        'badge_css' => $classification['badge'],
        'message' => $count . ' doses scheduled (' . $classification['level'] . ' workload)',
        'can_schedule' => true  // Always true - no hard limits
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
