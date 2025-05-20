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
$where = " WHERE p.barangay IS NOT NULL";
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
        p.barangay,
        COUNT(*) as case_count
    FROM 
        reports r
    JOIN 
        patients p ON r.patientId = p.patientId
    $where
    GROUP BY p.barangay
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

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $heatmapData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute($params);
    $totalCases = $totalStmt->fetchColumn();
} catch (PDOException $e) {
    $heatmapData = [];
    $totalCases = 0;
}

// Recent Cases: show the 5 most recent reports (regardless of patient)
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

// Function to build URL with updated parameters
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

// Fetch coordinates for each barangay dynamically from the database
$barangayCoordinates = [];
try {
    $stmt = $pdo->query("SELECT barangay, latitude, longitude FROM barangay_coordinates");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $barangayCoordinates[$row['barangay']] = [
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude']
        ];
    }
    // Debug: Log fetched coordinates
    error_log('Fetched Barangay Coordinates: ' . print_r($barangayCoordinates, true));
} catch (PDOException $e) {
    error_log('Error fetching barangay coordinates: ' . $e->getMessage());
}

// Center coordinates for the map (use the average if available)
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
    // Debug: Log each barangay being processed
    error_log("Processing barangay: " . $barangay);
    
    if (isset($barangayCoordinates[$barangay])) {
        $jsHeatmapData[] = [
            'lat' => $barangayCoordinates[$barangay]['lat'],
            'lng' => $barangayCoordinates[$barangay]['lng'],
            'count' => (int)$data['case_count'],
            'barangay' => $barangay
        ];
        // Debug: Log successful data point
        error_log("Added heatmap point for " . $barangay . " with count: " . $data['case_count']);
    } else {
        // Debug: Log missing coordinates
        error_log("No coordinates found for barangay: " . $barangay);
    }
}

// Debug: Log final JavaScript data
error_log("Final Heatmap Data for JavaScript: " . print_r($jsHeatmapData, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Geomapping | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <style>
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-rgb: 13, 110, 253;
            --bs-secondary: #f8f9fa;
            --bs-secondary-rgb: 248, 249, 250;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            color: var(--bs-primary);
            margin-right: 0.5rem;
            font-size: 1.5rem;
        }
        
        .nav-link.active {
            color: var(--bs-primary) !important;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        .btn-outline-primary {
            color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .btn-logout {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 5px;
            transition: all 0.2s;
        }
        
        .btn-logout:hover {
            background-color: #bb2d3b;
            color: white;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .geomapping-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            flex-grow: 1;
        }
        
        .content-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .content-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content-card-body {
            padding: 1.5rem;
        }
        
        .filter-form {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        #map {
            height: 600px;
            width: 100%;
            border-radius: 5px;
        }
        
        .legend {
            background-color: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            border-radius: 3px;
        }
        
        .stats-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: var(--bs-primary);
        }
        
        .card-icon {
            font-size: 2.5rem;
            color: rgba(var(--bs-primary-rgb), 0.2);
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table th {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            font-weight: 600;
            border-top: none;
        }
        
        .table td, .table th {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr {
            transition: all 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        
        .badge-category-i {
            background-color: #28a745;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-category-ii {
            background-color: #ffc107;
            color: #212529;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-category-iii {
            background-color: #dc3545;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        /* Notification Dropdown Styles */
        .notification-dropdown {
            min-width: 320px;
            max-width: 320px;
            max-height: 400px;
            overflow-y: auto;
            padding: 0;
        }
        
        .dropdown-header {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            font-weight: 600;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .dropdown-footer {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            text-align: center;
            font-weight: 500;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s;
        }
        
        .notification-item:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        
        .notification-item.unread {
            background-color: rgba(var(--bs-primary-rgb), 0.05);
        }
        
        .notification-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .notification-icon.info { background-color: rgba(13, 202, 240, 0.2); color: #0dcaf0; }
        .notification-icon.warning { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .notification-icon.danger { background-color: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .notification-icon.success { background-color: rgba(25, 135, 84, 0.2); color: #198754; }
        
        .notification-content {
            margin-left: 0.75rem;
            overflow: hidden;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .notification-message {
            font-size: 0.875rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .badge-counter {
            position: absolute;
            top: 0px;
            right: 0px;
            transform: translate(25%, -25%);
        }
        
        @media (max-width: 768px) {
            .geomapping-container {
                padding: 1rem;
            }
            
            .content-card-header, .content-card-body {
                padding: 1rem;
            }
            
            .filter-form {
                padding: 1rem;
            }
            
            #map {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <!-- Include the navbar -->
    <?php include 'includes/navbar.php'; ?>

    <div class="geomapping-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Geomapping Analysis</h2>
                <p class="text-muted mb-0">Visualize animal bite cases by location for better decision making</p>
            </div>
            <div>
                <a href="view_reports.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-file-earmark-text me-2"></i>View Reports
                </a>
                <a href="#" class="btn btn-primary" onclick="printMap()">
                    <i class="bi bi-printer me-2"></i>Print Map
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-form">
            <form method="GET" action="geomapping.php">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="animal_type" class="form-label">Animal Type</label>
                        <select class="form-select" id="animal_type" name="animal_type">
                            <option value="">All Types</option>
                            <?php foreach ($animalTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $animalType === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="bite_category" class="form-label">Bite Category</label>
                        <select class="form-select" id="bite_category" name="bite_category">
                            <option value="">All Categories</option>
                            <option value="Category I" <?php echo $biteCategory === 'Category I' ? 'selected' : ''; ?>>Category I</option>
                            <option value="Category II" <?php echo $biteCategory === 'Category II' ? 'selected' : ''; ?>>Category II</option>
                            <option value="Category III" <?php echo $biteCategory === 'Category III' ? 'selected' : ''; ?>>Category III</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-12 d-flex justify-content-end">
                        <a href="geomapping.php" class="btn btn-outline-secondary me-2">Reset Filters</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Total Cases</h5>
                            <div class="stats-number"><?php echo $totalCases; ?></div>
                            <p class="text-muted mb-0">In selected period</p>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Hotspot Areas</h5>
                            <div class="stats-number"><?php echo count($heatmapData) > 0 ? count($heatmapData) : 0; ?></div>
                            <p class="text-muted mb-0">Affected barangays</p>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Highest Concentration</h5>
                            <div class="stats-number">
                                <?php 
                                    echo count($heatmapData) > 0 ? $heatmapData[0]['case_count'] : 0; 
                                ?>
                            </div>
                            <p class="text-muted mb-0">
                                <?php 
                                    echo count($heatmapData) > 0 ? htmlspecialchars($heatmapData[0]['barangay']) : 'N/A'; 
                                ?>
                            </p>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Section -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Animal Bite Cases Heatmap</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="heatmapView">Heatmap</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="markerView">Markers</button>
                </div>
            </div>
            <div class="content-card-body">
                <div id="map"></div>
            </div>
        </div>
        
        <!-- Top Affected Areas -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Top Affected Areas</h5>
            </div>
            <div class="content-card-body">
                <?php if (count($heatmapData) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Barangay</th>
                                <th>Number of Cases</th>
                                <th>Percentage</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($heatmapData as $data): 
                                $percentage = $totalCases > 0 ? round(($data['case_count'] / $totalCases) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($data['barangay']); ?></td>
                                <td><?php echo $data['case_count']; ?></td>
                                <td><?php echo $percentage; ?>%</td>
                                <td>
                                    <a href="view_reports.php?barangay=<?php echo urlencode($data['barangay']); ?>" class="btn btn-sm btn-outline-primary">
                                        View Reports
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    No data available for the selected filters.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Cases -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Cases</h5>
            </div>
            <div class="content-card-body">
                <?php if (count($recentCases) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Barangay</th>
                                <th>Animal</th>
                                <th>Category</th>
                                <th>Report Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCases as $case): ?>
                            <tr>
                                <td><?php echo $case['reportId']; ?></td>
                                <td><?php echo htmlspecialchars($case['patientName']); ?></td>
                                <td><?php echo htmlspecialchars($case['barangay']); ?></td>
                                <td><?php echo htmlspecialchars($case['animalType']); ?></td>
                                <td>
                                    <?php 
                                        $biteType = $case['biteType'];
                                        $biteTypeClass = 'badge-' . strtolower(str_replace(' ', '-', $biteType));
                                        echo '<span class="badge ' . $biteTypeClass . '">' . $biteType . '</span>';
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($case['biteDate'])); ?></td>
                                <td>
                                    <a href="view_report.php?id=<?php echo $case['reportId']; ?>" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    No recent cases available for the selected filters.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Recommendations</h5>
            </div>
            <div class="content-card-body">
                <div class="alert alert-primary">
                    <h5><i class="bi bi-info-circle me-2"></i>Decision Support Recommendations</h5>
                    <p>Based on the current data analysis, here are some recommendations:</p>
                    <ul>
                        <?php if (count($heatmapData) > 0): ?>
                        <li><strong>Focus Areas:</strong> Prioritize resources in <?php echo htmlspecialchars($heatmapData[0]['barangay']); ?> where the highest concentration of cases is reported.</li>
                        <?php endif; ?>
                        <li><strong>Public Awareness:</strong> Conduct educational campaigns in high-risk areas about animal bite prevention.</li>
                        <li><strong>Vaccination Drives:</strong> Organize pet vaccination drives in areas with high incidence rates.</li>
                        <li><strong>Mobile Clinics:</strong> Deploy mobile treatment units to areas with limited access to healthcare facilities.</li>
                        <li><strong>Follow-up:</strong> Ensure proper follow-up for all Category III cases to prevent complications.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">&copy; <?php echo date('Y'); ?> Barangay Health Workers Management System</small>
                </div>
                <div>
                    <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    
    <script>
        var map = L.map('map').setView([<?php echo $centerLat; ?>, <?php echo $centerLng; ?>], 14);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        var heatData = <?php echo json_encode($jsHeatmapData); ?>;
        console.log('Heatmap Data:', heatData); // Debug output
        
        var heatPoints = [];
        var markers = L.markerClusterGroup();

        heatData.forEach(function(point) {
            // Add intensity based on case count
            var intensity = Math.min(point.count / 2, 1); // Adjusted for smaller numbers
            heatPoints.push([point.lat, point.lng, intensity]);
            console.log('Adding point:', point.barangay, 'with intensity:', intensity); // Debug output
            
            // Create marker with popup
            var marker = L.marker([point.lat, point.lng])
                .bindPopup('<strong>' + point.barangay + '</strong><br>Cases: ' + point.count);
            
            markers.addLayer(marker);
        });
        
        // Create heatmap layer with adjusted parameters
        var heat = L.heatLayer(heatPoints, {
            radius: 50, // Increased radius
            blur: 25,   // Increased blur
            maxZoom: 18,
            minOpacity: 0.6,
            gradient: {
                0.4: 'blue',
                0.6: 'lime',
                0.8: 'yellow',
                1.0: 'red'
            }
        }).addTo(map);
        
        // Initially show both heatmap and markers
        map.addLayer(heat);
        map.addLayer(markers);
        
        // Toggle between heatmap and markers
        document.getElementById('heatmapView').addEventListener('click', function() {
            map.removeLayer(markers);
            map.addLayer(heat);
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');
            document.getElementById('markerView').classList.remove('btn-primary');
            document.getElementById('markerView').classList.add('btn-outline-primary');
        });
        
        document.getElementById('markerView').addEventListener('click', function() {
            map.removeLayer(heat);
            map.addLayer(markers);
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');
            document.getElementById('heatmapView').classList.remove('btn-primary');
            document.getElementById('heatmapView').classList.add('btn-outline-primary');
        });
        
        // Add legend
        var legend = L.control({position: 'bottomright'});
        
        legend.onAdd = function (map) {
            var div = L.DomUtil.create('div', 'legend');
            div.innerHTML = '<h6>Case Density</h6>';
            
            // Add legend items
            var grades = [1, 5, 10, 20, 50];
            var colors = ['blue', 'lime', 'yellow', 'orange', 'red'];
            
            for (var i = 0; i < grades.length; i++) {
                div.innerHTML +=
                    '<div class="legend-item">' +
                    '<div class="legend-color" style="background:' + colors[i] + '"></div>' +
                    (grades[i + 1] ? grades[i] + '&ndash;' + grades[i + 1] + ' cases' : grades[i] + '+ cases') +
                    '</div>';
            }
            
            return div;
        };
        
        legend.addTo(map);
        
        // Print map function
        function printMap() {
            window.print();
        }
    </script>
</body>
</html>
