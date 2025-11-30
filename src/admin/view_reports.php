<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Initialize filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$animalType = isset($_GET['animal_type']) ? trim($_GET['animal_type']) : '';
$biteType = isset($_GET['bite_type']) ? trim($_GET['bite_type']) : '';
$barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 15;
$offset = ($page - 1) * $recordsPerPage;

// Build the query
$query = "
    SELECT r.reportId, r.reportDate, r.biteDate, r.animalType, r.biteType, r.status,
           p.patientId, p.firstName, p.lastName, p.contactNumber, p.barangay
    FROM reports r
    LEFT JOIN patients p ON r.patientId = p.patientId
    WHERE 1=1
";

$countQuery = "SELECT COUNT(*) FROM reports r LEFT JOIN patients p ON r.patientId = p.patientId WHERE 1=1";
$params = [];

// Add filters
if (!empty($search)) {
    $searchTerm = "%$search%";
    $query .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR CONCAT(p.firstName, ' ', p.lastName) LIKE ?)";
    $countQuery .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR CONCAT(p.firstName, ' ', p.lastName) LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}
if (!empty($status)) {
    $query .= " AND r.status = ?";
    $countQuery .= " AND r.status = ?";
    $params[] = $status;
}
if (!empty($animalType)) {
    $query .= " AND r.animalType = ?";
    $countQuery .= " AND r.animalType = ?";
    $params[] = $animalType;
}
if (!empty($biteType)) {
    $query .= " AND r.biteType = ?";
    $countQuery .= " AND r.biteType = ?";
    $params[] = $biteType;
}
if (!empty($barangay)) {
    $query .= " AND p.barangay = ?";
    $countQuery .= " AND p.barangay = ?";
    $params[] = $barangay;
}

// Get sort parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'reportDate';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
$allowedColumns = ['patientName', 'contactNumber', 'barangay', 'animalType', 'biteDate', 'biteType', 'status', 'reportDate'];
$sortMap = [
    'patientName' => 'p.lastName',
    'contactNumber' => 'p.contactNumber',
    'barangay' => 'p.barangay',
    'animalType' => 'r.animalType',
    'biteDate' => 'r.biteDate',
    'biteType' => 'r.biteType',
    'status' => 'r.status',
    'reportDate' => 'r.reportDate'
];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'reportDate';
}
$orderByColumn = $sortMap[$sortColumn];
$query .= " ORDER BY $orderByColumn $sortOrder LIMIT $offset, $recordsPerPage";

// Execute queries
try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $recordsPerPage);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $barangaysStmt = $pdo->query("SELECT DISTINCT barangay FROM barangay_coordinates ORDER BY barangay");
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $reports = [];
    $totalRecords = 0;
    $totalPages = 1;
    $barangays = [];
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
    if (array_key_exists('search', $newParams) || array_key_exists('status', $newParams) || array_key_exists('animal_type', $newParams) || array_key_exists('bite_type', $newParams) || array_key_exists('barangay', $newParams)) {
        $params['page'] = 1;
    }
    return 'view_reports.php?' . http_build_query($params);
}

function buildSortUrl($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'reportDate';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    $newOrder = ($currentSort === $column && $currentOrder === 'desc') ? 'asc' : 'desc';
    return buildUrl(['sort' => $column, 'order' => $newOrder]);
}

function getSortIcon($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'reportDate';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    if ($currentSort === $column) {
        return $currentOrder === 'asc' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>';
    }
    return '';
}

function getStatusClass($status) {
    switch ($status) {
        case 'pending': return 'badge-pending';
        case 'in_progress': return 'badge-progress';
        case 'completed': return 'badge-completed';
        case 'referred': return 'badge-referred';
        case 'cancelled': return 'badge-cancelled';
        default: return 'badge-pending';
    }
}

function getCategoryClass($category) {
    switch ($category) {
        case 'Category I': return 'badge-cat1';
        case 'Category II': return 'badge-cat2';
        case 'Category III': return 'badge-cat3';
        default: return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }
        .page-container {
            padding: 24px 32px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0;
        }
        .record-count {
            color: #666;
            font-size: 0.9rem;
        }
        .filters-row {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }
        .filters-row input,
        .filters-row select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fff;
        }
        .filters-row input { min-width: 200px; }
        .filters-row select { min-width: 130px; }
        .filters-row input:focus,
        .filters-row select:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .btn-search {
            background: #3b82f6;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            height: 42px;
        }
        .btn-search:hover { background: #2563eb; }
        .btn-clear {
            background: #fff;
            color: #666;
            border: 1px solid #ddd;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            height: 42px;
            display: inline-flex;
            align-items: center;
        }
        .btn-clear:hover { background: #f5f5f5; color: #333; }
        .btn-new {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-new:hover { background: #059669; color: #fff; }
        .btn-print {
            background: #f97316;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print:hover { background: #ea580c; color: #fff; }
        .table-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        th {
            background: #f8fafc;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        th a {
            color: #64748b;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        th a:hover { color: #3b82f6; }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.9rem;
        }
        tr:hover td { background: #f8fafc; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-progress { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-referred { background: #ede9fe; color: #5b21b6; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-cat1 { background: #d1fae5; color: #065f46; }
        .badge-cat2 { background: #ffedd5; color: #c2410c; }
        .badge-cat3 { background: #fee2e2; color: #991b1b; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .btn-view {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view:hover { background: #e2e8f0; color: #1e293b; }

        .action-buttons {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .btn-edit {
            background: #fef3c7;
            color: #92400e;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit:hover {
            background: #fde68a;
            color: #78350f;
        }
        .empty-state {
            text-align: center;
            padding: 48px;
            color: #94a3b8;
        }
        .pagination-row {
            display: flex;
            justify-content: center;
            gap: 4px;
            padding: 20px;
        }
        .pagination-row a,
        .pagination-row span {
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .pagination-row a {
            background: #fff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .pagination-row a:hover { background: #f1f5f9; }
        .pagination-row .active {
            background: #3b82f6;
            color: #fff;
            border: 1px solid #3b82f6;
        }
        @media (max-width: 992px) {
            .page-container {
                padding: 16px;
            }
        }
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .table-card {
                border-radius: 8px;
            }
            table {
                min-width: 720px;
            }
            .filters-row {
                flex-direction: column;
            }
            .filters-row input,
            .filters-row select {
                width: 100%;
                min-width: unset;
            }
            .btn-search,
            .btn-clear {
                width: 100%;
                justify-content: center;
            }
        }
        @media (max-width: 480px) {
            .page-container {
                padding: 12px;
            }
            table {
                min-width: 640px;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-container">
    <div class="page-header">
        <div>
            <h1>Reports</h1>
            <span class="record-count"><?php echo $totalRecords; ?> total records</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn-print" onclick="print_reports()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Print the current list of reports">
                <i class="bi bi-printer"></i> Print Reports
            </button>
        </div>
    </div>

    <form class="filters-row" method="get" action="view_reports.php">
        <div class="filter-group" style="flex:1; min-width:200px;">
            <label>Search</label>
            <input type="text" name="search" placeholder="Search name or contact..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="pending" <?php if ($status === 'pending') echo 'selected'; ?>>Pending</option>
                <option value="in_progress" <?php if ($status === 'in_progress') echo 'selected'; ?>>In Progress</option>
                <option value="completed" <?php if ($status === 'completed') echo 'selected'; ?>>Completed</option>
                <option value="referred" <?php if ($status === 'referred') echo 'selected'; ?>>Referred</option>
                <option value="cancelled" <?php if ($status === 'cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Animal</label>
            <select name="animal_type">
                <option value="">All</option>
                <option value="Dog" <?php if ($animalType === 'Dog') echo 'selected'; ?>>Dog</option>
                <option value="Cat" <?php if ($animalType === 'Cat') echo 'selected'; ?>>Cat</option>
                <option value="Rat" <?php if ($animalType === 'Rat') echo 'selected'; ?>>Rat</option>
                <option value="Other" <?php if ($animalType === 'Other') echo 'selected'; ?>>Other</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Category</label>
            <select name="bite_type">
                <option value="">All</option>
                <option value="Category I" <?php if ($biteType === 'Category I') echo 'selected'; ?>>Category I</option>
                <option value="Category II" <?php if ($biteType === 'Category II') echo 'selected'; ?>>Category II</option>
                <option value="Category III" <?php if ($biteType === 'Category III') echo 'selected'; ?>>Category III</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Barangay</label>
            <select name="barangay">
                <option value="">All</option>
                <?php foreach ($barangays as $b): ?>
                    <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($barangay === $b) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-search" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Search reports using the selected filters"><i class="bi bi-search"></i> Search</button>
        <?php if (!empty($search) || !empty($status) || !empty($animalType) || !empty($biteType) || !empty($barangay)): ?>
            <a href="view_reports.php" class="btn-clear" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Clear all search filters">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-card">
        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><a href="<?php echo buildSortUrl('patientName'); ?>">Patient <?php echo getSortIcon('patientName'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('contactNumber'); ?>">Contact <?php echo getSortIcon('contactNumber'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('barangay'); ?>">Barangay <?php echo getSortIcon('barangay'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('animalType'); ?>">Animal <?php echo getSortIcon('animalType'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('biteDate'); ?>">Bite Date <?php echo getSortIcon('biteDate'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('biteType'); ?>">Category <?php echo getSortIcon('biteType'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('status'); ?>">Status <?php echo getSortIcon('status'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('reportDate'); ?>">Reported <?php echo getSortIcon('reportDate'); ?></a></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reports) > 0): ?>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['lastName'] . ', ' . $r['firstName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['contactNumber']); ?></td>
                            <td><?php echo htmlspecialchars($r['barangay']); ?></td>
                            <td><?php echo htmlspecialchars($r['animalType']); ?></td>
                            <td><?php echo $r['biteDate'] ? date('M d, Y', strtotime($r['biteDate'])) : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><span class="status-badge <?php echo getCategoryClass($r['biteType']); ?>"><?php echo htmlspecialchars($r['biteType']); ?></span></td>
                            <td><span class="status-badge <?php echo getStatusClass($r['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?></span></td>
                            <td><?php echo $r['reportDate'] ? date('M d, Y', strtotime($r['reportDate'])) : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_report.php?id=<?php echo $r['reportId']; ?>" class="btn-view" data-bs-toggle="tooltip" data-bs-placement="left" title="View detailed report information">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="edit_report.php?id=<?php echo $r['reportId']; ?>" class="btn-edit" data-bs-toggle="tooltip" data-bs-placement="left" title="Edit report details">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="empty-state">No reports found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-row">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function print_reports() {
        const currentParams = new URLSearchParams(window.location.search);

        // Determine if we are printing all or filtered
        const hasFilters =
            currentParams.get('search') ||
            currentParams.get('status') ||
            currentParams.get('animal_type') ||
            currentParams.get('bite_type') ||
            currentParams.get('barangay') ||
            currentParams.get('page') > 1;

        const printParams = new URLSearchParams();
        printParams.set('type', hasFilters ? 'filtered' : 'all');

        // Pass through all available filter parameters
        ['search', 'status', 'animal_type', 'bite_type', 'barangay', 'page'].forEach(key => {
            const value = currentParams.get(key);
            if (value && value !== '1') { // Don't include page=1
                printParams.set(key, value);
            }
        });

        // Show loading indicator
        const printBtn = document.querySelector('.btn-print');
        const originalText = printBtn.innerHTML;
        printBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';
        printBtn.disabled = true;

        // Open print window
        window.open('print_reports.php?' + printParams.toString(), '_blank');

        // Reset button after a delay
        setTimeout(() => {
            printBtn.innerHTML = originalText;
            printBtn.disabled = false;
        }, 2000);
    }
</script>
</body>
</html>
