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
        <a class="navbar-brand fw-bold" href="admin_dashboard.php">BHW Admin Portal</a>
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
                    <a class="nav-link <?php echo $current_page == 'decisionSupport.php' ? 'active' : ''; ?>" href="decisionSupport.php">
                        <i class="bi bi-graph-up me-1"></i> Decision Support
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'view_staff.php' ? 'active' : ''; ?>" href="view_staff.php">
                        <i class="bi bi-people"></i> Staff
                    </a>
                </li>
                
                <!-- Notification Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger badge-counter">
                            <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" style="min-width: 320px; max-width: 320px; max-height: 400px; overflow-y: auto; padding: 0;">
                        <div class="dropdown-header d-flex justify-content-between align-items-center" style="background-color: #f8f9fa; padding: 0.75rem 1rem; font-weight: 600;">
                            <span>Notifications</span>
                            <?php if ($unread_count > 0): ?>
                            <a href="notifications.php?action=mark_all_read" class="text-decoration-none small">Mark all as read</a>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-divider m-0"></div>
                        <?php if (count($recent_notifications) > 0): ?>
                            <?php foreach ($recent_notifications as $notification): ?>
                            <a class="dropdown-item notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?> <?php echo $notification['type']; ?> p-2" 
                               href="<?php echo !empty($notification['link']) ? htmlspecialchars($notification['link']) : 'admin_dashboard.php?read_notification=' . $notification['id']; ?>"
                               style="border-left: 4px solid <?php echo getNotificationColor($notification['type']); ?>; transition: all 0.2s ease;">
                                <div class="d-flex align-items-center">
                                    <div class="notification-icon <?php echo $notification['type']; ?> me-3" 
                                         style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; 
                                                background-color: <?php echo getNotificationBgColor($notification['type']); ?>; 
                                                color: <?php echo getNotificationColor($notification['type']); ?>;">
                                        <i class="bi bi-<?php echo htmlspecialchars($notification['icon']); ?>"></i>
                                    </div>
                                    <div>
                                        <div class="small text-muted"><?php echo format_notification_date($notification['created_at']); ?></div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="text-truncate" style="max-width: 230px;"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-divider m-0"></div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dropdown-item text-center p-3">
                                <span class="text-muted">No notifications</span>
                            </div>
                            <div class="dropdown-divider m-0"></div>
                        <?php endif; ?>
                        <div class="dropdown-footer" style="background-color: #f8f9fa; padding: 0.75rem 1rem; text-align: center; font-weight: 500;">
                            <a href="notifications.php" class="text-decoration-none">View all notifications</a>
                        </div>
                    </div>
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
