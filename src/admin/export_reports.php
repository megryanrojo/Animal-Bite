<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$biteType = isset($_GET['bite_type']) ? $_GET['bite_type'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query
$whereConditions = [];
$params = [];

if (!empty($dateFrom)) {
    $whereConditions[] = "r.reportDate >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereConditions[] = "r.reportDate <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($animalType)) {
    $whereConditions[] = "r.animalType = ?";
    $params[] = $animalType;
}

if (!empty($biteType)) {
    $whereConditions[] = "r.biteType = ?";
    $params[] = $biteType;
}

if (!empty($barangay)) {
    $whereConditions[] = "p.barangay = ?";
    $params[] = $barangay;
}

if (!empty($status)) {
    $whereConditions[] = "r.status = ?";
    $params[] = $status;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

try {
    $query = "
        SELECT r.*, p.firstName, p.lastName, p.contactNumber, p.barangay
        FROM reports r
        LEFT JOIN patients p ON r.patientId = p.patientId
        $whereClause
        ORDER BY r.reportDate DESC
    ";
    
    $stmt = $pdo->prepare($query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Prepare the data
$headers = [
    'ID',
    'Patient Name',
    'Contact',
    'Barangay',
    'Animal Type',
    'Bite Date',
    'Category',
    'Status',
    'Report Date',
    'Animal Ownership',
    'Animal Status',
    'Animal Vaccinated',
    'Provoked',
    'Multiple Bites',
    'Bite Location',
    'Wash With Soap',
    'Rabies Vaccine',
    'Rabies Vaccine Date',
    'Anti Tetanus',
    'Anti Tetanus Date',
    'Antibiotics',
    'Antibiotics Details',
    'Referred To Hospital',
    'Hospital Name',
    'Follow Up Date',
    'Notes'
];

$data = [];
foreach ($reports as $report) {
    $data[] = [
        $report['reportId'],
        $report['firstName'] . ' ' . $report['lastName'],
        $report['contactNumber'],
        $report['barangay'],
        $report['animalType'],
        date('Y-m-d', strtotime($report['biteDate'])),
        $report['biteType'],
        ucfirst(str_replace('_', ' ', $report['status'])),
        date('Y-m-d', strtotime($report['reportDate'])),
        $report['animalOwnership'],
        $report['animalStatus'],
        $report['animalVaccinated'],
        $report['provoked'],
        $report['multipleBites'] ? 'Yes' : 'No',
        $report['biteLocation'],
        $report['washWithSoap'] ? 'Yes' : 'No',
        $report['rabiesVaccine'] ? 'Yes' : 'No',
        $report['rabiesVaccineDate'] ? date('Y-m-d', strtotime($report['rabiesVaccineDate'])) : '',
        $report['antiTetanus'] ? 'Yes' : 'No',
        $report['antiTetanusDate'] ? date('Y-m-d', strtotime($report['antiTetanusDate'])) : '',
        $report['antibiotics'] ? 'Yes' : 'No',
        $report['antibioticsDetails'],
        $report['referredToHospital'] ? 'Yes' : 'No',
        $report['hospitalName'],
        $report['followUpDate'] ? date('Y-m-d', strtotime($report['followUpDate'])) : '',
        $report['notes']
    ];
}

// Generate filename
$filename = 'animal_bite_reports_' . date('Y-m-d_His');

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
} else {
    // For Excel format, we'll use a simple HTML table that Excel can open
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; }';
    echo 'th, td { border: 1px solid #000; padding: 5px; }';
    echo 'th { background-color: #f0f0f0; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<table>';
    
    // Write headers
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    // Write data
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
} 