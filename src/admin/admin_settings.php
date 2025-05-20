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

$account_updated = false;
$password_updated = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_account'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        
        $stmt = $pdo->prepare("SELECT adminId FROM admin WHERE email = ? AND adminId != ?");
        $stmt->execute([$email, $admin_id]);
        $result = $stmt->fetch();
        
        if ($result) {
            $error = "Email already in use by another admin.";
        } else {
            // Update admin information
            $stmt = $pdo->prepare("UPDATE admin SET name = ?, email = ? WHERE adminId = ?");
            
            try {
                $stmt->execute([$name, $email, $admin_id]);
                $account_updated = true;
                // Update session data
                $_SESSION['admin_name'] = $name;
                // Refresh admin data
                $admin['name'] = $name;
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
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 60px; /* Add padding to prevent content from going under navbar */
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 600;
            color: #0d6efd;
        }

        .nav-link {
            color: #495057;
            padding: 0.5rem 1rem;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: #0d6efd;
        }

        .nav-link.active {
            color: #0d6efd;
            font-weight: 500;
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
        
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
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
            background-color: rgba(13, 110, 253, 0.03);
        }
        
        .settings-card-body {
            padding: 1.5rem;
        }
        
        .settings-icon {
            font-size: 1.5rem;
            color: #0d6efd;
            margin-right: 0.75rem;
        }

        .page-header {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 768px) {
            body {
                padding-top: 56px; /* Adjust padding for mobile navbar height */
            }
            
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
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Settings</h2>
                    <p class="text-muted mb-0">Manage your account settings</p>
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

        <!-- Account Settings -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h5 class="mb-0"><i class="bi bi-person settings-icon"></i>Account Settings</h5>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                    <button type="submit" name="update_account" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="settings-card">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
