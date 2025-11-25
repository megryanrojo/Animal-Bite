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
    <!-- Completely redesigned CSS for full-width, clean layout -->
    <style>
        * { box-sizing: border-box; }
        
        body {
            background: #f8fafc;
            font-family: system-ui, -apple-system, sans-serif;
            color: #1e293b;
            margin: 0;
        }
        
        /* Full width main container with no max-width constraint */
        .main {
            width: 100%;
            padding: 2rem 3rem;
        }
        
        /* Welcome Bar */
        .welcome-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .welcome-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: #1e293b;
        }
        
        .welcome-bar h1 span {
            color: #64748b;
            font-weight: 400;
        }
        
        .datetime {
            display: flex;
            gap: 0.75rem;
            font-size: 0.875rem;
        }
        
        .datetime span {
            background: #fff;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        
        .datetime .time {
            background: #2563eb;
            color: #fff;
            border: none;
            font-weight: 600;
        }
        
        /* Stats grid uses CSS Grid with 4 equal columns on large screens */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .stat-icon.primary { background: #eff6ff; color: #2563eb; }
        .stat-icon.success { background: #f0fdf4; color: #16a34a; }
        .stat-icon.warning { background: #fffbeb; color: #d97706; }
        .stat-icon.danger { background: #fef2f2; color: #dc2626; }
        
        .stat-content { flex: 1; }
        
        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }
        
        /* Card */
        .card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
        }
        
        .card-header h2 {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }
        
        .card-header h2 i { color: #2563eb; }
        
        .btn-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-link:hover { text-decoration: underline; }
        
        /* Table */
        .table-wrap { overflow-x: auto; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        td {
            padding: 1rem 1.5rem;
            font-size: 0.9rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        
        tr:last-child td { border-bottom: none; }
        
        tr:hover { background: #f8fafc; }
        
        td strong { color: #1e293b; }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-cat-i { background: #dcfce7; color: #166534; }
        .badge-cat-ii { background: #fef3c7; color: #92400e; }
        .badge-cat-iii { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #dbeafe; color: #1e40af; }
        .badge-in-progress { background: #e0e7ff; color: #4338ca; }
        .badge-completed { background: #dcfce7; color: #166534; }
        
        .btn-view {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            color: #2563eb;
            background: #eff6ff;
            border: none;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .btn-view:hover { background: #dbeafe; color: #1d4ed8; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            color: #cbd5e1;
        }
        
        /* Responsive breakpoints */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .main { padding: 1.5rem 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .welcome-bar { 
                flex-direction: column; 
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
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
                <a href="view_reports.php" class="btn-link">View All <i class="bi bi-arrow-right"></i></a>
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
                                <a href="view_report.php?id=<?php echo $report['reportId'] ?? '0'; ?>" class="btn-view">View</a>
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
    </script>
</body>
</html>
