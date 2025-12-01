<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get admin information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM admin WHERE adminId = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin = ['firstName' => 'Admin', 'lastName' => ''];
}

// Initialize filter variables with default dates (from January 2025)
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '2025-01-01';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';

// Ensure dates are valid
if (empty($dateFrom) || $dateFrom === '1970-01-01') {
    $dateFrom = '2025-01-01';
}
if (empty($dateTo) || $dateTo === '1970-01-01') {
    $dateTo = date('Y-m-d');
}

// --- Total Cases ---
$totalWhere = " WHERE r.biteDate BETWEEN ? AND ?";
$totalParams = [$dateFrom, $dateTo];

if (!empty($animalType)) {
    $totalWhere .= " AND r.animalType = ?";
    $totalParams[] = $animalType;
}
if (!empty($barangay)) {
    $totalWhere .= " AND p.barangay = ?";
    $totalParams[] = $barangay;
}
try {
    $totalQuery = "SELECT COUNT(*) as total_cases FROM reports r JOIN patients p ON r.patientId = p.patientId $totalWhere";
    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute($totalParams);
    $totalCases = $totalStmt->fetchColumn();
} catch (PDOException $e) {
    $totalCases = 0;
}

// --- Animal Type ---
$animalWhere = $totalWhere;
$animalParams = $totalParams;
try {
    $animalQuery = "SELECT r.animalType, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $animalWhere GROUP BY r.animalType ORDER BY count DESC";
    $animalStmt = $pdo->prepare($animalQuery);
    $animalStmt->execute($animalParams);
    $animalData = $animalStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $animalData = [];
}

// --- Bite Category ---
$categoryWhere = $totalWhere;
$categoryParams = $totalParams;
try {
    $categoryQuery = "SELECT r.biteType, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $categoryWhere GROUP BY r.biteType ORDER BY CASE WHEN r.biteType = 'Category I' THEN 1 WHEN r.biteType = 'Category II' THEN 2 WHEN r.biteType = 'Category III' THEN 3 ELSE 4 END";
    $categoryStmt = $pdo->prepare($categoryQuery);
    $categoryStmt->execute($categoryParams);
    $categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categoryData = [];
}

// --- Barangay (Top 10) ---
$barangayWhere = $totalWhere . " AND p.barangay IS NOT NULL";
$barangayParams = $totalParams;
try {
    $barangayQuery = "SELECT p.barangay, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $barangayWhere GROUP BY p.barangay ORDER BY count DESC LIMIT 10";
    $barangayStmt = $pdo->prepare($barangayQuery);
    $barangayStmt->execute($barangayParams);
    $barangayData = $barangayStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $barangayData = [];
}

// --- Trend (Cases by Month) ---
$trendWhere = $totalWhere;
$trendParams = $totalParams;
try {
    $trendQuery = "SELECT DATE_FORMAT(r.biteDate, '%Y-%m') as month, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $trendWhere GROUP BY DATE_FORMAT(r.biteDate, '%Y-%m') ORDER BY month";
    $trendStmt = $pdo->prepare($trendQuery);
    $trendStmt->execute($trendParams);
    $trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
    $trendLabels = [];
    $trendCounts = [];
    foreach ($trendData as $data) {
        $date = DateTime::createFromFormat('Y-m', $data['month']);
        $trendLabels[] = $date->format('M Y');
        $trendCounts[] = $data['count'];
    }
} catch (PDOException $e) {
    $trendData = [];
    $trendLabels = [];
    $trendCounts = [];
}

// --- Age Group ---
$ageWhere = " WHERE r.biteDate BETWEEN ? AND ? AND p.dateOfBirth IS NOT NULL";
$ageParams = [$dateFrom, $dateTo];

if (!empty($animalType)) {
    $ageWhere .= " AND r.animalType = ?";
    $ageParams[] = $animalType;
}
if (!empty($barangay)) {
    $ageWhere .= " AND p.barangay = ?";
    $ageParams[] = $barangay;
}
try {
    $ageQuery = "SELECT CASE WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) < 5 THEN 'Under 5' WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 5 AND 12 THEN '5-12' WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 13 AND 18 THEN '13-18' WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 19 AND 30 THEN '19-30' WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 31 AND 50 THEN '31-50' WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 51 AND 65 THEN '51-65' WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) > 65 THEN 'Over 65' ELSE 'Unknown' END as age_group, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $ageWhere GROUP BY age_group ORDER BY CASE WHEN age_group = 'Under 5' THEN 1 WHEN age_group = '5-12' THEN 2 WHEN age_group = '13-18' THEN 3 WHEN age_group = '19-30' THEN 4 WHEN age_group = '31-50' THEN 5 WHEN age_group = '51-65' THEN 6 WHEN age_group = 'Over 65' THEN 7 ELSE 8 END";
    $ageStmt = $pdo->prepare($ageQuery);
    $ageStmt->execute($ageParams);
    $ageData = $ageStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ageData = [];
}

// --- Gender ---
$genderWhere = " WHERE r.biteDate BETWEEN ? AND ? AND p.gender IS NOT NULL";
$genderParams = [$dateFrom, $dateTo];

if (!empty($animalType)) {
    $genderWhere .= " AND r.animalType = ?";
    $genderParams[] = $animalType;
}
if (!empty($barangay)) {
    $genderWhere .= " AND p.barangay = ?";
    $genderParams[] = $barangay;
}
try {
    $genderQuery = "SELECT p.gender, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $genderWhere GROUP BY p.gender";
    $genderStmt = $pdo->prepare($genderQuery);
    $genderStmt->execute($genderParams);
    $genderData = $genderStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $genderData = [];
}

// --- Treatment ---
$treatmentWhere = " WHERE r.biteDate BETWEEN ? AND ?";
$treatmentParams = [$dateFrom, $dateTo];

if (!empty($animalType)) {
    $treatmentWhere .= " AND r.animalType = ?";
    $treatmentParams[] = $animalType;
}
if (!empty($barangay)) {
    $treatmentWhere .= " AND p.barangay = ?";
    $treatmentParams[] = $barangay;
}
try {
    $treatmentQuery = "SELECT SUM(CASE WHEN r.washWithSoap = 1 THEN 1 ELSE 0 END) as wash_count, SUM(CASE WHEN r.rabiesVaccine = 1 THEN 1 ELSE 0 END) as rabies_count, SUM(CASE WHEN r.antiTetanus = 1 THEN 1 ELSE 0 END) as tetanus_count, SUM(CASE WHEN r.antibiotics = 1 THEN 1 ELSE 0 END) as antibiotics_count, SUM(CASE WHEN r.referredToHospital = 1 THEN 1 ELSE 0 END) as referred_count, COUNT(*) as total_count FROM reports r JOIN patients p ON r.patientId = p.patientId $treatmentWhere";
    $treatmentStmt = $pdo->prepare($treatmentQuery);
    $treatmentStmt->execute($treatmentParams);
    $treatmentData = $treatmentStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $treatmentData = [ 'wash_count' => 0, 'rabies_count' => 0, 'tetanus_count' => 0, 'antibiotics_count' => 0, 'referred_count' => 0, 'total_count' => 0 ];
}

// --- Ownership ---
$ownershipWhere = " WHERE r.biteDate BETWEEN ? AND ? AND r.animalOwnership IS NOT NULL";
$ownershipParams = [$dateFrom, $dateTo];

if (!empty($animalType)) {
    $ownershipWhere .= " AND r.animalType = ?";
    $ownershipParams[] = $animalType;
}
if (!empty($barangay)) {
    $ownershipWhere .= " AND p.barangay = ?";
    $ownershipParams[] = $barangay;
}
try {
    $ownershipQuery = "SELECT r.animalOwnership, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $ownershipWhere GROUP BY r.animalOwnership";
    $ownershipStmt = $pdo->prepare($ownershipQuery);
    $ownershipStmt->execute($ownershipParams);
    $ownershipData = $ownershipStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ownershipData = [];
}

// --- Vaccination ---
$vaccinationWhere = " WHERE r.biteDate BETWEEN ? AND ? AND r.animalVaccinated IS NOT NULL";
$vaccinationParams = [$dateFrom, $dateTo];

if (!empty($animalType)) {
    $vaccinationWhere .= " AND r.animalType = ?";
    $vaccinationParams[] = $animalType;
}
if (!empty($barangay)) {
    $vaccinationWhere .= " AND p.barangay = ?";
    $vaccinationParams[] = $barangay;
}
try {
    $vaccinationQuery = "SELECT r.animalVaccinated, COUNT(*) as count FROM reports r JOIN patients p ON r.patientId = p.patientId $vaccinationWhere GROUP BY r.animalVaccinated";
    $vaccinationStmt = $pdo->prepare($vaccinationQuery);
    $vaccinationStmt->execute($vaccinationParams);
    $vaccinationData = $vaccinationStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vaccinationData = [];
}

// Get unique animal types for filter dropdown
try {
    $animalTypesStmt = $pdo->query("SELECT DISTINCT animalType FROM reports WHERE animalType IS NOT NULL ORDER BY animalType");
    $animalTypes = $animalTypesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $animalTypes = [];
}

// Get unique barangays for filter dropdown
try {
    $barangaysStmt = $pdo->query("SELECT DISTINCT barangay FROM patients WHERE barangay IS NOT NULL ORDER BY barangay");
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $barangays = [];
}

// Calculate percentage change for total cases
try {
    $compareWhere = $totalWhere;
    $compareParams = $totalParams;
    
    if (!empty($dateFrom) && !empty($dateTo)) {
        $compareWhere = str_replace(
            ["r.biteDate >= ?", "r.biteDate <= ?"],
            ["r.biteDate >= DATE_SUB(?, INTERVAL DATEDIFF(?, ?) DAY)", "r.biteDate <= DATE_SUB(?, INTERVAL DATEDIFF(?, ?) DAY)"],
            $compareWhere
        );
        
        $compareParams = array_merge(
            [$dateFrom, $dateFrom, $dateTo],
            [$dateTo, $dateFrom, $dateTo],
            array_slice($compareParams, 2)
        );
    }
    
    $compareQuery = "SELECT COUNT(*) as total_cases FROM reports r JOIN patients p ON r.patientId = p.patientId $compareWhere";
    $compareStmt = $pdo->prepare($compareQuery);
    $compareStmt->execute($compareParams);
    $compareCases = $compareStmt->fetchColumn();
    
    if ($compareCases > 0) {
        $percentChange = (($totalCases - $compareCases) / $compareCases) * 100;
    } else {
        $percentChange = $totalCases > 0 ? 100 : 0;
    }
} catch (PDOException $e) {
    $compareCases = 0;
    $percentChange = 0;
}

function buildUrl($newParams = []) {
    $params = $_GET;
    foreach ($newParams as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    return 'decisionSupport.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Decision Support | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --sidebar-width: 250px;
        }
        
        * { box-sizing: border-box; }
        
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        
        /* Main Layout */
        .main-wrapper {
            margin-left: 0;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        body.has-sidebar .main-wrapper {
            margin-left: 0;
        }
        
        @media (max-width: 1024px) {
            body.has-sidebar .main-wrapper {
                margin-left: 0;
            }
        }
        
        /* Top Bar */
        .top-bar {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .top-bar h1 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .top-bar h1 i { color: var(--primary); }
        
        .top-bar-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 140px;
        }
        
        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            background: var(--card-bg);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        
        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        
        .btn-outline:hover {
            background: var(--bg);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* Content Area */
        .content {
            flex: 1;
            padding: 20px 24px;
            overflow-y: auto;
        }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .kpi-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .kpi-icon.primary { background: rgba(37, 99, 235, 0.1); color: var(--primary); }
        .kpi-icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .kpi-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .kpi-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        
        .kpi-content { flex: 1; min-width: 0; }
        
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text);
        }
        
        .kpi-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .kpi-change {
            font-size: 12px;
            font-weight: 500;
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .kpi-change.up { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .kpi-change.down { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .kpi-change.neutral { background: rgba(100, 116, 139, 0.1); color: var(--secondary); }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background: var(--card-bg);
            border-radius: 10px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .chart-card.full-width {
            grid-column: 1 / -1;
        }
        
        .chart-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(248, 250, 252, 0.5);
        }
        
        .chart-header h3 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-header h3 i { color: var(--primary); font-size: 16px; }
        
        .chart-body {
            padding: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }
        
        .chart-container.small { height: 220px; }
        
        /* Insights */
        .insights {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        
        .insights h4 {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 0 0 8px 0;
            letter-spacing: 0.5px;
        }
        
        .insights ul {
            margin: 0;
            padding-left: 16px;
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .insights li { margin-bottom: 4px; }
        .insights li:last-child { margin-bottom: 0; }
        
        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .data-table th,
        .data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .data-table th {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(248, 250, 252, 0.5);
        }
        
        .data-table tbody tr:hover {
            background: rgba(248, 250, 252, 0.8);
        }
        
        /* Toggle Buttons */
        .chart-toggle {
            display: flex;
            gap: 4px;
        }
        
        .chart-toggle button {
            padding: 4px 10px;
            font-size: 11px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 4px;
        }
        
        .chart-toggle button:first-child { border-radius: 4px 0 0 4px; }
        .chart-toggle button:last-child { border-radius: 0 4px 4px 0; }
        
        .chart-toggle button.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        /* Recommendations */
        .recommendations-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 10px;
            padding: 24px;
            margin-top: 20px;
        }
        
        .recommendations-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .recommendations-card ol {
            margin: 0;
            padding-left: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .recommendations-card li { margin-bottom: 8px; }
        .recommendations-card li:last-child { margin-bottom: 0; }
        
        /* Footer */
        .footer {
            background: var(--card-bg);
            border-top: 1px solid var(--border);
            padding: 12px 24px;
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer a { color: var(--primary); text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        
        /* Badge */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-primary { background: rgba(37, 99, 235, 0.1); color: var(--primary); }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text);
        }
        
        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            :root { --sidebar-width: 0px; }
            
            .main-wrapper { margin-left: 0; }
            
            .mobile-menu-toggle { display: block; }
            
            .top-bar {
                padding: 12px 16px;
            }
            
            .top-bar h1 { font-size: 16px; }
            
            .filter-bar { padding: 12px 16px; }
            
            .filter-group { min-width: 100%; }
            
            .filter-actions { width: 100%; }
            
            .filter-actions .btn { flex: 1; justify-content: center; }
            
            .content { padding: 16px; }
            
            .kpi-grid { grid-template-columns: 1fr; gap: 12px; }
            
            .kpi-card { padding: 16px; }
            
            .kpi-value { font-size: 24px; }
            
            .charts-grid { gap: 16px; }
            
            .chart-header { padding: 12px 16px; }
            
            .chart-body { padding: 16px; }
            
            .chart-container { height: 240px; }
            
            .recommendations-card { padding: 16px; }
            
            .footer {
                flex-direction: column;
                gap: 8px;
                text-align: center;
                padding: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .top-bar-actions { width: 100%; }
            .top-bar-actions .btn { flex: 1; justify-content: center; font-size: 12px; }
            .kpi-icon { width: 40px; height: 40px; font-size: 18px; }
            .kpi-value { font-size: 22px; }
        }
        
        /* Print Styles */
        @media print {
            .main-wrapper { margin-left: 0; }
            .top-bar, .filter-bar, .footer, .no-print { display: none !important; }
            .content { padding: 0; }
            .chart-card { break-inside: avoid; border: 1px solid #ddd; margin-bottom: 16px; }
            .kpi-card { break-inside: avoid; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i class="bi bi-list"></i>
                </button>
                <h1><i class="bi bi-graph-up-arrow"></i> Decision Support</h1>
                <span class="badge badge-primary">
                    <?php echo date('M d', strtotime($dateFrom)) . ' - ' . date('M d, Y', strtotime($dateTo)); ?>
                </span>
            </div>
            <div class="top-bar-actions">
                <button class="btn btn-outline btn-sm no-print" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <div class="dropdown">
                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="export_analytics.php?format=excel&<?php echo http_build_query($_GET); ?>"><i class="bi bi-file-excel me-2"></i>Excel</a></li>
                        <li><a class="dropdown-item" href="export_analytics.php?format=csv&<?php echo http_build_query($_GET); ?>"><i class="bi bi-file-csv me-2"></i>CSV</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar no-print">
            <form method="GET" action="decisionSupport.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Animal Type</label>
                        <select name="animal_type">
                            <option value="">All Types</option>
                            <?php foreach ($animalTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $animalType === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Barangay</label>
                        <select name="barangay">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $brgy): ?>
                            <option value="<?php echo htmlspecialchars($brgy); ?>" <?php echo $barangay === $brgy ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brgy); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search"></i> Apply
                        </button>
                        <a href="decisionSupport.php" class="btn btn-outline btn-sm">
                            <i class="bi bi-x-circle"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon primary"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo number_format($totalCases); ?></div>
                        <div class="kpi-label">Total Cases</div>
                        <?php if ($percentChange > 0): ?>
                        <div class="kpi-change up"><i class="bi bi-arrow-up-right"></i> <?php echo number_format(abs($percentChange), 1); ?>%</div>
                        <?php elseif ($percentChange < 0): ?>
                        <div class="kpi-change down"><i class="bi bi-arrow-down-right"></i> <?php echo number_format(abs($percentChange), 1); ?>%</div>
                        <?php else: ?>
                        <div class="kpi-change neutral"><i class="bi bi-dash"></i> No change</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-icon danger"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="kpi-content">
                        <?php
                            $categoryIIICount = 0;
                            foreach ($categoryData as $category) {
                                if ($category['biteType'] === 'Category III') {
                                    $categoryIIICount = $category['count'];
                                    break;
                                }
                            }
                            $categoryIIIPercent = $totalCases > 0 ? ($categoryIIICount / $totalCases) * 100 : 0;
                        ?>
                        <div class="kpi-value"><?php echo number_format($categoryIIICount); ?></div>
                        <div class="kpi-label">Category III Cases</div>
                        <div class="kpi-change neutral"><?php echo number_format($categoryIIIPercent, 1); ?>% of total</div>
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-icon success"><i class="bi bi-bandaid"></i></div>
                    <div class="kpi-content">
                        <?php
                            $treatmentRate = $treatmentData['total_count'] > 0 ? 
                                ($treatmentData['rabies_count'] / $treatmentData['total_count']) * 100 : 0;
                        ?>
                        <div class="kpi-value"><?php echo number_format($treatmentRate, 1); ?>%</div>
                        <div class="kpi-label">Vaccination Rate</div>
                        <div class="kpi-change neutral"><?php echo $treatmentData['rabies_count']; ?> of <?php echo $treatmentData['total_count']; ?></div>
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-icon warning"><i class="bi bi-geo-alt"></i></div>
                    <div class="kpi-content">
                        <?php $topBrgy = count($barangayData) > 0 ? $barangayData[0] : ['barangay' => 'N/A', 'count' => 0]; ?>
                        <div class="kpi-value"><?php echo number_format($topBrgy['count']); ?></div>
                        <div class="kpi-label">Top Area: <?php echo htmlspecialchars($topBrgy['barangay']); ?></div>
                        <a href="geomapping.php?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="kpi-change neutral" style="text-decoration: none;">
                            <i class="bi bi-map"></i> View Map
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row 1: Trend -->
            <div class="charts-grid">
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h3><i class="bi bi-graph-up"></i> Case Trend Analysis</h3>
                        <div class="chart-toggle no-print">
                            <button type="button" class="active" id="lineChartBtn">Line</button>
                            <button type="button" id="barChartBtn">Bar</button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Trend Analysis</h4>
                            <ul>
                                <?php
                                    $trendCount = count($trendCounts);
                                    if ($trendCount > 1) {
                                        $firstHalf = array_slice($trendCounts, 0, floor($trendCount / 2));
                                        $secondHalf = array_slice($trendCounts, floor($trendCount / 2));
                                        $firstHalfAvg = count($firstHalf) > 0 ? array_sum($firstHalf) / count($firstHalf) : 0;
                                        $secondHalfAvg = count($secondHalf) > 0 ? array_sum($secondHalf) / count($secondHalf) : 0;
                                        $trendChange = $firstHalfAvg > 0 ? (($secondHalfAvg - $firstHalfAvg) / $firstHalfAvg) * 100 : 0;
                                        
                                        if ($trendChange > 10) {
                                            echo "<li><strong>Increasing trend</strong> of " . number_format(abs($trendChange), 1) . "% detected. Consider enhanced preventive measures.</li>";
                                        } elseif ($trendChange < -10) {
                                            echo "<li><strong>Decreasing trend</strong> of " . number_format(abs($trendChange), 1) . "%. Current interventions appear effective.</li>";
                                        } else {
                                            echo "<li>Cases remain <strong>relatively stable</strong> over the selected period.</li>";
                                        }
                                    }
                                    echo "<li>Total of " . array_sum($trendCounts) . " cases recorded across " . count($trendCounts) . " months.</li>";
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row 2: Animal Type & Bite Category -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-pie-chart"></i> Animal Type Distribution</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="animalChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Key Insights</h4>
                            <ul>
                                <?php
                                    if (count($animalData) > 0) {
                                        $topAnimal = $animalData[0];
                                        $topAnimalPercent = $totalCases > 0 ? ($topAnimal['count'] / $totalCases) * 100 : 0;
                                        echo "<li><strong>" . htmlspecialchars($topAnimal['animalType']) . "</strong> bites are most common at " . number_format($topAnimalPercent, 1) . "%.</li>";
                                        echo "<li>" . count($animalData) . " different animal types recorded.</li>";
                                    } else {
                                        echo "<li>No data available for selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-bar-chart"></i> Bite Category Distribution</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Key Insights</h4>
                            <ul>
                                <?php
                                    $categoryICount = $categoryIICount = 0;
                                    foreach ($categoryData as $cat) {
                                        if ($cat['biteType'] === 'Category I') $categoryICount = $cat['count'];
                                        elseif ($cat['biteType'] === 'Category II') $categoryIICount = $cat['count'];
                                    }
                                    if ($totalCases > 0) {
                                        echo "<li>Category III (severe): " . number_format($categoryIIIPercent, 1) . "% require immediate attention.</li>";
                                        echo "<li>Category II (moderate): " . number_format(($categoryIICount / $totalCases) * 100, 1) . "% of cases.</li>";
                                    } else {
                                        echo "<li>No data available.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row 3: Age & Gender -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-people"></i> Age Group Distribution</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="ageChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Key Insights</h4>
                            <ul>
                                <?php
                                    if (count($ageData) > 0) {
                                        $maxAge = array_reduce($ageData, function($carry, $item) {
                                            return ($carry === null || $item['count'] > $carry['count']) ? $item : $carry;
                                        });
                                        echo "<li><strong>" . htmlspecialchars($maxAge['age_group']) . "</strong> age group has highest incidence.</li>";
                                        
                                        $childrenCount = 0;
                                        foreach ($ageData as $age) {
                                            if (in_array($age['age_group'], ['Under 5', '5-12'])) $childrenCount += $age['count'];
                                        }
                                        $childrenPct = $totalCases > 0 ? ($childrenCount / $totalCases) * 100 : 0;
                                        if ($childrenPct > 25) {
                                            echo "<li>Children under 12: " . number_format($childrenPct, 1) . "% - targeted education needed.</li>";
                                        }
                                    } else {
                                        echo "<li>No age data available.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-gender-ambiguous"></i> Gender Distribution</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="genderChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Key Insights</h4>
                            <ul>
                                <?php
                                    $maleCount = $femaleCount = 0;
                                    foreach ($genderData as $g) {
                                        if ($g['gender'] === 'Male') $maleCount = $g['count'];
                                        elseif ($g['gender'] === 'Female') $femaleCount = $g['count'];
                                    }
                                    $totalGender = $maleCount + $femaleCount;
                                    if ($totalGender > 0) {
                                        $malePct = ($maleCount / $totalGender) * 100;
                                        $femalePct = ($femaleCount / $totalGender) * 100;
                                        echo "<li>Males: " . number_format($malePct, 1) . "%, Females: " . number_format($femalePct, 1) . "%.</li>";
                                        if ($malePct > 60) echo "<li>Males significantly more affected - consider targeted interventions.</li>";
                                    } else {
                                        echo "<li>No gender data available.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row 4: Geographic & Ownership -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-geo-alt"></i> Top Affected Barangays</h3>
                        <a href="geomapping.php?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-outline btn-sm no-print">
                            <i class="bi bi-map"></i> Heatmap
                        </a>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="barangayChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-shield-exclamation"></i> Animal Ownership</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="ownershipChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Key Insights</h4>
                            <ul>
                                <?php
                                    $strayCount = $ownedCount = 0;
                                    foreach ($ownershipData as $o) {
                                        if ($o['animalOwnership'] === 'Stray') $strayCount = $o['count'];
                                        elseif (strpos($o['animalOwnership'], 'Owned') !== false) $ownedCount += $o['count'];
                                    }
                                    $totalOwn = array_sum(array_column($ownershipData, 'count'));
                                    if ($totalOwn > 0) {
                                        $strayPct = ($strayCount / $totalOwn) * 100;
                                        if ($strayPct > 50) {
                                            echo "<li>Stray animals account for " . number_format($strayPct, 1) . "% - animal control needed.</li>";
                                        } else {
                                            echo "<li>Owned animals dominant - pet owner education recommended.</li>";
                                        }
                                    } else {
                                        echo "<li>No ownership data available.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row 5: Vaccination & Treatment -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-virus"></i> Animal Vaccination Status</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="vaccinationChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Key Insights</h4>
                            <ul>
                                <?php
                                    $vaccinatedCount = $unvaccinatedCount = 0;
                                    foreach ($vaccinationData as $v) {
                                        if ($v['animalVaccinated'] === 'Yes') $vaccinatedCount = $v['count'];
                                        elseif ($v['animalVaccinated'] === 'No') $unvaccinatedCount = $v['count'];
                                    }
                                    $totalVac = array_sum(array_column($vaccinationData, 'count'));
                                    if ($totalVac > 0) {
                                        $unvacPct = ($unvaccinatedCount / $totalVac) * 100;
                                        if ($unvacPct > 50) {
                                            echo "<li>" . number_format($unvacPct, 1) . "% from unvaccinated animals - vaccination campaigns needed.</li>";
                                        } else {
                                            echo "<li>Majority from vaccinated animals (" . number_format(100 - $unvacPct, 1) . "%).</li>";
                                        }
                                    } else {
                                        echo "<li>No vaccination data available.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-hospital"></i> Treatment Measures</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container small">
                            <canvas id="treatmentChart"></canvas>
                        </div>
                        <div class="insights">
                            <h4>Key Insights</h4>
                            <ul>
                                <?php
                                    if ($treatmentData['total_count'] > 0) {
                                        $washPct = ($treatmentData['wash_count'] / $treatmentData['total_count']) * 100;
                                        $rabiesPct = ($treatmentData['rabies_count'] / $treatmentData['total_count']) * 100;
                                        echo "<li>Wound washing: " . number_format($washPct, 1) . "% compliance.</li>";
                                        echo "<li>Rabies vaccination: " . number_format($rabiesPct, 1) . "% coverage.</li>";
                                    } else {
                                        echo "<li>No treatment data available.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recommendations -->
            <div class="recommendations-card">
                <h3><i class="bi bi-lightbulb"></i> Recommendations</h3>
                <ol>
                    <?php
                        $recommendations = [];
                        
                        if ($categoryIIIPercent > 30) {
                            $recommendations[] = "<strong>Severe Case Management:</strong> With " . number_format($categoryIIIPercent, 1) . "% Category III cases, prioritize immediate medical intervention protocols.";
                        }
                        
                        if (isset($treatmentRate) && $treatmentRate < 80) {
                            $recommendations[] = "<strong>Vaccination Coverage:</strong> At " . number_format($treatmentRate, 1) . "%, increase rabies vaccination through mobile clinics and awareness campaigns.";
                        }
                        
                        if (count($barangayData) > 0) {
                            $topAreas = array_slice($barangayData, 0, 3);
                            $areaNames = array_map(function($a) { return $a['barangay']; }, $topAreas);
                            $recommendations[] = "<strong>Hotspot Focus:</strong> Concentrate resources in " . implode(', ', $areaNames) . " for maximum impact.";
                        }
                        
                        if (isset($strayPct) && $strayPct > 50) {
                            $recommendations[] = "<strong>Stray Animal Control:</strong> Implement community-based animal management programs.";
                        }
                        
                        if (count($recommendations) < 3) {
                            $recommendations[] = "<strong>Data Quality:</strong> Improve case reporting completeness, especially vaccination status and follow-up.";
                            $recommendations[] = "<strong>Community Engagement:</strong> Develop educational programs targeting high-risk demographics.";
                        }
                        
                        foreach ($recommendations as $rec) {
                            echo "<li>" . $rec . "</li>";
                        }
                    ?>
                </ol>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="footer">
            <div>&copy; <?php echo date('Y'); ?> Barangay Health Workers Management System</div>
            <div><a href="help.php">Help & Support</a></div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (document.querySelector('.sidebar')) {
                document.body.classList.add('has-sidebar');
            }
        });
        
        // Chart data from PHP
        const trendLabels = <?php echo json_encode($trendLabels); ?>;
        const trendCounts = <?php echo json_encode($trendCounts); ?>;
        
        const animalLabels = [];
        const animalCounts = [];
        <?php foreach ($animalData as $animal): ?>
        animalLabels.push('<?php echo addslashes($animal['animalType']); ?>');
        animalCounts.push(<?php echo $animal['count']; ?>);
        <?php endforeach; ?>
        
        const categoryLabels = [];
        const categoryCounts = [];
        <?php foreach ($categoryData as $category): ?>
        categoryLabels.push('<?php echo addslashes($category['biteType']); ?>');
        categoryCounts.push(<?php echo $category['count']; ?>);
        <?php endforeach; ?>
        
        const ageLabels = [];
        const ageCounts = [];
        <?php foreach ($ageData as $age): ?>
        ageLabels.push('<?php echo addslashes($age['age_group']); ?>');
        ageCounts.push(<?php echo $age['count']; ?>);
        <?php endforeach; ?>
        
        const genderLabels = [];
        const genderCounts = [];
        <?php foreach ($genderData as $gender): ?>
        genderLabels.push('<?php echo addslashes($gender['gender']); ?>');
        genderCounts.push(<?php echo $gender['count']; ?>);
        <?php endforeach; ?>
        
        const barangayLabels = [];
        const barangayCounts = [];
        <?php 
        $count = 0;
        foreach ($barangayData as $brgy): 
            if ($count < 10):
        ?>
        barangayLabels.push('<?php echo addslashes($brgy['barangay']); ?>');
        barangayCounts.push(<?php echo $brgy['count']; ?>);
        <?php 
            endif;
            $count++;
        endforeach; 
        ?>
        
        const ownershipLabels = [];
        const ownershipCounts = [];
        <?php foreach ($ownershipData as $ownership): ?>
        ownershipLabels.push('<?php echo addslashes($ownership['animalOwnership']); ?>');
        ownershipCounts.push(<?php echo $ownership['count']; ?>);
        <?php endforeach; ?>
        
        const vaccinationLabels = [];
        const vaccinationCounts = [];
        <?php foreach ($vaccinationData as $vaccination): ?>
        vaccinationLabels.push('<?php echo addslashes($vaccination['animalVaccinated']); ?>');
        vaccinationCounts.push(<?php echo $vaccination['count']; ?>);
        <?php endforeach; ?>
        
        const treatmentLabels = ['Wound Washing', 'Rabies Vaccine', 'Anti-Tetanus', 'Antibiotics', 'Hospital Referral'];
        const treatmentCounts = [
            <?php echo $treatmentData['wash_count']; ?>,
            <?php echo $treatmentData['rabies_count']; ?>,
            <?php echo $treatmentData['tetanus_count']; ?>,
            <?php echo $treatmentData['antibiotics_count']; ?>,
            <?php echo $treatmentData['referred_count']; ?>
        ];
        
        // Color palette
        const colors = [
            'rgba(37, 99, 235, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(236, 72, 153, 0.8)',
            'rgba(20, 184, 166, 0.8)',
            'rgba(249, 115, 22, 0.8)',
            'rgba(99, 102, 241, 0.8)',
            'rgba(34, 197, 94, 0.8)'
        ];
        
        const chartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { font: { size: 11 }, padding: 12 } }
            }
        };
        
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Cases',
                    data: trendCounts,
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                ...chartDefaults,
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                },
                plugins: { ...chartDefaults.plugins, legend: { display: false } }
            }
        });
        
        document.getElementById('lineChartBtn').addEventListener('click', function() {
            trendChart.config.type = 'line';
            trendChart.update();
            this.classList.add('active');
            document.getElementById('barChartBtn').classList.remove('active');
        });
        
        document.getElementById('barChartBtn').addEventListener('click', function() {
            trendChart.config.type = 'bar';
            trendChart.update();
            this.classList.add('active');
            document.getElementById('lineChartBtn').classList.remove('active');
        });
        
        // Animal Type Chart
        new Chart(document.getElementById('animalChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: animalLabels,
                datasets: [{ data: animalCounts, backgroundColor: colors, borderWidth: 0 }]
            },
            options: { ...chartDefaults, plugins: { ...chartDefaults.plugins, legend: { position: 'right' } } }
        });
        
        // Category Chart
        new Chart(document.getElementById('categoryChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'Cases',
                    data: categoryCounts,
                    backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(245, 158, 11, 0.8)', 'rgba(239, 68, 68, 0.8)'],
                    borderRadius: 4
                }]
            },
            options: {
                ...chartDefaults,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
            }
        });
        
        // Age Chart
        new Chart(document.getElementById('ageChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ageLabels,
                datasets: [{ label: 'Cases', data: ageCounts, backgroundColor: 'rgba(37, 99, 235, 0.8)', borderRadius: 4 }]
            },
            options: {
                ...chartDefaults,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
            }
        });
        
        // Gender Chart
        new Chart(document.getElementById('genderChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: genderLabels,
                datasets: [{ data: genderCounts, backgroundColor: ['rgba(37, 99, 235, 0.8)', 'rgba(236, 72, 153, 0.8)', 'rgba(139, 92, 246, 0.8)'], borderWidth: 0 }]
            },
            options: { ...chartDefaults, plugins: { ...chartDefaults.plugins, legend: { position: 'right' } } }
        });
        
        // Barangay Chart
        new Chart(document.getElementById('barangayChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: barangayLabels,
                datasets: [{ label: 'Cases', data: barangayCounts, backgroundColor: colors, borderRadius: 4 }]
            },
            options: {
                ...chartDefaults,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, y: { grid: { display: false } } }
            }
        });
        
        // Ownership Chart
        new Chart(document.getElementById('ownershipChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: ownershipLabels,
                datasets: [{ data: ownershipCounts, backgroundColor: colors, borderWidth: 0 }]
            },
            options: { ...chartDefaults, plugins: { ...chartDefaults.plugins, legend: { position: 'right' } } }
        });
        
        // Vaccination Chart
        new Chart(document.getElementById('vaccinationChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: vaccinationLabels,
                datasets: [{ data: vaccinationCounts, backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(100, 116, 139, 0.8)'], borderWidth: 0 }]
            },
            options: { ...chartDefaults, plugins: { ...chartDefaults.plugins, legend: { position: 'right' } } }
        });
        
        // Treatment Chart
        new Chart(document.getElementById('treatmentChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: treatmentLabels,
                datasets: [{ label: 'Cases', data: treatmentCounts, backgroundColor: colors, borderRadius: 4 }]
            },
            options: {
                ...chartDefaults,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
            }
        });
        
        // Mobile menu toggle
        function toggleMobileMenu() {
            // This would toggle the sidebar on mobile - implement based on your navbar.php
            document.querySelector('.sidebar')?.classList.toggle('show');
        }

        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '../logout/admin_logout.php';
            }
        }
    </script>
</body>
</html>
