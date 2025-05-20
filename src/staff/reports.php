<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login/staff_login.html");
    exit;
}

require_once '../conn/conn.php';

try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff = ['firstName' => 'User', 'lastName' => ''];
}

$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$biteCategory = isset($_GET['bite_category']) ? $_GET['bite_category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

$query = "
    SELECT r.*, 
           CONCAT(p.firstName, ' ', p.lastName) as patientName,
           CONCAT(s.firstName, ' ', s.lastName) as staffName
    FROM reports r
    LEFT JOIN patients p ON r.patientId = p.patientId
    LEFT JOIN staff s ON r.staffId = s.staffId
    WHERE 1=1
";

$countQuery = "
    SELECT COUNT(*) 
    FROM reports r
    LEFT JOIN patients p ON r.patientId = p.patientId
    LEFT JOIN staff s ON r.staffId = s.staffId
    WHERE 1=1
";
$params = [];

if (!empty($dateFrom)) {
    $query .= " AND r.biteDate >= ?";
    $countQuery .= " AND r.biteDate >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND r.biteDate <= ?";
    $countQuery .= " AND r.biteDate <= ?";
    $params[] = $dateTo;
}

if (!empty($animalType)) {
    $query .= " AND r.animalType = ?";
    $countQuery .= " AND r.animalType = ?";
    $params[] = $animalType;
}

if (!empty($biteCategory)) {
    $query .= " AND r.biteType = ?";
    $countQuery .= " AND r.biteType = ?";
    $params[] = $biteCategory;
}

if (!empty($status)) {
    $query .= " AND r.status = ?";
    $countQuery .= " AND r.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $searchTerm = "%$search%";
    $query .= " AND (r.reportId LIKE ? OR CONCAT(p.firstName, ' ', p.lastName) LIKE ? OR r.animalType LIKE ?)";
    $countQuery .= " AND (r.reportId LIKE ? OR CONCAT(p.firstName, ' ', p.lastName) LIKE ? OR r.animalType LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'reportDate';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

$allowedColumns = ['reportId', 'patientName', 'animalType', 'biteDate', 'biteType', 'status', 'reportDate'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'reportDate';
}

$query .= " ORDER BY $sortColumn $sortOrder LIMIT $offset, $recordsPerPage";

try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $animalTypesStmt = $pdo->query("SELECT DISTINCT animalType FROM reports ORDER BY animalType");
    $animalTypes = $animalTypesStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $reports = [];
    $totalRecords = 0;
    $totalPages = 1;
    $animalTypes = [];
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
    
    if (array_key_exists('date_from', $newParams) || 
        array_key_exists('date_to', $newParams) || 
        array_key_exists('animal_type', $newParams) || 
        array_key_exists('bite_category', $newParams) || 
        array_key_exists('status', $newParams) || 
        array_key_exists('search', $newParams)) {
        $params['page'] = 1;
    }
    
    return 'reports.php?' . http_build_query($params);
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
        return $currentOrder === 'asc' ? '<i class="bi bi-sort-up-alt ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>';
    }
    
    return '<i class="bi bi-sort ms-1 text-muted"></i>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Animal Bite Reports | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bs-primary: #28a745;
            --bs-primary-rgb: 40, 167, 69;
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
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .btn-outline-primary {
            color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .reports-container {
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
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table th {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            font-weight: 600;
            border-top: none;
            white-space: nowrap;
            cursor: pointer;
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
            background-color: #6c757d;
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
        
        .badge-pending {
            background-color: #17a2b8;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-in-progress {
            background-color: #007bff;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-completed {
            background-color: #28a745;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-referred {
            background-color: #6c757d;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .page-link {
            color: var(--bs-primary);
        }
        
        .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #adb5bd;
            margin-bottom: 1rem;
        }
        
        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .reports-container {
                padding: 1rem;
            }
            
            .content-card-header, .content-card-body {
                padding: 1rem;
            }
            
            .filter-form {
                padding: 1rem;
            }
            
            .table td, .table th {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
  <?php $activePage = 'reports'; include 'navbar.php'; ?>

    <div class="reports-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Animal Bite Reports</h2>
                <p class="text-muted mb-0">Manage and track all animal bite cases</p>
            </div>
            <div>
                <a href="new_report.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>New Report
                </a>
            </div>
        </div>
        
        <div class="filter-form">
            <form method="GET" action="reports.php">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by patient name, ID, or animal type" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex justify-content-end">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                            <i class="bi bi-funnel me-2"></i>Advanced Filters
                        </button>
                    </div>
                    
                    <div class="col-12 collapse <?php echo (!empty($dateFrom) || !empty($dateTo) || !empty($animalType) || !empty($biteCategory) || !empty($status)) ? 'show' : ''; ?>" id="advancedFilters">
                        <div class="card card-body border-0 p-0 pt-3">
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
                                        <option value="referred" <?php echo $status === 'referred' ? 'selected' : ''; ?>>Referred</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 d-flex justify-content-end">
                                    <a href="reports.php" class="btn btn-outline-secondary me-2">Clear Filters</a>
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Reports List</h5>
                <span class="badge bg-secondary"><?php echo $totalRecords; ?> Reports</span>
            </div>
            <div class="content-card-body p-0">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger m-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (count($reports) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th onclick="window.location.href='<?php echo buildSortUrl('reportId'); ?>'">
                                    ID <?php echo getSortIcon('reportId'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('patientName'); ?>'">
                                    Patient <?php echo getSortIcon('patientName'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('animalType'); ?>'">
                                    Animal <?php echo getSortIcon('animalType'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('biteDate'); ?>'">
                                    Bite Date <?php echo getSortIcon('biteDate'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('biteType'); ?>'">
                                    Category <?php echo getSortIcon('biteType'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('status'); ?>'">
                                    Status <?php echo getSortIcon('status'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('reportDate'); ?>'">
                                    Report Date <?php echo getSortIcon('reportDate'); ?>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['reportId']; ?></td>
                                <td><?php echo htmlspecialchars($report['patientName'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($report['animalType'] ?? 'Unknown'); ?></td>
                                <td><?php echo isset($report['biteDate']) ? date('M d, Y', strtotime($report['biteDate'])) : 'N/A'; ?></td>
                                <td>
                                    <?php 
                                        $biteType = $report['biteType'] ?? 'Category II';
                                        $biteTypeClass = 'badge-' . strtolower(str_replace(' ', '-', $biteType));
                                        echo '<span class="badge ' . $biteTypeClass . '">' . $biteType . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                        $status = $report['status'] ?? 'pending';
                                        $statusClass = 'badge-' . str_replace('_', '-', strtolower($status));
                                        echo '<span class="badge ' . $statusClass . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo isset($report['reportDate']) ? date('M d, Y', strtotime($report['reportDate'])) : 'N/A'; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_report.php?id=<?php echo $report['reportId']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit_report.php?id=<?php echo $report['reportId']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <div>
                        <span class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> reports</span>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrl(['page' => $page - 1]); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="' . buildUrl(['page' => 1]) . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="' . buildUrl(['page' => $i]) . '">' . $i . '</a></li>';
                            }
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="' . buildUrl(['page' => $totalPages]) . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrl(['page' => $page + 1]); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h4>No Reports Found</h4>
                    <p class="text-muted">No animal bite reports match your search criteria</p>
                    <a href="new_report.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle me-2"></i>Create New Report
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">&copy; <?php echo date('Y'); ?> Animal Bite Treatment Center</small>
                </div>
                <div>
                    <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
