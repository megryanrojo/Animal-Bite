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
$barangayFilter = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$biteCategory = isset($_GET['bite_category']) ? $_GET['bite_category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

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
if (!empty($barangayFilter)) {
    $where .= " AND p.barangay = ?";
    $params[] = $barangayFilter;
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

    // Prepare structured data for barangay counts and risk levels
    $barangayCounts = [];
    foreach ($heatmapData as $data) {
        $barangayCounts[] = [
            'barangay' => htmlspecialchars($data['barangay'], ENT_QUOTES, 'UTF-8'),
            'count' => (int)$data['case_count']
        ];
    }
    // Sort by case count descending
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

// Ensure each case has validated coordinates and consistent data types
foreach ($cases as &$case) {
    // Sanitize text fields
    $case['barangay'] = $case['barangay'] ? htmlspecialchars($case['barangay'], ENT_QUOTES, 'UTF-8') : 'Unknown';
    $case['animalType'] = $case['animalType'] ? htmlspecialchars($case['animalType'], ENT_QUOTES, 'UTF-8') : null;
    $case['biteType'] = $case['biteType'] ? htmlspecialchars($case['biteType'], ENT_QUOTES, 'UTF-8') : null;
    $case['status'] = $case['status'] ? htmlspecialchars($case['status'], ENT_QUOTES, 'UTF-8') : null;
    $case['patientName'] = $case['patientName'] ? htmlspecialchars($case['patientName'], ENT_QUOTES, 'UTF-8') : 'Unknown';

    // Validate and set coordinates with fallback to barangay centroid
    $latEmpty = empty($case['latitude']) || $case['latitude'] == 0;
    $lngEmpty = empty($case['longitude']) || $case['longitude'] == 0;

    if (($latEmpty || $lngEmpty) && !empty($case['barangay']) && isset($barangayCoordinates[$case['barangay']])) {
        $case['latitude'] = (float)$barangayCoordinates[$case['barangay']]['lat'];
        $case['longitude'] = (float)$barangayCoordinates[$case['barangay']]['lng'];
        $case['coordinates_fallback'] = true; // Flag to indicate fallback was used
    } else {
        $case['latitude'] = $case['latitude'] ? (float)$case['latitude'] : null;
        $case['longitude'] = $case['longitude'] ? (float)$case['longitude'] : null;
        $case['coordinates_fallback'] = false;
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

// Function to calculate standard deviation
function calculateStandardDeviation($numbers) {
    if (empty($numbers)) return 0;
    $mean = array_sum($numbers) / count($numbers);
    $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $numbers)) / count($numbers);
    return sqrt($variance);
}

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

// Calculate dynamic thresholds for legend based on statistical analysis
        $lowThreshold = max(1, (int)ceil($stats['p25']));
        $highThreshold = max($lowThreshold + 1, (int)ceil($stats['p95']));

// ==========================================
// DECISION SUPPORT SYSTEM (DSS) FEATURES
// ==========================================

/**
 * Calculate comprehensive risk scores for each barangay
 * @param array $barangayData Barangay case data
 * @param array $stats Statistical data
 * @return array Risk assessment data
 */
function calculateBarangayRiskScores($barangayData, $stats) {
    $riskScores = [];

    foreach ($barangayData as $barangay => $cases) {
        $caseCount = count($cases);
        if ($caseCount === 0) continue;

        // Factor 1: Case density (normalized by statistical distribution)
        $densityScore = 0;
        if ($stats['std_dev'] > 0) {
            $zScore = ($caseCount - $stats['mean']) / $stats['std_dev'];
            $densityScore = max(0, min(100, 50 + ($zScore * 10))); // Convert to 0-100 scale
        }

        // Factor 2: Severity index (Category III = 3, II = 2, I = 1)
        $severityScore = 0;
        $severeCases = 0;
        foreach ($cases as $case) {
            $severity = 1;
            if (strpos($case['biteType'], 'III') !== false) $severity = 3;
            elseif (strpos($case['biteType'], 'II') !== false) $severity = 2;
            $severityScore += $severity;
            if ($severity >= 2) $severeCases++;
        }
        $avgSeverity = $severityScore / $caseCount;
        $severityPercentile = min(100, ($avgSeverity - 1) * 33.33); // 1->0, 2->33.33, 3->66.66+

        // Factor 3: Recency score (cases in last 30 days get higher weight)
        $now = time();
        $recentScore = 0;
        $monthAgo = $now - (30 * 24 * 60 * 60);
        foreach ($cases as $case) {
            $caseTime = strtotime($case['biteDate']);
            if ($caseTime >= $monthAgo) {
                $daysOld = ($now - $caseTime) / (24 * 60 * 60);
                $recencyWeight = max(0.1, 1 - ($daysOld / 30)); // Exponential decay
                $recentScore += $recencyWeight;
            }
        }
        $recencyPercentile = min(100, ($recentScore / $caseCount) * 100);

        // Factor 4: Animal type risk (stray vs owned)
        $animalRiskScore = 0;
        $animalTypes = [];
        foreach ($cases as $case) {
            $animalType = $case['animalType'] ?: 'Unknown';
            if (!isset($animalTypes[$animalType])) {
                $animalTypes[$animalType] = ['total' => 0, 'stray' => 0];
            }
            $animalTypes[$animalType]['total']++;
            if (isset($case['status']) && stripos($case['status'], 'stray') !== false) {
                $animalTypes[$animalType]['stray']++;
            }
        }

        foreach ($animalTypes as $type => $data) {
            $strayRatio = $data['total'] > 0 ? $data['stray'] / $data['total'] : 0;
            $animalRiskScore += $strayRatio * $data['total'];
        }
        $animalPercentile = min(100, ($animalRiskScore / $caseCount) * 100);

        // Composite risk score (weighted average)
        $compositeScore = (
            $densityScore * 0.4 +      // Population density factor
            $severityPercentile * 0.3 + // Severity factor
            $recencyPercentile * 0.2 +  // Time factor
            $animalPercentile * 0.1     // Animal factor
        );

        // Determine risk level and color coding
        $riskLevel = 'low';
        $riskColor = '#22c55e'; // Green
        $riskLabel = 'Low Risk';
        $priority = 'Monitor';

        if ($compositeScore >= 75) {
            $riskLevel = 'critical';
            $riskColor = '#dc2626'; // Red
            $riskLabel = 'Critical Risk';
            $priority = 'Immediate Action';
        } elseif ($compositeScore >= 60) {
            $riskLevel = 'high';
            $riskColor = '#ea580c'; // Orange
            $riskLabel = 'High Risk';
            $priority = 'High Priority';
        } elseif ($compositeScore >= 40) {
            $riskLevel = 'medium';
            $riskColor = '#f59e0b'; // Yellow
            $riskLabel = 'Medium Risk';
            $priority = 'Moderate Attention';
        }

        $riskScores[$barangay] = [
            'barangay' => $barangay,
            'caseCount' => $caseCount,
            'compositeScore' => round($compositeScore, 1),
            'densityScore' => round($densityScore, 1),
            'severityScore' => round($severityPercentile, 1),
            'recencyScore' => round($recencyPercentile, 1),
            'animalRiskScore' => round($animalPercentile, 1),
            'severeCases' => $severeCases,
            'riskLevel' => $riskLevel,
            'riskColor' => $riskColor,
            'riskLabel' => $riskLabel,
            'priority' => $priority,
            'recommendations' => generateRiskRecommendations($riskLevel, $caseCount, $severeCases)
        ];
    }

    return $riskScores;
}

/**
 * Generate risk-based recommendations
 */
function generateRiskRecommendations($riskLevel, $caseCount, $severeCases) {
    $recommendations = [];

    switch ($riskLevel) {
        case 'critical':
            $recommendations[] = 'Immediate veterinary intervention required';
            $recommendations[] = 'Increase animal control patrols';
            $recommendations[] = 'Public health alert for residents';
            if ($severeCases > 0) {
                $recommendations[] = 'Priority vaccination program';
            }
            break;
        case 'high':
            $recommendations[] = 'Enhanced animal control measures';
            $recommendations[] = 'Community education campaigns';
            $recommendations[] = 'Regular veterinary monitoring';
            break;
        case 'medium':
            $recommendations[] = 'Monitor animal populations';
            $recommendations[] = 'Public awareness programs';
            $recommendations[] = 'Regular reporting and assessment';
            break;
        default:
            $recommendations[] = 'Maintain standard monitoring';
            $recommendations[] = 'Continue public education';
            break;
    }

    if ($caseCount > 10) {
        $recommendations[] = 'Consider establishing animal control station';
    }

    return $recommendations;
}

// Build barangay case data for risk calculation
$barangayCaseData = [];
foreach ($cases as $case) {
    $barangay = $case['barangay'] ?: 'Unknown';
    if (!isset($barangayCaseData[$barangay])) {
        $barangayCaseData[$barangay] = [];
    }
    $barangayCaseData[$barangay][] = $case;
}

// Calculate comprehensive risk scores for all barangays
$barangayRiskScores = calculateBarangayRiskScores($barangayCaseData, $stats);

// ==========================================
// STRUCTURED DATA OUTPUT FOR JAVASCRIPT
// ==========================================

/**
 * Prepare and validate all data for JavaScript consumption
 * Ensures consistent data types and proper JSON encoding
 */
$jsData = [
    'cases' => $cases,
    'heatmapData' => $jsHeatmapData,
    'barangayCoordinates' => $barangayCoordinates,
    'maxCount' => (int)$maxCount,
    'stats' => array_map(function($value) {
        return is_numeric($value) ? (float)$value : $value;
    }, $stats),
    'barangayCounts' => $barangayCounts,
    'recentCases' => $recentCases,
    'barangayRiskScores' => $barangayRiskScores,
    'config' => [
        'centerLat' => (float)$centerLat,
        'centerLng' => (float)$centerLng,
        'mapBounds' => $mapBounds,
        'lowThreshold' => (int)$lowThreshold,
        'highThreshold' => (int)$highThreshold,
        'totalCases' => (int)$totalCases,
        'dateRangeLabel' => $dateRangeLabel
    ]
];

// Validate JSON encoding will work
foreach ($jsData as $key => $value) {
    if (json_encode($value) === false) {
        error_log("JSON encoding failed for {$key}: " . json_last_error_msg());
        $jsData[$key] = []; // Fallback to empty array
    }
}
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
        }
        
        .filters-bar .page-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: auto;
            white-space: nowrap;
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
            flex: 1;
            justify-content: flex-end;
        }
        
        .filter-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            min-width: 130px;
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
                flex-wrap: wrap;
            }
            .page-title {
                flex-basis: 100%;
                font-size: 1rem;
            }
            .filter-group {
                flex-basis: 100%;
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            .filter-item {
                flex: 1 1 auto;
                min-width: 120px;
            }
            .filter-item label { 
                display: none; 
            }
            .filter-item input,
            .filter-item select {
                min-width: 100px;
                padding: 0.35rem 0.5rem;
                font-size: 0.8rem;
                width: 100%;
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
            <form method="GET" action="geomapping.php" class="filters-bar" onsubmit="event.preventDefault(); applyFilters(); return false;">
                <div class="page-title">
                    <i class="bi bi-geo-alt-fill"></i>
                    Geomapping
                    <span class="case-count-badge"><?= number_format(count($cases)) ?> cases</span>
                </div>
                
                <div class="filter-group">
                    <!-- Date Range Filters -->
                    <div class="filter-item">
                        <label>From</label>
                        <input type="date" name="date_from" id="dateFromFilter" value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyFilters()">
                    </div>
                    <div class="filter-item">
                        <label>To</label>
                        <input type="date" name="date_to" id="dateToFilter" value="<?= htmlspecialchars($dateTo) ?>" onchange="applyFilters()">
                    </div>

                    <!-- Barangay Filter -->
                    <div class="filter-item">
                        <label>Barangay</label>
                        <select name="barangay" id="barangayFilter" onchange="applyFilters()">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $brgy): ?>
                                <option value="<?= htmlspecialchars($brgy) ?>" <?= $barangayFilter === $brgy ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brgy) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Animal Type Filter -->
                    <div class="filter-item">
                        <label>Animal</label>
                        <select name="animal_type" id="animalTypeFilter" onchange="applyFilters()">
                            <option value="">All Animals</option>
                            <?php foreach ($animalTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $animalType === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div class="filter-item">
                        <label>Category</label>
                        <select name="bite_category" id="biteCategoryFilter" onchange="applyFilters()">
                            <option value="">All Categories</option>
                            <option value="Category I" <?= $biteCategory === 'Category I' ? 'selected' : '' ?>>Category I</option>
                            <option value="Category II" <?= $biteCategory === 'Category II' ? 'selected' : '' ?>>Category II</option>
                            <option value="Category III" <?= $biteCategory === 'Category III' ? 'selected' : '' ?>>Category III</option>
                        </select>
                    </div>

                    <!-- Clear Button (only show when filters are active) -->
                    <?php if (!empty($dateFrom) || !empty($dateTo) || !empty($barangayFilter) || !empty($animalType) || !empty($biteCategory)): ?>
                    <button type="button" class="filter-btn filter-btn-secondary" onclick="clearAllFilters()" title="Clear all filters">
                        <i class="bi bi-x-lg"></i> Clear
                    </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Main content area -->
            <div class="content-area">
                <!-- Map container -->
                <div class="map-container">
                    <div id="map"></div>
                    
                    <!-- Map controls -->
                    <div class="map-controls">
                        <button type="button" class="map-control-btn active" id="heatmapBtn" onclick="showHeatmap()" data-bs-toggle="tooltip" data-bs-placement="right" title="Display bite incidents as a heat map showing intensity by location">
                            <i class="bi bi-fire"></i> Heatmap
                        </button>
                        <button type="button" class="map-control-btn" id="markersBtn" onclick="showMarkers()" data-bs-toggle="tooltip" data-bs-placement="right" title="Display bite incidents as individual markers on the map">
                            <i class="bi bi-geo-alt"></i> Markers
                        </button>
                        <button type="button" class="map-control-btn" id="settingsBtn" onclick="toggleHeatmapSettings()" data-bs-toggle="tooltip" data-bs-placement="right" title="Customize heatmap appearance and intensity settings">
                            <i class="bi bi-sliders"></i> Settings
                        </button>
                        <button type="button" class="map-control-btn" id="timeFilterBtn" onclick="toggleTimeFilter()" data-bs-toggle="tooltip" data-bs-placement="right" title="Filter incidents by time period">
                            <i class="bi bi-clock"></i> Time
                        </button>
                        <button type="button" class="map-control-btn" id="exportBtn" onclick="exportHeatmapData()" data-bs-toggle="tooltip" data-bs-placement="right" title="Export heatmap data as CSV file">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>

                    <!-- Heatmap Settings Panel -->
                    <div class="heatmap-settings" id="heatmapSettings" style="display: none;">
                        <div class="settings-info">
                            <small style="color: var(--gray-600); font-size: 0.75rem;">
                                <i class="bi bi-info-circle"></i> Heatmap uses spatial clustering and zoom-independent scaling for accurate risk representation
                            </small>
                        </div>
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
                                    <option value="risk-weighted" selected>Risk-weighted (Recommended)</option>
                                    <option value="linear">Linear</option>
                                    <option value="percentile">Percentile-based</option>
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
                                <button class="time-filter-btn active" data-period="all" onclick="setTimeFilter('all')" data-bs-toggle="tooltip" data-bs-placement="top" title="Show all bite incidents from the beginning">All Time</button>
                                <button class="time-filter-btn" data-period="30" onclick="setTimeFilter('30')" data-bs-toggle="tooltip" data-bs-placement="top" title="Show incidents from the last 30 days">Last 30 Days</button>
                                <button class="time-filter-btn" data-period="90" onclick="setTimeFilter('90')" data-bs-toggle="tooltip" data-bs-placement="top" title="Show incidents from the last 3 months">Last 3 Months</button>
                                <button class="time-filter-btn" data-period="180" onclick="setTimeFilter('180')" data-bs-toggle="tooltip" data-bs-placement="top" title="Show incidents from the last 6 months">Last 6 Months</button>
                                <button class="time-filter-btn" data-period="365" onclick="setTimeFilter('365')" data-bs-toggle="tooltip" data-bs-placement="top" title="Show incidents from the last year">Last Year</button>
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
                                    <span id="legendLowLabel">Low (1–<?= $lowThreshold ?>)</span>
                                    <span id="legendHighLabel">High (<?= $highThreshold ?>+)</span>
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

                        <!-- Risk Analysis -->
                        <div class="data-section">
                            <div class="data-header" onclick="toggleSection(this)">
                                <h6><i class="bi bi-exclamation-triangle-fill"></i> Risk Analysis</h6>
                                <i class="bi bi-chevron-down toggle-icon"></i>
                            </div>
                            <div class="data-body" id="riskBody" style="max-height: 300px;">
                                <div class="risk-summary">
                                    <?php
                                    $criticalCount = 0;
                                    $highCount = 0;
                                    $mediumCount = 0;
                                    $lowCount = 0;

                                    foreach ($barangayRiskScores as $risk) {
                                        switch ($risk['riskLevel']) {
                                            case 'critical': $criticalCount++; break;
                                            case 'high': $highCount++; break;
                                            case 'medium': $mediumCount++; break;
                                            default: $lowCount++; break;
                                        }
                                    }
                                    ?>
                                    <div class="risk-overview">
                                        <div class="risk-stat"><span class="badge badge-critical"><?= $criticalCount ?></span> Critical</div>
                                        <div class="risk-stat"><span class="badge badge-high"><?= $highCount ?></span> High</div>
                                        <div class="risk-stat"><span class="badge badge-medium"><?= $mediumCount ?></span> Medium</div>
                                        <div class="risk-stat"><span class="badge badge-low"><?= $lowCount ?></span> Low</div>
                                    </div>
                                </div>
                                <table class="data-table risk-table">
                                    <thead>
                                        <tr>
                                            <th>Barangay</th>
                                            <th>Risk Score</th>
                                            <th>Priority</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Sort by risk score descending
                                        uasort($barangayRiskScores, function($a, $b) {
                                            return $b['compositeScore'] <=> $a['compositeScore'];
                                        });

                                        $count = 0;
                                        foreach ($barangayRiskScores as $barangay => $risk):
                                            if ($count >= 10) break; // Show top 10
                                            $count++;
                                        ?>
                                        <tr onclick="focusLocation('<?= htmlspecialchars($barangay) ?>')">
                                            <td><?= htmlspecialchars($barangay) ?></td>
                                            <td><strong style="color: <?= $risk['riskColor'] ?>;"><?= $risk['compositeScore'] ?></strong></td>
                                            <td><span class="badge badge-<?= $risk['riskLevel'] ?>"><?= $risk['priority'] ?></span></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); showRiskDetails('<?= htmlspecialchars($barangay) ?>')">Details</button></td>
                                        </tr>
                                        <?php endforeach; ?>
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
        // ==========================================
        // DATA INITIALIZATION AND PROCESSING
        // ==========================================

        /**
         * Structured data from PHP backend
         */
        const jsData = <?= json_encode($jsData) ?>;
        const cases = jsData.cases || [];
        const heatmapData = jsData.heatmapData || [];
        const barangayCoords = jsData.barangayCoordinates || {};
        const maxCount = jsData.maxCount || 1;
        const stats = jsData.stats || {};
        const originalBarangayCounts = jsData.barangayCounts || [];
        const originalRecentCases = jsData.recentCases || [];
        const barangayRiskScores = jsData.barangayRiskScores || {};
        const config = jsData.config || {};

        // ==========================================
        // MAP INITIALIZATION
        // ==========================================

        // Initialize map using structured config
        const centerLat = config.centerLat || 10.735;
        const centerLng = config.centerLng || 122.966;
        const talisayBounds = L.latLngBounds(
            [config.mapBounds?.southWest?.lat || 10.60, config.mapBounds?.southWest?.lng || 122.85],
            [config.mapBounds?.northEast?.lat || 10.90, config.mapBounds?.northEast?.lng || 123.08]
        );
        const map = L.map('map', {
            maxBounds: talisayBounds,
            maxBoundsViscosity: 1.0,
            minZoom: 11,
            maxZoom: 19,
            zoomControl: true,
            scrollWheelZoom: true,
            doubleClickZoom: true,
            boxZoom: true,
            keyboard: true,
            dragging: true,
            touchZoom: true
        }).setView([centerLat, centerLng], 13);

        // Enhanced zoom behavior - fit bounds initially but allow user control
        map.fitBounds(talisayBounds);

        // Add scale control
        L.control.scale({
            position: 'bottomleft',
            metric: true,
            imperial: false
        }).addTo(map);

        const heatmapBtnEl = document.getElementById('heatmapBtn');
        const markersBtnEl = document.getElementById('markersBtn');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        /**
         * Build barangay case data for quick lookup and risk calculations
         */
        const barangayCaseData = {};
        cases.forEach(c => {
            const barangay = c.barangay || 'Unknown';
            if (!barangayCaseData[barangay]) {
                barangayCaseData[barangay] = [];
            }
            barangayCaseData[barangay].push(c);
        });
        
        let focusedLocation = null;

        // ==========================================
        // MAP LAYERS AND VISUALIZATION
        // ==========================================

        /**
         * Map layers for different visualization modes
         */
        let heatLayer = null;
        let markersLayer = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
            removeOutsideVisibleBounds: true,
            animate: true,
            iconCreateFunction: function(cluster) {
                const childCount = cluster.getChildCount();
                let className = 'marker-cluster-';

                if (childCount < 10) {
                    className += 'small';
                } else if (childCount < 100) {
                    className += 'medium';
                } else {
                    className += 'large';
                }

                return new L.DivIcon({
                    html: '<div><span>' + childCount + '</span></div>',
                    className: 'marker-cluster ' + className,
                    iconSize: new L.Point(40, 40)
                });
            }
        });
        const markerPoints = [];

        // ==========================================
        // CONFIGURATION OBJECTS
        // ==========================================

        /**
         * Heatmap visualization configuration
         */
        let heatmapConfig = {
            radius: 25,        // Fixed radius representing ~500m influence area
            blur: 20,          // Fixed blur for consistent appearance
            max: 1.0,
            maxZoom: 18,
            minOpacity: 0.4,  // Slightly increased for better visibility
            intensityMode: 'risk-weighted' // 'percentile', 'linear', 'logarithmic', 'risk-weighted'
        };

        /**
         * Time-based filtering configuration
         */
        let currentTimeFilter = {
            type: 'all', // 'all', 'recent', 'month', 'quarter', 'year', 'custom'
            period: null, // for predefined periods
            days: null, // for custom day ranges
            startDate: null,
            endDate: null
        };

        /**
         * Multivariate filtering configuration
         */
        let currentMultivariateFilters = {
            animalType: '',
            biteCategory: '',
            status: ''
        };

        /**
         * Dynamic legend thresholds based on statistical analysis
         */
        let currentLegendThresholds = {
            low: config.lowThreshold || 1,
            high: config.highThreshold || 7
        };

        /**
         * Calculate heatmap intensity with enhanced statistical analysis and risk scoring
         * @param {number} count - Number of cases in the area
         * @param {string} barangay - Barangay name for risk calculation
         * @param {number} localMaxCount - Local maximum count for scaling (optional)
         * @returns {number} Intensity value between 0.0 and 1.0
         */
        function calculateHeatmapIntensity(count, barangay = null, localMaxCount = null) {
            if (count === 0) return 0.0;

            // Use local max count if provided (for filtered data), otherwise use global max
            const maxCountForScaling = localMaxCount || maxCount;
            const { p25, p50, p75, p95, mean, std_dev } = stats;

            let baseIntensity = 0;

            switch(heatmapConfig.intensityMode) {
                case 'percentile':
                    // Enhanced percentile-based scaling with better distribution
                    if (count <= p25) {
                        baseIntensity = Math.max(0.05, (count / Math.max(p25, 1)) * 0.3);
                    } else if (count <= p50) {
                        baseIntensity = 0.3 + ((count - p25) / Math.max(p50 - p25, 1)) * 0.2;
                    } else if (count <= p75) {
                        baseIntensity = 0.5 + ((count - p50) / Math.max(p75 - p50, 1)) * 0.2;
                    } else if (count <= p95) {
                        baseIntensity = 0.7 + ((count - p75) / Math.max(p95 - p75, 1)) * 0.2;
                    } else {
                        baseIntensity = 0.9 + Math.min(0.1, ((count - p95) / Math.max(p95, 1)) * 0.1);
                    }
                    break;

                case 'logarithmic':
                    // Improved logarithmic scaling with better minimum visibility
                    baseIntensity = Math.min(1.0, Math.max(0.08, Math.log(count + 1) / Math.log(maxCountForScaling + 1)));
                    break;

                case 'risk-weighted':
                    // New mode: Risk-weighted scaling combining count and statistical outliers
                    const zScore = std_dev > 0 ? (count - mean) / std_dev : 0;
                    const percentile = count <= p25 ? 0.2 :
                                     count <= p50 ? 0.4 :
                                     count <= p75 ? 0.6 :
                                     count <= p95 ? 0.8 : 0.95;

                    // Combine percentile ranking with z-score for outlier detection
                    baseIntensity = Math.min(1.0, Math.max(0.05,
                        (percentile * 0.7) + (Math.max(0, zScore) * 0.1) + (count / maxCountForScaling * 0.2)
                    ));
                    break;

                case 'linear':
                default:
                    // Enhanced linear scaling with better granularity
                    const ratio = count / maxCountForScaling;

                    // Multi-tier scaling for better visual differentiation
                    if (ratio >= 0.8) {
                        baseIntensity = 0.8 + ((ratio - 0.8) / 0.2) * 0.2; // 0.8-1.0
                    } else if (ratio >= 0.6) {
                        baseIntensity = 0.6 + ((ratio - 0.6) / 0.2) * 0.2; // 0.6-0.8
                    } else if (ratio >= 0.4) {
                        baseIntensity = 0.4 + ((ratio - 0.4) / 0.2) * 0.2; // 0.4-0.6
                    } else if (ratio >= 0.2) {
                        baseIntensity = 0.2 + ((ratio - 0.2) / 0.2) * 0.2; // 0.2-0.4
                    } else if (ratio >= 0.1) {
                        baseIntensity = 0.1 + ((ratio - 0.1) / 0.1) * 0.1; // 0.1-0.2
                    } else {
                        baseIntensity = Math.max(0.05, ratio * 2); // 0.05-0.1
                    }
                    break;
            }

            // Apply risk multiplier with enhanced scaling
            let finalIntensity = baseIntensity;
            if (barangay && barangayCaseData[barangay]) {
                const riskMultiplier = calculateRiskMultiplier(barangay);

                // Enhanced risk-based intensity scaling
                if (riskMultiplier >= 1.5) {
                    // Critical risk areas - significant boost
                    finalIntensity = Math.min(1.0, baseIntensity * riskMultiplier * 0.7);
                } else if (riskMultiplier >= 1.2) {
                    // High risk areas - moderate boost
                    finalIntensity = Math.min(1.0, baseIntensity * (1 + (riskMultiplier - 1) * 0.6));
                } else if (riskMultiplier >= 0.9) {
                    // Medium risk areas - light boost
                    finalIntensity = Math.min(1.0, baseIntensity * (1 + (riskMultiplier - 1) * 0.3));
                } else {
                    // Low risk areas - minimal boost
                    finalIntensity = Math.max(0.05, baseIntensity * riskMultiplier);
                }
            }

            // Ensure minimum visibility for areas with cases
            return Math.max(0.05, Math.min(1.0, finalIntensity));

            return intensity;
        }

        // Basic risk scoring algorithm
        // ==========================================
        // CORE CALCULATION FUNCTIONS
        // ==========================================

        /**
         * Calculate risk multiplier for a barangay based on multiple factors
         * @param {string} barangay - Barangay name
         * @returns {number} Risk multiplier between 0.8 and 2.0
         */
        function calculateRiskMultiplier(barangay) {
            const barangayCases = barangayCaseData[barangay] || [];
            if (barangayCases.length === 0) return 1.0;

            const now = new Date();
            const totalCases = barangayCases.length;

            // Factor 1: Time-based decay (exponential decay over time)
            let timeWeightedScore = 0;
            const halfLifeDays = 90; // Risk halves every 90 days
            barangayCases.forEach(c => {
                const caseDate = new Date(c.biteDate);
                const daysDiff = (now - caseDate) / (1000 * 60 * 60 * 24);
                const decayFactor = Math.pow(0.5, daysDiff / halfLifeDays);
                timeWeightedScore += decayFactor;
            });
            const recencyScore = Math.min(timeWeightedScore / totalCases, 2.0);

            // Factor 2: Severity weighting (Category III > II > I)
            let severityWeightedScore = 0;
            barangayCases.forEach(c => {
                let severityWeight = 1.0;
                if (c.biteType === 'Category II') severityWeight = 1.5;
                else if (c.biteType === 'Category III') severityWeight = 2.0;
                severityWeightedScore += severityWeight;
            });
            const severityScore = severityWeightedScore / totalCases;

            // Factor 3: Animal type risk (stray vs owned, diversity)
            const animalStats = {};
            barangayCases.forEach(c => {
                const animalType = c.animalType || 'Unknown';
                if (!animalStats[animalType]) {
                    animalStats[animalType] = { count: 0, stray: 0, owned: 0 };
                }
                animalStats[animalType].count++;

                // Assume status indicates stray vs owned (this could be enhanced with actual data)
                if (c.status && c.status.toLowerCase().includes('stray')) {
                    animalStats[animalType].stray++;
                } else {
                    animalStats[animalType].owned++;
                }
            });

            // Calculate animal risk (stray animals are higher risk, diversity increases risk)
            let animalRiskScore = 0;
            const animalTypes = Object.keys(animalStats);
            animalTypes.forEach(type => {
                const stats = animalStats[type];
                const strayRatio = stats.stray / stats.count;
                const typeRisk = 1.0 + (strayRatio * 0.5); // Stray animals increase risk
                animalRiskScore += typeRisk * stats.count;
            });
            animalRiskScore = animalRiskScore / totalCases;
            const diversityBonus = Math.min(animalTypes.length * 0.1, 0.5); // Diversity bonus

            // Factor 4: Case density (population-adjusted risk if available)
            const densityScore = 1.0; // Placeholder - could be enhanced with population data

            // Calculate composite risk score with weighted factors
            const compositeRisk = (
                recencyScore * 0.4 +      // Time decay (most important)
                severityScore * 0.3 +     // Severity
                animalRiskScore * 0.2 +   // Animal factors
                diversityBonus * 0.1      // Diversity bonus
            ) * densityScore;

            // Return multiplier (0.8 to 2.0 range for better differentiation)
            return Math.max(0.8, Math.min(2.0, 1.0 + (compositeRisk - 1.0) * 0.5));
        }

        // ==========================================
        // SPATIAL ANALYSIS FUNCTIONS
        // ==========================================

        /**
         * Cluster nearby cases to prevent heatmap overcrowding and false intensity buildup
         * @param {Array} cases - Array of case objects with latitude/longitude
         * @param {number} maxDistance - Maximum distance in degrees for clustering (~0.001 = ~100m)
         * @returns {Array} Array of case clusters
         */
        function clusterNearbyCases(cases, maxDistance = 0.001) {
            const clusters = [];
            const used = new Set();

            cases.forEach((caseItem, index) => {
                if (used.has(index)) return;

                const cluster = [caseItem];
                used.add(index);

                // Find all nearby cases within maxDistance
                cases.forEach((otherCase, otherIndex) => {
                    if (used.has(otherIndex) || index === otherIndex) return;

                    const distance = calculateDistance(
                        parseFloat(caseItem.latitude), parseFloat(caseItem.longitude),
                        parseFloat(otherCase.latitude), parseFloat(otherCase.longitude)
                    );

                    if (distance <= maxDistance) {
                        cluster.push(otherCase);
                        used.add(otherIndex);
                    }
                });

                clusters.push(cluster);
            });

            return clusters;
        }

        /**
         * Calculate distance between two geographic points using Haversine formula
         * @param {number} lat1 - Latitude of first point
         * @param {number} lng1 - Longitude of first point
         * @param {number} lat2 - Latitude of second point
         * @param {number} lng2 - Longitude of second point
         * @returns {number} Distance in degrees (approximately)
         */
        function calculateDistance(lat1, lng1, lat2, lng2) {
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                     Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                     Math.sin(dLng/2) * Math.sin(dLng/2);
            return 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        }

        // ==========================================
        // DATA FILTERING FUNCTIONS
        // ==========================================

        /**
         * Filter cases by current time and multivariate filters
         * @returns {Array} Filtered array of cases
         */
        function getFilteredCases() {
            let filteredCases = cases;

            // Apply enhanced time filter
            if (currentTimeFilter.type !== 'all') {
                const now = new Date();
                let startDate = null;
                let endDate = now;

                switch (currentTimeFilter.type) {
                    case 'recent':
                        // Last 7 days
                        startDate = new Date(now.getTime() - (7 * 24 * 60 * 60 * 1000));
                        break;
                    case 'month':
                        // Current month
                        startDate = new Date(now.getFullYear(), now.getMonth(), 1);
                        break;
                    case 'quarter':
                        // Current quarter
                        const quarterStartMonth = Math.floor(now.getMonth() / 3) * 3;
                        startDate = new Date(now.getFullYear(), quarterStartMonth, 1);
                        break;
                    case 'year':
                        // Current year
                        startDate = new Date(now.getFullYear(), 0, 1);
                        break;
                    case 'days':
                        if (currentTimeFilter.days) {
                            startDate = new Date(now.getTime() - (currentTimeFilter.days * 24 * 60 * 60 * 1000));
                        }
                        break;
                    case 'custom':
                        if (currentTimeFilter.startDate) {
                            startDate = new Date(currentTimeFilter.startDate);
                        }
                        if (currentTimeFilter.endDate) {
                            endDate = new Date(currentTimeFilter.endDate);
                        }
                        break;
                }

                if (startDate) {
                    filteredCases = filteredCases.filter(c => {
                        const caseDate = new Date(c.biteDate);
                        return caseDate >= startDate && caseDate <= endDate;
                    });
                }
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
                multivariateFilters: currentMultivariateFilters,
                intensityMode: heatmapConfig.intensityMode
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

                // Calculate local maximum for filtered data
                const filteredMaxCount = Math.max(...Object.values(filteredBarangayCounts), 1);

                // Generate heat points with spatial distribution to prevent artificial overlap
                // This approach uses actual case locations when available, clustering nearby cases
                // to provide more accurate risk visualization without zoom-dependent artifacts
                heatPoints = [];

                filteredHeatmapData.forEach(point => {
                    if (!point.lat || !point.lng) return;

                    const barangay = point.barangay;
                    const caseCount = point.count;
                    const intensity = calculateHeatmapIntensity(caseCount, barangay, filteredMaxCount);

                    // Get actual case locations for this barangay if available
                    const barangayCases = filteredCases.filter(c => c.barangay === barangay && c.latitude && c.longitude);

                    if (barangayCases.length > 0) {
                        // Distribute heat based on actual case locations
                        // Group nearby cases to prevent overcrowding
                        const caseClusters = clusterNearbyCases(barangayCases, 0.001); // ~100m clustering

                        caseClusters.forEach(cluster => {
                            const avgLat = cluster.reduce((sum, c) => sum + parseFloat(c.latitude), 0) / cluster.length;
                            const avgLng = cluster.reduce((sum, c) => sum + parseFloat(c.longitude), 0) / cluster.length;
                            const clusterIntensity = intensity * Math.min(cluster.length / caseCount, 1); // Proportional intensity
                            heatPoints.push([avgLat, avgLng, clusterIntensity]);
                        });
                    } else {
                        // Fallback to barangay centroid with reduced intensity to minimize overlap
                        const lat = parseFloat(point.lat);
                        const lng = parseFloat(point.lng);
                        // Reduce intensity for centroid-only data to prevent false high-risk appearance
                        const centroidIntensity = intensity * 0.7;
                        heatPoints.push([lat, lng, centroidIntensity]);
                    }
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

        // ==========================================
        // HEATMAP VISUALIZATION FUNCTIONS
        // ==========================================

        /**
         * Create heatmap layer directly (for small datasets)
         * @param {Array} heatPoints - Array of [lat, lng, intensity] points
         */
        function createHeatmapDirectly(heatPoints) {
            // Fixed radius and blur for consistent risk visualization across all zoom levels
            // Radius represents approximate 500m influence area (scaled for map pixels)
            const adjustedRadius = heatmapConfig.radius || 25;
            const adjustedBlur = heatmapConfig.blur || 20;

            heatLayer = L.heatLayer(heatPoints, {
                radius: adjustedRadius,
                blur: adjustedBlur,
                max: heatmapConfig.max,
                maxZoom: heatmapConfig.maxZoom,
                minOpacity: heatmapConfig.minOpacity,
                gradient: {
                    0.0: '#22c55e',   // Low - Green
                    0.1: '#16a34a',   // Dark Green
                    0.2: '#65a30d',   // Green
                    0.3: '#84cc16',   // Light Green
                    0.4: '#eab308',   // Yellow
                    0.5: '#f59e0b',   // Orange-Yellow
                    0.6: '#f97316',   // Orange
                    0.7: '#ea580c',   // Dark Orange
                    0.8: '#ef4444',   // Light Red
                    0.9: '#dc2626',   // Red
                    1.0: '#b91c1c'    // Dark Red
                }
            });

            if (heatmapBtnEl.classList.contains('active')) {
                heatLayer.addTo(map);
            }
        }

        function createHeatmapWithWorker(heatPoints) {
            // Fixed radius and blur for consistent risk visualization across all zoom levels
            // Radius represents approximate 500m influence area (scaled for map pixels)
            const adjustedRadius = heatmapConfig.radius || 25;
            const adjustedBlur = heatmapConfig.blur || 20;

            // For very large datasets, process in chunks to prevent UI blocking
            const chunkSize = 500;
            const chunks = [];
            for (let i = 0; i < heatPoints.length; i += chunkSize) {
                chunks.push(heatPoints.slice(i, i + chunkSize));
            }

            // Process first chunk immediately
            heatLayer = L.heatLayer(chunks[0], {
                radius: adjustedRadius,
                blur: adjustedBlur,
                max: heatmapConfig.max,
                maxZoom: heatmapConfig.maxZoom,
                minOpacity: heatmapConfig.minOpacity,
                gradient: {
                    0.0: '#22c55e',   // Low - Green
                    0.1: '#16a34a',   // Dark Green
                    0.2: '#65a30d',   // Green
                    0.3: '#84cc16',   // Light Green
                    0.4: '#eab308',   // Yellow
                    0.5: '#f59e0b',   // Orange-Yellow
                    0.6: '#f97316',   // Orange
                    0.7: '#ea580c',   // Dark Orange
                    0.8: '#ef4444',   // Light Red
                    0.9: '#dc2626',   // Red
                    1.0: '#b91c1c'    // Dark Red
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

        // Update heatmap when zoom level changes to maintain consistent visual density
        map.on('zoomend', function() {
            if (heatLayer && heatmapBtnEl.classList.contains('active')) {
                createHeatmap();
            }
        });

        // Initialize legend thresholds
        updateLegendThresholds();
        
        /**
         * Create enhanced markers with risk-based styling
         */
        function createMarkers() {
            // Clear existing markers
            markersLayer.clearLayers();
            markerPoints.length = 0;

        cases.forEach(c => {
            if (c.latitude && c.longitude) {
                const lat = parseFloat(c.latitude);
                const lng = parseFloat(c.longitude);

                    // Determine marker style based on bite category and risk
                    let markerStyle = {
                        radius: 6,
                    weight: 2,
                    opacity: 1,
                        fillOpacity: 0.8
                    };

                    // Color coding based on bite category (risk level)
                    switch (c.biteType) {
                        case 'Category III':
                            markerStyle.fillColor = '#dc2626'; // Red - highest risk
                            markerStyle.color = '#b91c1c';
                            markerStyle.radius = 8;
                            break;
                        case 'Category II':
                            markerStyle.fillColor = '#ea580c'; // Orange - high risk
                            markerStyle.color = '#c2410c';
                            markerStyle.radius = 7;
                            break;
                        case 'Category I':
                        default:
                            markerStyle.fillColor = '#f59e0b'; // Yellow - moderate risk
                            markerStyle.color = '#d97706';
                            break;
                    }

                    // Additional styling for stray animals
                    if (c.status && c.status.toLowerCase().includes('stray')) {
                        markerStyle.weight = 3;
                        markerStyle.opacity = 0.9;
                    }

                    const marker = L.circleMarker([lat, lng], markerStyle);

                markerPoints.push([lat, lng]);

                    // Enhanced popup with more information
                    const formatDate = (dateStr) => {
                        if (!dateStr) return 'N/A';
                        const date = new Date(dateStr);
                        return date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        });
                    };

                    const riskLevel = c.biteType === 'Category III' ? 'Critical' :
                                     c.biteType === 'Category II' ? 'High' : 'Moderate';

                marker.bindPopup(`
                        <div style="font-family: system-ui, sans-serif; font-size: 14px; max-width: 250px;">
                            <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px;">
                                <strong style="font-size: 16px; color: #1f2937;">${c.barangay || 'Unknown Location'}</strong>
                            </div>
                            <div style="display: grid; gap: 4px;">
                                <div><span style="color: #6b7280;">Patient:</span> ${c.patientName || 'N/A'}</div>
                                <div><span style="color: #6b7280;">Animal:</span> ${c.animalType || 'N/A'}</div>
                                <div><span style="color: #6b7280;">Category:</span> <strong>${c.biteType || 'N/A'}</strong></div>
                                <div><span style="color: #6b7280;">Risk Level:</span> <span style="color: ${markerStyle.fillColor};">${riskLevel}</span></div>
                                <div><span style="color: #6b7280;">Date:</span> ${formatDate(c.biteDate)}</div>
                                <div><span style="color: #6b7280;">Status:</span> ${c.status || 'N/A'}</div>
                            </div>
                    </div>
                `);

                markersLayer.addLayer(marker);
            }
        });
        }

        // ==========================================
        // MARKER VISUALIZATION FUNCTIONS
        // ==========================================

        // Initialize markers
        createMarkers();
        
        if (!heatLayer && markerPoints.length > 0) {
            showMarkers();
            heatmapBtnEl.disabled = true;
        }
        
        // ==========================================
        // LAYER CONTROL FUNCTIONS
        // ==========================================

        /**
         * Show heatmap layer and hide markers
         */
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

        // ==========================================
        // UI CONTROL FUNCTIONS
        // ==========================================

        /**
         * Toggle heatmap settings panel visibility
         */
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
            updateLegendThresholds();
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
            updateLegendThresholds();
        }

        // ==========================================
        // ENHANCED FILTER SYSTEM
        // ==========================================


        /**
         * Apply filters and update URL parameters
         */
        function applyFilters() {
            const params = new URLSearchParams();
            
            const dateFrom = document.getElementById('dateFromFilter')?.value || '';
            const dateTo = document.getElementById('dateToFilter')?.value || '';
            const barangay = document.getElementById('barangayFilter')?.value || '';
            const animalType = document.getElementById('animalTypeFilter')?.value || '';
            const biteCategory = document.getElementById('biteCategoryFilter')?.value || '';
            const status = document.getElementById('statusFilter')?.value || '';

            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            if (barangay) params.set('barangay', barangay);
            if (animalType) params.set('animal_type', animalType);
            if (biteCategory) params.set('bite_category', biteCategory);
            if (status) params.set('status', status);

            // Update URL and reload
            const newUrl = 'geomapping.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = newUrl;
        }

        /**
         * Clear all filters
         */
        function clearAllFilters() {
            window.location.href = 'geomapping.php';
        }


        /**
         * Initialize filters from URL parameters
         */
        function initializeFiltersFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('date_from')) {
                const dateFromInput = document.getElementById('dateFromFilter');
                if (dateFromInput) dateFromInput.value = urlParams.get('date_from');
            }
            if (urlParams.has('date_to')) {
                const dateToInput = document.getElementById('dateToFilter');
                if (dateToInput) dateToInput.value = urlParams.get('date_to');
            }
            if (urlParams.has('barangay')) {
                const barangayInput = document.getElementById('barangayFilter');
                if (barangayInput) barangayInput.value = urlParams.get('barangay');
            }
            if (urlParams.has('animal_type')) {
                const animalInput = document.getElementById('animalTypeFilter');
                if (animalInput) animalInput.value = urlParams.get('animal_type');
            }
            if (urlParams.has('bite_category')) {
                const categoryInput = document.getElementById('biteCategoryFilter');
                if (categoryInput) categoryInput.value = urlParams.get('bite_category');
            }
        }

        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeFiltersFromURL();
        });

        // ==========================================
        // FILTER UPDATE FUNCTIONS (Legacy - for sidebar filters)
        // ==========================================

        /**
         * Update multivariate filters from UI inputs (for sidebar)
         */
        function updateMultivariateFilters() {
            currentMultivariateFilters = {
                animalType: document.getElementById('animalTypeFilter')?.value || '',
                biteCategory: document.getElementById('biteCategoryFilter')?.value || '',
                status: document.getElementById('statusFilter')?.value || ''
            };

            createHeatmap();
            updateSidebarWithFilteredData();
            updateLegendThresholds();
        }

        // Calculate and update dynamic legend thresholds based on filtered data
        function updateLegendThresholds() {
            const filteredCases = getFilteredCases();
            const filteredBarangayCounts = {};

            filteredCases.forEach(c => {
                const barangay = c.barangay || 'Unknown';
                filteredBarangayCounts[barangay] = (filteredBarangayCounts[barangay] || 0) + 1;
            });

            const counts = Object.values(filteredBarangayCounts);
            if (counts.length === 0) {
                currentLegendThresholds = { low: 1, high: 7 };
            } else {
                counts.sort((a, b) => a - b);
                const p25 = counts[Math.floor(counts.length * 0.25)] || 1;
                const p95 = counts[Math.floor(counts.length * 0.95)] || Math.max(...counts);

                currentLegendThresholds.low = Math.max(1, Math.ceil(p25));
                currentLegendThresholds.high = Math.max(currentLegendThresholds.low + 1, Math.ceil(p95));
            }

            // Update legend labels
            const lowLabel = document.getElementById('legendLowLabel');
            const highLabel = document.getElementById('legendHighLabel');

            if (lowLabel && highLabel) {
                lowLabel.textContent = `Low (1–${currentLegendThresholds.low})`;
                highLabel.textContent = `High (${currentLegendThresholds.high}+)`;
            }
        }

        // ==========================================
        // SIDEBAR AND EXPORT FUNCTIONS
        // ==========================================

        /**
         * Update the risk analysis section with filtered data
         */
        function updateRiskAnalysis(filteredBarangayCounts) {
            // Calculate risk statistics for filtered data
            let criticalCount = 0, highCount = 0, mediumCount = 0, lowCount = 0;

            Object.keys(filteredBarangayCounts).forEach(barangay => {
                const riskData = barangayRiskScores[barangay];
                if (riskData) {
                    switch (riskData.riskLevel) {
                        case 'critical': criticalCount++; break;
                        case 'high': highCount++; break;
                        case 'medium': mediumCount++; break;
                        default: lowCount++; break;
                    }
                } else {
                    // Fallback for filtered data without risk scores
                    lowCount++;
                }
            });

            // Update risk overview
            const riskOverviewHtml = `
                <div class="risk-overview">
                    <div class="risk-stat"><span class="badge badge-critical">${criticalCount}</span> Critical</div>
                    <div class="risk-stat"><span class="badge badge-high">${highCount}</span> High</div>
                    <div class="risk-stat"><span class="badge badge-medium">${mediumCount}</span> Medium</div>
                    <div class="risk-stat"><span class="badge badge-low">${lowCount}</span> Low</div>
                </div>
            `;

            // Update risk table with top 10 highest risk areas from filtered data
            let riskTableHtml = `
                <table class="data-table risk-table">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th>Risk Score</th>
                            <th>Priority</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            const riskItems = Object.entries(filteredBarangayCounts)
                .map(([barangay, count]) => {
                    const riskData = barangayRiskScores[barangay];
                    return {
                        barangay,
                        count,
                        riskData,
                        sortScore: riskData ? riskData.compositeScore : 0
                    };
                })
                .sort((a, b) => b.sortScore - a.sortScore)
                .slice(0, 10); // Top 10

            if (riskItems.length > 0) {
                riskItems.forEach(({ barangay, riskData }) => {
                    if (riskData) {
                        riskTableHtml += `
                            <tr onclick="focusLocation('${barangay.replace(/'/g, "\\'")}')">
                                <td>${barangay}</td>
                                <td><strong style="color: ${riskData.riskColor};">${riskData.compositeScore}</strong></td>
                                <td><span class="badge badge-${riskData.riskLevel}">${riskData.priority}</span></td>
                                <td><button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); showRiskDetails('${barangay.replace(/'/g, "\\'")}')">Details</button></td>
                            </tr>
                        `;
                    }
                });
            } else {
                riskTableHtml += '<tr><td colspan="4" style="text-align: center; color: var(--gray-500);">No risk data available</td></tr>';
            }

            riskTableHtml += '</tbody></table>';

            document.getElementById('riskBody').innerHTML = riskOverviewHtml + riskTableHtml;
        }

        /**
         * Show detailed risk analysis for a barangay
         */
        function showRiskDetails(barangay) {
            const riskData = barangayRiskScores[barangay];
            if (!riskData) {
                alert('Risk data not available for this barangay');
                return;
            }

            const modalContent = `
                <div class="risk-details-modal">
                    <h5 style="color: ${riskData.riskColor};">${barangay} - ${riskData.riskLabel}</h5>
                    <div class="risk-metrics">
                        <div class="metric">
                            <span class="metric-label">Composite Risk Score:</span>
                            <span class="metric-value" style="color: ${riskData.riskColor};">${riskData.compositeScore}/100</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Case Density:</span>
                            <span class="metric-value">${riskData.densityScore}/100</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Severity Score:</span>
                            <span class="metric-value">${riskData.severityScore}/100</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Recency Score:</span>
                            <span class="metric-value">${riskData.recencyScore}/100</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Animal Risk Score:</span>
                            <span class="metric-value">${riskData.animalRiskScore}/100</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Total Cases:</span>
                            <span class="metric-value">${riskData.caseCount}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Severe Cases:</span>
                            <span class="metric-value">${riskData.severeCases}</span>
                        </div>
                    </div>
                    <div class="recommendations">
                        <h6>Recommended Actions:</h6>
                        <ul>
                            ${riskData.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            `;

            // Create and show modal (using Bootstrap if available)
            if (typeof bootstrap !== 'undefined') {
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Risk Analysis Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">${modalContent}</div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="focusLocation('${barangay.replace(/'/g, "\\'")}')">View on Map</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                new bootstrap.Modal(modal).show();
                modal.addEventListener('hidden.bs.modal', () => document.body.removeChild(modal));
            } else {
                // Fallback alert
                alert(`${barangay} Risk Analysis:\n\nRisk Level: ${riskData.riskLabel}\nScore: ${riskData.compositeScore}/100\n\nRecommendations:\n${riskData.recommendations.join('\n')}`);
            }
        }

        /**
         * Update sidebar with filtered data
         */
        function updateSidebarWithFilteredData() {
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

            // Sort barangays by comprehensive risk score, then by case count
            const sortedBarangays = Object.entries(filteredBarangayCounts)
                .map(([barangay, count]) => {
                    const riskData = barangayRiskScores[barangay];
                    return {
                        barangay,
                        count,
                        riskData,
                        sortKey: riskData ? riskData.compositeScore : (count / maxFilteredCount * 50) // Fallback for filtered data
                    };
                })
                .sort((a, b) => b.sortKey - a.sortKey);

            sortedBarangays.forEach(({ barangay, count, riskData }) => {
                let risk = 'low', riskLabel = 'Low', riskColor = '#22c55e';

                if (riskData) {
                    // Use comprehensive risk scoring
                    risk = riskData.riskLevel;
                    riskLabel = riskData.riskLabel;
                    riskColor = riskData.riskColor;
                } else {
                    // Fallback to simple ratio-based scoring for filtered data
                    const ratio = maxFilteredCount > 0 ? count / maxFilteredCount : 0;
                    if (ratio >= 0.75) { risk = 'critical'; riskLabel = 'Critical'; riskColor = '#dc2626'; }
                    else if (ratio >= 0.5) { risk = 'high'; riskLabel = 'High'; riskColor = '#ea580c'; }
                    else if (ratio >= 0.25) { risk = 'medium'; riskLabel = 'Medium'; riskColor = '#f59e0b'; }
                }

                const scoreDisplay = riskData ? `${riskData.compositeScore}` : '';

                    areasHtml += `
                    <tr onclick="focusLocation('${barangay.replace(/'/g, "\\'")}')" title="${riskData ? riskData.priority : ''}">
                            <td>${barangay}</td>
                        <td><strong>${count}</strong>${scoreDisplay ? ` <small style="color: ${riskColor};">(${scoreDisplay})</small>` : ''}</td>
                        <td><span class="badge badge-${risk}" style="background-color: ${riskColor};">${riskLabel}</span></td>
                        </tr>
                    `;
                });

            if (Object.keys(filteredBarangayCounts).length === 0) {
                areasHtml += '<tr><td colspan="3" style="text-align: center; color: var(--gray-500);">No data for selected period</td></tr>';
            }

            areasHtml += '</tbody></table>';
            document.getElementById('areasBody').innerHTML = areasHtml;

            // Update risk analysis section
            updateRiskAnalysis(filteredBarangayCounts);

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
            var targetLat, targetLng;

            // Try barangay coordinates first
            if (barangayCoords[barangay]) {
                targetLat = barangayCoords[barangay].lat;
                targetLng = barangayCoords[barangay].lng;
            } else {
                // Fallback to case coordinates - find all cases for this barangay and use average position
                const barangayCases = cases.filter(c => c.barangay === barangay && c.latitude && c.longitude);
                if (barangayCases.length > 0) {
                    // Use average position of all cases in this barangay for better centering
                    const avgLat = barangayCases.reduce((sum, c) => sum + parseFloat(c.latitude), 0) / barangayCases.length;
                    const avgLng = barangayCases.reduce((sum, c) => sum + parseFloat(c.longitude), 0) / barangayCases.length;
                    targetLat = avgLat;
                    targetLng = avgLng;
                } else {
                    return; // Exit if no coordinates
                }
            }

            // Basic validation
            if (!targetLat || !targetLng) {
                return;
            }

            // Get case count for zoom level calculation
            var casesForBarangay = barangayCaseData[barangay] || [];
            var caseCount = casesForBarangay.length;

            // Enhanced zoom level calculation based on case density and geographic spread
            let zoomLevel;
            if (caseCount > 50) {
                zoomLevel = 14; // Wider view for high-density areas
            } else if (caseCount > 20) {
                zoomLevel = 15; // Medium zoom for moderate density
            } else if (caseCount > 5) {
                zoomLevel = 16; // Closer zoom for low density
            } else {
                zoomLevel = 17; // Closest zoom for very few cases
            }

            // Adjust zoom based on current map zoom to avoid jarring jumps
            const currentZoom = map.getZoom();
            if (Math.abs(currentZoom - zoomLevel) > 3) {
                zoomLevel = Math.max(currentZoom - 1, zoomLevel);
            }

            // Basic bounds check (optional for now)

            // Use setView for immediate zoom
            map.setView([targetLat, targetLng], zoomLevel);

            focusedLocation = barangay;
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
    <script>
        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '../logout/admin_logout.php';
            }
        }
    </script>
</body>
</html>

