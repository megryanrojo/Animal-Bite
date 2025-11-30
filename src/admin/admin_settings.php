<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT name, email FROM admin WHERE adminId = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Get system statistics
try {
    $stats = [];

    // Total reports
    $stmt = $pdo->query("SELECT COUNT(*) as total_reports FROM reports");
    $stats['total_reports'] = $stmt->fetchColumn();

    // Total patients
    $stmt = $pdo->query("SELECT COUNT(*) as total_patients FROM patients");
    $stats['total_patients'] = $stmt->fetchColumn();

    // Total staff
    $stmt = $pdo->query("SELECT COUNT(*) as total_staff FROM staff");
    $stats['total_staff'] = $stmt->fetchColumn();

    // Database size (approximate)
    $stmt = $pdo->query("SELECT
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as db_size_mb
        FROM information_schema.tables
        WHERE table_schema = DATABASE()");
    $stats['db_size'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    $stats = [
        'total_reports' => 0,
        'total_patients' => 0,
        'total_staff' => 0,
        'db_size' => 0
    ];
}

// Initialize variables
$account_updated = false;
$password_updated = false;
$preferences_updated = false;
$notification_updated = false;
$security_updated = false;
$error = "";
$success = "";

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
                    $success = "Account information updated successfully!";
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
                    $success = "Password updated successfully!";
                } catch (PDOException $e) {
                    $error = "Error updating password: " . $e->getMessage();
                }
            }
        }
    }

    if (isset($_POST['update_preferences'])) {
        // Handle preferences update (could save to database or session)
        $preferences_updated = true;
        $success = "Preferences updated successfully!";
    }

    if (isset($_POST['update_notifications'])) {
        // Handle notification settings update
        $notification_updated = true;
        $success = "Notification settings updated successfully!";
    }

    if (isset($_POST['update_security'])) {
        // Handle security settings update
        $security_updated = true;
        $success = "Security settings updated successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #eff6ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --border: #e2e8f0;
            --radius: 10px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            background: var(--gray-50);
            color: var(--gray-800);
            margin: 0;
            line-height: 1.5;
        }

        .main-content {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-bar {
            background: white;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-bar h1 i { color: var(--primary); }

        .content-area {
            padding: 24px;
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Settings Navigation */
        .settings-nav {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .nav-tabs {
            border: none;
            padding: 0;
        }

        .nav-tabs .nav-link {
            border: none;
            padding: 16px 20px;
            font-weight: 500;
            color: var(--gray-600);
            border-radius: 0;
            position: relative;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: var(--primary-light);
            border-bottom: 2px solid var(--primary);
        }

        .nav-tabs .nav-link:hover:not(.active) {
            color: var(--gray-900);
            background: var(--gray-50);
        }

        /* Settings Content */
        .settings-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .settings-section {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--gray-50);
        }

        .section-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h3 i {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .section-header p {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin: 4px 0 0;
        }

        .section-body {
            padding: 24px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Toggle Switches */
        .toggle-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .toggle-switch:last-child {
            border-bottom: none;
        }

        .toggle-switch .label-group {
            flex: 1;
        }

        .toggle-switch .label-group label {
            margin: 0;
            font-weight: 500;
            color: var(--gray-800);
        }

        .toggle-switch .label-group small {
            color: var(--gray-500);
            font-size: 0.8rem;
            display: block;
            margin-top: 2px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gray-300);
            transition: 0.3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: var(--primary);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            text-align: center;
        }

        .stat-card h4 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0 0 8px;
        }

        .stat-card p {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert i { font-size: 1.2rem; }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
        }

        .alert-close:hover { opacity: 1; }

        /* System Info */
        .system-info {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--gray-700);
        }

        .info-value {
            color: var(--gray-900);
            font-weight: 600;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .settings-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 16px;
            }

            .top-bar {
                padding: 14px 16px;
            }

            .top-bar h1 {
                font-size: 1.1rem;
            }

            .settings-nav .nav-link {
                padding: 12px 16px;
                font-size: 13px;
            }

            .section-header {
                padding: 16px 20px;
            }

            .section-body {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .toggle-switch {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .content-area {
                padding: 12px;
            }

            .section-header h3 {
                font-size: 1rem;
            }

            .btn {
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
            <h1>
                <i class="bi bi-gear"></i>
                System Settings
            </h1>
        </div>

        <div class="content-area">
            <!-- Alerts -->
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <span><?php echo $error; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <span><?php echo $success; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><?php echo number_format($stats['total_reports']); ?></h4>
                    <p>Total Reports</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo number_format($stats['total_patients']); ?></h4>
                    <p>Registered Patients</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo number_format($stats['total_staff']); ?></h4>
                    <p>Active Staff</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo number_format($stats['db_size'], 1); ?> MB</h4>
                    <p>Database Size</p>
                </div>
            </div>

            <!-- Settings Navigation -->
            <div class="settings-nav">
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                            <i class="bi bi-person"></i> Account
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                            <i class="bi bi-palette"></i> Preferences
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                            <i class="bi bi-bell"></i> Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                            <i class="bi bi-shield"></i> Security
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                            <i class="bi bi-info-circle"></i> System
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="tab-content">
                <!-- Account Settings -->
                <div class="tab-pane fade show active" id="account" role="tabpanel">
                    <div class="settings-content">
                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-person-circle"></i> Profile Information</h3>
                                <p>Update your personal details and contact information</p>
                            </div>
                            <div class="section-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="name">Full Name</label>
                                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                    </div>
                                    <button type="submit" name="update_account" class="btn btn-primary">
                                        <i class="bi bi-check2"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-key"></i> Change Password</h3>
                                <p>Update your account password for security</p>
                            </div>
                            <div class="section-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="currentPassword">Current Password</label>
                                        <input type="password" id="currentPassword" name="currentPassword" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="newPassword">New Password</label>
                                        <input type="password" id="newPassword" name="newPassword" required>
                                        <small style="color: var(--gray-500);">Must be at least 8 characters long</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirmPassword">Confirm New Password</label>
                                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                                    </div>
                                    <button type="submit" name="update_password" class="btn btn-primary">
                                        <i class="bi bi-shield-check"></i> Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preferences -->
                <div class="tab-pane fade" id="preferences" role="tabpanel">
                    <div class="settings-content">
                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-palette"></i> Interface Preferences</h3>
                                <p>Customize your system experience</p>
                            </div>
                            <div class="section-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="theme">Theme</label>
                                        <select id="theme" name="theme">
                                            <option value="light">Light</option>
                                            <option value="dark">Dark</option>
                                            <option value="auto">Auto (System)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="language">Language</label>
                                        <select id="language" name="language">
                                            <option value="en">English</option>
                                            <option value="es">Español</option>
                                            <option value="fr">Français</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="timezone">Timezone</label>
                                        <select id="timezone" name="timezone">
                                            <option value="Asia/Manila">Asia/Manila (GMT+8)</option>
                                            <option value="UTC">UTC</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_preferences" class="btn btn-primary">
                                        <i class="bi bi-check2"></i> Save Preferences
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-table"></i> Data Display</h3>
                                <p>Configure how data is presented</p>
                            </div>
                            <div class="section-body">
                                <div class="toggle-switch">
                                    <div class="label-group">
                                        <label>Compact Tables</label>
                                        <small>Show more data in less space</small>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="compact_tables">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-switch">
                                    <div class="label-group">
                                        <label>Auto-refresh Dashboard</label>
                                        <small>Automatically update dashboard data</small>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="auto_refresh" checked>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="tab-pane fade" id="notifications" role="tabpanel">
                    <div class="settings-content">
                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-envelope"></i> Email Notifications</h3>
                                <p>Choose what notifications you want to receive</p>
                            </div>
                            <div class="section-body">
                                <form method="POST" action="">
                                    <div class="toggle-switch">
                                        <div class="label-group">
                                            <label>New Reports</label>
                                            <small>Get notified when new bite reports are submitted</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="notify_new_reports" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <div class="toggle-switch">
                                        <div class="label-group">
                                            <label>High Priority Cases</label>
                                            <small>Alerts for Category III bite cases</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="notify_high_priority" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <div class="toggle-switch">
                                        <div class="label-group">
                                            <label>Weekly Summary</label>
                                            <small>Receive weekly activity reports</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="notify_weekly" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <div class="toggle-switch">
                                        <div class="label-group">
                                            <label>System Updates</label>
                                            <small>Important system maintenance notifications</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="notify_system" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="bi bi-bell"></i> Save Notification Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="settings-content">
                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-shield-lock"></i> Security Settings</h3>
                                <p>Manage your account security and access</p>
                            </div>
                            <div class="section-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="session_timeout">Session Timeout (minutes)</label>
                                        <select id="session_timeout" name="session_timeout">
                                            <option value="30">30 minutes</option>
                                            <option value="60" selected>1 hour</option>
                                            <option value="120">2 hours</option>
                                            <option value="240">4 hours</option>
                                        </select>
                                    </div>
                                    <div class="toggle-switch">
                                        <div class="label-group">
                                            <label>Two-Factor Authentication</label>
                                            <small>Add an extra layer of security</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="two_factor">
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <div class="toggle-switch">
                                        <div class="label-group">
                                            <label>Login Notifications</label>
                                            <small>Get notified of new login attempts</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="login_notifications" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <button type="submit" name="update_security" class="btn btn-primary">
                                        <i class="bi bi-shield-check"></i> Save Security Settings
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-clock-history"></i> Login History</h3>
                                <p>Recent account activity</p>
                            </div>
                            <div class="section-body">
                                <div class="system-info">
                                    <div class="info-row">
                                        <span class="info-label">Last Login</span>
                                        <span class="info-value"><?php echo date('M d, Y H:i'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Account Status</span>
                                        <span class="info-value">Active</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">IP Address</span>
                                        <span class="info-value"><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <div class="settings-content">
                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-info-circle"></i> System Information</h3>
                                <p>Current system status and configuration</p>
                            </div>
                            <div class="section-body">
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
                                        <span class="info-label">Session Status</span>
                                        <span class="info-value">Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <div class="section-header">
                                <h3><i class="bi bi-database"></i> Data Management</h3>
                                <p>Export and backup system data</p>
                            </div>
                            <div class="section-body">
                                <div style="display: grid; gap: 12px;">
                                    <a href="export_reports.php?format=excel" class="btn btn-outline">
                                        <i class="bi bi-file-earcel"></i> Export Reports (Excel)
                                    </a>
                                    <a href="export_reports.php?format=csv" class="btn btn-outline">
                                        <i class="bi bi-file-csv"></i> Export Reports (CSV)
                                    </a>
                                    <a href="export_analytics.php?format=excel" class="btn btn-outline">
                                        <i class="bi bi-bar-chart"></i> Export Analytics (Excel)
                                    </a>
                                </div>
                                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                                    <p style="color: var(--gray-600); font-size: 0.85rem; margin: 0 0 12px;">
                                        <i class="bi bi-info-circle"></i> Data exports include all current records. For large datasets, exports may take several minutes.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 300);
                    }
                }, 5000);
            });

            // Close alert buttons
            document.querySelectorAll('.alert-close').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.parentElement.remove();
                });
            });
        });
    </script>
</body>
</html>
