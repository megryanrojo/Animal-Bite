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

$records_pages = ['view_reports.php', 'create_report.php', 'view_patients.php'];
$is_records_active = in_array($current_page, $records_pages);
?>

    <link rel="stylesheet" href="../css/system-theme.css">

    <!-- Bootstrap Tooltip initialization (Bootstrap JS loaded by individual pages) -->
    <script>
        // Initialize tooltips when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Bootstrap is available before initializing tooltips
            if (typeof bootstrap !== 'undefined') {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });
    </script>

<style>
    .navbar {
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        padding: 0.5rem 0;
    }
    .navbar-brand {
        color: #2c7be5 !important;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .nav-link {
        color: #525f7f !important;
        font-size: 0.875rem;
        padding: 0.5rem 0.875rem !important;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .nav-link:hover {
        color: #2c7be5 !important;
        background: #f0f7ff;
    }
    .nav-link.active {
        color: #2c7be5 !important;
        background: #e8f2ff;
        font-weight: 500;
    }
    .nav-link i {
        font-size: 1rem;
        vertical-align: -2px;
    }
    .dropdown-menu {
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        border-radius: 8px;
        padding: 0.5rem;
        min-width: 180px;
    }
    .dropdown-item {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        color: #525f7f;
    }
    .dropdown-item:hover {
        background: #f0f7ff;
        color: #2c7be5;
    }
    .dropdown-item.active {
        background: #e8f2ff;
        color: #2c7be5;
    }
    .btn-logout {
        background: #fee2e2 !important;
        color: #dc2626 !important;
    }
    .btn-logout:hover {
        background: #fecaca !important;
    }
    .dropdown-toggle::after {
        margin-left: 0.4rem;
        vertical-align: 1px;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand" href="admin_dashboard.php">
            <i class="bi bi-shield-plus me-2"></i>Animal Bite Center
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-1">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>" href="admin_dashboard.php" data-bs-toggle="tooltip" data-bs-placement="bottom" title="View system overview and statistics">
                        <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                </li>
                
                <!-- Combined Patients & Reports into single "Records" dropdown, removed Add Patient -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo $is_records_active ? 'active' : ''; ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-folder me-1"></i>Records
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo $current_page == 'view_patients.php' ? 'active' : ''; ?>" href="view_patients.php"><i class="bi bi-people me-2"></i>Patients</a></li>
                        <li><a class="dropdown-item <?php echo $current_page == 'view_reports.php' ? 'active' : ''; ?>" href="view_reports.php"><i class="bi bi-file-text me-2"></i>Reports</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?php echo $current_page == 'create_report.php' ? 'active' : ''; ?>" href="new_report.php"><i class="bi bi-plus-circle me-2"></i>New Report</a></li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'geomapping.php' ? 'active' : ''; ?>" href="geomapping.php" data-bs-toggle="tooltip" data-bs-placement="bottom" title="View geographical distribution of bite incidents">
                        <i class="bi bi-geo-alt me-1"></i>Geomapping
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'decisionSupport.php' ? 'active' : ''; ?>" href="decisionSupport.php" data-bs-toggle="tooltip" data-bs-placement="bottom" title="View analytics and decision support reports">
                        <i class="bi bi-bar-chart me-1"></i>Analytics
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'view_staff.php' ? 'active' : ''; ?>" href="view_staff.php" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Manage staff accounts and permissions">
                        <i class="bi bi-person-badge me-1"></i>Staff
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>" href="admin_settings.php" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Configure system settings and preferences">
                        <i class="bi bi-gear me-1"></i>Settings
                    </a>
                </li>

                <li class="nav-item ms-2">
                    <button class="nav-link btn-logout" onclick="confirmLogout()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Sign out of the system">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>
