<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/logging_helper.php';

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT name FROM admin WHERE adminId = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Activity logs with pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$filters = [];
if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
if (!empty($_GET['entity_type'])) $filters['entity_type'] = $_GET['entity_type'];
if (!empty($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];

$logs = getActivityLogs($pdo, $filters, $limit, $offset);
$total_logs = getActivityLogCount($pdo, $filters);
$total_pages = ceil($total_logs / $limit);

// Get filter options
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entity_types = $pdo->query("SELECT DISTINCT entity_type FROM activity_logs ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query("SELECT DISTINCT u.user_id, CASE WHEN a.name IS NOT NULL THEN a.name ELSE CONCAT('ID: ', u.user_id) END as display_name FROM activity_logs u LEFT JOIN admin a ON u.user_id = a.adminId ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Logs | Animal Bite Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-light: #e0f2fe;
            --primary-dark: #0284c7;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --bg-light: #f8fafc;
            --success: #10b981;
            --success-light: #d1fae5;
            --error: #ef4444;
            --error-light: #fee2e2;
            --warning-light: #fef3c7;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .page-header {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .page-header .breadcrumb {
            margin-top: 8px;
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
            width: 100%;
            box-sizing: border-box;
        }

        .filters-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .filters-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .filters-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .form-group select,
        .form-group input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.875rem;
            background: #fff;
            color: var(--text-primary);
        }

        .btn-filter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-filter:hover {
            background: var(--primary-dark);
        }

        .btn-clear {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #fff;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-clear:hover {
            border-color: var(--text-secondary);
            color: var(--text-primary);
            text-decoration: none;
        }

        .logs-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .logs-table {
            width: 100%;
            min-width: 800px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .logs-table thead th {
            background: var(--bg-light);
            padding: 16px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .logs-table tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .logs-table tbody td:first-child {
            width: 120px;
        }

        .logs-table tbody td:nth-child(2) {
            width: 150px;
        }

        .logs-table tbody td:nth-child(3) {
            width: 120px;
        }

        .logs-table tbody td:nth-child(4) {
            min-width: 200px;
        }

        .logs-table tbody td:nth-child(5) {
            width: 140px;
        }

        .logs-table tbody tr:hover {
            background: var(--bg-light);
        }

        .log-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            white-space: nowrap;
        }

        .log-action-create { background: var(--success-light); color: var(--success); }
        .log-action-update { background: var(--warning-light); color: var(--warning); }
        .log-action-delete { background: var(--error-light); color: var(--error); }
        .log-action-view { background: var(--primary-light); color: var(--primary); }
        .log-action-login { background: #d1fae5; color: #16a34a; }

        .log-entity {
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--bg-light);
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }

        .log-details {
            max-width: 300px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .log-time {
            white-space: nowrap;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 16px;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .pagination .current {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .logs-summary {
            text-align: center;
            margin-top: 16px;
            padding: 16px;
            background: var(--bg-light);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .logs-empty {
            text-align: center;
            padding: 64px 24px;
            color: var(--text-secondary);
        }

        .logs-empty i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--border);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #fff;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
            text-decoration: none;
        }

        @media (max-width: 1024px) {
            /* Tablet adjustments */
            .main-content {
                padding: 20px;
            }

            .page-header {
                padding: 20px;
            }

            .filters-card {
                padding: 20px;
            }

            .logs-table {
                font-size: 0.85rem;
            }

            .logs-table thead th,
            .logs-table tbody td {
                padding: 14px 12px;
            }

            .log-action {
                padding: 5px 10px;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            /* Small tablet optimizations */
            .main-content {
                padding: 16px;
            }

            .page-header {
                padding: 16px;
                text-align: center;
            }

            .page-header h1 {
                font-size: 1.5rem;
                margin-bottom: 8px;
            }

            .page-header p {
                font-size: 0.85rem;
            }

            .filters-card {
                padding: 16px;
            }

            .filters-header {
                margin-bottom: 12px;
            }

            .filters-header h3 {
                font-size: 1rem;
            }

            .filters-form {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .form-group {
                margin-bottom: 0;
            }

            .btn-filter,
            .btn-clear {
                width: 100%;
                justify-content: center;
                padding: 10px 14px;
                font-size: 0.85rem;
            }

            .logs-table {
                font-size: 0.8rem;
            }

            .logs-table thead th,
            .logs-table tbody td {
                padding: 12px 8px;
            }

            .logs-wrapper {
                margin: 0 -10px;
                border-radius: 6px;
            }

            .log-action {
                font-size: 0.7rem;
                padding: 4px 8px;
            }

            .log-details {
                max-width: 200px;
            }

            .pagination {
                gap: 4px;
                margin-top: 16px;
            }

            .pagination a,
            .pagination span {
                min-width: 32px;
                height: 32px;
                font-size: 0.8rem;
                padding: 0 8px;
            }
        }

        @media (max-width: 640px) {
            /* Large phone adjustments */
            .main-content {
                padding: 12px;
            }

            .page-header {
                padding: 12px;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .filters-card {
                padding: 12px;
            }

            .filters-form {
                gap: 10px;
            }

            .form-group label {
                font-size: 0.8rem;
                margin-bottom: 4px;
            }

            .form-group select,
            .form-group input {
                padding: 8px 10px;
                font-size: 0.8rem;
            }

            .logs-table {
                font-size: 0.75rem;
            }

            .logs-table thead th,
            .logs-table tbody td {
                padding: 10px 6px;
            }

            .log-action {
                font-size: 0.65rem;
                padding: 3px 6px;
            }

            .log-details {
                max-width: 150px;
                font-size: 0.7rem;
            }

            .logs-summary {
                font-size: 0.75rem;
                padding: 12px;
            }
        }

        @media (max-width: 480px) {
            /* Small phone optimizations */
            .main-content {
                padding: 8px;
            }

            .page-header {
                padding: 10px 12px;
                margin-bottom: 15px;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }

            .page-header p {
                font-size: 0.8rem;
            }

            .filters-card {
                padding: 10px;
                margin-bottom: 16px;
            }

            .filters-header {
                margin-bottom: 10px;
            }

            .filters-header h3 {
                font-size: 0.9rem;
            }

            .filters-form {
                gap: 8px;
            }

            .form-group label {
                font-size: 0.75rem;
                margin-bottom: 3px;
            }

            .form-group select,
            .form-group input {
                padding: 8px 10px;
                font-size: 0.8rem;
                height: 36px;
            }

            .btn-filter,
            .btn-clear {
                padding: 8px 12px;
                font-size: 0.8rem;
                height: 36px;
            }

            /* Table becomes card-like on very small screens */
            .logs-table {
                font-size: 0.7rem !important;
                border: none !important;
                background: transparent !important;
            }

            .logs-table thead {
                display: none !important; /* Hide headers on mobile */
            }

            .logs-table tbody tr {
                display: block !important;
                background: #fff !important;
                border: 1px solid var(--border) !important;
                border-radius: 8px !important;
                margin-bottom: 8px !important;
                padding: 12px !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
            }

            .logs-table tbody td {
                display: block !important;
                padding: 4px 0 !important;
                border: none !important;
                text-align: left !important;
                position: relative !important;
                padding-left: 50px !important;
            }

            .logs-table tbody td:before {
                content: attr(data-label) !important;
                position: absolute !important;
                left: 0 !important;
                top: 4px !important;
                font-weight: 600 !important;
                color: var(--text-secondary) !important;
                font-size: 0.7rem !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }

            .logs-table tbody td:first-child:before {
                content: "Action";
            }

            .logs-table tbody td:nth-child(2):before {
                content: "Entity";
            }

            .logs-table tbody td:nth-child(3):before {
                content: "User";
            }

            .logs-table tbody td:nth-child(4):before {
                content: "Details";
            }

            .logs-table tbody td:nth-child(5):before {
                content: "Time";
            }

            .log-action {
                margin-bottom: 4px;
            }

            .log-entity {
                margin-top: 2px;
            }

            .log-details {
                max-width: none;
                word-break: break-word;
                line-height: 1.3;
            }

            .pagination {
                gap: 2px;
                margin-top: 12px;
            }

            .pagination a,
            .pagination span {
                min-width: 28px;
                height: 28px;
                font-size: 0.75rem;
                padding: 0 6px;
            }

            .logs-summary {
                font-size: 0.7rem;
                padding: 10px;
                text-align: center;
            }
        }

        @media (max-width: 360px) {
            /* Extra small phone optimizations */
            .main-content {
                padding: 8px;
            }

            .page-header {
                padding: 8px;
            }

            .page-header h1 {
                font-size: 1rem;
            }

            .filters-card {
                padding: 8px;
            }

            .logs-table tbody tr {
                padding: 8px !important;
                margin-bottom: 6px !important;
            }

            .logs-table tbody td {
                padding-left: 45px !important;
            }

            .logs-table tbody td:before {
                font-size: 0.65rem !important;
                left: 0 !important;
            }

            .log-action {
                font-size: 0.6rem !important;
                padding: 2px 6px !important;
            }

            .pagination a,
            .pagination span {
                min-width: 24px !important;
                height: 24px !important;
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="page-header">
        <h1>Activity Logs</h1>
        <p>Complete audit trail of all system activities</p>
        <div style="margin-top: 8px;">
            <a href="admin_settings.php" class="btn-back">
                <i class="bi bi-arrow-left"></i>
                Back to Settings
            </a>
        </div>
    </div>

    <div class="main-content">
        <!-- Filters -->
        <div class="filters-card">
            <div class="filters-header">
                <h3>Filter Logs</h3>
            </div>
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label for="action">Action</label>
                    <select name="action" id="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo (isset($_GET['action']) && $_GET['action'] === $action) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="entity_type">Entity Type</label>
                    <select name="entity_type" id="entity_type">
                        <option value="">All Entities</option>
                        <?php foreach ($entity_types as $entity): ?>
                        <option value="<?php echo htmlspecialchars($entity); ?>" <?php echo (isset($_GET['entity_type']) && $_GET['entity_type'] === $entity) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($entity); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="user_id">User</label>
                    <select name="user_id" id="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['user_id']); ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['display_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-filter">
                        <i class="bi bi-funnel"></i>
                        Filter
                    </button>
                    <?php if (!empty($_GET)): ?>
                    <a href="activity_logs.php" class="btn-clear">
                        <i class="bi bi-x-circle"></i>
                        Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <?php if (empty($logs)): ?>
        <div class="logs-empty">
            <i class="bi bi-journal-x"></i>
            <p>No activity logs found matching your filters</p>
        </div>
        <?php else: ?>
        <div class="logs-wrapper">
        <table class="logs-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>User</th>
                    <th>Details</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td data-label="Action">
                        <span class="log-action log-action-<?php echo strtolower($log['action']); ?>">
                            <i class="bi <?php
                                $icon = match(strtolower($log['action'])) {
                                    'login' => 'box-arrow-in-right',
                                    'logout' => 'box-arrow-right',
                                    'create' => 'plus-circle',
                                    'update' => 'pencil',
                                    'delete' => 'trash',
                                    'view' => 'eye',
                                    default => 'circle'
                                };
                                echo $icon;
                            ?>"></i>
                            <?php echo htmlspecialchars($log['action']); ?>
                        </span>
                    </td>
                    <td data-label="Entity">
                        <div><?php echo htmlspecialchars($log['entity_type']); ?></div>
                        <?php if ($log['entity_id']): ?>
                        <div class="log-entity">ID: <?php echo htmlspecialchars($log['entity_id']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="User"><?php echo htmlspecialchars(getUserName($pdo, $log['user_id'])); ?></td>
                    <td data-label="Details">
                        <div class="log-details">
                            <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                        </div>
                    </td>
                    <td data-label="Time">
                        <div class="log-time"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
            <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Summary -->
        <div class="logs-summary">
            Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?> total activity logs
            <?php if (!empty($filters)): ?>
            (filtered results)
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
