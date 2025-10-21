<?php
// Return JSON details for report preview modal

header('Content-Type: application/json');

// Start session and enforce admin auth
session_start();
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../conn/conn.php';

// Validate id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid report id']);
    exit;
}

$reportId = (int) $_GET['id'];

try {
    $stmt = $pdo->prepare('
        SELECT 
            r.reportId,
            r.animalType,
            r.biteType,
            r.status,
            p.firstName,
            p.lastName,
            p.contactNumber,
            p.barangay
        FROM reports r
        LEFT JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportId = ?
    ');
    $stmt->execute([$reportId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit;
    }

    $response = [
        'patientName'   => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
        'contactNumber' => $row['contactNumber'] ?? '',
        'barangay'      => $row['barangay'] ?? '',
        'animalType'    => $row['animalType'] ?? '',
        'biteType'      => $row['biteType'] ?? '',
        'status'        => isset($row['status']) ? ucfirst(str_replace('_', ' ', $row['status'])) : ''
    ];

    echo json_encode($response);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}


