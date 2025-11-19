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

// Get unread notification count
$unread_count = getUnreadNotificationCount($pdo);

// Get recent notifications
$recent_notifications = getAllNotifications($pdo, 5);

// Mark notification as read if requested
if (isset($_GET['read_notification']) && !empty($_GET['read_notification'])) {
    markNotificationAsRead($pdo, $_GET['read_notification']);
    
    // If a redirect URL is provided, go there
    if (isset($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit;
    }
    
    // Otherwise, redirect back to dashboard
    header("Location: admin_dashboard.php");
    exit;
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

// Add some sample notifications for testing if none exist
if (count(getAllNotifications($pdo)) === 0) {
    add_notification(
        $pdo,
        'Welcome to the Admin Dashboard',
        'This is your notification center where you can see important alerts and updates.',
        'info',
        'info-circle',
        null
    );
    
    add_notification(
        $pdo,
        'New Animal Bite Report',
        'A new animal bite report has been submitted for patient: John Doe',
        'info',
        'file-medical',
        'view_report.php?id=1'
    );
    
    add_notification(
        $pdo,
        'Critical Case Alert',
        'A critical animal bite case (Category III) has been reported for patient: Jane Smith',
        'danger',
        'exclamation-circle',
        'view_report.php?id=2'
    );
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
            --bs-surface: #f4f6fb;
            --panel-radius: 18px;
        }
        
        body {
            background-color: #f6f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #1f2937;
        }
        
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            font-weight: 600;
            letter-spacing: 0.4px;
        }
        
        .navbar-brand i {
            color: var(--bs-primary);
            margin-right: 0.65rem;
            font-size: 1.8rem;
        }
        
        .nav-link.active {
            color: var(--bs-primary) !important;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            border-radius: 12px;
            padding: 0.65rem 1.5rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        .btn-outline-primary {
            color: var(--bs-primary);
            border-color: var(--bs-primary);
            border-radius: 12px;
            padding: 0.45rem 1.2rem;
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .dashboard-container {
            width: 100%;
            margin: 0 auto;
            padding: 1.35rem 2.5vw 2.25rem;
            flex-grow: 1;
        }
        
        .dashboard-layout {
            display: grid;
            grid-template-columns: minmax(210px, 240px) minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }
        
        .quick-actions-panel {
            background-color: white;
            border-radius: var(--panel-radius);
            box-shadow: 0 25px 45px rgba(15, 23, 42, 0.08);
            padding: 1.25rem 1.4rem;
            position: sticky;
            top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-self: flex-start;
        }
        
        .quick-actions-header h5 {
            font-weight: 700;
            margin: 0;
        }
        
        .quick-actions-header p {
            margin: 0.15rem 0 0;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .quick-action-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin-top: 0.35rem;
        }
        
        .quick-action-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            background-color: #f3f5fb;
            border-radius: 12px;
            padding: 0.75rem 0.85rem;
            text-decoration: none;
            color: #1f2937;
            transition: all 0.2s ease;
        }
        
        .quick-action-item:nth-child(even) {
            background-color: #eef1f9;
        }
        
        .quick-action-item:hover {
            transform: translateX(6px);
            background-color: rgba(13, 110, 253, 0.18);
            color: #0b264a;
        }
        
        .quick-action-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--bs-primary);
            box-shadow: 0 10px 25px rgba(13, 110, 253, 0.25);
        }
        
        .quick-action-content span {
            display: block;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }
        
        .welcome-section {
            background-color: white;
            border-radius: var(--panel-radius);
            padding: 0.9rem 1.2rem;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
        }
        
        .welcome-section h3 {
            font-weight: 600;
            font-size: clamp(1.05rem, 1.6vw, 1.3rem);
            margin-bottom: 0.2rem;
        }
        
        .eyebrow {
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.18em;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.15rem;
        }
        
        .date-time-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.35rem;
        }
        
        .time-chip {
            background-color: rgba(15, 23, 42, 0.05);
            border-radius: 999px;
            padding: 0.25rem 0.8rem;
            font-weight: 500;
            font-size: 0.85rem;
            color: #0f172a;
        }
        
        .time-chip.primary {
            background-color: rgba(13, 110, 253, 0.15);
            color: #0b264a;
        }
        
        .stats-card {
            background-color: #f8f9fa;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 0.35rem 0.5rem;
            transition: all 0.15s;
            cursor: default;
            text-align: center;
        }
        
        .stats-card:hover {
            background-color: #e9ecef;
            border-bottom-color: var(--bs-primary);
        }
        
        .stats-number {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0;
            color: var(--bs-primary);
            line-height: 1;
        }
        
        .card-icon {
            display: none;
        }
        
        .stats-tab-label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6b7280;
            font-weight: 1000;
            margin-bottom: 0.15rem;
            line-height: 1;
        }
        
        .content-card {
            background-color: white;
            border-radius: var(--panel-radius);
            box-shadow: 0 22px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        
        .overview-grid .content-card {
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        
        .content-card-header {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .content-card-header small {
            color: #6b7280;
            font-weight: 500;
        }
        
        .content-card-body {
            padding: 1.25rem;
        }
        
        .overview-grid {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        
        .metrics-column {
            display: flex;
            flex-direction: row;
            gap: 0;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 0;
        }
        
        .metrics-column .row {
            width: 100%;
            margin: 0;
            gap: 0;
        }
        
        .metrics-column .col-sm-6 {
            flex: 1;
            padding: 0;
        }
        
        .metrics-column .col-sm-6:not(:last-child) .stats-card {
            border-right: 1px solid #dee2e6;
        }
        
        .reports-column {
            width: 100%;
        }
        
        .reports-column .content-card {
            border-radius: 0;
            margin-top: 0;
            border-top: none;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-scroll {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table th {
            background-color: rgba(var(--bs-primary-rgb), 0.05);
            font-weight: 600;
            border-top: none;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.8rem;
        }
        
        .table td, .table th {
            padding: 0.95rem 1rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .table tbody tr {
            transition: all 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        
        .badge-category-i {
            background-color: #22c55e;
            color: white;
        }
        
        .badge-category-ii {
            background-color: #facc15;
            color: #1f2937;
        }
        
        .badge-category-iii {
            background-color: #ef4444;
            color: white;
        }
        
        .badge-pending {
            background-color: #0ea5e9;
            color: white;
        }
        
        .badge-in-progress {
            background-color: #6366f1;
            color: white;
        }
        
        .badge-completed {
            background-color: #22c55e;
            color: white;
        }
        
        .badge-referred {
            background-color: #475569;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #94a3b8;
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
        
        /* Notification Styles */
        .badge-counter {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(50%, -50%);
            font-size: 0.65rem;
        }
        
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
        }
        
        .dropdown-footer {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            text-align: center;
            font-weight: 500;
        }
        
        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
        }
        
        .notification-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .notification-item.unread {
            border-left-color: var(--bs-primary);
            background-color: rgba(var(--bs-primary-rgb), 0.05);
        }
        
        .notification-item.info { border-left-color: #0dcaf0; }
        .notification-item.warning { border-left-color: #ffc107; }
        .notification-item.danger { border-left-color: #dc3545; }
        .notification-item.success { border-left-color: #198754; }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .notification-icon.info { background-color: rgba(13, 202, 240, 0.2); color: #0dcaf0; }
        .notification-icon.warning { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .notification-icon.danger { background-color: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .notification-icon.success { background-color: rgba(25, 135, 84, 0.2); color: #198754; }
        
        @media (max-width: 992px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-panel {
                position: static;
                max-height: none;
                width: 100%;
            }
            
            .metrics-column {
                flex-direction: column;
            }
            
            .metrics-column .row {
                flex-direction: column;
            }
            
            .reports-column .content-card {
                border-radius: 0 0 var(--panel-radius) var(--panel-radius);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem 5vw 2rem;
            }
            
            .welcome-section {
                padding: 1.25rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .content-card-header, .content-card-body {
                padding: 1.1rem;
            }
            
            .table td, .table th {
                padding: 0.75rem;
            }
        }
    </style>
    <link rel="stylesheet" href="../css/system-theme.css">
</head>
<body class="theme-admin">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
  
    <div class="dashboard-container">
        <div class="dashboard-layout">
            <aside class="quick-actions-panel">
                <div class="quick-actions-header">
                    <h5><i class="bi bi-lightning-charge me-2 text-primary"></i>City Health Quick Actions</h5>
                    <p>Jump straight into barangay escalations and approvals.</p>
                </div>
                <div class="quick-action-list">
                    <a href="view_reports.php" class="quick-action-item">
                        <div class="quick-action-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="quick-action-content">
                            <strong>Review Bite Reports</strong>
                            <span>See queue, verify details, update status</span>
                        </div>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </a>
                    <a href="new_report.php" class="quick-action-item">
                        <div class="quick-action-icon">
                            <i class="bi bi-journal-plus"></i>
                        </div>
                        <div class="quick-action-content">
                            <strong>Log New Case</strong>
                            <span>Record a bite incident on the fly</span>
                        </div>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </a>
                </div>
            </aside>

            <section class="main-content">
                <div class="welcome-section">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <p class="eyebrow mb-1">Animal Bite City Health Oversight</p>
                            <div class="date-time-group" role="status" aria-live="polite">
                                <span class="time-chip primary" id="dashboardTime">--:-- --</span>
                                <span class="time-chip" id="dashboardDate">Loading date…</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                        </div>
                    </div>
                </div>

                <div class="overview-grid">
                    <div class="metrics-column">
                        <div class="row g-0">
                            <div class="col-sm-6 col-md-3">
                                <div class="stats-card">
                                    <div class="stats-tab-label">Total Cases</div>
                                    <div class="stats-number"><?php echo $totalReports; ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="stats-card">
                                    <div class="stats-tab-label">Today's Cases</div>
                                    <div class="stats-number"><?php echo $todayReports; ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="stats-card">
                                    <div class="stats-tab-label">Pending</div>
                                    <div class="stats-number"><?php echo $pendingReports; ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="stats-card">
                                    <div class="stats-tab-label">Severity III</div>
                                    <div class="stats-number"><?php echo $categoryIIICases; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="reports-column">
                        <div class="content-card h-100">
                            <div class="content-card-header">
                                <div>
                                    <h5 class="mb-1"><i class="bi bi-clock-history me-2"></i>Recent Bite Cases</h5>
                                    <small>City Health view — shows the freshest 5 submissions only.</small>
                                </div>
                            </div>
                            <div class="content-card-body p-0">
                                <?php if (count($recentReports) > 0): ?>
                                <div class="table-responsive table-scroll">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
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
                </div>
            </section>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">&copy; 2025 City Health Office · Barangay Health Workers Management System</small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateDashboardDateTime() {
            const now = new Date();
            const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit' };
            const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };

            const timeElement = document.getElementById('dashboardTime');
            const dateElement = document.getElementById('dashboardDate');

            if (!timeElement || !dateElement) {
                return;
            }

            timeElement.textContent = now.toLocaleTimeString([], timeOptions);
            dateElement.textContent = now.toLocaleDateString([], dateOptions);
        }

        updateDashboardDateTime();
        setInterval(updateDashboardDateTime, 1000);
    </script>
</body>
</html>
