<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get admin information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM admin WHERE adminId = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin = ['firstName' => 'Admin', 'lastName' => ''];
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
    
    // Total patients
    $totalPatientsStmt = $pdo->query("SELECT COUNT(*) FROM patients");
    $totalPatients = $totalPatientsStmt->fetchColumn();
    
    // Total staff
    $totalStaffStmt = $pdo->query("SELECT COUNT(*) FROM staff");
    $totalStaff = $totalStaffStmt->fetchColumn();
    
    // Get recent reports (last 5)
    $recentReportsStmt = $pdo->prepare("
        SELECT r.*, CONCAT(p.firstName, ' ', p.lastName) as patientName 
        FROM reports r
        LEFT JOIN patients p ON r.patientId = p.patientId
        ORDER BY r.reportDate DESC LIMIT 5
    ");
    $recentReportsStmt->execute();
    $recentReports = $recentReportsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $totalReports = 0;
    $todayReports = 0;
    $pendingReports = 0;
    $categoryIIICases = 0;
    $totalPatients = 0;
    $totalStaff = 0;
    $recentReports = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Animal Bite Center</title>
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
            background-color: #28a745;
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
            <a class="nav-link active" href="admin_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="view_reports.php"><i class="bi bi-file-earmark-text"></i> Reports</a>
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
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Welcome, <?php echo htmlspecialchars($admin['firstName']); ?>!</h2>
                    <p class="text-muted mb-0">Here's an overview of animal bite cases and activities</p>
                </div>
                <div>
                    <a href="view_reports.php" class="btn btn-primary">
                        <i class="bi bi-file-earmark-text me-2"></i>View All Reports
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

        <!-- Additional Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Total Patients</h5>
                            <div class="stats-number"><?php echo $totalPatients; ?></div>
                            <p class="text-muted mb-0">Registered in system</p>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Total Staff</h5>
                            <div class="stats-number"><?php echo $totalStaff; ?></div>
                            <p class="text-muted mb-0">Active personnel</p>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-person-badge"></i>
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
                    <a href="view_reports.php" class="action-card">
                        <div class="action-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h5>View Reports</h5>
                        <p class="text-muted mb-0">Manage animal bite cases</p>
                    </a>
                    
                    <a href="view_staff.php" class="action-card">
                        <div class="action-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h5>Manage Staff</h5>
                        <p class="text-muted mb-0">Add or edit staff accounts</p>
                    </a>
                    
                    <a href="admin_settings.php" class="action-card">
                        <div class="action-icon">
                            <i class="bi bi-gear"></i>
                        </div>
                        <h5>System Settings</h5>
                        <p class="text-muted mb-0">Configure system parameters</p>
                    </a>
                    
                    <a href="notifications.php" class="action-card">
                        <div class="action-icon">
                            <i class="bi bi-bell"></i>
                        </div>
                        <h5>Notifications</h5>
                        <p class="text-muted mb-0">Manage system alerts</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Bite Cases</h5>
                <a href="view_reports.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="content-card-body p-0">
                <?php if (count($recentReports) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Animal</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReports as $report): ?>
                            <tr>
                                <td><?php echo $report['reportId'] ?? 'N/A'; ?></td>
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
                                <td>
                                    <a href="view_report.php?id=<?php echo $report['reportId'] ?? '0'; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h4>No bite reports yet</h4>
                    <p class="text-muted">No animal bite reports have been submitted</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
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
</body>
</html>
