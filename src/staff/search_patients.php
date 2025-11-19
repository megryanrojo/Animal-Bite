<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../conn/conn.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = max(1, min($limit, 100));

header('Content-Type: application/json');

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    // Check if query is a numeric ID (exact match)
    $isNumericId = is_numeric($query);
    
    if ($isNumericId) {
        // Try exact ID match first
        $stmt = $pdo->prepare("
            SELECT patientId, firstName, lastName, contactNumber, barangay
            FROM patients
            WHERE patientId = ?
            LIMIT 1
        ");
        $stmt->execute([$query]);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If exact match found, return it; otherwise fall through to LIKE search
        if (count($patients) > 0) {
            $results = [];
            foreach ($patients as $patient) {
                $results[] = [
                    'id' => $patient['patientId'],
                    'text' => $patient['lastName'] . ', ' . $patient['firstName'],
                    'firstName' => $patient['firstName'],
                    'lastName' => $patient['lastName'],
                    'contactNumber' => $patient['contactNumber'] ?? '',
                    'barangay' => $patient['barangay'] ?? ''
                ];
            }
            echo json_encode($results);
            exit;
        }
    }
    
    // Regular text search
    $searchTerm = "%$query%";
    $stmt = $pdo->prepare("
        SELECT patientId, firstName, lastName, contactNumber, barangay
        FROM patients
        WHERE firstName LIKE ? 
           OR lastName LIKE ? 
           OR contactNumber LIKE ?
           OR patientId LIKE ?
           OR CONCAT(lastName, ', ', firstName) LIKE ?
           OR CONCAT(firstName, ' ', lastName) LIKE ?
        ORDER BY 
            CASE 
                WHEN patientId = ? THEN 1
                WHEN firstName LIKE ? OR lastName LIKE ? THEN 2
                ELSE 3
            END,
            lastName, firstName
        LIMIT ?
    ");
    
    $exactMatch = $query; // For exact ID matching in ORDER BY
    $nameMatch = "$query%"; // For name prefix matching
    
    $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(3, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(4, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(5, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(6, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(7, $exactMatch, PDO::PARAM_STR);
    $stmt->bindValue(8, $nameMatch, PDO::PARAM_STR);
    $stmt->bindValue(9, $nameMatch, PDO::PARAM_STR);
    $stmt->bindValue(10, $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    foreach ($patients as $patient) {
        $results[] = [
            'id' => $patient['patientId'],
            'text' => $patient['lastName'] . ', ' . $patient['firstName'],
            'firstName' => $patient['firstName'],
            'lastName' => $patient['lastName'],
            'contactNumber' => $patient['contactNumber'] ?? '',
            'barangay' => $patient['barangay'] ?? ''
        ];
    }
    
    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

