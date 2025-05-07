<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Initialize filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$urgency_filter = isset($_GET['urgency']) ? $_GET['urgency'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT r.*, p.firstName, p.lastName, p.contactNumber, p.barangay 
          FROM reports r 
          JOIN patients p ON r.patientId = p.patientId 
          WHERE 1=1";

$params = [];

// Remove urgency filter since it's not in the table
if (!empty($status_filter)) {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND r.reportDate >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $query .= " AND r.reportDate <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($search)) {
    $query .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR p.barangay LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'reportDate';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_columns = ['reportDate', 'status', 'biteDate', 'firstName', 'lastName', 'barangay', 'animalType'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'reportDate';
}

// Validate sort direction
if ($sort_direction != 'ASC' && $sort_direction != 'DESC') {
    $sort_direction = 'DESC';
}

$query .= " ORDER BY $sort_column $sort_direction";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

$query .= " LIMIT $offset, $records_per_page";

// Execute query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total records for pagination
    $count_query = "SELECT COUNT(*) FROM reports r JOIN patients p ON r.patientId = p.patientId WHERE 1=1";
    $count_params = [];
    
    if (!empty($status_filter)) {
        $count_query .= " AND r.status = ?";
        $count_params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $count_query .= " AND r.reportDate >= ?";
        $count_params[] = $date_from . ' 00:00:00';
    }
    
    if (!empty($date_to)) {
        $count_query .= " AND r.reportDate <= ?";
        $count_params[] = $date_to . ' 23:59:59';
    }
    
    if (!empty($search)) {
        $count_query .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR p.barangay LIKE ?)";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $reports = [];
    $total_pages = 0;
}

// Get counts for summary cards
try {
    $pending_query = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $pending_count = $pending_query->fetchColumn();
    
    $in_progress_query = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'in_progress'");
    $in_progress_count = $in_progress_query->fetchColumn();
    
    $completed_query = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'completed'");
    $completed_count = $completed_query->fetchColumn();
} catch (PDOException $e) {
    $pending_count = 0;
    $in_progress_count = 0;
    $completed_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Reports - Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
    }
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }
    
    .navbar {
      background-color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .nav-link.active {
            color: var(--bs-primary) !important;
            font-weight: 500;
    }

    .page-header {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .stats-card {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    
    .stats-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .stats-number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--bs-primary);
    }
    
    .card-icon {
      font-size: 2rem;
      color: var(--bs-primary);
    }
    
    .filter-card {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .reports-table {
      background-color: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .table {
      margin-bottom: 0;
    }
    
    .table th {
      background-color: rgba(var(--bs-primary-rgb), 0.05);
      font-weight: 600;
      border-top: none;
    }
    
    .table td {
      vertical-align: middle;
    }
    
    .status-badge {
      font-size: 0.75rem;
      padding: 0.35em 0.65em;
      border-radius: 0.25rem;
    }
    
    .status-pending {
      background-color: #ffc107;
      color: #212529;
    }
    
    .status-in-progress {
      background-color: #17a2b8;
      color: white;
    }
    
    .status-completed {
      background-color: #28a745;
      color: white;
    }
    
    .status-cancelled {
      background-color: #dc3545;
      color: white;
    }
    
    .urgency-high {
      background-color: #dc3545;
      color: white;
    }
    
    .urgency-medium {
      background-color: #fd7e14;
      color: white;
    }
    
    .urgency-low {
      background-color: #28a745;
      color: white;
    }
    
    .pagination {
      margin-top: 1.5rem;
      justify-content: center;
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
    
    .sort-icon {
      cursor: pointer;
    }
    
    @media (max-width: 768px) {
      .dashboard-container {
        padding: 1rem;
      }
      
      .page-header, .filter-card, .stats-card {
        padding: 1rem;
      }
      
      .table-responsive {
        border-radius: 10px;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light sticky-top mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">BHW Admin Portal</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="view_reports.php"><i class="bi bi-file-earmark-text"></i> Reports</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="view_staff.php"><i class="bi bi-people"></i> Staff</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="admin_settings.php"><i class="bi bi-gear"></i> Settings</a>
          </li>
          <li class="nav-item">
            <a class="nav-link btn-logout ms-2" href="../logout/admin_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
  
  <div class="dashboard-container">
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="mb-0">Animal Bite Reports</h2>
          <p class="text-muted mb-0">View and manage all submitted reports</p>
        </div>
        <div>
          <a href="admin_dashboard.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
          </a>
        </div>
      </div>
    </div>
    
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Pending</h5>
              <div class="stats-number"><?php echo $pending_count; ?></div>
              <p class="text-muted mb-0">Reports awaiting action</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-hourglass-split"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">In Progress</h5>
              <div class="stats-number"><?php echo $in_progress_count; ?></div>
              <p class="text-muted mb-0">Reports being processed</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-arrow-repeat"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Completed</h5>
              <div class="stats-number"><?php echo $completed_count; ?></div>
              <p class="text-muted mb-0">Successfully resolved</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-check-circle"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="filter-card">
      <h5 class="mb-3">Filter Reports</h5>
      <form method="GET" action="view_reports.php" class="row g-3">
        <div class="col-md-4">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status">
            <option value="">All Statuses</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="referred" <?php echo $status_filter === 'referred' ? 'selected' : ''; ?>>Referred</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
          </select>
        </div>
        
        <div class="col-md-4">
          <label for="date_from" class="form-label">Date From</label>
          <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        
        <div class="col-md-4">
          <label for="date_to" class="form-label">Date To</label>
          <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        
        <div class="col-md-8">
          <label for="search" class="form-label">Search</label>
          <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, contact, or barangay" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">
            <i class="bi bi-filter me-2"></i>Apply Filters
          </button>
          <a href="view_reports.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle me-2"></i>Clear Filters
          </a>
        </div>
        
        <!-- Hidden fields to maintain sort order when filtering -->
        <input type="hidden" name="sort" value="<?php echo $sort_column; ?>">
        <input type="hidden" name="direction" value="<?php echo $sort_direction; ?>">
      </form>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="reports-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>
                <a href="<?php echo buildSortUrl('reportDate'); ?>" class="text-decoration-none text-dark">
                  Report Date
                  <?php echo getSortIcon('reportDate'); ?>
                </a>
              </th>
              <th>
                <a href="<?php echo buildSortUrl('biteDate'); ?>" class="text-decoration-none text-dark">
                  Bite Date
                  <?php echo getSortIcon('biteDate'); ?>
                </a>
              </th>
              <th>
                <a href="<?php echo buildSortUrl('firstName'); ?>" class="text-decoration-none text-dark">
                  Patient
                  <?php echo getSortIcon('firstName'); ?>
                </a>
              </th>
              <th>Contact</th>
              <th>
                <a href="<?php echo buildSortUrl('barangay'); ?>" class="text-decoration-none text-dark">
                  Barangay
                  <?php echo getSortIcon('barangay'); ?>
                </a>
              </th>
              <th>
                <a href="<?php echo buildSortUrl('animalType'); ?>" class="text-decoration-none text-dark">
                  Animal
                  <?php echo getSortIcon('animalType'); ?>
                </a>
              </th>
              <th>
                <a href="<?php echo buildSortUrl('status'); ?>" class="text-decoration-none text-dark">
                  Status
                  <?php echo getSortIcon('status'); ?>
                </a>
              </th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reports)): ?>
            <tr>
              <td colspan="8" class="text-center py-4">
                <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0 text-muted">No reports found matching your criteria.</p>
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($reports as $report): ?>
              <tr>
                <td><?php echo date('M d, Y', strtotime($report['reportDate'])); ?></td>
                <td><?php echo date('M d, Y', strtotime($report['biteDate'])); ?></td>
                <td><?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></td>
                <td><?php echo htmlspecialchars($report['contactNumber']); ?></td>
                <td><?php echo htmlspecialchars($report['barangay']); ?></td>
                <td><?php echo htmlspecialchars($report['animalType']); ?></td>
                <td>
                  <span class="status-badge <?php 
                    switch($report['status']) {
                      case 'pending': echo 'status-pending'; break;
                      case 'in_progress': echo 'status-in-progress'; break;
                      case 'completed': echo 'status-completed'; break;
                      case 'referred': echo 'status-in-progress'; break;
                      case 'cancelled': echo 'status-cancelled'; break;
                      default: echo 'status-pending';
                    }
                  ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                  </span>
                </td>
                <td>
                  <a href="view_report.php?id=<?php echo $report['reportId']; ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye"></i> View
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
      <ul class="pagination">
        <?php if ($page > 1): ?>
        <li class="page-item">
          <a class="page-link" href="<?php echo buildPaginationUrl($page - 1); ?>" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
          </a>
        </li>
        <?php endif; ?>
        
        <?php
        // Calculate range of page numbers to display
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        // Always show first page
        if ($start_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl(1) . '">1</a></li>';
            if ($start_page > 2) {
                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
            }
        }
        
        // Display page numbers
        for ($i = $start_page; $i <= $end_page; $i++) {
            echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="' . buildPaginationUrl($i) . '">' . $i . '</a></li>';
        }
        
        // Always show last page
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl($total_pages) . '">' . $total_pages . '</a></li>';
        }
        ?>
        
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
          <a class="page-link" href="<?php echo buildPaginationUrl($page + 1); ?>" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Helper function to build sort URL
function buildSortUrl($column) {
    $params = $_GET;
    $params['sort'] = $column;
    $params['direction'] = (isset($_GET['sort']) && $_GET['sort'] == $column && $_GET['direction'] == 'ASC') ? 'DESC' : 'ASC';
    return 'view_reports.php?' . http_build_query($params);
}

// Helper function to get sort icon
function getSortIcon($column) {
    if (isset($_GET['sort']) && $_GET['sort'] == $column) {
        return $_GET['direction'] == 'ASC' ? '<i class="bi bi-sort-up-alt ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>';
    }
    return '<i class="bi bi-filter ms-1 text-muted"></i>';
}

// Helper function to build pagination URL
function buildPaginationUrl($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return 'view_reports.php?' . http_build_query($params);
}
?>
