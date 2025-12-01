<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/logging_helper.php';

// Ensure barangay_coordinates table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barangay_coordinates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barangay VARCHAR(255) NOT NULL UNIQUE,
            latitude DECIMAL(10, 6) NOT NULL,
            longitude DECIMAL(10, 6) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_barangay (barangay),
            INDEX idx_coordinates (latitude, longitude)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    error_log('Error creating barangay_coordinates table: ' . $e->getMessage());
}

// Handle AJAX requests for barangay management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO barangay_coordinates (barangay, latitude, longitude) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['barangay'], $_POST['latitude'], $_POST['longitude']]);
                echo json_encode(['success' => true, 'message' => 'Barangay added successfully']);
                break;

            case 'update':
                $stmt = $pdo->prepare("UPDATE barangay_coordinates SET barangay = ?, latitude = ?, longitude = ? WHERE barangay = ?");
                $stmt->execute([$_POST['barangay'], $_POST['latitude'], $_POST['longitude'], $_POST['old_barangay']]);
                echo json_encode(['success' => true, 'message' => 'Barangay updated successfully']);
                break;

            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM barangay_coordinates WHERE barangay = ?");
                $stmt->execute([$_POST['barangay']]);
                echo json_encode(['success' => true, 'message' => 'Barangay deleted successfully']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle GET request for barangay list
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_barangays') {
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->query("SELECT barangay, latitude, longitude FROM barangay_coordinates ORDER BY barangay");
        $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($barangays);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT name, email FROM admin WHERE adminId = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$account_updated = false;
$password_updated = false;
$error = "";

// Activity logs - Show only recent logs (no pagination)
$recent_limit = 50; // Show only the 50 most recent logs
$logs = getActivityLogs($pdo, [], $recent_limit, 0);
$total_logs = getActivityLogCount($pdo, []);

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
    <title>Settings | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #fff;
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }

        /* Page Header */
        .page-header {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Alert Messages */
        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .alert-success {
            background: var(--success-light);
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: var(--error-light);
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
            font-size: 1.25rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @media (max-width: 480px) {
            .alert {
                padding: 12px 14px;
                margin-bottom: 16px;
                font-size: 0.8rem;
                gap: 10px;
            }

            .alert i {
                font-size: 1rem;
                flex-shrink: 0;
            }
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 640px) {
            .settings-grid {
                gap: 16px;
            }

            .settings-card {
                border-radius: 10px;
            }

            .card-header {
                padding: 14px 16px;
                border-radius: 10px 10px 0 0;
            }

            .card-body {
                padding: 14px 16px;
            }
        }

        /* Settings Card */
        .settings-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .settings-card.full-width {
            grid-column: 1 / -1;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-light);
        }

        .card-icon {
            width: 44px;
            height: 44px;
            background: var(--primary-light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.25rem;
        }

        .card-header-text h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .card-header-text p {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .card-body {
            padding: 24px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            font-size: 0.9rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--success);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-outline:hover {
            background: var(--bg-light);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: var(--error-light);
            border-color: var(--error);
            color: var(--error);
        }

        /* Coordinate Tip */
        .coordinate-tip {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 12px 16px;
            background: var(--bg-light);
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .coordinate-tip i {
            color: var(--primary);
            font-size: 1rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .coordinate-tip small {
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Barangay Management Responsive */
        @media (max-width: 768px) {
            .barangay-controls {
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }

            .barangay-controls button {
                justify-content: center;
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
            }

            .table-header,
            .barangay-row {
                grid-template-columns: 2fr 1fr 1fr;
            }

            .barangay-row .barangay-actions {
                grid-column: 1 / -1;
                justify-content: center;
                margin-top: 8px;
            }

            .coordinate-tip {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }

            .coordinate-tip small {
                text-align: left;
            }
        }

        /* System Info */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-primary);
        }

        .info-value {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Export Buttons */
        .export-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .export-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .export-btn:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .export-btn i {
            font-size: 1.1rem;
            color: var(--primary);
        }

        .export-note {
            margin-top: 16px;
            padding: 12px 16px;
            background: var(--bg-light);
            border-radius: 8px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        /* Compact Activity Logs */
        .logs-compact {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
        }

        .log-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }

        .log-item:hover {
            background: var(--bg-light);
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-icon {
            flex-shrink: 0;
            margin-top: 2px;
        }

        .log-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .log-action-create { background: var(--success-light); color: var(--success); }
        .log-action-update { background: var(--warning-light); color: var(--warning); }
        .log-action-delete { background: var(--error-light); color: var(--error); }
        .log-action-view { background: var(--primary-light); color: var(--primary); }
        .log-action-login { background: #d1fae5; color: #16a34a; }

        .log-content {
            flex: 1;
            min-width: 0;
        }

        .log-primary {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .log-primary strong {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .log-entity {
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--bg-light);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .log-entity small {
            font-weight: 500;
            opacity: 0.8;
        }

        .log-secondary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }

        .log-user {
            font-weight: 500;
        }

        .log-time {
            white-space: nowrap;
        }

        .log-details {
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.4;
            word-break: break-word;
        }

        .logs-summary {
            margin-top: 8px;
        }

            .logs-footer {
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid var(--border);
                text-align: center;
            }

            /* Info items mobile stack */
            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .info-label {
                font-size: 0.8rem;
            }

            .info-value {
                font-size: 0.75rem;
                align-self: flex-start;
            }

        .btn-view-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-view-all:hover {
            background: var(--primary-dark);
            color: #fff;
            text-decoration: none;
        }

        .logs-empty {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-secondary);
        }

        .logs-empty i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            color: var(--border);
        }

        .logs-empty p {
            font-size: 0.9rem;
        }

        /* Barangay Management */
        .barangay-management {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .barangay-controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .barangay-form {
            background: var(--bg-light);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .barangay-form .form-group {
            margin: 0;
        }

        .barangay-form label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .barangay-form input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.875rem;
            background: #fff;
            transition: border-color 0.2s;
        }

        .barangay-form input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        .barangay-table {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .table-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 120px;
            gap: 16px;
            padding: 14px 20px;
            background: var(--bg-light);
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-body {
            max-height: 400px;
            overflow-y: auto;
        }

        .barangay-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 120px;
            gap: 16px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            align-items: center;
            transition: background 0.15s;
        }

        .barangay-row:last-child {
            border-bottom: none;
        }

        .barangay-row:hover {
            background: var(--bg-light);
        }

        .barangay-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .barangay-coords {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--bg-light);
            padding: 4px 8px;
            border-radius: 4px;
        }

        .barangay-actions {
            display: flex;
            gap: 6px;
        }

        .btn-edit, .btn-delete {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit {
            background: var(--primary);
            color: white;
        }

        .btn-edit:hover {
            background: var(--primary-dark);
        }

        .btn-delete {
            background: var(--error);
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .loading-state {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 40px;
            color: var(--text-secondary);
        }

        .loading-state i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            color: var(--border);
        }

        .empty-state p {
            font-size: 0.9rem;
            margin-bottom: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .table-header,
            .barangay-row {
                grid-template-columns: 2fr 1fr 1fr;
            }

            .barangay-row .barangay-actions {
                grid-column: 1 / -1;
                justify-content: center;
                margin-top: 8px;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .main-content {
                padding: 24px;
            }

            .page-header {
                padding: 20px 24px;
            }
        }

        @media (max-width: 768px) {
            body {
                font-size: 13px;
            }

            .page-header {
                padding: 16px 20px;
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 1.25rem;
                margin-bottom: 4px;
            }

            .page-header p {
                font-size: 0.8rem;
            }

            .main-content {
                padding: 16px;
                max-width: none;
            }

            .settings-grid {
                gap: 16px;
            }

            .card-header {
                padding: 16px 20px;
                gap: 12px;
            }

            .card-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            .card-header-text h3 {
                font-size: 0.9rem;
            }

            .card-header-text p {
                font-size: 0.75rem;
            }

            .card-body {
                padding: 16px 20px;
            }

            /* Form improvements */
            .form-group {
                margin-bottom: 16px;
            }

            .form-group label {
                font-size: 0.75rem;
                margin-bottom: 6px;
            }

            .form-group input,
            .form-group select {
                padding: 10px 14px;
                font-size: 0.875rem;
            }

            .btn-primary {
                width: 100%;
                padding: 12px 16px;
                font-size: 0.875rem;
                justify-content: center;
            }

            /* Alert messages */
            .alert {
                padding: 12px 16px;
                font-size: 0.8rem;
            }

            .alert i {
                font-size: 1rem;
            }

            /* System info */
            .info-list {
                gap: 12px;
            }

            .info-item {
                padding: 12px 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .info-label {
                font-size: 0.75rem;
                opacity: 0.8;
            }

            .info-value {
                font-size: 0.85rem;
                font-weight: 600;
            }

            /* Export buttons */
            .export-grid {
                gap: 12px;
            }

            .export-btn {
                padding: 12px 16px;
                font-size: 0.8rem;
            }

            .export-btn i {
                font-size: 1rem;
            }

            .export-note {
                font-size: 0.75rem;
                padding: 10px 14px;
            }

            /* Activity logs */
            .logs-compact {
                max-height: 400px;
                font-size: 0.8rem;
            }

            .log-item {
                padding: 12px 14px;
                gap: 10px;
                flex-direction: column;
                align-items: flex-start;
            }

            .log-icon {
                align-self: flex-start;
                margin-top: 0;
            }

            .log-content {
                width: 100%;
            }

            .log-action {
                width: 28px;
                height: 28px;
                font-size: 0.7rem;
            }

            .log-primary {
                gap: 6px;
                width: 100%;
                justify-content: space-between;
                align-items: flex-start;
            }

            .log-primary strong {
                font-size: 0.8rem;
                flex: 1;
            }

            .log-entity {
                font-size: 0.7rem;
                padding: 2px 4px;
                white-space: nowrap;
            }

            .log-secondary {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                font-size: 0.7rem;
                margin-top: 4px;
            }

            .log-user {
                flex: 1;
                text-align: left;
            }

            .log-time {
                text-align: right;
                opacity: 0.8;
            }

            .log-details {
                font-size: 0.75rem;
                margin-top: 6px;
                width: 100%;
                padding: 6px 8px;
                background: var(--bg-light);
                border-radius: 4px;
                word-break: break-word;
            }

            .logs-footer {
                margin-top: 12px;
                padding-top: 12px;
            }

            .btn-view-all {
                padding: 8px 14px;
                font-size: 0.8rem;
            }

            .logs-summary {
                padding: 12px;
                font-size: 0.8rem;
            }

            /* Activity logs header */
            .card-header-text {
                flex: 1;
            }

            .logs-summary {
                margin-top: 6px;
                opacity: 0.8;
            }
        }

        @media (max-width: 768px) {
            /* Additional tablet styles */
            .logs-compact {
                max-height: 400px;
            }

            .log-item {
                padding: 12px 14px;
                gap: 10px;
            }

            .log-primary {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
                margin-bottom: 6px;
            }

            .log-entity {
                font-size: 0.75rem;
            }

            .log-details {
                font-size: 0.75rem;
                line-height: 1.3;
                word-break: break-word;
            }
        }

        @media (max-width: 640px) {
            /* Small tablet adjustments */
            .main-content {
                padding: 16px;
            }

            .page-header {
                padding: 16px 20px;
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 480px) {
            body {
                overflow-x: hidden;
            }

            .main-content {
                padding: 12px;
                margin: 0;
                max-width: none;
            }

            .page-header {
                padding: 12px 16px;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }

            .card-body {
                padding: 12px 16px;
            }

            .card-header {
                padding: 12px 16px;
            }

            .settings-grid {
                gap: 12px;
            }

            /* Activity logs mobile optimization */
            .logs-compact {
                max-height: 300px;
                border-radius: 6px;
            }

            .log-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px 12px;
                gap: 8px;
            }

            .log-icon {
                align-self: flex-start;
            }

            .log-primary {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
            }

            .log-primary strong {
                font-size: 0.8rem;
                flex: 1;
            }

            .log-entity {
                font-size: 0.7rem;
                flex-shrink: 0;
                margin-bottom: 0;
            }

            .log-secondary {
                width: 100%;
                justify-content: space-between;
                font-size: 0.7rem;
            }

            .log-user,
            .log-time {
                font-size: 0.7rem;
            }

            .log-details {
                font-size: 0.7rem;
                line-height: 1.3;
                margin-top: 4px;
                padding-top: 4px;
                border-top: 1px solid rgba(0,0,0,0.05);
                width: 100%;
                word-break: break-word;
                overflow-wrap: break-word;
            }

            .info-item {
                padding: 10px 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .info-label {
                font-size: 0.8rem;
            }

            .info-value {
                font-size: 0.75rem;
                align-self: flex-end;
            }

            .export-btn {
                padding: 10px 12px;
                font-size: 0.75rem;
                justify-content: center;
            }

            .export-btn i {
                font-size: 1rem;
            }

            .btn-primary {
                padding: 10px 14px;
                font-size: 0.8rem;
                justify-content: center;
            }

            .btn-view-all {
                font-size: 0.75rem;
                padding: 8px 12px;
            }

            /* Form elements mobile optimization */
            .form-group {
                margin-bottom: 14px;
            }

            .form-group label {
                font-size: 0.75rem;
                margin-bottom: 5px;
            }

            .form-group input,
            .form-group select {
                width: 100%;
                padding: 10px 12px;
                font-size: 0.8rem;
                height: 40px;
            }

            .btn-primary {
                width: 100%;
                padding: 12px 16px;
                font-size: 0.85rem;
                margin-top: 8px;
            }

            /* Export buttons stack on mobile */
            .export-grid {
                flex-direction: column;
                gap: 8px;
            }

            .export-btn {
                width: 100%;
                justify-content: center;
                padding: 12px 16px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="page-header">
        <h1>Settings</h1>
        <p>Manage your account and system preferences</p>
    </div>

    <div class="main-content">
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
                <div class="card-header">
                    <div class="card-icon">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>Account Settings</h3>
                        <p>Update your profile information</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_account" class="btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Save account information changes">
                            <i class="bi bi-check2"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>Change Password</h3>
                        <p>Update your security credentials</p>
                    </div>
                </div>
                <div class="card-body">
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
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" required>
                        </div>
                        <button type="submit" name="update_password" class="btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Change your account password">
                            <i class="bi bi-shield-check"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Information -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>System Information</h3>
                        <p>Current system status and details</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label">System Version</span>
                            <span class="info-value">Animal Bite Center v2.1</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Database</span>
                            <span class="info-value">MySQL <?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Time</span>
                            <span class="info-value"><?php echo date('M d, Y H:i T'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Admin Account</span>
                            <span class="info-value"><?php echo htmlspecialchars($admin['name']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Export -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="bi bi-download"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>Data Export</h3>
                        <p>Download system data for backup</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="export-grid">
                        <a href="export_reports.php?format=excel" class="export-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Download all bite incident reports as Excel spreadsheet">
                            <i class="bi bi-file-earmark-excel"></i>
                            Export Reports (Excel)
                        </a>
                        <a href="export_reports.php?format=csv" class="export-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Download all bite incident reports as CSV file">
                            <i class="bi bi-file-earmark-text"></i>
                            Export Reports (CSV)
                        </a>
                        <a href="export_analytics.php?format=excel" class="export-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Download analytics and statistics as Excel spreadsheet">
                            <i class="bi bi-bar-chart"></i>
                            Export Analytics (Excel)
                        </a>
                    </div>
                    <div class="export-note">
                        <i class="bi bi-info-circle"></i>
                        <span>Data exports include all current records. Large datasets may take time to process.</span>
                    </div>
                </div>
            </div>

            <!-- Barangay Coordinates Management -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>Barangay Coordinates</h3>
                        <p>Manage geographic coordinates for heatmap visualization</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="barangay-management">
                        <div class="barangay-controls">
                            <button type="button" class="btn-secondary" onclick="showAddBarangayForm()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add a new barangay to the coordinate system">
                                <i class="bi bi-plus-circle"></i> Add Barangay
                            </button>
                            <button type="button" class="btn-outline" onclick="loadBarangayList()" data-bs-toggle="tooltip" data-bs-placement="top" title="Reload the barangay list from the database">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>

                        <!-- Add/Edit Barangay Form -->
                        <div class="barangay-form" id="barangayForm" style="display: none;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="barangayName">Barangay Name</label>
                                    <input type="text" id="barangayName" placeholder="Enter barangay name" required>
                                </div>
                                <div class="form-group">
                                    <label for="barangayLat">Latitude</label>
                                    <input type="number" id="barangayLat" step="0.000001" placeholder="e.g., 10.735000" required>
                                </div>
                                <div class="form-group">
                                    <label for="barangayLng">Longitude</label>
                                    <input type="number" id="barangayLng" step="0.000001" placeholder="e.g., 122.966000" required>
                                </div>
                            </div>

                            <!-- Google Maps Tip -->
                            <div class="coordinate-tip">
                                <i class="bi bi-info-circle"></i>
                                <small><strong>Tip:</strong> To get coordinates, right-click on Google Maps at the desired location and select "What's here?" - the coordinates will appear at the bottom.</small>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn-cancel" onclick="hideBarangayForm()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Close form without saving">Cancel</button>
                                <button type="button" class="btn-primary" onclick="saveBarangay()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Add barangay with coordinates to the system">Save Barangay</button>
                            </div>
                        </div>

                        <!-- Barangay List -->
                        <div class="barangay-table">
                            <div class="table-header">
                                <span>Barangay</span>
                                <span>Latitude</span>
                                <span>Longitude</span>
                                <span>Actions</span>
                            </div>
                            <div class="table-body" id="barangayListBody">
                                <div class="loading-state">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    <span>Loading barangays...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Logs -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div class="card-header-text">
                        <h3>Recent Activity</h3>
                        <p>Latest system activities and changes</p>
                        <?php if ($total_logs > $recent_limit): ?>
                        <div class="logs-summary">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i>
                                Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?> total activities
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                    <div class="logs-empty">
                        <i class="bi bi-journal-x"></i>
                        <p>No recent activity found</p>
                    </div>
                    <?php else: ?>
                    <div class="logs-compact">
                        <?php foreach ($logs as $log): ?>
                        <div class="log-item">
                            <div class="log-icon">
                                <span class="log-action log-action-<?php echo strtolower($log['action']); ?>">
                                    <?php
                                    $action_icon = match(strtolower($log['action'])) {
                                        'login' => 'bi-box-arrow-in-right',
                                        'logout' => 'bi-box-arrow-right',
                                        'create' => 'bi-plus-circle',
                                        'update' => 'bi-pencil',
                                        'delete' => 'bi-trash',
                                        'view' => 'bi-eye',
                                        default => 'bi-circle'
                                    };
                                    ?>
                                    <i class="bi <?php echo $action_icon; ?>"></i>
                                </span>
                            </div>
                            <div class="log-content">
                                <div class="log-primary">
                                    <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                    <span class="log-entity"><?php echo htmlspecialchars($log['entity_type']); ?>
                                        <?php if ($log['entity_id']): ?>
                                        <small class="text-muted">#<?php echo htmlspecialchars($log['entity_id']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="log-secondary">
                                    <span class="log-user"><?php echo htmlspecialchars(getUserName($pdo, $log['user_id'])); ?></span>
                                    <span class="log-time"><?php echo date('M d, H:i', strtotime($log['timestamp'])); ?></span>
                                </div>
                                <?php if (!empty($log['details'])): ?>
                                <div class="log-details">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_logs > 0): ?>
                    <div class="logs-footer">
                        <a href="activity_logs.php" class="btn-view-all" data-bs-toggle="tooltip" data-bs-placement="top" title="View complete system activity log">
                            <i class="bi bi-list-ul"></i>
                            View All Activity Logs (<?php echo $total_logs; ?>)
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Barangay management functions
        let editingBarangay = null;

        // Initialize barangay list on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadBarangayList();
        });

        function showAddBarangayForm() {
            editingBarangay = null;
            document.getElementById('barangayName').value = '';
            document.getElementById('barangayLat').value = '';
            document.getElementById('barangayLng').value = '';
            document.getElementById('barangayForm').style.display = 'block';
            document.getElementById('barangayName').focus();
        }

        function hideBarangayForm() {
            document.getElementById('barangayForm').style.display = 'none';
            editingBarangay = null;
        }

        function editBarangay(barangay) {
            editingBarangay = barangay;
            document.getElementById('barangayName').value = barangay.barangay;
            document.getElementById('barangayLat').value = barangay.latitude;
            document.getElementById('barangayLng').value = barangay.longitude;
            document.getElementById('barangayForm').style.display = 'block';
            document.getElementById('barangayName').focus();
        }

        async function saveBarangay() {
            const name = document.getElementById('barangayName').value.trim();
            const lat = parseFloat(document.getElementById('barangayLat').value);
            const lng = parseFloat(document.getElementById('barangayLng').value);

            // Validation
            if (!name) {
                showAlert('Please enter a barangay name.', 'error');
                return;
            }
            if (isNaN(lat) || lat < -90 || lat > 90) {
                showAlert('Please enter a valid latitude (-90 to 90).', 'error');
                return;
            }
            if (isNaN(lng) || lng < -180 || lng > 180) {
                showAlert('Please enter a valid longitude (-180 to 180).', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', editingBarangay ? 'update' : 'add');
            formData.append('barangay', name);
            formData.append('latitude', lat);
            formData.append('longitude', lng);
            if (editingBarangay) {
                formData.append('old_barangay', editingBarangay.barangay);
            }

            try {
                const response = await fetch('admin_settings.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    hideBarangayForm();
                    loadBarangayList();
                    showAlert(result.message, 'success');
                } else {
                    showAlert('Error: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred while saving the barangay.', 'error');
                console.error('Save error:', error);
            }
        }

        async function deleteBarangay(barangayName) {
            if (!confirm(`Are you sure you want to delete "${barangayName}"? This action cannot be undone.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('barangay', barangayName);

            try {
                const response = await fetch('admin_settings.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    loadBarangayList();
                    showAlert(result.message, 'success');
                } else {
                    showAlert('Error: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred while deleting the barangay.', 'error');
                console.error('Delete error:', error);
            }
        }

        async function loadBarangayList() {
            const listBody = document.getElementById('barangayListBody');
            listBody.innerHTML = `
                <div class="loading-state">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Loading barangays...</span>
                </div>
            `;

            try {
                const response = await fetch('admin_settings.php?action=get_barangays');
                const barangays = await response.json();

                if (barangays.length === 0) {
                    listBody.innerHTML = `
                        <div class="empty-state">
                            <i class="bi bi-geo-alt"></i>
                            <p>No barangays configured</p>
                            <button type="button" class="btn-secondary" onclick="showAddBarangayForm()">
                                <i class="bi bi-plus-circle"></i> Add First Barangay
                            </button>
                        </div>
                    `;
                    return;
                }

                listBody.innerHTML = '';
                barangays.forEach(barangay => {
                    const row = document.createElement('div');
                    row.className = 'barangay-row';
                    row.innerHTML = `
                        <div class="barangay-name">${barangay.barangay}</div>
                        <div class="barangay-coords">${parseFloat(barangay.latitude).toFixed(6)}</div>
                        <div class="barangay-coords">${parseFloat(barangay.longitude).toFixed(6)}</div>
                        <div class="barangay-actions">
                            <button class="btn-edit" onclick="editBarangay(${JSON.stringify(barangay).replace(/"/g, '&quot;')})" title="Edit">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn-delete" onclick="deleteBarangay('${barangay.barangay.replace(/'/g, "\\'")}')" title="Delete">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    `;
                    listBody.appendChild(row);
                });
            } catch (error) {
                console.error('Error loading barangay list:', error);
                listBody.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-exclamation-triangle"></i>
                        <p>Error loading barangays</p>
                        <button type="button" class="btn-secondary" onclick="loadBarangayList()">
                            <i class="bi bi-arrow-clockwise"></i> Try Again
                        </button>
                    </div>
                `;
            }
        }

        // Alert function for user feedback
        function showAlert(message, type = 'info') {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert-temp');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-temp`;
            alertDiv.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button class="alert-close" onclick="this.parentElement.remove()">
                    <i class="bi bi-x"></i>
                </button>
            `;

            // Insert at the top of main content
            const mainContent = document.querySelector('.main-content');
            mainContent.insertBefore(alertDiv, mainContent.firstChild);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '../logout/admin_logout.php';
            }
        }
    </script>
</body>
</html>
