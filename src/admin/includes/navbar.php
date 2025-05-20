<?php
// Include notification helper if not already included
if (!function_exists('getUnreadNotificationCount')) {
    require_once 'notification_helper.php';
}

// Get unread notification count
$unread_count = getUnreadNotificationCount($pdo);

// Get recent notifications (5 most recent)
$recent_notifications = get_notifications($pdo, ['is_read' => 0], 5);

// Get admin information if not already available
if (!isset($admin) && isset($_SESSION['admin_id'])) {
    try {
        $admin_stmt = $pdo->prepare("SELECT firstName, lastName FROM admin WHERE adminId = ?");
        $admin_stmt->execute([$_SESSION['admin_id']]);
        $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $admin = ['firstName' => 'Admin', 'lastName' => ''];
    }
}

// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-light sticky-top mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin_dashboard.php">Animal Bite Admin Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>" href="admin_dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'geomapping.php' ? 'active' : ''; ?>" href="geomapping.php">
                        <i class="bi bi-geo-alt me-1"></i> Geomapping
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'view_reports.php' ? 'active' : ''; ?>" href="view_reports.php">
                        <i class="bi bi-file-earmark-text"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'view_patients.php' ? 'active' : ''; ?>" href="view_patients.php">
                        <i class="bi bi-person"></i> Patients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'decisionSupport.php' ? 'active' : ''; ?>" href="decisionSupport.php">
                        <i class="bi bi-graph-up me-1"></i> Decision Support
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'view_staff.php' ? 'active' : ''; ?>" href="view_staff.php">
                        <i class="bi bi-people"></i> Staff
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>" href="admin_settings.php">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn-logout ms-2" href="../logout/admin_logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
