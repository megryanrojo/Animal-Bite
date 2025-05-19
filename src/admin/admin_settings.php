<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/notification_helper.php';

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT firstName, lastName, email FROM admin WHERE adminId = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$account_updated = false;
$password_updated = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_account'])) {
        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        
        $stmt = $pdo->prepare("SELECT adminId FROM admin WHERE email = ? AND adminId != ?");
        $stmt->execute([$email, $admin_id]);
        $result = $stmt->fetch();
        
        if ($result) {
            $error = "Email already in use by another admin.";
        } else {
            // Update admin information
            $stmt = $pdo->prepare("UPDATE admin SET firstName = ?, lastName = ?, email = ? WHERE adminId = ?");
            
            try {
                $stmt->execute([$firstName, $lastName, $email, $admin_id]);
                $account_updated = true;
                // Update session data
                $_SESSION['admin_name'] = $firstName . ' ' . $lastName;
                // Refresh admin data
                $admin['firstName'] = $firstName;
                $admin['lastName'] = $lastName;
                $admin['email'] = $email;
            } catch (PDOException $e) {
                $error = "Error updating account: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['update_password'])) {
        $current_password = $_POST['currentPassword'];
        $new_password = $_POST['newPassword'];
        $confirm_password = $_POST['confirmPassword'];
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM admin WHERE adminId = ?");
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $row['password'])) {
            $error = "Current password is incorrect.";
        } else if ($new_password != $confirm_password) {
            $error = "New passwords do not match.";
        } else if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE adminId = ?");
            
            try {
                $stmt->execute([$hashed_password, $admin_id]);
                $password_updated = true;
            } catch (PDOException $e) {
                $error = "Error updating password: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Settings</title>
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
    
    .settings-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }
    
    .navbar {
      background-color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .nav-link.active {
            color: var(--bs-primary) !important;
            font-weight: 500;
    }
    
    .page-header {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .settings-card {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 1.5rem;
      overflow: hidden;
    }
    
    .settings-card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      background-color: rgba(var(--bs-primary-rgb), 0.03);
    }
    
    .settings-card-body {
      padding: 1.5rem;
    }
    
    .settings-icon {
      font-size: 1.5rem;
      color: var(--bs-primary);
      margin-right: 0.75rem;
    }
    
    .btn-logout {
      background-color: #dc3545;
      color: white;
      border: none;
      padding: 0.5rem 1.25rem;
      border-radius: 5px;
      transition: all 0.2s;
    }
    
    .btn-logout:hover {
      background-color: #bb2d3b;
      color: white;
    }
    
    .nav-pills .nav-link.active {
      background-color: var(--bs-primary);
    }
    
    .nav-pills .nav-link {
      color: #495057;
      padding: 0.75rem 1rem;
      margin-bottom: 0.5rem;
    }
    
    .nav-pills .nav-link:hover:not(.active) {
      background-color: rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .form-check-input:checked {
      background-color: var(--bs-primary);
      border-color: var(--bs-primary);
    }
    
    /* Notification Dropdown Styles */
    .notification-dropdown {
        min-width: 320px;
        max-width: 320px;
        max-height: 400px;
        overflow-y: auto;
        padding: 0;
    }
    
    .dropdown-header {
        background-color: #f8f9fa;
        padding: 0.75rem 1rem;
        font-weight: 600;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .dropdown-footer {
        background-color: #f8f9fa;
        padding: 0.75rem 1rem;
        text-align: center;
        font-weight: 500;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .notification-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background-color 0.2s;
    }
    
    .notification-item:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.03);
    }
    
    .notification-item.unread {
        background-color: rgba(var(--bs-primary-rgb), 0.05);
    }
    
    .notification-icon {
        width: 36px;
        height: 36px;
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
        margin-left: 0.75rem;
        overflow: hidden;
    }
    
    .notification-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .notification-message {
        font-size: 0.875rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .notification-time {
        font-size: 0.75rem;
        color: #6c757d;
    }
    
    .badge-counter {
        position: absolute;
        top: 0px;
        right: 0px;
        transform: translate(25%, -25%);
    }
    
    @media (max-width: 768px) {
      .settings-container {
        padding: 1rem;
      }
      
      .page-header {
        padding: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Include the navbar -->
  <?php include 'includes/navbar.php'; ?>

  <div class="settings-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="mb-0">Settings</h2>
          <p class="text-muted mb-0">Manage your system preferences and configurations</p>
        </div>
      </div>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo $error; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($account_updated): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Account information updated successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($password_updated): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Password updated successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row">
      <!-- Settings Navigation -->
      <div class="col-md-3 mb-4">
        <div class="settings-card">
          <div class="settings-card-body p-0">
            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
              <button class="nav-link active" id="account-tab" data-bs-toggle="pill" data-bs-target="#account" type="button" role="tab">
                <i class="bi bi-person settings-icon"></i> Account Settings
              </button>
              <button class="nav-link" id="system-tab" data-bs-toggle="pill" data-bs-target="#system" type="button" role="tab">
                <i class="bi bi-gear settings-icon"></i> System Configuration
              </button>
              <button class="nav-link" id="notifications-tab" data-bs-toggle="pill" data-bs-target="#notifications" type="button" role="tab">
                <i class="bi bi-bell settings-icon"></i> Notifications
              </button>
              <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                <i class="bi bi-shield-lock settings-icon"></i> Security
              </button>
              <button class="nav-link" id="data-tab" data-bs-toggle="pill" data-bs-target="#data" type="button" role="tab">
                <i class="bi bi-database settings-icon"></i> Data Management
              </button>
              <button class="nav-link" id="location-tab" data-bs-toggle="pill" data-bs-target="#location" type="button" role="tab">
                <i class="bi bi-geo-alt settings-icon"></i> Location Settings
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Settings Content -->
      <div class="col-md-9">
        <div class="tab-content" id="v-pills-tabContent">
          <!-- Account Settings -->
          <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
            <div class="settings-card">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-person settings-icon"></i>Account Settings</h5>
              </div>
              <div class="settings-card-body">
                <form method="POST" action="">
                  <div class="mb-3">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($admin['firstName']); ?>" required>
                  </div>
                  <div class="mb-3">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($admin['lastName']); ?>" required>
                  </div>
                  <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                  </div>
                  <button type="submit" name="update_account" class="btn btn-primary">Save Changes</button>
                </form>
              </div>
            </div>
            
            <div class="settings-card mt-4">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-key settings-icon"></i>Change Password</h5>
              </div>
              <div class="settings-card-body">
                <form method="POST" action="">
                  <div class="mb-3">
                    <label for="currentPassword" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                  </div>
                  <div class="mb-3">
                    <label for="newPassword" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                  </div>
                  <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                  </div>
                  <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                </form>
              </div>
            </div>
          </div>
          
          <!-- System Configuration -->
          <div class="tab-pane fade" id="system" role="tabpanel" aria-labelledby="system-tab">
            <div class="settings-card">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-gear settings-icon"></i>System Configuration</h5>
              </div>
              <div class="settings-card-body">
                <!-- To make this section functional, you would need to create a system_settings table -->
                <form method="POST" action="">
                  <div class="mb-3">
                    <label for="systemName" class="form-label">System Name</label>
                    <input type="text" class="form-control" id="systemName" name="systemName" value="Barangay Health Workers Management System">
                  </div>
                  <div class="mb-3">
                    <label for="recordsPerPage" class="form-label">Records Per Page</label>
                    <select class="form-select" id="recordsPerPage" name="recordsPerPage">
                      <option value="10">10</option>
                      <option value="25" selected>25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="dateFormat" class="form-label">Date Format</label>
                    <select class="form-select" id="dateFormat" name="dateFormat">
                      <option value="mm/dd/yyyy">MM/DD/YYYY</option>
                      <option value="dd/mm/yyyy" selected>DD/MM/YYYY</option>
                      <option value="yyyy-mm-dd">YYYY-MM-DD</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="timeFormat" class="form-label">Time Format</label>
                    <select class="form-select" id="timeFormat" name="timeFormat">
                      <option value="12" selected>12-hour (AM/PM)</option>
                      <option value="24">24-hour</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="language" class="form-label">System Language</label>
                    <select class="form-select" id="language" name="language">
                      <option value="en" selected>English</option>
                      <option value="fil">Filipino</option>
                      <option value="ceb">Cebuano</option>
                    </select>
                  </div>
                  <button type="submit" name="update_system" class="btn btn-primary">Save Configuration</button>
                </form>
              </div>
            </div>
            
            <div class="settings-card mt-4">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-palette settings-icon"></i>Appearance</h5>
              </div>
              <div class="settings-card-body">
                <form>
                  <div class="mb-3">
                    <label class="form-label">Theme</label>
                    <div class="d-flex gap-3">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="lightTheme" checked>
                        <label class="form-check-label" for="lightTheme">
                          Light
                        </label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="darkTheme">
                        <label class="form-check-label" for="darkTheme">
                          Dark
                        </label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="systemTheme">
                        <label class="form-check-label" for="systemTheme">
                          System Default
                        </label>
                      </div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="primaryColor" class="form-label">Primary Color</label>
                    <input type="color" class="form-control form-control-color" id="primaryColor" value="#0d6efd" title="Choose primary color">
                  </div>
                  <button type="submit" class="btn btn-primary">Save Appearance</button>
                </form>
              </div>
            </div>
          </div>
          
          <!-- Notifications -->
          <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
            <div class="settings-card">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-bell settings-icon"></i>Notification Preferences</h5>
              </div>
              <div class="settings-card-body">
                <form>
                  <div class="mb-4">
                    <h6 class="mb-3">Email Notifications</h6>
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" id="newReportEmail" checked>
                      <label class="form-check-label" for="newReportEmail">New bite reports</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" id="staffRegistrationEmail" checked>
                      <label class="form-check-label" for="staffRegistrationEmail">Staff registration</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" id="systemUpdatesEmail">
                      <label class="form-check-label" for="systemUpdatesEmail">System updates</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" id="urgentCasesEmail" checked>
                      <label class="form-check-label" for="urgentCasesEmail">Urgent cases</label>
                    </div>
                  </div>
                  
                  <div class="mb-4">
                    <h6 class="mb-3">SMS Notifications</h6>
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" id="urgentCasesSMS" checked>
                      <label class="form-check-label" for="urgentCasesSMS">Urgent cases only</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" id="dailySummarySMS">
                      <label class="form-check-label" for="dailySummarySMS">Daily summary</label>
                    </div>
                  </div>
                  
                  <div class="mb-3">
                    <h6 class="mb-3">Notification Frequency</h6>
                    <select class="form-select" id="notificationFrequency">
                      <option value="immediate" selected>Immediate</option>
                      <option value="hourly">Hourly Digest</option>
                      <option value="daily">Daily Digest</option>
                    </select>
                  </div>
                  
                  <button type="submit" class="btn btn-primary">Save Preferences</button>
                </form>
              </div>
            </div>
          </div>
          
          <!-- Security -->
          <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
            <div class="settings-card">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-shield-lock settings-icon"></i>Security Settings</h5>
              </div>
              <div class="settings-card-body">
                <form>
                  <div class="mb-4">
                    <h6 class="mb-3">Two-Factor Authentication</h6>
                    <div class="form-check form-switch mb-3">
                      <input class="form-check-input" type="checkbox" id="enable2FA">
                      <label class="form-check-label" for="enable2FA">Enable Two-Factor Authentication</label>
                    </div>
                    <p class="text-muted small">When enabled, you'll be required to enter a verification code sent to your phone in addition to your password when logging in.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm">Set Up Two-Factor Authentication</button>
                  </div>
                  
                  <div class="mb-4">
                    <h6 class="mb-3">Session Settings</h6>
                    <div class="mb-3">
                      <label for="sessionTimeout" class="form-label">Session Timeout (minutes)</label>
                      <select class="form-select" id="sessionTimeout">
                        <option value="15">15 minutes</option>
                        <option value="30" selected>30 minutes</option>
                        <option value="60">1 hour</option>
                        <option value="120">2 hours</option>
                      </select>
                    </div>
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" id="rememberDevice" checked>
                      <label class="form-check-label" for="rememberDevice">
                        Remember this device for 30 days
                      </label>
                    </div>
                  </div>
                  
                  <div class="mb-4">
                    <h6 class="mb-3">Login History</h6>
                    <p class="text-muted small">View your recent login activity</p>
                    <button type="button" class="btn btn-outline-primary btn-sm">View Login History</button>
                  </div>
                  
                  <button type="submit" class="btn btn-primary">Save Security Settings</button>
                </form>
              </div>
            </div>
          </div>
          
          <!-- Data Management -->
          <div class="tab-pane fade" id="data" role="tabpanel" aria-labelledby="data-tab">
            <div class="settings-card">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-database settings-icon"></i>Data Management</h5>
              </div>
              <div class="settings-card-body">
                <div class="mb-4">
                  <h6 class="mb-3">Backup & Restore</h6>
                  <p class="text-muted small">Create backups of your system data or restore from a previous backup</p>
                  <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary">Create Backup</button>
                    <button type="button" class="btn btn-outline-secondary">Restore Data</button>
                  </div>
                </div>
                
                <div class="mb-4">
                  <h6 class="mb-3">Data Retention</h6>
                  <div class="mb-3">
                    <label for="reportRetention" class="form-label">Report Retention Period</label>
                    <select class="form-select" id="reportRetention">
                      <option value="1">1 year</option>
                      <option value="3" selected>3 years</option>
                      <option value="5">5 years</option>
                      <option value="0">Indefinitely</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="logRetention" class="form-label">System Log Retention</label>
                    <select class="form-select" id="logRetention">
                      <option value="30">30 days</option>
                      <option value="90" selected>90 days</option>
                      <option value="180">180 days</option>
                      <option value="365">1 year</option>
                    </select>
                  </div>
                </div>
                
                <div class="mb-4">
                  <h6 class="mb-3">Export Data</h6>
                  <p class="text-muted small">Export system data for reporting or analysis</p>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm">Export Staff Data</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm">Export Reports</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm">Export Statistics</button>
                  </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Data Settings</button>
              </div>
            </div>
          </div>
          
          <!-- Location Settings -->
          <div class="tab-pane fade" id="location" role="tabpanel" aria-labelledby="location-tab">
            <div class="settings-card">
              <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-geo-alt settings-icon"></i>Location Settings</h5>
              </div>
              <div class="settings-card-body">
                <form>
                  <div class="mb-3">
                    <label for="barangayName" class="form-label">Barangay Name</label>
                    <input type="text" class="form-control" id="barangayName" value="Barangay Sample">
                  </div>
                  <div class="mb-3">
                    <label for="municipality" class="form-label">Municipality/City</label>
                    <input type="text" class="form-control" id="municipality" value="Sample City">
                  </div>
                  <div class="mb-3">
                    <label for="province" class="form-label">Province</label>
                    <input type="text" class="form-control" id="province" value="Sample Province">
                  </div>
                  <div class="mb-3">
                    <label for="region" class="form-label">Region</label>
                    <select class="form-select" id="region">
                      <option value="NCR">National Capital Region (NCR)</option>
                      <option value="CAR">Cordillera Administrative Region (CAR)</option>
                      <option value="R1">Region I (Ilocos Region)</option>
                      <option value="R2">Region II (Cagayan Valley)</option>
                      <option value="R3">Region III (Central Luzon)</option>
                      <option value="R4A" selected>Region IV-A (CALABARZON)</option>
                      <option value="R4B">Region IV-B (MIMAROPA)</option>
                      <option value="R5">Region V (Bicol Region)</option>
                      <option value="R6">Region VI (Western Visayas)</option>
                      <option value="R7">Region VII (Central Visayas)</option>
                      <option value="R8">Region VIII (Eastern Visayas)</option>
                      <option value="R9">Region IX (Zamboanga Peninsula)</option>
                      <option value="R10">Region X (Northern Mindanao)</option>
                      <option value="R11">Region XI (Davao Region)</option>
                      <option value="R12">Region XII (SOCCSKSARGEN)</option>
                      <option value="R13">Region XIII (Caraga)</option>
                      <option value="BARMM">Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="zipCode" class="form-label">ZIP Code</label>
                    <input type="text" class="form-control" id="zipCode" value="1234">
                  </div>
                  <div class="mb-4">
                    <h6 class="mb-3">Coverage Areas</h6>
                    <p class="text-muted small">Define the areas covered by your health workers</p>
                    <div class="mb-3">
                      <div class="d-flex gap-2 align-items-center mb-2">
                        <input type="text" class="form-control" placeholder="Add new area" id="newArea">
                        <button type="button" class="btn btn-outline-primary">Add</button>
                      </div>
                      <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                          Purok 1
                          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                          Purok 2
                          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                          Purok 3
                          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                          Sitio A
                          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Save Location Settings</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
