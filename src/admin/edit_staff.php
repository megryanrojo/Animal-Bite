<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Fetch available barangays for dropdown
try {
    $stmt = $pdo->query("SELECT barangay FROM barangay_coordinates ORDER BY barangay");
    $barangays = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $barangays = [];
}

// Check if staff ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid staff ID.";
    header("Location: view_staff.php");
    exit;
}

$staffId = $_GET['id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $birthDate = $_POST['birthDate'];
        $address = trim($_POST['address']);
        $assignedBarangay = trim($_POST['assignedBarangay']);
        $contactNumber = trim($_POST['contactNumber']);
        $email = trim($_POST['email']);

        // Check if password should be updated
        $passwordUpdate = "";
        $params = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'birthDate' => $birthDate,
            'address' => $address,
            'assignedBarangay' => $assignedBarangay,
            'contactNumber' => $contactNumber,
            'email' => $email,
            'staffId' => $staffId
        ];
        
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $passwordUpdate = ", password = :password";
            $params['password'] = $hashedPassword;
        }

        $stmt = $pdo->prepare("UPDATE staff SET
            firstName = :firstName,
            lastName = :lastName,
            birthDate = :birthDate,
            address = :address,
            assignedBarangay = :assignedBarangay,
            contactNumber = :contactNumber,
            email = :email" . $passwordUpdate . "
            WHERE staffId = :staffId");

        $stmt->execute($params);

        $_SESSION['message'] = "Staff information updated successfully!";
        header("Location: view_staff.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating staff: " . $e->getMessage();
    }
}

// Get staff details
try {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staffId = ?");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        $_SESSION['error'] = "Staff member not found.";
        header("Location: view_staff.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving staff details: " . $e->getMessage();
    header("Location: view_staff.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Staff | BHW Admin Portal</title>
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
    
    .form-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }
    
    .navbar {
      background-color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .page-header {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .form-card {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 1.5rem;
      overflow: hidden;
      border: none;
    }
    
    .form-card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      background-color: rgba(var(--bs-primary-rgb), 0.03);
    }
    
    .form-card-body {
      padding: 1.5rem;
    }
    
    .form-card-footer {
      padding: 1.25rem 1.5rem;
      background-color: rgba(0, 0, 0, 0.02);
      border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .form-section {
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .form-section:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    
    .form-section-title {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 1.25rem;
      color: var(--bs-primary);
    }
    
    .form-label {
      font-weight: 500;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--bs-primary);
      box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
    }
    
    .btn-back {
      color: #6c757d;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
    }
    
    .btn-back:hover {
      color: #495057;
    }
    
    .btn-back i {
      margin-right: 0.5rem;
    }
    
    .password-strength {
      height: 5px;
      border-radius: 5px;
      margin-top: 0.5rem;
      transition: all 0.3s;
    }
    
    .password-feedback {
      font-size: 0.8rem;
      margin-top: 0.5rem;
    }
    
    .form-floating > .form-control,
    .form-floating > .form-select {
      height: calc(3.5rem + 2px);
      padding: 1rem 0.75rem;
    }
    
    .form-floating > label {
      padding: 1rem 0.75rem;
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
    
    @media (max-width: 768px) {
      .form-container {
        padding: 1rem;
      }
      
      .page-header {
        padding: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light sticky-top mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="#">BHW Admin Portal</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="view_staff.php"><i class="bi bi-people"></i> View Staff</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="view_reports.php"><i class="bi bi-file-text"></i> View Reports</a>
          </li>
          <li class="nav-item">
            <a class="nav-link btn-logout ms-2" href="../logout/admin_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="form-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="mb-0">Edit Staff</h2>
          <p class="text-muted mb-0">Update staff member information</p>
        </div>
        <a href="view_staff.php" class="btn-back">
          <i class="bi bi-arrow-left"></i> Back to Staff List
        </a>
      </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo $_SESSION['error']; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="form-card">
      <div class="form-card-header">
        <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>Edit Staff Information</h5>
      </div>
      <div class="form-card-body">
        <form action="" method="POST" id="staffForm">
          <!-- Personal Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-person me-2"></i>Personal Information
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="form-floating">
                  <input type="text" name="firstName" id="firstName" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($staff['firstName']); ?>" required>
                  <label for="firstName">First Name</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating">
                  <input type="text" name="lastName" id="lastName" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($staff['lastName']); ?>" required>
                  <label for="lastName">Last Name</label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <input type="date" name="birthDate" id="birthDate" class="form-control" placeholder="Birth Date" value="<?php echo $staff['birthDate']; ?>" required>
                <label for="birthDate">Birth Date</label>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <textarea name="address" id="address" class="form-control" placeholder="Address" style="height: 100px" required><?php echo htmlspecialchars($staff['address']); ?></textarea>
                <label for="address">Address</label>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <select name="assignedBarangay" id="assignedBarangay" class="form-select" required>
                  <option value="">Select Barangay</option>
                  <?php foreach ($barangays as $barangay): ?>
                    <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo ($staff['assignedBarangay'] ?? '') === $barangay ? 'selected' : ''; ?>><?php echo htmlspecialchars($barangay); ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="assignedBarangay">Assigned Barangay</label>
              </div>
            </div>
          </div>

          <!-- Contact Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-telephone me-2"></i>Contact Information
            </div>
            <div class="mb-3">
              <div class="form-floating">
                <input type="tel" name="contactNumber" id="contactNumber" class="form-control" placeholder="Contact Number" value="<?php echo htmlspecialchars($staff['contactNumber']); ?>" required>
                <label for="contactNumber">Contact Number</label>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <input type="email" name="email" id="email" class="form-control" placeholder="Email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                <label for="email">Email Address</label>
              </div>
            </div>
          </div>

          <!-- Account Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-shield-lock me-2"></i>Account Information
            </div>
            <div class="mb-3">
              <div class="form-floating">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password">
                <label for="password">New Password (leave blank to keep current)</label>
              </div>
              <div class="password-strength bg-light"></div>
              <div class="password-feedback text-muted"></div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <input type="password" id="confirmPassword" class="form-control" placeholder="Confirm Password">
                <label for="confirmPassword">Confirm New Password</label>
              </div>
            </div>
          </div>

          <div class="form-card-footer">
            <div class="d-flex justify-content-between align-items-center">
              <a href="view_staff.php" class="btn btn-outline-secondary">
                Cancel
              </a>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i>Save Changes
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('confirmPassword');
      const passwordStrength = document.querySelector('.password-strength');
      const passwordFeedback = document.querySelector('.password-feedback');
      const form = document.getElementById('staffForm');
      
      // Password strength indicator
      password.addEventListener('input', function() {
        const value = password.value;
        let strength = 0;
        
        if (value.length === 0) {
          passwordStrength.style.width = '0%';
          passwordStrength.className = 'password-strength bg-light';
          passwordFeedback.textContent = '';
          return;
        }
        
        if (value.length >= 8) strength += 1;
        if (value.match(/[a-z]/) && value.match(/[A-Z]/)) strength += 1;
        if (value.match(/\d/)) strength += 1;
        if (value.match(/[^a-zA-Z\d]/)) strength += 1;
        
        switch (strength) {
          case 0:
            passwordStrength.style.width = '0%';
            passwordStrength.className = 'password-strength bg-light';
            passwordFeedback.textContent = '';
            break;
          case 1:
            passwordStrength.style.width = '25%';
            passwordStrength.className = 'password-strength bg-danger';
            passwordFeedback.textContent = 'Weak password';
            passwordFeedback.className = 'password-feedback text-danger';
            break;
          case 2:
            passwordStrength.style.width = '50%';
            passwordStrength.className = 'password-strength bg-warning';
            passwordFeedback.textContent = 'Fair password';
            passwordFeedback.className = 'password-feedback text-warning';
            break;
          case 3:
            passwordStrength.style.width = '75%';
            passwordStrength.className = 'password-strength bg-info';
            passwordFeedback.textContent = 'Good password';
            passwordFeedback.className = 'password-feedback text-info';
            break;
          case 4:
            passwordStrength.style.width = '100%';
            passwordStrength.className = 'password-strength bg-success';
            passwordFeedback.textContent = 'Strong password';
            passwordFeedback.className = 'password-feedback text-success';
            break;
        }
      });
      
      // Form submission validation
      form.addEventListener('submit', function(event) {
        // Only validate passwords if a new password is being set
        if (password.value !== '') {
          if (password.value !== confirmPassword.value) {
            event.preventDefault();
            alert('Passwords do not match!');
            return false;
          }
        }
        
        return true;
      });
    });
  </script>
</body>
</html>