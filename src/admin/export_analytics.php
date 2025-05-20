<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get filters
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '2025-01-01';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Build WHERE clause
$where = " WHERE r.biteDate BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];
if (!empty($animalType)) {
    $where .= " AND r.animalType = ?";
    $params[] = $animalType;
}
if (!empty($barangay)) {
    $where .= " AND p.barangay = ?";
    $params[] = $barangay;
}

// Fetch analytics data
try {
    // Total cases
    $totalQuery = "SELECT COUNT(*) as total_cases FROM reports r JOIN patients p ON r.patientId = p.patientId $where";
    $stmt = $pdo->prepare($totalQuery);
    $stmt->execute($params);
    $totalCases = $stmt->fetchColumn();

    // Animal type
    $animalQuery = "SELECT r.animalType, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $where GROUP BY r.animalType ORDER BY count DESC";
    $stmt = $pdo->prepare($animalQuery);
    $stmt->execute($params);
    $animalData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Barangay
    $barangayQuery = "SELECT p.barangay, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $where GROUP BY p.barangay ORDER BY count DESC";
    $stmt = $pdo->prepare($barangayQuery);
    $stmt->execute($params);
    $barangayData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Bite category
    $categoryQuery = "SELECT r.biteType, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $where GROUP BY r.biteType ORDER BY count DESC";
    $stmt = $pdo->prepare($categoryQuery);
    $stmt->execute($params);
    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

function output_csv($totalCases, $animalData, $barangayData, $categoryData, $dateFrom, $dateTo) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=analytics_export_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    // Summary
    fputcsv($output, ['Analytics Export']);
    fputcsv($output, ['Date From', $dateFrom, 'Date To', $dateTo]);
    fputcsv($output, ['Total Cases', $totalCases]);
    fputcsv($output, []);
    // Animal Type
    fputcsv($output, ['Animal Type', 'Cases']);
    foreach ($animalData as $row) {
        fputcsv($output, [$row['animalType'], $row['count']]);
    }
    fputcsv($output, []);
    // Barangay
    fputcsv($output, ['Barangay', 'Cases']);
    foreach ($barangayData as $row) {
        fputcsv($output, [$row['barangay'], $row['count']]);
    }
    fputcsv($output, []);
    // Bite Category
    fputcsv($output, ['Bite Category', 'Cases']);
    foreach ($categoryData as $row) {
        fputcsv($output, [$row['biteType'], $row['count']]);
    }
    fclose($output);
    exit;
}

function output_excel($totalCases, $animalData, $barangayData, $categoryData, $dateFrom, $dateTo) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=analytics_export_' . date('Ymd_His') . '.xls');
    echo "<table border='1'>";
    echo "<tr><th colspan='4'>Analytics Export</th></tr>";
    echo "<tr><td>Date From</td><td>$dateFrom</td><td>Date To</td><td>$dateTo</td></tr>";
    echo "<tr><td>Total Cases</td><td colspan='3'>$totalCases</td></tr>";
    echo "<tr><td colspan='4'></td></tr>";
    // Animal Type
    echo "<tr><th colspan='2'>Animal Type</th><th colspan='2'>Cases</th></tr>";
    foreach ($animalData as $row) {
        echo "<tr><td colspan='2'>" . htmlspecialchars($row['animalType']) . "</td><td colspan='2'>" . $row['count'] . "</td></tr>";
    }
    echo "<tr><td colspan='4'></td></tr>";
    // Barangay
    echo "<tr><th colspan='2'>Barangay</th><th colspan='2'>Cases</th></tr>";
    foreach ($barangayData as $row) {
        echo "<tr><td colspan='2'>" . htmlspecialchars($row['barangay']) . "</td><td colspan='2'>" . $row['count'] . "</td></tr>";
    }
    echo "<tr><td colspan='4'></td></tr>";
    // Bite Category
    echo "<tr><th colspan='2'>Bite Category</th><th colspan='2'>Cases</th></tr>";
    foreach ($categoryData as $row) {
        echo "<tr><td colspan='2'>" . htmlspecialchars($row['biteType']) . "</td><td colspan='2'>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";
    exit;
}

if ($format === 'excel') {
    output_excel($totalCases, $animalData, $barangayData, $categoryData, $dateFrom, $dateTo);
} else {
    output_csv($totalCases, $animalData, $barangayData, $categoryData, $dateFrom, $dateTo);
} 