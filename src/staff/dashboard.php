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
    $totalReportsStmt = $pdo->query("SELECT COUNT(*) FROM reports");
    $totalReports = $totalReportsStmt->fetchColumn();
    
    $todayReportsStmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE DATE(reportDate) = CURDATE()");
    $todayReports = $todayReportsStmt->fetchColumn();
    
    $pendingReportsStmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $pendingReports = $pendingReportsStmt->fetchColumn();
    
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
  <title>Dashboard | Animal Bite Center</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    * {
      box-sizing: border-box;
    }
    
    body {
      background-color: #f5f7fa;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh;
      margin: 0;
    }
    
    /* Header */
    .header {
      background: white;
      border-bottom: 1px solid #e5e7eb;
      padding: 1rem 1.5rem;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    
    .logo {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      font-size: 1.1rem;
      color: #1a1a1a;
    }
    
    .logo i {
      color: #16a34a;
      font-size: 1.25rem;
    }
    
    .header-actions {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    .btn-new {
      background: #16a34a;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      transition: background 0.2s;
    }
    
    .btn-new:hover {
      background: #15803d;
      color: white;
    }
    
    .btn-logout {
      background: transparent;
      color: #6b7280;
      border: 1px solid #e5e7eb;
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      font-size: 0.875rem;
      text-decoration: none;
      transition: all 0.2s;
    }
    
    .btn-logout:hover {
      background: #fee2e2;
      color: #dc2626;
      border-color: #fecaca;
    }
    
    /* Main Content */
    .main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1.5rem;
    }
    
    /* Welcome */
    .welcome {
      margin-bottom: 1.5rem;
    }
    
    .welcome h1 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1a1a1a;
      margin: 0 0 0.25rem 0;
    }
    
    .welcome p {
      color: #6b7280;
      margin: 0;
      font-size: 0.9rem;
    }
    
    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    
    @media (max-width: 900px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    
    @media (max-width: 500px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
    
    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 1.25rem;
      border: 1px solid #e5e7eb;
    }
    
    .stat-card-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 0.5rem;
    }
    
    .stat-label {
      font-size: 0.8rem;
      color: #6b7280;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }
    
    .stat-icon {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
    }
    
    .stat-icon.green {
      background: #dcfce7;
      color: #16a34a;
    }
    
    .stat-icon.blue {
      background: #dbeafe;
      color: #2563eb;
    }
    
    .stat-icon.yellow {
      background: #fef3c7;
      color: #d97706;
    }
    
    .stat-icon.red {
      background: #fee2e2;
      color: #dc2626;
    }
    
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: #1a1a1a;
      line-height: 1;
    }
    
    /* Card */
    .card {
      background: white;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      overflow: hidden;
    }
    
    .card-header {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid #e5e7eb;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .card-title {
      font-size: 1rem;
      font-weight: 600;
      color: #1a1a1a;
      margin: 0;
    }
    
    .btn-link {
      color: #16a34a;
      font-size: 0.875rem;
      text-decoration: none;
      font-weight: 500;
    }
    
    .btn-link:hover {
      text-decoration: underline;
    }
    
    /* Table */
    .table-wrap {
      overflow-x: auto;
    }
    
    .table {
      width: 100%;
      border-collapse: collapse;
      margin: 0;
    }
    
    .table th,
    .table td {
      padding: 0.875rem 1.25rem;
      text-align: left;
      border-bottom: 1px solid #f3f4f6;
    }
    
    .table th {
      background: #f9fafb;
      font-size: 0.75rem;
      font-weight: 600;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    
    .table tbody tr:hover {
      background: #f9fafb;
    }
    
    .table tbody tr:last-child td {
      border-bottom: none;
    }
    
    .patient-name {
      font-weight: 500;
      color: #1a1a1a;
    }
    
    /* Badges */
    .badge {
      display: inline-block;
      padding: 0.25rem 0.625rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .badge-cat1 {
      background: #f3f4f6;
      color: #4b5563;
    }
    
    .badge-cat2 {
      background: #fef3c7;
      color: #92400e;
    }
    
    .badge-cat3 {
      background: #fee2e2;
      color: #dc2626;
    }
    
    .badge-pending {
      background: #dbeafe;
      color: #1d4ed8;
    }
    
    .badge-progress {
      background: #e0e7ff;
      color: #4338ca;
    }
    
    .badge-completed {
      background: #dcfce7;
      color: #16a34a;
    }
    
    /* Empty State */
    .empty-state {
      padding: 3rem 1rem;
      text-align: center;
      color: #9ca3af;
    }
    
    .empty-state i {
      font-size: 2.5rem;
      margin-bottom: 0.75rem;
      display: block;
    }
    
    /* Responsive */
    @media (max-width: 600px) {
      .header-content {
        flex-wrap: wrap;
      }
      
      .main {
        padding: 1rem;
      }
      
      .table th,
      .table td {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
      }
    }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="header">
    <div class="header-content">
      <div class="logo">
        <i class="bi bi-heart-pulse"></i>
        <span>Animal Bite Center</span>
      </div>
      <div class="header-actions">
        <a href="new_report.php" class="btn-new">
          <i class="bi bi-plus"></i>
          New Report
        </a>
        <a href="../login/logout.php" class="btn-logout">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main">
    
    <!-- Welcome -->
    <div class="welcome">
      <h1>Welcome back, <?php echo htmlspecialchars($staff['firstName']); ?></h1>
      <p>Here's what's happening with your cases today.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-label">Total Cases</span>
          <div class="stat-icon green">
            <i class="bi bi-file-text"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $totalReports; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-label">Today</span>
          <div class="stat-icon blue">
            <i class="bi bi-calendar"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $todayReports; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-label">Pending</span>
          <div class="stat-icon yellow">
            <i class="bi bi-clock"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $pendingReports; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-header">
          <span class="stat-label">Category III</span>
          <div class="stat-icon red">
            <i class="bi bi-exclamation-triangle"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $categoryIIICases; ?></div>
      </div>
    </div>

    <!-- Recent Cases -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Recent Cases</h2>
        <a href="reports.php" class="btn-link">View all</a>
      </div>
      
      <?php if (empty($recentReports)): ?>
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <p>No cases recorded yet</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Patient</th>
                <th>Date</th>
                <th>Category</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentReports as $report): ?>
                <tr>
                  <td class="patient-name"><?php echo htmlspecialchars($report['patientName'] ?? 'Unknown'); ?></td>
                  <td><?php echo date('M j, Y', strtotime($report['reportDate'])); ?></td>
                  <td>
                    <?php
                      $catClass = 'badge-cat1';
                      if ($report['biteType'] === 'Category II') $catClass = 'badge-cat2';
                      if ($report['biteType'] === 'Category III') $catClass = 'badge-cat3';
                    ?>
                    <span class="badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($report['biteType']); ?></span>
                  </td>
                  <td>
                    <?php
                      $statusClass = 'badge-pending';
                      if ($report['status'] === 'in-progress') $statusClass = 'badge-progress';
                      if ($report['status'] === 'completed') $statusClass = 'badge-completed';
                    ?>
                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(htmlspecialchars($report['status'])); ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </main>

</body>
</html>
