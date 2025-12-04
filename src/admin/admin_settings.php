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
    <link rel="stylesheet" href="../css/admin/admin-settings.css">
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
