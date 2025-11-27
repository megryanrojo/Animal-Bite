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
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$biteCategory = isset($_GET['bite_category']) ? $_GET['bite_category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

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

// Center coordinates for the map
if (count($barangayCoordinates) > 0) {
    $latSum = 0;
    $lngSum = 0;
    $count = 0;
    foreach ($barangayCoordinates as $coord) {
        $latSum += $coord['lat'];
        $lngSum += $coord['lng'];
        $count++;
    }
    $centerLat = $latSum / $count;
    $centerLng = $lngSum / $count;
} else {
    $centerLat = 10.7425;
    $centerLng = 122.9740;
}

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
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --accent: #0891b2;
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
            --sidebar-width: 250px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        html, body {
            height: 100%;
            /* Increased base font size from 13px to 14px */
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: var(--gray-800);
            background: var(--gray-100);
            overflow: hidden;
        }
        
        body {
            display: flex;
            flex-direction: column;
        }
        
        /* Added wrapper to account for navbar */
        .page-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 100%;
        }
        
        /* Main content area below navbar */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            margin-left: 0;
        }
        
        /* Compact filters bar - single row */
        .filters-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: white;
            border-bottom: 1px solid var(--gray-200);
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        
        .filters-bar .page-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: auto;
        }
        
        .filters-bar .page-title i {
            color: var(--primary);
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
        
        /* Main content fills remaining space */
        .content-area {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* Map takes all available space */
        .map-container {
            flex: 1;
            position: relative;
            min-width: 0;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        /* Improved floating legend - larger and more readable */
        .map-legend {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            width: 260px;
            font-size: 0.85rem;
            max-height: 320px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
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
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .legend-gradient {
            height: 14px;
            border-radius: 4px;
            background: linear-gradient(to right, #22c55e, #facc15, #f97316, #ef4444, #7f1d1d);
            margin-bottom: 0.35rem;
        }
        
        .legend-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.5rem;
            margin: 0 -0.5rem;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.15s;
        }
        
        .legend-item:hover { background: var(--gray-100); }
        
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
        }
        
        .legend-item-value {
            font-weight: 600;
            color: var(--gray-800);
            background: var(--gray-100);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        /* Right sidebar - properly sized */
        .data-sidebar {
            width: 340px;
            background: white;
            border-left: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex-shrink: 0;
            transition: width 0.2s, opacity 0.2s;
        }
        
        .data-sidebar.collapsed {
            width: 0;
            opacity: 0;
            border-left: none;
        }
        
        .sidebar-toggle {
            position: absolute;
            right: 340px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 48px;
            background: white;
            border: 1px solid var(--gray-200);
            border-right: none;
            border-radius: 6px 0 0 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            z-index: 100;
            transition: right 0.2s;
        }
        
        .sidebar-toggle:hover { 
            color: var(--primary); 
            background: var(--gray-50); 
        }
        
        .sidebar-toggle.collapsed {
            right: 0;
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
        
        .data-header i.toggle-icon {
            font-size: 0.85rem;
            color: var(--gray-400);
            transition: transform 0.2s;
        }
        
        .data-body {
            padding: 0.75rem 1rem;
            overflow-y: auto;
        }
        
        .data-body.collapsed { display: none; }
        
        /* Larger stat cards */
        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        
        .stat-card {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 0.25rem;
        }
        
        .stat-card.primary .stat-value { color: var(--primary); }
        .stat-card.danger .stat-value { color: var(--danger); }
        .stat-card.warning .stat-value { color: var(--warning); }
        .stat-card.success .stat-value { color: var(--success); }
        
        /* Better table styling */
        .data-table {
            width: 100%;
            font-size: 0.85rem;
        }
        
        .data-table th {
            text-align: left;
            font-weight: 600;
            color: var(--gray-500);
            padding: 0.5rem 0.5rem;
            border-bottom: 2px solid var(--gray-200);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 0.5rem 0.5rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }
        
        .data-table tbody tr {
            cursor: pointer;
            transition: background 0.15s;
        }
        
        .data-table tbody tr:hover td { background: var(--gray-50); }
        
        .badge {
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-critical { background: #fef2f2; color: #991b1b; }
        .badge-high { background: #fff7ed; color: #9a3412; }
        .badge-medium { background: #fefce8; color: #854d0e; }
        .badge-low { background: #f0fdf4; color: #166534; }
        
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
            border-radius: 6px;
            cursor: pointer;
            background: white;
            color: var(--gray-700);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
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
            pointer-events: none;
        }
        
        /* Case count badge */
        .case-count-badge {
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-wrapper {
                margin-left: 0;
            }
            .data-sidebar {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                z-index: 200;
                box-shadow: -4px 0 12px rgba(0,0,0,0.1);
            }
            .sidebar-toggle {
                right: 0;
            }
            .sidebar-toggle:not(.collapsed) {
                right: 340px;
            }
        }
        
        @media (max-width: 768px) {
            .filters-bar {
                padding: 0.5rem;
                gap: 0.5rem;
            }
            .filter-item label { display: none; }
            .data-sidebar { width: 100%; }
        }
    </style>
</head>
<body>
    <!-- Navbar sits above the geomapping workspace -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="main-wrapper">
        <!-- Filters bar with title -->
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
                </div>
                
                <!-- Legend -->
                <div class="map-legend" id="mapLegend">
                    <div class="legend-header">
                        <div class="legend-title">
                            <i class="bi bi-layers-fill"></i> Heat Legend
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
                                <span>Low (1-<?= ceil($maxCount * 0.25) ?>)</span>
                                <span>High (<?= ceil($maxCount * 0.75) ?>+)</span>
                            </div>
                        </div>
                        <div class="legend-section">
                            <div class="legend-section-title">Top Affected Areas</div>
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
                
                <!-- Sidebar toggle button -->
                <button type="button" class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
                    <i class="bi bi-chevron-right" id="sidebarToggleIcon"></i>
                </button>
            </div>
            
            <!-- Data sidebar -->
            <div class="data-sidebar" id="dataSidebar">
                <!-- Stats Section -->
                <div class="data-section">
                    <div class="data-header" onclick="toggleSection(this)">
                        <h6><i class="bi bi-bar-chart-fill"></i> Statistics</h6>
                        <i class="bi bi-chevron-down toggle-icon"></i>
                    </div>
                    <div class="data-body">
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
                <div class="data-section" style="flex: 1; overflow: hidden; display: flex; flex-direction: column;">
                    <div class="data-header" onclick="toggleSection(this)">
                        <h6><i class="bi bi-geo-fill"></i> Affected Areas</h6>
                        <i class="bi bi-chevron-down toggle-icon"></i>
                    </div>
                    <div class="data-body" style="flex: 1; overflow-y: auto;">
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
                <div class="data-section" style="max-height: 220px; display: flex; flex-direction: column;">
                    <div class="data-header" onclick="toggleSection(this)">
                        <h6><i class="bi bi-clock-history"></i> Recent Cases</h6>
                        <i class="bi bi-chevron-down toggle-icon"></i>
                    </div>
                    <div class="data-body" style="flex: 1; overflow-y: auto;">
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

<script>
        // Initialize map with center coordinates from PHP
        const centerLat = <?= $centerLat ?>;
        const centerLng = <?= $centerLng ?>;
        const map = L.map('map').setView([centerLat, centerLng], 13);
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
        
        // Layers
        let heatLayer = null;
        let markersLayer = L.layerGroup();
        const markerPoints = [];
        
        // Create heatmap from case coordinates
        const heatPoints = heatmapData
            .filter(point => point.lat && point.lng)
            .map(point => [
                parseFloat(point.lat),
                parseFloat(point.lng),
                Math.max(point.count / maxCount, 0.2)
            ]);
        
        let dataBounds = null;
        
        if (heatPoints.length > 0) {
            heatLayer = L.heatLayer(heatPoints, {
                radius: 30,
                blur: 20,
                maxZoom: 17,
                gradient: {0.2: '#22c55e', 0.4: '#facc15', 0.6: '#f97316', 0.8: '#ef4444', 1: '#7f1d1d'}
            }).addTo(map);
            
            dataBounds = L.latLngBounds(heatPoints.map(p => [p[0], p[1]]));
        }
        
        // Create markers
        cases.forEach(c => {
            if (c.latitude && c.longitude) {
                const lat = parseFloat(c.latitude);
                const lng = parseFloat(c.longitude);
                const marker = L.circleMarker([lat, lng], {
                    radius: 7,
                    fillColor: '#2563eb',
                    color: '#1d4ed8',
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
        
        if (!dataBounds && markerPoints.length > 0) {
            dataBounds = L.latLngBounds(markerPoints.map(p => [p[0], p[1]]));
        }
        
        if (dataBounds) {
            map.fitBounds(dataBounds, { padding: [50, 50] });
        }
        
        if (!heatLayer && markerPoints.length > 0) {
            showMarkers();
            heatmapBtnEl.disabled = true;
            heatmapBtnEl.title = 'No heatmap data available for current filters';
        } else {
            heatmapBtnEl.disabled = false;
            heatmapBtnEl.title = '';
        }
        
        // View toggle functions
        function showHeatmap() {
            if (!heatLayer) {
                return;
            }
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
        
        // UI toggle functions
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
            sidebar.classList.toggle('collapsed');
            toggle.classList.toggle('collapsed');
            icon.className = sidebar.classList.contains('collapsed') ? 'bi bi-chevron-left' : 'bi bi-chevron-right';
            setTimeout(() => map.invalidateSize(), 250);
        }
        
        function toggleSection(header) {
            const body = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            body.classList.toggle('collapsed');
            icon.style.transform = body.classList.contains('collapsed') ? 'rotate(-90deg)' : '';
        }
        
        function focusLocation(barangay) {
            // First try barangay coordinates
            if (barangayCoords[barangay]) {
                map.setView([barangayCoords[barangay].lat, barangayCoords[barangay].lng], 15);
                return;
            }
            // Fallback to first case in that barangay
            const location = cases.find(c => c.barangay === barangay && c.latitude && c.longitude);
            if (location) {
                map.setView([parseFloat(location.latitude), parseFloat(location.longitude)], 15);
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
