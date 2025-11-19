<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login/staff_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get staff information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff = ['firstName' => 'User', 'lastName' => ''];
}

// Get recent animal bite reports (last 5)
try {
    $recentReportsStmt = $pdo->prepare("
        SELECT r.*, CONCAT(p.firstName, ' ', p.lastName) as patientName 
        FROM reports r
        LEFT JOIN patients p ON r.patientId = p.patientId
        ORDER BY r.reportDate DESC LIMIT 5
    ");
    $recentReportsStmt->execute();
    $recentReports = $recentReportsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentReports = [];
}

// Get statistics
try {
    // Total reports
    $totalReportsStmt = $pdo->query("SELECT COUNT(*) FROM reports");
    $totalReports = $totalReportsStmt->fetchColumn();
    
    // Reports today
    $todayReportsStmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE DATE(reportDate) = CURDATE()");
    $todayReports = $todayReportsStmt->fetchColumn();
    
    // Pending reports
    $pendingReportsStmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $pendingReports = $pendingReportsStmt->fetchColumn();
    
    // Category III cases (most severe)
    $categoryIIIStmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE biteType = 'Category III' AND status != 'completed'");
    $categoryIIICases = $categoryIIIStmt->fetchColumn();
} catch (PDOException $e) {
    $totalReports = 0;
    $todayReports = 0;
    $pendingReports = 0;
    $categoryIIICases = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Dashboard | Animal Bite Center</title>
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
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1rem;
      flex-grow: 1;
    }
    
    .welcome-section {
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
    
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
    }
    
    .action-card {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
      text-decoration: none;
      color: #212529;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    
    .action-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      color: var(--bs-primary);
    }
    
    .action-icon {
      font-size: 2rem;
      margin-bottom: 1rem;
      color: var(--bs-primary);
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
      background-color: #6c757d;
      color: white;
    }
    
    .badge-category-ii {
      background-color: #ffc107;
      color: #212529;
    }
    
    .badge-category-iii {
      background-color: #dc3545;
      color: white;
    }
    
    .badge-pending {
      background-color: #17a2b8;
      color: white;
    }
    
    .badge-in-progress {
      background-color: #007bff;
      color: white;
    }
    
    .badge-completed {
      background-color: #28a745;
      color: white;
    }
    
    .badge-referred {
      background-color: #6c757d;
      color: white;
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
    
    .footer {
      background-color: white;
      padding: 1rem 0;
      margin-top: auto;
      border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    @media (max-width: 768px) {
      .dashboard-container {
        padding: 1rem;
      }
      
      .welcome-section {
        padding: 1rem;
      }
      
      .stats-card {
        margin-bottom: 1rem;
      }
      
      .content-card-header, .content-card-body {
        padding: 1rem;
      }
      
      .table td, .table th {
        padding: 0.75rem;
      }
    }
  </style>
</head>
<body>
  <?php $activePage = 'dashboard'; include 'navbar.php'; ?>

  <div class="dashboard-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="mb-0">Welcome, <?php echo htmlspecialchars($staff['firstName']); ?>!</h2>
          <p class="text-muted mb-0">Here's an overview of animal bite cases and activities</p>
        </div>
        <div>
          <a href="new_report.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>New Bite Report
          </a>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-3 col-sm-6">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Total Cases</h5>
              <div class="stats-number"><?php echo $totalReports; ?></div>
              <p class="text-muted mb-0">All time</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-file-earmark-text"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3 col-sm-6">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Today's Cases</h5>
              <div class="stats-number"><?php echo $todayReports; ?></div>
              <p class="text-muted mb-0">New submissions</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-calendar-check"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3 col-sm-6">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Pending</h5>
              <div class="stats-number"><?php echo $pendingReports; ?></div>
              <p class="text-muted mb-0">Awaiting action</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-hourglass-split"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3 col-sm-6">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Category III</h5>
              <div class="stats-number"><?php echo $categoryIIICases; ?></div>
              <p class="text-muted mb-0">Severe cases</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-exclamation-triangle"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="content-card mb-4">
      <div class="content-card-header">
        <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
      </div>
      <div class="content-card-body">
        <div class="quick-actions">
          <a href="new_report.php" class="action-card">
            <div class="action-icon">
              <i class="bi bi-file-earmark-plus"></i>
            </div>
            <h5>New Bite Report</h5>
            <p class="text-muted mb-0">Record a new animal bite case</p>
          </a>
        </div>
      </div>
    </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <small class="text-muted">&copy; <?php echo date('Y'); ?> Animal Bite Treatment Center</small>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>