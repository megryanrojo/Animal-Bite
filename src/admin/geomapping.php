<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/notification_helper.php';


// Get admin information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM admin WHERE adminId = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin = ['firstName' => 'Admin', 'lastName' => ''];
}

// Initialize filter variables
$defaultDateTo = date('Y-m-d');
$defaultDateFrom = date('Y-m-d', strtotime('-12 months', strtotime($defaultDateTo)));
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$biteCategory = isset($_GET['bite_category']) ? $_GET['bite_category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$appliedDefaultRange = false;

if ($dateFrom === '' && $dateTo === '') {
    $dateFrom = $defaultDateFrom;
    $dateTo = $defaultDateTo;
    $appliedDefaultRange = true;
} elseif ($dateFrom === '' && $dateTo !== '') {
    $dateFrom = date('Y-m-d', strtotime('-12 months', strtotime($dateTo)));
} elseif ($dateFrom !== '' && $dateTo === '') {
    $dateTo = $defaultDateTo;
}

if ($dateFrom !== '' && $dateTo !== '' && strtotime($dateFrom) > strtotime($dateTo)) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

$dateRangeLabel = date('M d, Y', strtotime($dateFrom)) . ' – ' . date('M d, Y', strtotime($dateTo));
$dateWindowMonths = max(1, round((strtotime($dateTo) - strtotime($dateFrom)) / (30 * 24 * 60 * 60)));

// Get unique animal types for filter dropdown
try {
    $animalTypesStmt = $pdo->query("SELECT DISTINCT animalType FROM reports WHERE animalType IS NOT NULL ORDER BY animalType");
    $animalTypes = $animalTypesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $animalTypes = [];
}

// Get unique barangays for the legend
try {
    $barangaysStmt = $pdo->query("SELECT DISTINCT barangay FROM patients WHERE barangay IS NOT NULL ORDER BY barangay");
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $barangays = [];
}

// Build the WHERE clause for filters
$where = " WHERE 1=1";
$params = [];

if (!empty($dateFrom)) {
    $where .= " AND r.biteDate >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $where .= " AND r.biteDate <= ?";
    $params[] = $dateTo;
}
if (!empty($animalType)) {
    $where .= " AND r.animalType = ?";
    $params[] = $animalType;
}
if (!empty($biteCategory)) {
    $where .= " AND r.biteType = ?";
    $params[] = $biteCategory;
}
if (!empty($status)) {
    $where .= " AND r.status = ?";
    $params[] = $status;
}

// Main query for heatmapData (Top Affected Areas): count all reports per barangay
$query = "
    SELECT 
        COALESCE(p.barangay, 'Unspecified') AS barangay,
        COUNT(*) as case_count
    FROM 
        reports r
    JOIN 
        patients p ON r.patientId = p.patientId
    $where
    GROUP BY COALESCE(p.barangay, 'Unspecified')
    ORDER BY case_count DESC
";

// Total cases query: count all reports
$totalQuery = "
    SELECT 
        COUNT(*) as total_cases
    FROM 
        reports r
    JOIN 
        patients p ON r.patientId = p.patientId
    $where
";

$allCasesQuery = "
    SELECT
        r.reportId,
        r.biteDate,
        r.animalType,
        r.biteType,
        r.status,
        p.barangay,
        bc.latitude as latitude,
        bc.longitude as longitude,
        CONCAT(p.firstName, ' ', p.lastName) as patientName
    FROM
        reports r
    JOIN
        patients p ON r.patientId = p.patientId
    LEFT JOIN
        barangay_coordinates bc ON p.barangay = bc.barangay
    $where
    ORDER BY r.biteDate DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $heatmapData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute($params);
    $totalCases = $totalStmt->fetchColumn();

    $allCasesStmt = $pdo->prepare($allCasesQuery);
    $allCasesStmt->execute($params);
    $cases = $allCasesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for barangay counts and risk levels
    $barangayCounts = [];
    foreach ($heatmapData as $data) {
        $barangayCounts[] = [
            'barangay' => $data['barangay'],
            'count' => (int)$data['case_count']
        ];
    }
    usort($barangayCounts, fn($a, $b) => $b['count'] <=> $a['count']);

} catch (PDOException $e) {
    $heatmapData = [];
    $totalCases = 0;
    $cases = [];
    $barangayCounts = [];
    error_log("Error fetching data: " . $e->getMessage());
}

// Recent Cases
$recentQuery = "
    SELECT 
        r.reportId,
        r.biteDate,
        r.animalType,
        r.biteType,
        p.barangay,
        CONCAT(p.firstName, ' ', p.lastName) as patientName
    FROM 
        reports r
    JOIN 
        patients p ON r.patientId = p.patientId
    $where
    ORDER BY r.biteDate DESC
    LIMIT 5
";

try {
    $recentStmt = $pdo->prepare($recentQuery);
    $recentStmt->execute($params);
    $recentCases = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentCases = [];
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
    return 'geomapping.php?' . http_build_query($params);
}

// Fetch coordinates for each barangay
$barangayCoordinates = [];
try {
    $stmt = $pdo->query("SELECT barangay, latitude, longitude FROM barangay_coordinates");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $barangayCoordinates[$row['barangay']] = [
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude']
        ];
    }
} catch (PDOException $e) {
    error_log('Error fetching barangay coordinates: ' . $e->getMessage());
}

// Ensure each case has coordinates, fallback to barangay centroid if missing
foreach ($cases as &$case) {
    $latEmpty = empty($case['latitude']) || $case['latitude'] == 0;
    $lngEmpty = empty($case['longitude']) || $case['longitude'] == 0;
    if (($latEmpty || $lngEmpty) && !empty($case['barangay']) && isset($barangayCoordinates[$case['barangay']])) {
        $case['latitude'] = $barangayCoordinates[$case['barangay']]['lat'];
        $case['longitude'] = $barangayCoordinates[$case['barangay']]['lng'];
    }
    if (!empty($case['latitude'])) {
        $case['latitude'] = (float)$case['latitude'];
    }
    if (!empty($case['longitude'])) {
        $case['longitude'] = (float)$case['longitude'];
    }
}
unset($case);

// Lock map to Talisay City bounds
$centerLat = 10.735;
$centerLng = 122.966;
$mapBounds = [
    'southWest' => ['lat' => 10.60, 'lng' => 122.85],
    'northEast' => ['lat' => 10.90, 'lng' => 123.08]
];

// Prepare heatmap data for JavaScript
$jsHeatmapData = [];
foreach ($heatmapData as $data) {
    $barangay = $data['barangay'];
    if (isset($barangayCoordinates[$barangay])) {
        $jsHeatmapData[] = [
            'lat' => $barangayCoordinates[$barangay]['lat'],
            'lng' => $barangayCoordinates[$barangay]['lng'],
            'count' => (int)$data['case_count'],
            'barangay' => $barangay
        ];
    }
}

$maxCount = !empty($barangayCounts) ? max(array_column($barangayCounts, 'count')) : 1;

// Enhanced statistical analysis for better risk assessment
$allCounts = array_column($barangayCounts, 'count');
sort($allCounts);
$stats = [
    'count' => count($allCounts),
    'min' => !empty($allCounts) ? min($allCounts) : 0,
    'max' => $maxCount,
    'mean' => !empty($allCounts) ? array_sum($allCounts) / count($allCounts) : 0,
    'median' => !empty($allCounts) ? $allCounts[floor(count($allCounts)/2)] : 0,
    'p25' => !empty($allCounts) ? $allCounts[floor(count($allCounts) * 0.25)] : 0,
    'p75' => !empty($allCounts) ? $allCounts[floor(count($allCounts) * 0.75)] : 0,
    'p95' => !empty($allCounts) ? $allCounts[floor(count($allCounts) * 0.95)] : 0,
    'std_dev' => calculateStandardDeviation($allCounts)
];

function calculateStandardDeviation($numbers) {
    if (empty($numbers)) return 0;
    $mean = array_sum($numbers) / count($numbers);
    $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $numbers)) / count($numbers);
    return sqrt($variance);
}

// Risk thresholds based on statistical analysis
$lowThreshold = max(1, (int)ceil($stats['p25']));
$highThreshold = max($lowThreshold + 1, (int)ceil($stats['p75']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geomapping Analysis - Animal Bite Cases</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    
    <style>
        /* Updated color scheme to light sky blue */
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #38bdf8;
            --accent: #06b6d4;
            --danger: #dc2626;
            --warning: #d97706;
            --success: #059669;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        html, body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: var(--gray-800);
            background: #fff;
            overflow: hidden;
        }
        
        body {
            display: flex;
            flex-direction: column;
        }
        
        .page-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 100%;
        }
        
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }
        
        /* Improved filters bar layout */
        .filters-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.25rem;
            background: white;
            border-bottom: 1px solid var(--gray-200);
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        
        .filters-bar .page-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: auto;
        }
        
        .filters-bar .page-title i {
            color: var(--primary);
        }
        
        .case-count-badge {
            background: var(--primary-light);
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .filter-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        
        .filter-item label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-600);
            white-space: nowrap;
        }
        
        .filter-item input,
        .filter-item select {
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background: white;
            min-width: 120px;
        }
        
        .filter-item input:focus,
        .filter-item select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }
        
        .filter-btn {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.15s;
        }
        
        .filter-btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .filter-btn-primary:hover { background: var(--primary-dark); }
        
        .filter-btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .filter-btn-secondary:hover { background: var(--gray-200); }
        
        .filters-meta {
            flex-basis: 100%;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 0.78rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        .range-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(14, 165, 233, 0.1);
            border: 1px solid rgba(14, 165, 233, 0.2);
            border-radius: 999px;
            padding: 0.15rem 0.75rem;
            font-weight: 600;
            color: var(--primary-dark);
            width: fit-content;
        }
        
        /* Main content area */
        .content-area {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .map-container {
            flex: 1;
            position: relative;
            min-width: 0;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        /* Map legend */
        .map-legend {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            z-index: 1000;
            width: 260px;
            font-size: 0.85rem;
            max-height: 320px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--gray-200);
        }
        
        .legend-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        
        .legend-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .legend-title i {
            color: var(--primary);
        }
        
        .legend-toggle {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-500);
            padding: 0.25rem;
            font-size: 1rem;
            border-radius: 4px;
        }
        
        .legend-toggle:hover { 
            color: var(--primary); 
            background: var(--gray-100);
        }
        
        .legend-body {
            padding: 0.75rem 1rem;
            overflow-y: auto;
        }
        
        .legend-body.collapsed { display: none; }
        
        .legend-section {
            margin-bottom: 0.75rem;
        }
        
        .legend-section:last-child { margin-bottom: 0; }
        
        .legend-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .legend-gradient {
            height: 16px;
            border-radius: 4px;
            background: linear-gradient(to right, #22c55e, #84cc16, #facc15, #f97316, #ef4444, #be123c);
            margin-bottom: 0.5rem;
        }
        
        .legend-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.5rem;
            margin: 0 -0.5rem;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.15s;
        }
        
        .legend-item:hover { background: rgba(14, 165, 233, 0.08); }
        
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .legend-item-label {
            flex: 1;
            color: var(--gray-700);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.8rem;
        }
        
        .legend-item-value {
            font-weight: 600;
            color: var(--gray-800);
            background: var(--gray-100);
            padding: 0.1rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        /* Map controls */
        .map-controls {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 0.5rem;
            z-index: 1000;
        }

        .map-control-btn {
            padding: 0.5rem 0.85rem;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background: white;
            color: var(--gray-700);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.15s;
        }

        .map-control-btn:hover { background: var(--gray-50); }
        .map-control-btn.active { background: var(--primary); color: white; }
        .map-control-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Heatmap Settings Panel */
        .heatmap-settings {
            position: absolute;
            top: 80px;
            left: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 280px;
            border: 1px solid var(--gray-200);
        }

        .settings-panel {
            padding: 1rem;
        }

        .setting-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .setting-group:last-child {
            margin-bottom: 0;
        }

        .setting-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-700);
            min-width: 80px;
        }

        .setting-group input[type="range"] {
            flex: 1;
            height: 6px;
            border-radius: 3px;
            background: var(--gray-200);
            outline: none;
            -webkit-appearance: none;
        }

        .setting-group input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .setting-group input[type="range"]::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .setting-group select {
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background: white;
            font-size: 0.85rem;
            min-width: 120px;
        }

        .setting-value {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            min-width: 35px;
            text-align: center;
        }

        /* Time Filter Panel */
        .time-filter-panel {
            position: absolute;
            top: 80px;
            left: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 300px;
            border: 1px solid var(--gray-200);
        }

        .time-filter-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
        }

        .time-filter-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        .time-filter-btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background: white;
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.15s;
        }

        .time-filter-btn:hover {
            background: var(--gray-50);
            border-color: var(--primary);
        }

        .time-filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .time-filter-custom input[type="date"] {
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background: white;
        }

        
        /* Slideable sidebar panel */
        .data-sidebar {
            width: 320px;
            background: white;
            border-left: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex-shrink: 0;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -2px 0 8px rgba(0,0,0,0.1);
        }

        .data-sidebar.collapsed {
            width: 0;
            transform: translateX(20px);
            opacity: 0;
            border-left: none;
            box-shadow: none;
        }
        
        /* Focused location header - fixed positioning */
        .focused-location-header {
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            display: none;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .focused-location-header.active {
            display: flex;
        }
        
        .focused-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .focused-indicator i {
            font-size: 1rem;
        }
        
        .focused-title {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .risk-badge {
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 0.5rem;
        }
        
        .clear-focus-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.35rem 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.15s;
            display: flex;
            align-items: center;
        }
        
        .clear-focus-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Sidebar sections container */
        .sidebar-sections {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .data-section {
            border-bottom: 1px solid var(--gray-200);
        }
        
        .data-section:last-child { border-bottom: none; }
        
        .data-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: var(--gray-50);
            cursor: pointer;
            user-select: none;
            transition: background 0.15s;
        }
        
        .data-header:hover { background: var(--gray-100); }
        
        .data-header h6 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        
        .data-header h6 i {
            color: var(--primary);
        }
        
        .data-header i.toggle-icon {
            font-size: 0.85rem;
            color: var(--gray-400);
            transition: transform 0.2s;
        }
        
        .data-body {
            padding: 0.75rem;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .data-body.collapsed { display: none; }
        
        /* Fixed stat grid layout */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.6rem;
        }
        
        .stat-card {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            border: 1px solid var(--gray-100);
        }
        
        .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 0.2rem;
        }
        
        .stat-card.primary .stat-value { color: var(--primary); }
        .stat-card.danger .stat-value { color: var(--danger); }
        .stat-card.warning .stat-value { color: var(--warning); }
        .stat-card.success .stat-value { color: var(--success); }
        
        /* Tables */
        .data-table {
            width: 100%;
            font-size: 0.8rem;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            font-weight: 600;
            color: var(--gray-500);
            padding: 0.5rem;
            border-bottom: 2px solid var(--gray-200);
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }
        
        .data-table tbody tr {
            cursor: pointer;
            transition: background 0.15s;
        }
        
        .data-table tbody tr:hover td { background: rgba(14, 165, 233, 0.05); }
        
        .badge {
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
            display: inline-block;
        }
        
        .badge-critical { background: #fef2f2; color: #991b1b; }
        .badge-high { background: #fff7ed; color: #9a3412; }
        .badge-medium { background: #fefce8; color: #854d0e; }
        .badge-low { background: #f0fdf4; color: #166534; }
        
        /* Sidebar toggle */
        /* Enhanced sidebar toggle button */
        /* Desktop sidebar toggle (default) - positioned relative to sidebar */
        .sidebar-toggle {
            position: absolute !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 32px !important;
            height: 56px !important;
            background: white !important;
            border: 2px solid var(--gray-200) !important;
            border-right: none !important;
            border-radius: 8px 0 0 8px !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: var(--gray-600) !important;
            z-index: 10000 !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-shadow: -2px 2px 8px rgba(0,0,0,0.1) !important;
            /* Position ON the sidebar when expanded */
            right: 0 !important;
        }

        .sidebar-toggle:hover {
            color: var(--primary);
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-50%) scale(1.05);
        }

        .sidebar-toggle:active {
            transform: translateY(-50%) scale(0.95);
        }

        .sidebar-toggle.collapsed {
            right: 0 !important; /* Position at container edge when collapsed */
            border-radius: 8px 0 0 8px !important; /* Rounded side faces outward */
            border-left: 2px solid var(--gray-200) !important; /* Border on the visible side */
            border-right: none !important;
            z-index: 10001 !important; /* Even higher when collapsed to ensure visibility */
        }

        .sidebar-toggle i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        .sidebar-toggle:hover i {
            transform: scale(1.1);
        }
        
        /* Responsive sidebar behavior */
        @media (max-width: 1024px) {
            .data-sidebar {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                z-index: 200;
                box-shadow: -4px 0 12px rgba(0,0,0,0.15);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .data-sidebar.collapsed {
                transform: translateX(340px);
            }

            /* Mobile sidebar toggle - completely override desktop styles */
            .sidebar-toggle {
                position: fixed !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                width: 50px !important;
                height: 50px !important;
                border-radius: 50% !important;
                background: var(--primary) !important;
                color: white !important;
                border: 3px solid white !important;
                box-shadow: 0 4px 20px rgba(37, 99, 235, 0.6) !important;
                z-index: 10000 !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                pointer-events: all !important;
                opacity: 1 !important;
            }

            .sidebar-toggle:hover {
                background: var(--primary-dark) !important;
                transform: translateY(-50%) scale(1.1) !important;
                box-shadow: 0 6px 25px rgba(37, 99, 235, 0.8) !important;
            }

            /* When sidebar is collapsed (hidden), button is at right edge */
            .sidebar-toggle.collapsed {
                right: 20px !important;
            }

            /* When sidebar is expanded (visible), button moves left of sidebar */
            .sidebar-toggle:not(.collapsed) {
                right: 340px !important;
            }

            .sidebar-toggle i {
                font-size: 1.2rem !important;
                transition: transform 0.2s ease !important;
                filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5)) !important;
            }
        }
        
        @media (max-width: 768px) {
            .filters-bar {
                padding: 0.5rem 0.75rem;
                gap: 0.5rem;
            }
            .page-title {
                flex-basis: 100%;
                font-size: 1rem;
            }
            .filter-item label { display: none; }
            .filter-item input,
            .filter-item select {
                min-width: 100px;
                padding: 0.35rem 0.5rem;
                font-size: 0.8rem;
            }
            .filter-btn {
                padding: 0.35rem 0.6rem;
                font-size: 0.8rem;
            }
            .data-sidebar {
                width: 100%;
            }
            .sidebar-toggle.collapsed {
                right: 10px;
            }
            .map-legend {
                bottom: 60px;
                left: 10px;
                width: calc(100% - 20px);
                max-width: 280px;
            }
            .map-controls {
                top: 10px;
                left: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="main-wrapper">
            <!-- Filters bar -->
            <form method="GET" action="geomapping.php" class="filters-bar">
                <div class="page-title">
                    <i class="bi bi-geo-alt-fill"></i>
                    Geomapping
                    <span class="case-count-badge"><?= number_format(count($cases)) ?> cases</span>
                </div>
                
                <div class="filter-group">
                    <div class="filter-item">
                        <label>From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="filter-item">
                        <label>To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="filter-item">
                        <label>Animal</label>
                        <select name="animal_type">
                            <option value="">All Animals</option>
                            <?php foreach ($animalTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $animalType === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label>Category</label>
                        <select name="bite_category">
                            <option value="">All Categories</option>
                            <option value="Category I" <?= $biteCategory === 'Category I' ? 'selected' : '' ?>>Category I</option>
                            <option value="Category II" <?= $biteCategory === 'Category II' ? 'selected' : '' ?>>Category II</option>
                            <option value="Category III" <?= $biteCategory === 'Category III' ? 'selected' : '' ?>>Category III</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn filter-btn-primary">
                        <i class="bi bi-search"></i> Apply
                    </button>
                    <a href="geomapping.php" class="filter-btn filter-btn-secondary">
                        <i class="bi bi-x-lg"></i> Clear
                    </a>
                </div>
                <div class="filters-meta">
                    <span class="range-pill">
                        <i class="bi bi-calendar3"></i> <?= $dateRangeLabel; ?>
                    </span>
                </div>
            </form>
            
            <!-- Main content area -->
            <div class="content-area">
                <!-- Map container -->
                <div class="map-container">
                    <div id="map"></div>
                    
                    <!-- Map controls -->
                    <div class="map-controls">
                        <button type="button" class="map-control-btn active" id="heatmapBtn" onclick="showHeatmap()">
                            <i class="bi bi-fire"></i> Heatmap
                        </button>
                        <button type="button" class="map-control-btn" id="markersBtn" onclick="showMarkers()">
                            <i class="bi bi-geo-alt"></i> Markers
                        </button>
                        <button type="button" class="map-control-btn" id="settingsBtn" onclick="toggleHeatmapSettings()">
                            <i class="bi bi-sliders"></i> Settings
                        </button>
                        <button type="button" class="map-control-btn" id="timeFilterBtn" onclick="toggleTimeFilter()">
                            <i class="bi bi-clock"></i> Time
                        </button>
                        <button type="button" class="map-control-btn" id="exportBtn" onclick="exportHeatmapData()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>

                    <!-- Heatmap Settings Panel -->
                    <div class="heatmap-settings" id="heatmapSettings" style="display: none;">
                        <div class="settings-panel">
                            <div class="setting-group">
                                <label class="setting-label">Radius</label>
                                <input type="range" id="radiusSlider" min="10" max="100" value="50" step="5" oninput="updateHeatmapSetting('radius', this.value)">
                                <span class="setting-value" id="radiusValue">50</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Blur</label>
                                <input type="range" id="blurSlider" min="10" max="50" value="30" step="2" oninput="updateHeatmapSetting('blur', this.value)">
                                <span class="setting-value" id="blurValue">30</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Opacity</label>
                                <input type="range" id="opacitySlider" min="0.1" max="1.0" value="0.2" step="0.1" oninput="updateHeatmapSetting('minOpacity', this.value)">
                                <span class="setting-value" id="opacityValue">0.2</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Intensity Mode</label>
                                <select id="intensityMode" onchange="updateIntensityMode(this.value)">
                                    <option value="percentile">Percentile-based</option>
                                    <option value="linear">Linear</option>
                                    <option value="logarithmic">Logarithmic</option>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Animal Type</label>
                                <select id="animalTypeFilter" onchange="updateMultivariateFilters()">
                                    <option value="">All Animals</option>
                                    <?php foreach ($animalTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Category</label>
                                <select id="biteCategoryFilter" onchange="updateMultivariateFilters()">
                                    <option value="">All Categories</option>
                                    <option value="Category I">Category I</option>
                                    <option value="Category II">Category II</option>
                                    <option value="Category III">Category III</option>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Status</label>
                                <select id="statusFilter" onchange="updateMultivariateFilters()">
                                    <option value="">All Status</option>
                                    <option value="Active">Active</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Pending">Pending</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Time Filter Panel -->
                    <div class="time-filter-panel" id="timeFilterPanel" style="display: none;">
                        <div class="settings-panel">
                            <div class="time-filter-title">Time Period</div>
                            <div class="time-filter-options">
                                <button class="time-filter-btn active" data-period="all" onclick="setTimeFilter('all')">All Time</button>
                                <button class="time-filter-btn" data-period="30" onclick="setTimeFilter('30')">Last 30 Days</button>
                                <button class="time-filter-btn" data-period="90" onclick="setTimeFilter('90')">Last 3 Months</button>
                                <button class="time-filter-btn" data-period="180" onclick="setTimeFilter('180')">Last 6 Months</button>
                                <button class="time-filter-btn" data-period="365" onclick="setTimeFilter('365')">Last Year</button>
                            </div>
                            <div class="time-filter-custom" style="margin-top: 0.75rem;">
                                <label class="setting-label" style="margin-bottom: 0.5rem; display: block;">Custom Range</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="date" id="customStartDate" onchange="updateCustomTimeFilter()">
                                    <span style="color: var(--gray-500);">to</span>
                                    <input type="date" id="customEndDate" onchange="updateCustomTimeFilter()">
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Legend -->
                    <div class="map-legend" id="mapLegend">
                        <div class="legend-header">
                            <div class="legend-title">
                                <i class="bi bi-layers-fill"></i> Legend
                            </div>
                            <button type="button" class="legend-toggle" onclick="toggleLegend()">
                                <i class="bi bi-chevron-down" id="legendToggleIcon"></i>
                            </button>
                        </div>
                        <div class="legend-body" id="legendBody">
                            <div class="legend-section">
                                <div class="legend-section-title">Intensity Scale</div>
                                <div class="legend-gradient"></div>
                                <div class="legend-labels">
                                    <span>Low (1–<?= $lowThreshold ?>)</span>
                                    <span>High (<?= $highThreshold ?>+)</span>
                                </div>
                            </div>
                            <div class="legend-section">
                                <div class="legend-section-title">Top Affected Areas</div>
                                <div id="legendTopAreas">
                                    <?php
                                    $topAreas = array_slice($barangayCounts, 0, 5);
                                    foreach ($topAreas as $area):
                                        $ratio = $maxCount > 0 ? $area['count'] / $maxCount : 0;
                                        if ($ratio >= 0.75) { $color = '#dc2626'; }
                                        elseif ($ratio >= 0.5) { $color = '#f97316'; }
                                        elseif ($ratio >= 0.25) { $color = '#facc15'; }
                                        else { $color = '#22c55e'; }
                                    ?>
                                    <div class="legend-item" onclick="focusLocation('<?= htmlspecialchars($area['barangay']) ?>')">
                                        <div class="legend-dot" style="background: <?= $color ?>"></div>
                                        <span class="legend-item-label"><?= htmlspecialchars($area['barangay']) ?></span>
                                        <span class="legend-item-value"><?= $area['count'] ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($topAreas)): ?>
                                    <div style="color: var(--gray-500); font-size: 0.8rem; padding: 0.5rem 0;">No data available</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <!-- Enhanced sidebar toggle button -->
                <button type="button"
                        class="sidebar-toggle"
                        id="sidebarToggle"
                        onclick="toggleSidebar()"
                        aria-expanded="true"
                        aria-label="Hide sidebar"
                        title="Toggle sidebar visibility">
                    <i class="bi bi-chevron-right" id="sidebarToggleIcon"></i>
                </button>
                </div>
                
                <!-- Data sidebar -->
                <div class="data-sidebar" id="dataSidebar">
                    <!-- Focused location header (hidden by default) -->
                    <div class="focused-location-header" id="focusedHeader">
                        <div class="focused-indicator">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span class="focused-title" id="focusedTitle">Focused Area</span>
                            <span class="risk-badge" id="focusedRisk">Low</span>
                        </div>
                        <button onclick="clearFocusedLocation()" class="clear-focus-btn" title="Clear focus">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    
                    <!-- Scrollable sections container -->
                    <div class="sidebar-sections">
                        <!-- Stats Section -->
                        <div class="data-section">
                            <div class="data-header" onclick="toggleSection(this)">
                                <h6><i class="bi bi-bar-chart-fill"></i> Statistics</h6>
                                <i class="bi bi-chevron-down toggle-icon"></i>
                            </div>
                            <div class="data-body" id="statsBody">
                                <div class="stat-grid">
                                    <div class="stat-card primary">
                                        <div class="stat-value"><?= number_format(count($cases)) ?></div>
                                        <div class="stat-label">Total Cases</div>
                                    </div>
                                    <div class="stat-card danger">
                                        <div class="stat-value"><?= count($barangayCounts) ?></div>
                                        <div class="stat-label">Hotspots</div>
                                    </div>
                                    <div class="stat-card warning">
                                        <div class="stat-value"><?= $maxCount ?></div>
                                        <div class="stat-label">Highest Count</div>
                                    </div>
                                    <div class="stat-card success">
                                        <div class="stat-value"><?= count($animalTypes) ?></div>
                                        <div class="stat-label">Animal Types</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Affected Areas -->
                        <div class="data-section">
                            <div class="data-header" onclick="toggleSection(this)">
                                <h6><i class="bi bi-geo-fill"></i> Affected Areas</h6>
                                <i class="bi bi-chevron-down toggle-icon"></i>
                            </div>
                            <div class="data-body" id="areasBody" style="max-height: 250px;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Barangay</th>
                                            <th>Cases</th>
                                            <th>Risk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($barangayCounts as $area): 
                                            $ratio = $maxCount > 0 ? $area['count'] / $maxCount : 0;
                                            if ($ratio >= 0.75) { $risk = 'critical'; $riskLabel = 'Critical'; }
                                            elseif ($ratio >= 0.5) { $risk = 'high'; $riskLabel = 'High'; }
                                            elseif ($ratio >= 0.25) { $risk = 'medium'; $riskLabel = 'Medium'; }
                                            else { $risk = 'low'; $riskLabel = 'Low'; }
                                        ?>
                                        <tr onclick="focusLocation('<?= htmlspecialchars($area['barangay']) ?>')">
                                            <td><?= htmlspecialchars($area['barangay']) ?></td>
                                            <td><strong><?= $area['count'] ?></strong></td>
                                            <td><span class="badge badge-<?= $risk ?>"><?= $riskLabel ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($barangayCounts)): ?>
                                        <tr><td colspan="3" style="text-align: center; color: var(--gray-500);">No data</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Recent Cases -->
                        <div class="data-section">
                            <div class="data-header" onclick="toggleSection(this)">
                                <h6><i class="bi bi-clock-history"></i> Recent Cases</h6>
                                <i class="bi bi-chevron-down toggle-icon"></i>
                            </div>
                            <div class="data-body" id="recentBody" style="max-height: 200px;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Animal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCases as $case): ?>
                                        <tr>
                                            <td><?= date('M j', strtotime($case['biteDate'])) ?></td>
                                            <td><?= htmlspecialchars($case['barangay'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($case['animalType'] ?? 'N/A') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recentCases)): ?>
                                        <tr><td colspan="3" style="text-align: center; color: var(--gray-500);">No recent cases</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize map
        const centerLat = <?= $centerLat ?>;
        const centerLng = <?= $centerLng ?>;
        const talisayBounds = L.latLngBounds(
            [<?= $mapBounds['southWest']['lat'] ?>, <?= $mapBounds['southWest']['lng'] ?>],
            [<?= $mapBounds['northEast']['lat'] ?>, <?= $mapBounds['northEast']['lng'] ?>]
        );
        const map = L.map('map', {
            maxBounds: talisayBounds,
            maxBoundsViscosity: 1.0,
            minZoom: 12,
            maxZoom: 18
        }).setView([centerLat, centerLng], 13);
        map.fitBounds(talisayBounds);
        
        const heatmapBtnEl = document.getElementById('heatmapBtn');
        const markersBtnEl = document.getElementById('markersBtn');
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        
        // Data from PHP
        const cases = <?= json_encode($cases) ?>;
        const heatmapData = <?= json_encode($jsHeatmapData) ?>;
        const barangayCoords = <?= json_encode($barangayCoordinates) ?>;
        const maxCount = <?= (int)$maxCount ?> || 1;
        const stats = <?= json_encode($stats) ?>;
        const originalBarangayCounts = <?= json_encode($barangayCounts) ?>;
        const originalRecentCases = <?= json_encode($recentCases) ?>;
        
        // Build barangay case data for quick lookup
        const barangayCaseData = {};
        cases.forEach(c => {
            const barangay = c.barangay || 'Unknown';
            if (!barangayCaseData[barangay]) {
                barangayCaseData[barangay] = [];
            }
            barangayCaseData[barangay].push(c);
        });
        
        let focusedLocation = null;

        // Layers
        let heatLayer = null;
        let markersLayer = L.layerGroup();
        const markerPoints = [];

        // Heatmap configuration with interactive controls
        let heatmapConfig = {
            radius: 50,
            blur: 30,
            max: 1.0,
            maxZoom: 18,
            minOpacity: 0.2,
            intensityMode: 'percentile' // 'percentile', 'linear', 'logarithmic'
        };

        // Time filter configuration
        let currentTimeFilter = {
            type: 'all', // 'all', 'days', 'custom'
            days: null,
            startDate: null,
            endDate: null
        };

        // Multivariate filter configuration
        let currentMultivariateFilters = {
            animalType: '',
            biteCategory: '',
            status: ''
        };

        // Enhanced intensity calculation using statistical analysis and risk scoring
        function calculateHeatmapIntensity(count, barangay = null) {
            const { p25, p50, p75, p95, mean, std_dev } = stats;
            let intensity = 0;

            switch(heatmapConfig.intensityMode) {
                case 'percentile':
                    if (count <= p25) intensity = Math.max(0.1, count / Math.max(p25, 1) * 0.3);
                    else if (count <= p50) intensity = 0.3 + (count - p25) / Math.max(p50 - p25, 1) * 0.2;
                    else if (count <= p75) intensity = 0.5 + (count - p50) / Math.max(p75 - p50, 1) * 0.3;
                    else if (count <= p95) intensity = 0.8 + (count - p75) / Math.max(p95 - p75, 1) * 0.15;
                    else intensity = 0.95 + Math.min(0.05, (count - p95) / Math.max(p95, 1) * 0.05);

                case 'logarithmic':
                    intensity = Math.min(1.0, Math.max(0.1, Math.log(count + 1) / Math.log(maxCount + 1)));
                    break;

                case 'linear':
                default:
                    intensity = Math.min(1.0, Math.max(0.1, count / maxCount));
            }

            // Apply risk multiplier if barangay data available
            if (barangay && barangayCaseData[barangay]) {
                const riskMultiplier = calculateRiskMultiplier(barangay);
                intensity = Math.min(1.0, intensity * riskMultiplier);
            }

            return intensity;
        }

        // Basic risk scoring algorithm
        function calculateRiskMultiplier(barangay) {
            const barangayCases = barangayCaseData[barangay] || [];
            if (barangayCases.length === 0) return 1.0;

            // Factor 1: Recency (more recent cases = higher risk)
            const now = new Date();
            const recentCases = barangayCases.filter(c => {
                const caseDate = new Date(c.biteDate);
                const daysDiff = (now - caseDate) / (1000 * 60 * 60 * 24);
                return daysDiff <= 30; // Last 30 days
            });

            // Factor 2: Severity (higher category = higher risk)
            const severeCases = barangayCases.filter(c =>
                c.biteType === 'Category II' || c.biteType === 'Category III'
            );

            // Factor 3: Animal diversity (more animal types = higher risk)
            const animalTypes = new Set(barangayCases.map(c => c.animalType).filter(Boolean));

            // Calculate composite risk score
            const recencyScore = recentCases.length / Math.max(barangayCases.length, 1);
            const severityScore = severeCases.length / Math.max(barangayCases.length, 1);
            const diversityScore = Math.min(animalTypes.size / 3, 1); // Cap at 3 animal types

            const compositeRisk = (recencyScore * 0.4) + (severityScore * 0.4) + (diversityScore * 0.2);

            // Return multiplier (1.0 to 1.5)
            return 1.0 + (compositeRisk * 0.5);
        }

        // Filter cases by current filters (time + multivariate)
        function getFilteredCases() {
            let filteredCases = cases;

            // Apply time filter
            if (currentTimeFilter.type !== 'all') {
                const now = new Date();
                let cutoffDate = null;

                if (currentTimeFilter.type === 'days') {
                    cutoffDate = new Date(now.getTime() - (currentTimeFilter.days * 24 * 60 * 60 * 1000));
                } else if (currentTimeFilter.type === 'custom' && currentTimeFilter.startDate) {
                    cutoffDate = new Date(currentTimeFilter.startDate);
                }

                filteredCases = filteredCases.filter(c => {
                    const caseDate = new Date(c.biteDate);
                    if (currentTimeFilter.type === 'custom') {
                        if (currentTimeFilter.endDate) {
                            const endDate = new Date(currentTimeFilter.endDate);
                            return caseDate >= cutoffDate && caseDate <= endDate;
                        }
                        return caseDate >= cutoffDate;
                    }
                    return caseDate >= cutoffDate;
                });
            }

            // Apply multivariate filters
            if (currentMultivariateFilters.animalType) {
                filteredCases = filteredCases.filter(c => c.animalType === currentMultivariateFilters.animalType);
            }
            if (currentMultivariateFilters.biteCategory) {
                filteredCases = filteredCases.filter(c => c.biteType === currentMultivariateFilters.biteCategory);
            }
            if (currentMultivariateFilters.status) {
                filteredCases = filteredCases.filter(c => c.status === currentMultivariateFilters.status);
            }

            return filteredCases;
        }

        // Performance optimization: Cache for filtered data
        let heatmapCache = {
            timeFilter: null,
            multivariateFilters: null,
            heatPoints: null,
            lastUpdate: 0
        };

        // Create heatmap with enhanced intensity and time filtering
        function createHeatmap() {
            // Remove existing heatmap
            if (heatLayer) {
                map.removeLayer(heatLayer);
                heatLayer = null;
            }

            const filteredCases = getFilteredCases();

            // Performance optimization: Check if we can use cached data
            const currentFilterState = JSON.stringify({
                timeFilter: currentTimeFilter,
                multivariateFilters: currentMultivariateFilters
            });

            let heatPoints;
            if (heatmapCache.lastUpdate > 0 &&
                heatmapCache.filterState === currentFilterState &&
                Date.now() - heatmapCache.lastUpdate < 5000) { // Cache for 5 seconds
                heatPoints = heatmapCache.heatPoints;
            } else {
                // Recalculate barangay counts for filtered data
                const filteredBarangayCounts = {};
                filteredCases.forEach(c => {
                    const barangay = c.barangay || 'Unknown';
                    filteredBarangayCounts[barangay] = (filteredBarangayCounts[barangay] || 0) + 1;
                });

                const filteredHeatmapData = heatmapData.map(point => ({
                    ...point,
                    count: filteredBarangayCounts[point.barangay] || 0
                })).filter(point => point.count > 0);

                heatPoints = filteredHeatmapData
                    .filter(point => point.lat && point.lng)
                    .map(point => {
                        const lat = parseFloat(point.lat);
                        const lng = parseFloat(point.lng);
                        const intensity = calculateHeatmapIntensity(point.count, point.barangay);
                        return [lat, lng, intensity];
                    });

                // Update cache
                heatmapCache = {
                    filterState: currentFilterState,
                    heatPoints: heatPoints,
                    lastUpdate: Date.now()
                };
            }

            if (heatPoints.length > 0) {
                // Performance optimization: Use Web Workers for large datasets (if supported)
                if (heatPoints.length > 1000 && window.Worker) {
                    createHeatmapWithWorker(heatPoints);
                } else {
                    createHeatmapDirectly(heatPoints);
                }
            }
        }

        function createHeatmapDirectly(heatPoints) {
            heatLayer = L.heatLayer(heatPoints, {
                radius: heatmapConfig.radius,
                blur: heatmapConfig.blur,
                max: heatmapConfig.max,
                maxZoom: heatmapConfig.maxZoom,
                minOpacity: heatmapConfig.minOpacity,
                gradient: {
                    0.0: '#22c55e',
                    0.1: '#22c55e',
                    0.25: '#84cc16',
                    0.4: '#facc15',
                    0.55: '#f97316',
                    0.7: '#ef4444',
                    0.85: '#fb7185',
                    1.0: '#7f1d1d'
                }
            });

            if (heatmapBtnEl.classList.contains('active')) {
                heatLayer.addTo(map);
            }
        }

        function createHeatmapWithWorker(heatPoints) {
            // For very large datasets, process in chunks to prevent UI blocking
            const chunkSize = 500;
            const chunks = [];
            for (let i = 0; i < heatPoints.length; i += chunkSize) {
                chunks.push(heatPoints.slice(i, i + chunkSize));
            }

            // Process first chunk immediately
            heatLayer = L.heatLayer(chunks[0], {
                radius: heatmapConfig.radius,
                blur: heatmapConfig.blur,
                max: heatmapConfig.max,
                maxZoom: heatmapConfig.maxZoom,
                minOpacity: heatmapConfig.minOpacity,
                gradient: {
                    0.0: '#22c55e',
                    0.1: '#22c55e',
                    0.25: '#84cc16',
                    0.4: '#facc15',
                    0.55: '#f97316',
                    0.7: '#ef4444',
                    0.85: '#fb7185',
                    1.0: '#7f1d1d'
                }
            });

            if (heatmapBtnEl.classList.contains('active')) {
                heatLayer.addTo(map);
            }

            // Add remaining chunks asynchronously
            let chunkIndex = 1;
            const addNextChunk = () => {
                if (chunkIndex < chunks.length) {
                    setTimeout(() => {
                        heatLayer.addData(chunks[chunkIndex]);
                        chunkIndex++;
                        addNextChunk();
                    }, 50); // Small delay to prevent UI blocking
                }
            };
            addNextChunk();
        }

        // Initialize heatmap
        createHeatmap();
        
        // Create markers
        cases.forEach(c => {
            if (c.latitude && c.longitude) {
                const lat = parseFloat(c.latitude);
                const lng = parseFloat(c.longitude);
                const marker = L.circleMarker([lat, lng], {
                    radius: 7,
                    fillColor: '#0ea5e9',
                    color: '#0284c7',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.7
                });
                markerPoints.push([lat, lng]);
                marker.bindPopup(`
                    <div style="font-size: 13px;">
                        <strong style="font-size: 14px;">${c.barangay || 'Unknown Location'}</strong><br>
                        <span style="color: #6b7280;">Animal:</span> ${c.animalType || 'N/A'}<br>
                        <span style="color: #6b7280;">Category:</span> ${c.biteType || 'N/A'}<br>
                        <span style="color: #6b7280;">Date:</span> ${c.biteDate || 'N/A'}
                    </div>
                `);
                markersLayer.addLayer(marker);
            }
        });
        
        if (!heatLayer && markerPoints.length > 0) {
            showMarkers();
            heatmapBtnEl.disabled = true;
        }
        
        function showHeatmap() {
            if (!heatLayer) return;
            heatLayer.addTo(map);
            map.removeLayer(markersLayer);
            heatmapBtnEl.classList.add('active');
            markersBtnEl.classList.remove('active');
        }
        
        function showMarkers() {
            if (heatLayer) map.removeLayer(heatLayer);
            markersLayer.addTo(map);
            markersBtnEl.classList.add('active');
            heatmapBtnEl.classList.remove('active');
        }

        // Interactive heatmap controls
        function toggleHeatmapSettings() {
            const settings = document.getElementById('heatmapSettings');
            const btn = document.getElementById('settingsBtn');
            const isVisible = settings.style.display !== 'none';

            settings.style.display = isVisible ? 'none' : 'block';
            btn.classList.toggle('active', !isVisible);
        }

        function updateHeatmapSetting(property, value) {
            heatmapConfig[property] = parseFloat(value);

            // Update display values
            const valueDisplays = {
                'radius': 'radiusValue',
                'blur': 'blurValue',
                'minOpacity': 'opacityValue'
            };

            if (valueDisplays[property]) {
                document.getElementById(valueDisplays[property]).textContent = value;
            }

            // Recreate heatmap with new settings
            createHeatmap();
        }

        function updateIntensityMode(mode) {
            heatmapConfig.intensityMode = mode;
            createHeatmap();
        }

        // Time filter functions
        function toggleTimeFilter() {
            const panel = document.getElementById('timeFilterPanel');
            const btn = document.getElementById('timeFilterBtn');
            const isVisible = panel.style.display !== 'none';

            panel.style.display = isVisible ? 'none' : 'block';
            btn.classList.toggle('active', !isVisible);
        }

        function setTimeFilter(period) {
            // Update button states
            document.querySelectorAll('.time-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            if (period === 'all') {
                currentTimeFilter = { type: 'all' };
            } else {
                currentTimeFilter = {
                    type: 'days',
                    days: parseInt(period)
                };
            }

            createHeatmap();
            updateSidebarWithFilteredData();
        }

        function updateCustomTimeFilter() {
            const startDate = document.getElementById('customStartDate').value;
            const endDate = document.getElementById('customEndDate').value;

            // Update button states
            document.querySelectorAll('.time-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            currentTimeFilter = {
                type: 'custom',
                startDate: startDate,
                endDate: endDate
            };

            createHeatmap();
            updateSidebarWithFilteredData();
        }

        // Multivariate filter functions
        function updateMultivariateFilters() {
            currentMultivariateFilters = {
                animalType: document.getElementById('animalTypeFilter').value,
                biteCategory: document.getElementById('biteCategoryFilter').value,
                status: document.getElementById('statusFilter').value
            };

            createHeatmap();
            updateSidebarWithFilteredData();
        }

        // Export functionality
        function exportHeatmapData() {
            const filteredCases = getFilteredCases();
            const filteredBarangayCounts = {};

            filteredCases.forEach(c => {
                const barangay = c.barangay || 'Unknown';
                filteredBarangayCounts[barangay] = (filteredBarangayCounts[barangay] || 0) + 1;
            });

            // Create CSV content
            let csvContent = 'Barangay,Cases,Risk_Level\n';

            Object.entries(filteredBarangayCounts)
                .sort((a, b) => b[1] - a[1])
                .forEach(([barangay, count]) => {
                    const maxCount = Math.max(...Object.values(filteredBarangayCounts));
                    const ratio = maxCount > 0 ? count / maxCount : 0;
                    let riskLevel = 'Low';
                    if (ratio >= 0.75) riskLevel = 'Critical';
                    else if (ratio >= 0.5) riskLevel = 'High';
                    else if (ratio >= 0.25) riskLevel = 'Medium';

                    csvContent += `"${barangay}",${count},"${riskLevel}"\n`;
                });

            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `heatmap_data_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }


        function updateSidebarWithFilteredData() {
            const filteredCases = getFilteredCases();

            // Update total cases count
            document.querySelector('.case-count-badge').textContent = number_format(filteredCases.length) + ' cases';

            // Update statistics
            const filteredBarangayCounts = {};
            filteredCases.forEach(c => {
                const barangay = c.barangay || 'Unknown';
                filteredBarangayCounts[barangay] = (filteredBarangayCounts[barangay] || 0) + 1;
            });

            const maxFilteredCount = Math.max(...Object.values(filteredBarangayCounts), 0);

            document.getElementById('statsBody').innerHTML = `
                <div class="stat-grid">
                    <div class="stat-card primary">
                        <div class="stat-value">${filteredCases.length.toLocaleString()}</div>
                        <div class="stat-label">Filtered Cases</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value">${Object.keys(filteredBarangayCounts).length}</div>
                        <div class="stat-label">Hotspots</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value">${maxFilteredCount}</div>
                        <div class="stat-label">Highest Count</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value">${Object.keys(filteredCases.reduce((acc, c) => { if (c.animalType) acc[c.animalType] = true; return acc; }, {})).length}</div>
                        <div class="stat-label">Animal Types</div>
                    </div>
                </div>
            `;

            // Update affected areas table
            let areasHtml = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th>Cases</th>
                            <th>Risk</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            Object.entries(filteredBarangayCounts)
                .sort((a, b) => b[1] - a[1])
                .forEach(([barangay, count]) => {
                    const ratio = maxFilteredCount > 0 ? count / maxFilteredCount : 0;
                    let risk = 'low', riskLabel = 'Low';
                    if (ratio >= 0.75) { risk = 'critical'; riskLabel = 'Critical'; }
                    else if (ratio >= 0.5) { risk = 'high'; riskLabel = 'High'; }
                    else if (ratio >= 0.25) { risk = 'medium'; riskLabel = 'Medium'; }

                    areasHtml += `
                        <tr onclick="focusLocation('${barangay.replace(/'/g, "\\'")}')">
                            <td>${barangay}</td>
                            <td><strong>${count}</strong></td>
                            <td><span class="badge badge-${risk}">${riskLabel}</span></td>
                        </tr>
                    `;
                });

            if (Object.keys(filteredBarangayCounts).length === 0) {
                areasHtml += '<tr><td colspan="3" style="text-align: center; color: var(--gray-500);">No data for selected period</td></tr>';
            }

            areasHtml += '</tbody></table>';
            document.getElementById('areasBody').innerHTML = areasHtml;

            // Update recent cases
            const recentFilteredCases = filteredCases
                .sort((a, b) => new Date(b.biteDate) - new Date(a.biteDate))
                .slice(0, 5);

            let recentHtml = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Animal</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            recentFilteredCases.forEach(c => {
                const dateStr = new Date(c.biteDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                recentHtml += `
                    <tr>
                        <td>${dateStr}</td>
                        <td>${c.barangay || 'N/A'}</td>
                        <td>${c.animalType || 'N/A'}</td>
                    </tr>
                `;
            });

            if (recentFilteredCases.length === 0) {
                recentHtml += '<tr><td colspan="3" style="text-align: center; color: var(--gray-500);">No cases in selected period</td></tr>';
            }

            recentHtml += '</tbody></table>';
            document.getElementById('recentBody').innerHTML = recentHtml;

            // Update legend with filtered data
            const topFilteredAreas = Object.entries(filteredBarangayCounts)
                .sort((a, b) => b[1] - a[1])
                .slice(0, 5);

            let legendHtml = '';
            if (topFilteredAreas.length > 0) {
                topFilteredAreas.forEach(([barangay, count]) => {
                    const ratio = maxFilteredCount > 0 ? count / maxFilteredCount : 0;
                    let color = '#22c55e';
                    if (ratio >= 0.75) color = '#dc2626';
                    else if (ratio >= 0.5) color = '#f97316';
                    else if (ratio >= 0.25) color = '#facc15';

                    legendHtml += `
                        <div class="legend-item" onclick="focusLocation('${barangay.replace(/'/g, "\\'")}')">
                            <div class="legend-dot" style="background: ${color}"></div>
                            <span class="legend-item-label">${barangay}</span>
                            <span class="legend-item-value">${count}</span>
                        </div>
                    `;
                });
            } else {
                legendHtml = '<div style="color: var(--gray-500); font-size: 0.8rem; padding: 0.5rem 0;">No data for selected period</div>';
            }

            document.getElementById('legendTopAreas').innerHTML = legendHtml;
        }

        function toggleLegend() {
            const body = document.getElementById('legendBody');
            const icon = document.getElementById('legendToggleIcon');
            body.classList.toggle('collapsed');
            icon.className = body.classList.contains('collapsed') ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('dataSidebar');
            const toggle = document.getElementById('sidebarToggle');
            const icon = document.getElementById('sidebarToggleIcon');

            const isCollapsed = sidebar.classList.contains('collapsed');
            const willCollapse = !isCollapsed;

            sidebar.classList.toggle('collapsed');
            toggle.classList.toggle('collapsed');

            // Update icon with animation
            icon.style.transform = 'scale(0.8)';
            setTimeout(() => {
                // Determine correct icon based on device type and new state
                const isMobile = window.innerWidth <= 1024;
                if (isMobile) {
                    // On mobile, always show chevron-left when collapsed (sidebar hidden)
                    icon.className = willCollapse ? 'bi bi-chevron-left' : 'bi bi-chevron-right';
                } else {
                    // On desktop, show chevron-right when expanded (sidebar visible)
                    icon.className = willCollapse ? 'bi bi-chevron-left' : 'bi bi-chevron-right';
                }
                icon.style.transform = 'scale(1)';
            }, 75);

            // Update accessibility attributes
            toggle.setAttribute('aria-expanded', willCollapse ? 'false' : 'true');
            toggle.setAttribute('aria-label', willCollapse ? 'Show sidebar' : 'Hide sidebar');

            // Invalidate map size with longer delay for smooth animation
            setTimeout(() => map.invalidateSize(), 350);
        }
        
        function toggleSection(header) {
            const body = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            body.classList.toggle('collapsed');
            icon.style.transform = body.classList.contains('collapsed') ? 'rotate(-90deg)' : '';
        }

        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeSidebarState();
        });

        function initializeSidebarState() {
            const sidebar = document.getElementById('dataSidebar');
            const toggle = document.getElementById('sidebarToggle');
            const icon = document.getElementById('sidebarToggleIcon');

            // Check if we're on mobile/tablet
            const isMobile = window.innerWidth <= 1024;

            if (isMobile) {
                // On mobile, sidebar should start collapsed
                if (!sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    toggle.classList.add('collapsed');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Show sidebar');
                    icon.className = 'bi bi-chevron-left';
                }
            } else {
                // On desktop, sidebar should start expanded
                sidebar.classList.remove('collapsed');
                toggle.classList.remove('collapsed');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Hide sidebar');
                icon.className = 'bi bi-chevron-right';
            }
        }

        // Handle window resize to update sidebar state
        window.addEventListener('resize', function() {
            // Debounce resize events
            clearTimeout(window.resizeTimeout);
            window.resizeTimeout = setTimeout(initializeSidebarState, 250);
        });

        // Keyboard accessibility for sidebar
        document.addEventListener('keydown', function(e) {
            // Toggle sidebar with Ctrl/Cmd + B
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }

            // Close sidebar with Escape when open
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('dataSidebar');
                if (!sidebar.classList.contains('collapsed')) {
                    toggleSidebar();
                }
            }
        });
        
        function focusLocation(barangay) {
            // Pan to location
            if (barangayCoords[barangay]) {
                map.setView([barangayCoords[barangay].lat, barangayCoords[barangay].lng], 15);
            } else {
                const location = cases.find(c => c.barangay === barangay && c.latitude && c.longitude);
                if (location) {
                    map.setView([parseFloat(location.latitude), parseFloat(location.longitude)], 15);
                }
            }
            
            focusedLocation = barangay;
            const casesForBarangay = barangayCaseData[barangay] || [];
            const caseCount = casesForBarangay.length;
            const ratio = maxCount > 0 ? caseCount / maxCount : 0;
            
            let riskLevel = 'Low', riskColor = '#22c55e';
            if (ratio >= 0.75) { riskLevel = 'Critical'; riskColor = '#dc2626'; }
            else if (ratio >= 0.5) { riskLevel = 'High'; riskColor = '#f97316'; }
            else if (ratio >= 0.25) { riskLevel = 'Medium'; riskColor = '#facc15'; }
            
            // Update focused header
            const focusedHeader = document.getElementById('focusedHeader');
            const focusedTitle = document.getElementById('focusedTitle');
            const focusedRisk = document.getElementById('focusedRisk');
            
            focusedHeader.classList.add('active');
            focusedTitle.textContent = barangay;
            focusedRisk.textContent = riskLevel;
            focusedRisk.style.background = riskColor;
            
            // Update stats
            const animalTypesSet = new Set(casesForBarangay.map(c => c.animalType).filter(Boolean));
            const completedCount = casesForBarangay.filter(c => c.status === 'Completed').length;
            
            document.getElementById('statsBody').innerHTML = `
                <div class="stat-grid">
                    <div class="stat-card primary">
                        <div class="stat-value">${caseCount}</div>
                        <div class="stat-label">Cases</div>
                    </div>
                    <div class="stat-card" style="border-color: ${riskColor}20;">
                        <div class="stat-value" style="color: ${riskColor}; font-size: 1rem;">${riskLevel}</div>
                        <div class="stat-label">Risk Level</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value">${animalTypesSet.size}</div>
                        <div class="stat-label">Animal Types</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value">${completedCount}</div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
            `;
            
            // Update areas table to show animal breakdown
            const animalCounts = {};
            casesForBarangay.forEach(c => {
                const animal = c.animalType || 'Unknown';
                animalCounts[animal] = (animalCounts[animal] || 0) + 1;
            });
            
            let areasHtml = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Animal Type</th>
                            <th>Count</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            Object.entries(animalCounts)
                .sort((a, b) => b[1] - a[1])
                .forEach(([animal, count]) => {
                    const pct = caseCount > 0 ? ((count / caseCount) * 100).toFixed(1) : 0;
                    areasHtml += `
                        <tr>
                            <td>${animal}</td>
                            <td><strong>${count}</strong></td>
                            <td>${pct}%</td>
                        </tr>
                    `;
                });
            
            areasHtml += '</tbody></table>';
            document.getElementById('areasBody').innerHTML = areasHtml;
            
            // Update recent cases
            const recentBarangayCases = casesForBarangay
                .sort((a, b) => new Date(b.biteDate) - new Date(a.biteDate))
                .slice(0, 10);
            
            let recentHtml = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Animal</th>
                            <th>Category</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            recentBarangayCases.forEach(c => {
                const dateStr = new Date(c.biteDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                recentHtml += `
                    <tr>
                        <td>${dateStr}</td>
                        <td>${c.animalType || 'N/A'}</td>
                        <td><span class="badge badge-medium">${c.biteType || 'N/A'}</span></td>
                    </tr>
                `;
            });
            
            if (recentBarangayCases.length === 0) {
                recentHtml += '<tr><td colspan="3" style="text-align: center; color: var(--gray-500);">No cases</td></tr>';
            }
            
            recentHtml += '</tbody></table>';
            document.getElementById('recentBody').innerHTML = recentHtml;
        }
        
        function clearFocusedLocation() {
            focusedLocation = null;
            document.getElementById('focusedHeader').classList.remove('active');
            
            // Reset stats
            document.getElementById('statsBody').innerHTML = `
                <div class="stat-grid">
                    <div class="stat-card primary">
                        <div class="stat-value">${cases.length.toLocaleString()}</div>
                        <div class="stat-label">Total Cases</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value">${Object.keys(barangayCaseData).length}</div>
                        <div class="stat-label">Hotspots</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value">${maxCount}</div>
                        <div class="stat-label">Highest Count</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value">${Object.keys(cases.reduce((acc, c) => { if (c.animalType) acc[c.animalType] = true; return acc; }, {})).length}</div>
                        <div class="stat-label">Animal Types</div>
                    </div>
                </div>
            `;
            
            // Reset areas table
            let areasHtml = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th>Cases</th>
                            <th>Risk</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            originalBarangayCounts.forEach(area => {
                const ratio = maxCount > 0 ? area.count / maxCount : 0;
                let risk = 'low', riskLabel = 'Low';
                if (ratio >= 0.75) { risk = 'critical'; riskLabel = 'Critical'; }
                else if (ratio >= 0.5) { risk = 'high'; riskLabel = 'High'; }
                else if (ratio >= 0.25) { risk = 'medium'; riskLabel = 'Medium'; }
                
                areasHtml += `
                    <tr onclick="focusLocation('${area.barangay.replace(/'/g, "\\'")}')">
                        <td>${area.barangay}</td>
                        <td><strong>${area.count}</strong></td>
                        <td><span class="badge badge-${risk}">${riskLabel}</span></td>
                    </tr>
                `;
            });
            
            areasHtml += '</tbody></table>';
            document.getElementById('areasBody').innerHTML = areasHtml;
            
            // Reset recent cases
            let recentHtml = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Animal</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            originalRecentCases.forEach(c => {
                const dateStr = new Date(c.biteDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                recentHtml += `
                    <tr>
                        <td>${dateStr}</td>
                        <td>${c.barangay || 'N/A'}</td>
                        <td>${c.animalType || 'N/A'}</td>
                    </tr>
                `;
            });
            
            if (originalRecentCases.length === 0) {
                recentHtml += '<tr><td colspan="3" style="text-align: center; color: var(--gray-500);">No recent cases</td></tr>';
            }
            
            recentHtml += '</tbody></table>';
            document.getElementById('recentBody').innerHTML = recentHtml;
            
            // Reset map view
            map.fitBounds(talisayBounds);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
