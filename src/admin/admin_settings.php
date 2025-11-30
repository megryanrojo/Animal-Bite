<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/logging_helper.php';

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT name, email FROM admin WHERE adminId = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$account_updated = false;
$password_updated = false;
$error = "";

// Activity logs
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get activity logs with pagination
$logs = getActivityLogs($pdo, [], $limit, $offset);
$total_logs = getActivityLogCount($pdo, []);
$total_pages = ceil($total_logs / $limit);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_account'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);

        if (empty($name) || empty($email)) {
            $error = "Name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $stmt = $pdo->prepare("SELECT adminId FROM admin WHERE email = ? AND adminId != ?");
            $stmt->execute([$email, $admin_id]);
            $result = $stmt->fetch();

            if ($result) {
                $error = "Email already in use by another admin.";
            } else {
                $stmt = $pdo->prepare("UPDATE admin SET name = ?, email = ? WHERE adminId = ?");

                try {
                    $stmt->execute([$name, $email, $admin_id]);
                    $account_updated = true;
                    $_SESSION['admin_name'] = $name;
                    $admin['name'] = $name;
                    $admin['email'] = $email;

                    // Log account update
                    logActivity($pdo, 'UPDATE', 'admin', $admin_id, $admin_id, "Updated account information: name='$name', email='$email'");
                } catch (PDOException $e) {
                    $error = "Error updating account: " . $e->getMessage();
                }
            }
        }
    }

    if (isset($_POST['update_password'])) {
        $current_password = $_POST['currentPassword'];
        $new_password = $_POST['newPassword'];
        $confirm_password = $_POST['confirmPassword'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($new_password != $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $stmt = $pdo->prepare("SELECT password FROM admin WHERE adminId = ?");
            $stmt->execute([$admin_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($current_password, $row['password'])) {
                $error = "Current password is incorrect.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE adminId = ?");

                try {
                    $stmt->execute([$hashed_password, $admin_id]);
                    $password_updated = true;

                    // Log password change
                    logActivity($pdo, 'UPDATE', 'admin', $admin_id, $admin_id, "Password changed");
                } catch (PDOException $e) {
                    $error = "Error updating password: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Settings | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #4361ee;
            --primary-hover: #3a56d4;
            --bg-color: #f0f2f5;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --border-color: #e0e0e0;
            --success-color: #10b981;
            --error-color: #ef4444;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            background-color: var(--bg-color);
            color: var(--text-primary);
        }

        .main-content {
            margin-left: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-bar {
            background: var(--card-bg);
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }

        .top-bar .subtitle {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .content-area {
            padding: 24px;
            flex: 1;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .settings-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .settings-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-card-header .icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .settings-card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }

        .settings-card-header p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .settings-card-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group:last-of-type {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafafa;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            background: white;
        }

        .btn-save {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: var(--primary-hover);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1.1rem;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* System Info Section */
        .system-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-primary);
        }

        .info-value {
            color: var(--text-secondary);
        }

        /* Export buttons */
        .export-section {
            margin-top: 20px;
        }

        .export-buttons {
            display: grid;
            gap: 10px;
        }

        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .export-btn:hover {
            background: var(--bg-color);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Logs Table Styles */
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .logs-table thead th {
            background: var(--bg-color);
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
        }

        .logs-table tbody td {
            padding: 12px 8px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }

        .logs-table tbody tr:hover {
            background: var(--bg-color);
        }

        .log-action {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .log-action-create { background: #ecfdf5; color: #065f46; }
        .log-action-update { background: #fef3c7; color: #92400e; }
        .log-action-delete { background: #fef2f2; color: #991b1b; }
        .log-action-view { background: #eff6ff; color: #1e40af; }
        .log-action-login { background: #f0fdf4; color: #166534; }

        .log-entity {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .log-details {
            color: var(--text-secondary);
            font-size: 0.8rem;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .log-timestamp {
            color: var(--text-secondary);
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
        }

        .pagination a, .pagination span {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 0.85rem;
        }

        .pagination a:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .logs-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 14px 16px;
            }

            .content-area {
                padding: 16px;
            }

            .settings-card-header {
                padding: 14px 16px;
            }

            .settings-card-body {
                padding: 16px;
            }

            .form-group input {
                padding: 12px 14px;
            }

            .btn-save {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>Settings</h1>
                <p class="subtitle">Manage your account and system preferences</p>
            </div>
        </div>

        <div class="content-area">
            <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <?php if ($account_updated): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <span>Account information updated successfully!</span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <?php if ($password_updated): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <span>Password updated successfully!</span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Account Settings -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="icon">
                            <i class="bi bi-person"></i>
                        </div>
                        <div>
                            <h3>Account Settings</h3>
                            <p>Update your profile information</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                            <button type="submit" name="update_account" class="btn-save">
                                <i class="bi bi-check2"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="icon">
                            <i class="bi bi-key"></i>
                        </div>
                        <div>
                            <h3>Change Password</h3>
                            <p>Update your security credentials</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <input type="password" id="currentPassword" name="currentPassword" required>
                            </div>
                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <input type="password" id="newPassword" name="newPassword" required>
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" required>
                            </div>
                            <button type="submit" name="update_password" class="btn-save">
                                <i class="bi bi-shield-check"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Information -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="icon">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div>
                            <h3>System Information</h3>
                            <p>Current system status and details</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <div class="system-info">
                            <div class="info-row">
                                <span class="info-label">System Version</span>
                                <span class="info-value">Animal Bite Center v2.1</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">PHP Version</span>
                                <span class="info-value"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Database</span>
                                <span class="info-value">MySQL <?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Server Time</span>
                                <span class="info-value"><?php echo date('M d, Y H:i T'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Admin Account</span>
                                <span class="info-value"><?php echo htmlspecialchars($admin['name']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Export -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="icon">
                            <i class="bi bi-download"></i>
                        </div>
                        <div>
                            <h3>Data Export</h3>
                            <p>Download system data for backup</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <div class="export-section">
                            <div class="export-buttons">
                                <a href="export_reports.php?format=excel" class="export-btn">
                                    <i class="bi bi-file-earcel"></i> Export Reports (Excel)
                                </a>
                                <a href="export_reports.php?format=csv" class="export-btn">
                                    <i class="bi bi-file-csv"></i> Export Reports (CSV)
                                </a>
                                <a href="export_analytics.php?format=excel" class="export-btn">
                                    <i class="bi bi-bar-chart"></i> Export Analytics (Excel)
                                </a>
                            </div>
                            <p style="margin-top: 16px; font-size: 0.8rem; color: var(--text-secondary);">
                                <i class="bi bi-info-circle"></i> Data exports include all current records. Large datasets may take time to process.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Activity Logs -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="icon">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div>
                            <h3>Activity Logs</h3>
                            <p>Recent system activity and audit trail</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <?php if (empty($logs)): ?>
                        <div class="logs-empty">
                            <i class="bi bi-journal-x" style="font-size: 2rem; color: var(--text-secondary);"></i>
                            <p>No activity logs found</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
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
                                        <td>
                                            <span class="log-action log-action-<?php echo strtolower($log['action']); ?>">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($log['entity_type']); ?></div>
                                            <?php if ($log['entity_id']): ?>
                                            <div class="log-entity">ID: <?php echo htmlspecialchars($log['entity_id']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(getUserName($pdo, $log['user_id'])); ?></td>
                                        <td>
                                            <div class="log-details" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($log['details'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="log-timestamp"><?php echo date('M d, H:i', strtotime($log['timestamp'])); ?></div>
                                            <div class="log-entity"><?php echo date('Y', strtotime($log['timestamp'])); ?></div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'current' : ''; ?>" style="pointer-events: <?php echo $i === $page ? 'none' : 'auto'; ?>;"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div style="margin-top: 16px; font-size: 0.8rem; color: var(--text-secondary);">
                            <i class="bi bi-info-circle"></i> Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?> total activities
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
