<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/notification_helper.php';

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'mark_read':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                markNotificationAsRead($pdo, $_GET['id']);
                $_SESSION['message'] = "Notification marked as read.";
            }
            break;
            
        case 'mark_all_read':
            mark_all_notifications_read($pdo);
            $_SESSION['message'] = "All notifications marked as read.";
            break;
            
        case 'delete':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                delete_notification($pdo, $_GET['id']);
                $_SESSION['message'] = "Notification deleted.";
            }
            break;
            
        case 'delete_all_read':
            delete_all_read_notifications($pdo);
            $_SESSION['message'] = "All read notifications deleted.";
            break;
            
        case 'clear_all':
            clear_all_notifications($pdo);
            $_SESSION['message'] = "All notifications cleared.";
            break;
    }
    
    // Redirect to remove action from URL
    header("Location: notifications.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Filters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$is_read = isset($_GET['is_read']) ? (int)$_GET['is_read'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : '';

$filters = [];
if (!empty($type)) {
    $filters['type'] = $type;
}
if ($is_read !== null) {
    $filters['is_read'] = $is_read;
}
if (!empty($search)) {
    $filters['search'] = $search;
}

// Get notifications
try {
    $query = "SELECT n.*, 
              CASE 
                WHEN n.type = 'info' THEN 'bi-info-circle'
                WHEN n.type = 'warning' THEN 'bi-exclamation-triangle'
                WHEN n.type = 'danger' THEN 'bi-exclamation-circle'
                WHEN n.type = 'success' THEN 'bi-check-circle'
                ELSE 'bi-bell'
              END as icon
              FROM notifications n 
              WHERE 1=1";
    $params = [];

    if (!empty($type)) {
        $query .= " AND n.type = ?";
        $params[] = $type;
    }
    if ($is_read !== null) {
        $query .= " AND n.is_read = ?";
        $params[] = $is_read;
    }
    if (!empty($search)) {
        $query .= " AND (n.title LIKE ? OR n.message LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $recordsPerPage;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM notifications n WHERE 1=1";
    $countParams = [];

    if (!empty($type)) {
        $countQuery .= " AND n.type = ?";
        $countParams[] = $type;
    }
    if ($is_read !== null) {
        $countQuery .= " AND n.is_read = ?";
        $countParams[] = $is_read;
    }
    if (!empty($search)) {
        $countQuery .= " AND (n.title LIKE ? OR n.message LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalNotifications = $countStmt->fetchColumn();
    $totalPages = ceil($totalNotifications / $recordsPerPage);

    // Get notification stats
    $statsQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'danger' THEN 1 ELSE 0 END) as danger,
        SUM(CASE WHEN type = 'success' THEN 1 ELSE 0 END) as success
        FROM notifications";
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $notifications = [];
    $totalNotifications = 0;
    $totalPages = 0;
    $stats = [
        'total' => 0,
        'unread' => 0,
        'danger' => 0,
        'success' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | Admin Portal</title>
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
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .nav-link.active {
            color: var(--bs-primary) !important;
            font-weight: 500;
        }
        
        .notifications-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .page-header {
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
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .filter-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .notification-list {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .notification-item {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        
        .notification-item:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        
        .notification-item.unread {
            background-color: rgba(var(--bs-primary-rgb), 0.05);
            border-left: 4px solid var(--bs-primary);
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .notification-icon.info { background-color: rgba(13, 202, 240, 0.2); color: #0dcaf0; }
        .notification-icon.warning { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .notification-icon.danger { background-color: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .notification-icon.success { background-color: rgba(25, 135, 84, 0.2); color: #198754; }
        
        .notification-content {
            flex-grow: 1;
            margin-left: 1rem;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            color: #6c757d;
        }
        
        .notification-time {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .notification-actions {
            margin-left: 1rem;
        }
        
        .pagination {
            margin-top: 1.5rem;
            justify-content: center;
        }
        
        .page-link {
            color: var(--bs-primary);
            border-radius: 0.25rem;
            margin: 0 0.25rem;
        }
        
        .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: 3rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .notifications-container {
                padding: 1rem;
            }
            
            .page-header, .filter-card, .stats-card {
                padding: 1rem;
            }
            
            .notification-actions {
                margin-top: 1rem;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Include the navbar with notifications -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="notifications-container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Notifications</h2>
                    <p class="text-muted mb-0">View and manage all system notifications</p>
                </div>
                <div class="d-flex">
                    <?php if ($stats['unread'] > 0): ?>
                    <a href="notifications.php?action=mark_all_read" class="btn btn-outline-primary me-2">
                        <i class="bi bi-check-all me-2"></i>Mark All as Read
                    </a>
                    <?php endif; ?>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i> Actions
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                            <li><a class="dropdown-item" href="notifications.php?action=delete_all_read" onclick="return confirm('Are you sure you want to delete all read notifications?');">Delete All Read Notifications</a></li>
                            <li><a class="dropdown-item" href="notifications.php?action=clear_all" onclick="return confirm('Are you sure you want to clear all notifications? This action cannot be undone.');">Clear All Notifications</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i> <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Total</h5>
                            <div class="stats-number"><?php echo $stats['total']; ?></div>
                            <p class="text-muted mb-0">Notifications</p>
                        </div>
                        <div class="fs-1 text-primary">
                            <i class="bi bi-bell"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Unread</h5>
                            <div class="stats-number text-primary"><?php echo $stats['unread']; ?></div>
                            <p class="text-muted mb-0">Notifications</p>
                        </div>
                        <div class="fs-1 text-primary">
                            <i class="bi bi-envelope"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Alerts</h5>
                            <div class="stats-number text-danger"><?php echo $stats['danger']; ?></div>
                            <p class="text-muted mb-0">Critical notifications</p>
                        </div>
                        <div class="fs-1 text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Updates</h5>
                            <div class="stats-number text-success"><?php echo $stats['success']; ?></div>
                            <p class="text-muted mb-0">System updates</p>
                        </div>
                        <div class="fs-1 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="filter-card">
            <form method="GET" action="notifications.php" class="row g-3">
                <div class="col-md-4">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All Types</option>
                        <option value="info" <?php echo $type === 'info' ? 'selected' : ''; ?>>Information</option>
                        <option value="warning" <?php echo $type === 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="danger" <?php echo $type === 'danger' ? 'selected' : ''; ?>>Alert</option>
                        <option value="success" <?php echo $type === 'success' ? 'selected' : ''; ?>>Success</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="is_read" class="form-label">Status</label>
                    <select class="form-select" id="is_read" name="is_read">
                        <option value="">All Statuses</option>
                        <option value="0" <?php echo $is_read === 0 ? 'selected' : ''; ?>>Unread</option>
                        <option value="1" <?php echo $is_read === 1 ? 'selected' : ''; ?>>Read</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search notifications" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-12 d-flex">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-filter me-2"></i>Apply Filters
                    </button>
                    <a href="notifications.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
        
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 mb-0 text-muted">No notifications found.</p>
            </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                    <div class="d-flex flex-wrap">
                        <div class="notification-icon <?php echo $notification['type']; ?>">
                            <i class="bi bi-<?php echo htmlspecialchars($notification['icon']); ?> fs-4"></i>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                            <div class="notification-time">
                                <i class="bi bi-clock me-1"></i> <?php echo format_notification_date($notification['created_at']); ?>
                            </div>
                        </div>
                        
                        <div class="notification-actions d-flex">
                            <?php if (!$notification['is_read']): ?>
                            <a href="notifications.php?action=mark_read&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary me-2" title="Mark as Read">
                                <i class="bi bi-check"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($notification['link'])): ?>
                            <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-sm btn-outline-secondary me-2" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php endif; ?>
                            
                            <a href="notifications.php?action=delete&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => '', 'action' => ''])) : ''; ?>" aria-label="First">
                        <span aria-hidden="true">&laquo;&laquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => '', 'action' => ''])) : ''; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => '', 'action' => ''])) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => '', 'action' => ''])) : ''; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => '', 'action' => ''])) : ''; ?>" aria-label="Last">
                        <span aria-hidden="true">&raquo;&raquo;</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <div class="text-center mt-3">
            <p class="text-muted">
                Showing <?php echo min(($page - 1) * $recordsPerPage + 1, $totalNotifications); ?> to 
                <?php echo min($page * $recordsPerPage, $totalNotifications); ?> of 
                <?php echo $totalNotifications; ?> notifications
            </p>
        </div>
    </div>
    
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
    <script>
        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '../logout/admin_logout.php';
            }
        }
    </script>
</body>
</html>
