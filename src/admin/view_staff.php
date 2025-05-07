<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Handle staff deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $staffId = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM staff WHERE staffId = ?");
        $stmt->execute([$staffId]);
        $_SESSION['message'] = "Staff member deleted successfully!";
        header("Location: view_staff.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting staff: " . $e->getMessage();
    }
}

// Pagination settings
$recordsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$params = [];

if (!empty($search)) {
    $searchCondition = "WHERE firstName LIKE ? OR lastName LIKE ? OR email LIKE ? OR contactNumber LIKE ?";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Get total records for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM staff $searchCondition");
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get staff records
$stmt = $pdo->prepare("SELECT staffId, firstName, lastName, birthDate, contactNumber, email FROM staff $searchCondition ORDER BY lastName, firstName LIMIT $offset, $recordsPerPage");
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Staff | BHW Admin Portal</title>
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
    
    .content-container {
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
    
    .content-card {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 1.5rem;
      overflow: hidden;
      border: none;
    }
    
    .content-card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      background-color: rgba(var(--bs-primary-rgb), 0.03);
    }
    
    .content-card-body {
      padding: 1.5rem;
    }
    
    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    
    .table {
      margin-bottom: 0;
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
    
    .btn-action {
      padding: 0.25rem 0.5rem;
      font-size: 0.875rem;
    }
    
    .btn-action + .btn-action {
      margin-left: 0.25rem;
    }
    
    .search-container {
      max-width: 400px;
    }
    
    .pagination {
      margin-bottom: 0;
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
    
    @media (max-width: 768px) {
      .content-container {
        padding: 1rem;
      }
      
      .page-header {
        padding: 1rem;
      }
      
      .content-card-header, .content-card-body {
        padding: 1rem;
      }
      
      .table td, .table th {
        padding: 0.75rem;
      }
      
      .d-flex.flex-column.flex-md-row {
        gap: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
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
            <a class="nav-link" href="view_reports.php"><i class="bi bi-file-earmark-text"></i> Reports</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="view_staff.php"><i class="bi bi-people"></i> Staff</a>
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

  <div class="content-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div class="mb-3 mb-md-0">
          <h2 class="mb-0">Staff Management</h2>
          <p class="text-muted mb-0">View and manage barangay health workers</p>
        </div>
        <div>
          <a href="add_staff.html" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-2"></i>Add New Staff
          </a>
        </div>
      </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo $_SESSION['message']; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo $_SESSION['error']; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="content-card mb-4">
      <div class="content-card-body">
        <form action="" method="GET" class="row g-3 align-items-center">
          <div class="col-md-6">
            <div class="search-container">
              <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or contact number" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-search"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="col-md-6 text-md-end">
            <?php if (!empty($search)): ?>
            <a href="view_staff.php" class="btn btn-outline-secondary">
              <i class="bi bi-x-circle me-2"></i>Clear Search
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Staff Table -->
    <div class="content-card">
      <div class="content-card-header">
        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Staff List</h5>
      </div>
      <div class="content-card-body p-0">
        <?php if (count($staffMembers) > 0): ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Birth Date</th>
                <th>Contact Number</th>
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($staffMembers as $staff): ?>
              <tr>
                <td><?php echo $staff['staffId']; ?></td>
                <td><?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?></td>
                <td><?php echo date('M d, Y', strtotime($staff['birthDate'])); ?></td>
                <td><?php echo htmlspecialchars($staff['contactNumber']); ?></td>
                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                <td>
                  <a href="view_staff_details.php?id=<?php echo $staff['staffId']; ?>" class="btn btn-sm btn-outline-primary btn-action" title="View Details">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="edit_staff.php?id=<?php echo $staff['staffId']; ?>" class="btn btn-sm btn-outline-secondary btn-action" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-action" title="Delete" 
                          onclick="confirmDelete(<?php echo $staff['staffId']; ?>, '<?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?>')">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
          <div>
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> entries
          </div>
          <nav aria-label="Page navigation">
            <ul class="pagination mb-0">
              <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                  <span aria-hidden="true">&laquo;</span>
                </a>
              </li>
              
              <?php
              $startPage = max(1, $page - 2);
              $endPage = min($totalPages, $page + 2);
              
              if ($startPage > 1) {
                  echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                  if ($startPage > 2) {
                      echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                  }
              }
              
              for ($i = $startPage; $i <= $endPage; $i++) {
                  echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a></li>';
              }
              
              if ($endPage < $totalPages) {
                  if ($endPage < $totalPages - 1) {
                      echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                  }
                  echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $totalPages . '</a></li>';
              }
              ?>
              
              <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
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
            <i class="bi bi-people"></i>
          </div>
          <h4>No staff members found</h4>
          <?php if (!empty($search)): ?>
          <p class="text-muted">No results match your search criteria. Try different keywords or clear your search.</p>
          <a href="view_staff.php" class="btn btn-outline-primary mt-3">Clear Search</a>
          <?php else: ?>
          <p class="text-muted">There are no staff members in the system yet.</p>
          <a href="add_staff.html" class="btn btn-primary mt-3">
            <i class="bi bi-person-plus-fill me-2"></i>Add New Staff
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete <span id="staffName" class="fw-bold"></span>? This action cannot be undone.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function confirmDelete(staffId, staffName) {
      document.getElementById('staffName').textContent = staffName;
      document.getElementById('confirmDeleteBtn').href = 'view_staff.php?delete=' + staffId;
      
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      deleteModal.show();
    }
  </script>
</body>
</html>