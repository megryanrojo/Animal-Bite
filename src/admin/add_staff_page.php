<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin_login.php");
  exit;
}
require_once '../conn/conn.php';
require_once 'includes/notification_helper.php';
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
    
    /* Alert */
    .alert-success-custom {
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      color: #065f46;
      padding: 0.875rem 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.875rem;
      font-weight: 500;
    }
    
    .alert-success-custom i {
      font-size: 1.125rem;
    }
    
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
      margin-bottom: 1.75rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--border-color);
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
    
    /* Form Inputs */
    .form-floating {
      position: relative;
    }
    
    .form-floating > .form-control,
    .form-floating > .form-select {
      height: calc(3.25rem + 2px);
      padding: 0.875rem 0.875rem;
      font-size: 0.9375rem;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: white;
      transition: all 0.2s;
    }
    
    .form-floating > .form-control:focus,
    .form-floating > .form-select:focus {
      border-color: var(--primary-light);
      box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
      outline: none;
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
        padding: 1.25rem 1rem;
      }
      
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .form-card-body {
        padding: 1.25rem;
      }
      
      .form-card-footer {
        flex-direction: column;
      }
      
      .btn-cancel, .btn-submit {
        width: 100%;
        justify-content: center;
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
        <h2>Add New Staff</h2>
        <p>Register a new barangay health worker to the system</p>
      </div>
      <a href="view_staff.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back
      </a>
    </div>

    <!-- Success Message -->
    <div id="successMessage" class="alert-success-custom d-none">
      <i class="bi bi-check-circle-fill"></i>
      <span>Staff member added successfully!</span>
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
            <div class="row g-3 mb-3">
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

            <div class="mb-3">
              <div class="form-floating">
                <input type="date" name="birthDate" id="birthDate" class="form-control" placeholder="Birth Date" required>
                <label for="birthDate">Birth Date</label>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <textarea name="address" id="address" class="form-control" placeholder="Address" required></textarea>
                <label for="address">Complete Address</label>
              </div>
            </div>
          </div>

          <!-- Contact Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-telephone"></i>
              Contact Information
            </div>
            <div class="mb-3">
              <div class="form-floating">
                <input type="tel" name="contactNumber" id="contactNumber" class="form-control" placeholder="Contact Number" required>
                <label for="contactNumber">Contact Number</label>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <input type="email" name="email" id="email" class="form-control" placeholder="Email" required>
                <label for="email">Email Address</label>
              </div>
            </div>
          </div>

          <!-- Account Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-shield-lock"></i>
              Account Information
            </div>
            <div class="mb-3">
              <div class="form-floating">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                <label for="password">Password</label>
              </div>
              <div class="password-strength-container">
                <div class="password-strength">
                  <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>
                <div class="password-feedback" id="passwordFeedback"></div>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-floating">
                <input type="password" id="confirmPassword" class="form-control" placeholder="Confirm Password" required>
                <label for="confirmPassword">Confirm Password</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="form-card-footer">
        <button type="button" class="btn-cancel" onclick="window.location.href='admin_dashboard.php'">
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
        document.getElementById('successMessage').classList.remove('d-none');
      }
      
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('confirmPassword');
      const strengthBar = document.getElementById('passwordStrengthBar');
      const feedback = document.getElementById('passwordFeedback');
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
      
      form.addEventListener('submit', function(event) {
        if (password.value !== confirmPassword.value) {
          event.preventDefault();
          alert('Passwords do not match!');
          return false;
        }
        return true;
      });
    });
  </script>
</body>
</html>
