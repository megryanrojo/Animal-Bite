<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin_login.php");
  exit;
}
require_once '../conn/conn.php';
require_once 'includes/notification_helper.php';

// Fetch available barangays for dropdown
try {
    $stmt = $pdo->query("SELECT barangay FROM barangay_coordinates ORDER BY barangay");
    $barangays = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $barangays = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New Staff | CHW Admin Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0ea5e9;
      --primary-light: #38bdf8;
      --primary-lighter: #7dd3fc;
      --primary-dark: #0284c7;
      --primary-bg: #f0f9ff;
      --secondary: #f8fafc;
      --text-dark: #0f172a;
      --text-muted: #64748b;
      --border-color: #e2e8f0;
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      /* Changed background to white */
      background: #ffffff;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      min-height: 100vh;
      color: var(--text-dark);
    }
    
    /* Redesigned navbar to be cleaner and professional */
    .top-navbar {
      background: white;
      border-bottom: 1px solid var(--border-color);
      padding: 0 2rem;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: var(--text-dark);
      font-weight: 600;
      font-size: 1rem;
    }
    
    .navbar-brand-icon {
      width: 36px;
      height: 36px;
      background: var(--primary);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1rem;
    }
    
    .navbar-center {
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .nav-link {
      padding: 0.5rem 1rem;
      color: var(--text-muted);
      text-decoration: none;
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
    }
    
    .nav-link:hover {
      background: var(--primary-bg);
      color: var(--primary);
    }
    
    .nav-link.active {
      background: var(--primary-bg);
      color: var(--primary);
    }
    
    .btn-logout {
      background: transparent;
      color: var(--text-muted);
      border: 1px solid var(--border-color);
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-logout:hover {
      background: #fef2f2;
      color: var(--danger);
      border-color: #fecaca;
    }
    
    /* Main Container */
    .form-container {
      max-width: 720px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
    }
    
    /* Page Header */
    .page-header {
      margin-bottom: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .page-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 0.25rem;
    }
    
    .page-header p {
      color: var(--text-muted);
      font-size: 0.875rem;
      margin: 0;
    }
    
    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-muted);
      text-decoration: none;
      font-size: 0.875rem;
      font-weight: 500;
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      transition: all 0.2s;
      border: 1px solid var(--border-color);
      background: white;
    }
    
    .btn-back:hover {
      background: var(--primary-bg);
      color: var(--primary);
      border-color: var(--primary-light);
    }
    
    /* Alert Styles */
    
    /* Form Card */
    .form-card {
      background: white;
      border-radius: 12px;
      border: 1px solid var(--border-color);
      overflow: hidden;
    }
    
    .form-card-header {
      padding: 1rem 1.5rem;
      background: var(--primary-bg);
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      gap: 0.625rem;
    }
    
    .form-card-header h5 {
      font-size: 0.9375rem;
      font-weight: 600;
      color: var(--primary-dark);
      margin: 0;
    }
    
    .form-card-header i {
      color: var(--primary);
      font-size: 1rem;
    }
    
    .form-card-body {
      padding: 1.5rem;
    }
    
    /* Form Sections */
    .form-section {
      margin-bottom: 2rem;
      padding-bottom: 1.75rem;
    }

    .form-section:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    
    .form-section-title {
      font-size: 0.8125rem;
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }
    
    .form-section-title i {
      font-size: 0.875rem;
    }


    .form-text {
      margin-top: 0.25rem;
    }
    
    /* Form Inputs */
    .form-floating {
      position: relative;
    }
    
    /* Form Inputs */
    .form-floating > .form-control {
      height: calc(3.25rem + 2px);
      padding: 0.875rem 0.875rem;
      font-size: 0.9375rem;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: white;
      transition: all 0.2s;
    }

    .form-floating > .form-control:focus {
      border-color: var(--primary-light);
      box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
      outline: none;
    }

    /* Form Select */
    .form-select {
      height: calc(3.25rem + 2px);
      padding: 0.875rem 2.5rem 0.875rem 0.875rem;
      font-size: 0.9375rem;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: white;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
      background-position: right 0.75rem center;
      background-repeat: no-repeat;
      background-size: 1rem;
      transition: all 0.2s;
      appearance: none;
    }

    .form-select:focus {
      border-color: var(--primary-light);
      box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
      outline: none;
    }

    /* Form Labels */
    .form-label {
      font-size: 0.9375rem;
      font-weight: 500;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .form-floating > label {
      padding: 0.875rem 0.875rem;
      color: var(--text-muted);
      font-size: 0.9375rem;
    }
    
    .form-floating > textarea.form-control {
      height: auto;
      min-height: 90px;
    }
    
    /* Password Strength */
    .password-strength-container {
      margin-top: 0.625rem;
    }
    
    .password-strength {
      height: 4px;
      border-radius: 2px;
      background: var(--border-color);
      overflow: hidden;
    }
    
    .password-strength-bar {
      height: 100%;
      border-radius: 2px;
      transition: all 0.3s;
      width: 0%;
    }
    
    .password-strength-bar.weak { width: 25%; background: var(--danger); }
    .password-strength-bar.fair { width: 50%; background: var(--warning); }
    .password-strength-bar.good { width: 75%; background: var(--primary); }
    .password-strength-bar.strong { width: 100%; background: var(--success); }
    
    .password-feedback {
      font-size: 0.75rem;
      margin-top: 0.375rem;
      font-weight: 500;
    }
    
    .password-feedback.weak { color: var(--danger); }
    .password-feedback.fair { color: var(--warning); }
    .password-feedback.good { color: var(--primary); }
    .password-feedback.strong { color: var(--success); }

    /* Form Validation */
    .form-floating > .form-control.is-invalid {
      border-color: var(--danger);
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }

    .form-select.is-invalid {
      border-color: var(--danger);
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }

    .form-floating > .form-control.is-valid {
      border-color: var(--success);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .form-select.is-valid {
      border-color: var(--success);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .invalid-feedback {
      display: none;
      font-size: 0.75rem;
      color: var(--danger);
      margin-top: 0.25rem;
      font-weight: 500;
    }

    .form-floating > .form-control.is-invalid ~ .invalid-feedback {
      display: block;
    }

    .form-select.is-invalid ~ .invalid-feedback {
      display: block;
    }
    
    /* Form Footer */
    .form-card-footer {
      padding: 1rem 1.5rem;
      background: var(--secondary);
      border-top: 1px solid var(--border-color);
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 0.75rem;
    }
    
    .btn-cancel {
      background: white;
      color: var(--text-muted);
      border: 1px solid var(--border-color);
      padding: 0.625rem 1.25rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .btn-cancel:hover {
      background: var(--secondary);
      border-color: var(--text-muted);
      color: var(--text-dark);
    }
    
    .btn-submit {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0.625rem 1.25rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-submit:hover {
      background: var(--primary-dark);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .top-navbar {
        padding: 0 1rem;
      }

      .navbar-center {
        display: none;
      }

      .form-container {
        padding: 1rem 0.75rem;
        max-width: 100%;
      }

      .page-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
      }

      .page-header h2 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
      }

      .form-card {
        border-radius: 8px;
      }

      .form-card-body {
        padding: 1.25rem 1rem;
      }

      .form-card-header {
        padding: 1rem 1.25rem;
      }

      .form-card-footer {
        padding: 1rem 1.25rem;
        flex-direction: column;
        gap: 0.75rem;
      }

      .btn-cancel, .btn-submit {
        width: 100%;
        justify-content: center;
        padding: 0.75rem 1rem;
      }

      .form-section {
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
      }

      .form-floating > .form-control {
        font-size: 1rem;
      }

      .form-floating > label {
        font-size: 0.9375rem;
      }

      .form-select {
        font-size: 1rem;
      }

      .form-label {
        font-size: 0.9375rem;
      }

      .password-strength-container {
        margin-top: 0.5rem;
      }

      .password-feedback {
        font-size: 0.6875rem;
      }
    }

    @media (max-width: 480px) {
      .form-container {
        padding: 0.75rem 0.5rem;
      }

      .page-header {
        margin-bottom: 1rem;
      }

      .form-card-body {
        padding: 1rem 0.75rem;
      }

      .form-card-header {
        padding: 0.875rem 1rem;
      }

      .form-card-header h5 {
        font-size: 0.875rem;
      }

      .form-section-title {
        font-size: 0.75rem;
        margin-bottom: 0.875rem;
      }
    }
  </style>
</head>
<body>
  <!-- Simplified navbar structure -->
  <?php include 'includes/navbar.php'; ?>

  <div class="form-container">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h2><i class="bi bi-person-plus me-2"></i>Add New Staff Member</h2>
        <p>Register a new barangay health worker and assign them to a specific barangay</p>
      </div>
      <a href="view_staff.php" class="btn-back" title="Back to Staff Management">
        <i class="bi bi-arrow-left"></i> Back to Staff
      </a>
    </div>

    <!-- Success Message -->
    <div id="successMessage" class="alert alert-success alert-dismissible fade d-none" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i>
      <strong>Success!</strong> Staff member has been added successfully.
      <a href="view_staff.php" class="alert-link ms-2">View Staff List</a>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Form Card -->
    <div class="form-card">
      <div class="form-card-header">
        <i class="bi bi-person-plus-fill"></i>
        <h5>Staff Information</h5>
      </div>
      <div class="form-card-body">
        <form action="add_staff.php" method="POST" id="staffForm">
          <!-- Personal Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-person"></i>
              Personal Information
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="form-floating">
                  <input type="text" name="firstName" id="firstName" class="form-control" placeholder="First Name" required>
                  <label for="firstName">First Name</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating">
                  <input type="text" name="lastName" id="lastName" class="form-control" placeholder="Last Name" required>
                  <label for="lastName">Last Name</label>
                </div>
              </div>
            </div>

            <div class="form-floating mb-3">
              <input type="date" name="birthDate" id="birthDate" class="form-control" placeholder="Birth Date" required>
              <label for="birthDate">Birth Date</label>
            </div>

            <div class="form-floating mb-3">
              <textarea name="address" id="address" class="form-control" placeholder="Address" required style="min-height: 80px;"></textarea>
              <label for="address">Complete Address</label>
            </div>

            <label for="assignedBarangay" class="form-label">
              <i class="bi bi-geo-alt-fill me-1"></i>Assigned Barangay
            </label>
            <select name="assignedBarangay" id="assignedBarangay" class="form-select" required>
              <option value="">Select Barangay Assignment</option>
              <?php foreach ($barangays as $barangay): ?>
                <option value="<?php echo htmlspecialchars($barangay); ?>"><?php echo htmlspecialchars($barangay); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text text-muted mb-3">
              <small>This staff member will be responsible for reports from the selected barangay.</small>
            </div>
          </div>

          <!-- Contact Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-telephone"></i>
              Contact Information
            </div>
            <div class="form-floating mb-3">
              <input type="tel" name="contactNumber" id="contactNumber" class="form-control" placeholder="Contact Number" required>
              <label for="contactNumber">Contact Number</label>
            </div>

            <div class="form-floating">
              <input type="email" name="email" id="email" class="form-control" placeholder="Email" required>
              <label for="email">Email Address</label>
            </div>
          </div>

          <!-- Account Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-shield-lock"></i>
              Account Information
            </div>
            <div class="form-floating mb-3">
              <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
              <label for="password">Password</label>
              <div class="password-strength-container">
                <div class="password-strength">
                  <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>
                <div class="password-feedback" id="passwordFeedback"></div>
              </div>
            </div>

            <div class="form-floating">
              <input type="password" id="confirmPassword" class="form-control" placeholder="Confirm Password" required>
              <label for="confirmPassword">Confirm Password</label>
              <div class="invalid-feedback" id="confirmPasswordError">
                Passwords do not match.
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="form-card-footer">
        <button type="button" class="btn-cancel" onclick="window.location.href='view_staff.php'">
          <i class="bi bi-x-circle"></i>
          Cancel
        </button>
        <button type="submit" form="staffForm" class="btn-submit">
          <i class="bi bi-person-plus-fill"></i>
          Add Staff Member
        </button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('success')) {
        const successAlert = document.getElementById('successMessage');
        successAlert.classList.remove('d-none');
        successAlert.classList.add('show');

        // Auto-hide after 5 seconds
        setTimeout(() => {
          successAlert.classList.remove('show');
          setTimeout(() => successAlert.classList.add('d-none'), 150);
        }, 5000);
      }
      
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('confirmPassword');
      const strengthBar = document.getElementById('passwordStrengthBar');
      const feedback = document.getElementById('passwordFeedback');
      const confirmPasswordError = document.getElementById('confirmPasswordError');
      const form = document.getElementById('staffForm');
      
      password.addEventListener('input', function() {
        const value = password.value;
        let strength = 0;
        
        if (value.length >= 8) strength += 1;
        if (value.match(/[a-z]/) && value.match(/[A-Z]/)) strength += 1;
        if (value.match(/\d/)) strength += 1;
        if (value.match(/[^a-zA-Z\d]/)) strength += 1;
        
        strengthBar.className = 'password-strength-bar';
        feedback.className = 'password-feedback';
        
        switch (strength) {
          case 0:
            feedback.textContent = '';
            break;
          case 1:
            strengthBar.classList.add('weak');
            feedback.classList.add('weak');
            feedback.textContent = 'Weak password';
            break;
          case 2:
            strengthBar.classList.add('fair');
            feedback.classList.add('fair');
            feedback.textContent = 'Fair password';
            break;
          case 3:
            strengthBar.classList.add('good');
            feedback.classList.add('good');
            feedback.textContent = 'Good password';
            break;
          case 4:
            strengthBar.classList.add('strong');
            feedback.classList.add('strong');
            feedback.textContent = 'Strong password';
            break;
        }
      });

      // Real-time password confirmation validation
      confirmPassword.addEventListener('input', function() {
        if (confirmPassword.value === '') {
          confirmPassword.classList.remove('is-invalid', 'is-valid');
          return;
        }

        if (password.value === confirmPassword.value) {
          confirmPassword.classList.remove('is-invalid');
          confirmPassword.classList.add('is-valid');
        } else {
          confirmPassword.classList.remove('is-valid');
          confirmPassword.classList.add('is-invalid');
        }
      });

      // Also validate on password change
      password.addEventListener('input', function() {
        if (confirmPassword.value !== '') {
          if (password.value === confirmPassword.value) {
            confirmPassword.classList.remove('is-invalid');
            confirmPassword.classList.add('is-valid');
          } else {
            confirmPassword.classList.remove('is-valid');
            confirmPassword.classList.add('is-invalid');
          }
        }
      });

      form.addEventListener('submit', function(event) {
        let isValid = true;

        // Check password match
        if (password.value !== confirmPassword.value) {
          confirmPassword.classList.add('is-invalid');
          isValid = false;
        }

        if (!isValid) {
          event.preventDefault();
          // Scroll to first error
          const firstError = form.querySelector('.is-invalid');
          if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          return false;
        }

        return true;
      });

      function confirmLogout() {
        if (confirm('Are you sure you want to log out?')) {
          window.location.href = '../logout/admin_logout.php';
        }
      }
    });
  </script>
</body>
</html>
