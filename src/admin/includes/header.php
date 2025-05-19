<?php
// Include notification helper
include_once 'notification_helper.php';

// Initialize the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
$admin_logged_in = isset($_SESSION['admin_id']);

// Get unread notification count if admin is logged in
$unread_count = 0;
$recent_notifications = [];
if ($admin_logged_in) {
    // Include database connection if not already included
    if (!isset($conn)) {
        include_once '../includes/db_connect.php';
    }
    
    $unread_count = getUnreadNotificationCount($conn);
    $recent_notifications = getUnreadNotifications($conn, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Bite Center - Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
        }
        .sidebar .nav-link:hover {
            color: rgba(255, 255, 255, 1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .notification-dropdown {
            max-height: 400px;
            overflow-y: auto;
            width: 350px;
        }
        .notification-item {
            border-left: 4px solid #ccc;
            transition: all 0.3s ease;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            border-left-color: #007bff;
            background-color: #f0f7ff;
        }
        .notification-item.alert {
            border-left-color: #dc3545;
        }
        .notification-item.warning {
            border-left-color: #ffc107;
        }
        .notification-item.info {
            border-left-color: #17a2b8;
        }
        .notification-item.success {
            border-left-color: #28a745;
        }
        .notification-meta {
            color: #6c757d;
            font-size: 0.75rem;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(25%, -25%);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="admin_dashboard.php">
            <i class="fas fa-paw mr-2"></i>Animal Bite Center
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <?php if ($admin_logged_in): ?>
                    <!-- Notifications Dropdown -->
                    <li class="nav-item dropdown mr-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge badge-danger notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right notification-dropdown" aria-labelledby="notificationsDropdown">
                            <div class="dropdown-header d-flex justify-content-between align-items-center">
                                <span>Notifications</span>
                                <?php if ($unread_count > 0): ?>
                                    <a href="notifications.php?action=mark_all_read" class="text-primary">
                                        <small>Mark all as read</small>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($recent_notifications)): ?>
                                <div class="dropdown-item text-center text-muted">
                                    <i class="fas fa-bell-slash mr-1"></i> No new notifications
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_notifications as $notification): ?>
                                    <a class="dropdown-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo htmlspecialchars($notification['type']); ?>" 
                                       href="notifications.php?action=mark_read&id=<?php echo $notification['id']; ?>">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <?php echo getNotificationIcon($notification['type']); ?>
                                                <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                            </div>
                                            <p class="mb-1 text-truncate"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <div class="notification-meta">
                                                <i class="far fa-clock mr-1"></i> <?php echo formatNotificationDate($notification['created_at']); ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-center" href="notifications.php">
                                    View all notifications
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                    
                    <!-- Admin Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-shield mr-1"></i>
                            <?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin'; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="adminDropdown">
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-cog mr-1"></i> Profile
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cogs mr-1"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt mr-1"></i> Logout
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
