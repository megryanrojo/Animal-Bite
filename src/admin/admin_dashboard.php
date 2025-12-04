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
    if (isset($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit;
    }
    header("Location: admin_dashboard.php");
    exit;
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
    <link rel="stylesheet" href="../css/system-theme.css">
    <link rel="stylesheet" href="../css/admin/admin-dashboard.css">
</head>
<body class="theme-admin">
    <?php include 'includes/navbar.php'; ?>

    <main class="main">
        <!-- Welcome Bar -->
        <div class="welcome-bar">
            <h1>Welcome back, <span><?php echo htmlspecialchars($admin['firstName']); ?></span></h1>
            <div class="datetime">
                <span class="time" id="time">--:--</span>
                <span id="date">Loading...</span>
            </div>
        </div>

        <!-- Stats with icons for better visual appeal -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="bi bi-folder"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Cases</div>
                    <div class="stat-value"><?php echo $totalReports; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Today's Cases</div>
                    <div class="stat-value"><?php echo $todayReports; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="bi bi-clock"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $pendingReports; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Category III</div>
                    <div class="stat-value"><?php echo $categoryIIICases; ?></div>
                </div>
            </div>
        </div>

        <!-- Recent Cases -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-clock-history"></i> Recent Cases</h2>
                <a href="view_reports.php" class="btn-link" data-bs-toggle="tooltip" data-bs-placement="top" title="View complete list of all bite incident reports">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (count($recentReports) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Animal</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReports as $report): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($report['patientName'] ?? 'Unknown'); ?></strong></td>
                            <td><?php echo htmlspecialchars($report['animalType'] ?? 'Unknown'); ?></td>
                            <td><?php echo isset($report['biteDate']) ? date('M d, Y', strtotime($report['biteDate'])) : 'N/A'; ?></td>
                            <td>
                                <?php 
                                    $cat = $report['biteType'] ?? 'Category II';
                                    $catClass = str_contains($cat, 'III') ? 'cat-iii' : (str_contains($cat, 'II') ? 'cat-ii' : 'cat-i');
                                ?>
                                <span class="badge badge-<?php echo $catClass; ?>"><?php echo $cat; ?></span>
                            </td>
                            <td>
                                <?php 
                                    $status = $report['status'] ?? 'pending';
                                    $statusClass = str_replace('_', '-', strtolower($status));
                                ?>
                                <span class="badge badge-<?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                            </td>
                            <td>
                                <a href="view_report.php?id=<?php echo $report['reportId'] ?? '0'; ?>" class="btn-view" data-bs-toggle="tooltip" data-bs-placement="left" title="View detailed report information">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No reports yet</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateDateTime() {
            const now = new Date();
            document.getElementById('time').textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            document.getElementById('date').textContent = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '../logout/admin_logout.php';
            }
        }
    </script>
</body>
</html>
