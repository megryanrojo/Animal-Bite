<?php
session_start();
require_once '../conn/conn.php';
require_once '../admin/includes/logging_helper.php'; 

// --- Rate Limiting Settings ---
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 5);
define('LOCKOUT_SECONDS', LOCKOUT_MINUTES * 60); // 300 seconds = 5 minutes

// --- Initialize session variables ---
if (!isset($_SESSION['admin_login_attempts'])) $_SESSION['admin_login_attempts'] = 0;
if (!isset($_SESSION['admin_lockout_time'])) $_SESSION['admin_lockout_time'] = 0;

// --- Check lockout status ---
$locked_out = false;
$lockout_remaining = 0;

if ($_SESSION['admin_login_attempts'] >= MAX_ATTEMPTS) {
    $time_elapsed = time() - $_SESSION['admin_lockout_time'];
    if ($time_elapsed < LOCKOUT_SECONDS) {
        $lockout_remaining = LOCKOUT_SECONDS - $time_elapsed;
        $locked_out = true;
        $error = "Too many login attempts. Please try again in {$lockout_remaining} seconds.";
    } else {
        // Auto-reset after lockout period expires
        $_SESSION['admin_login_attempts'] = 0;
        $_SESSION['admin_lockout_time'] = 0;
        $locked_out = false;
        $lockout_remaining = 0;
    }
}

// --- Email/password login ---
if (!$locked_out && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                // Successful login: reset attempts
                $_SESSION['admin_login_attempts'] = 0;
                $_SESSION['admin_lockout_time'] = 0;
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['adminId'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];

                // Log successful login
                logActivity($pdo, 'LOGIN', 'admin', $admin['adminId'], $admin['adminId'], "Admin logged in successfully");

              // Show a success message on this page, then let client-side JS redirect
              $success = "Login successful!";
              // Note: do not redirect here so the alert can be rendered for the user
            } else {
                // Failed login attempt: increment counter
                $_SESSION['admin_login_attempts']++;

                // Log failed login attempt
                logActivity($pdo, 'LOGIN_FAILED', 'admin', null, null, "Failed login attempt for email: {$email}");

                if ($_SESSION['admin_login_attempts'] >= MAX_ATTEMPTS) {
                    // Trigger lockout
                    $_SESSION['admin_lockout_time'] = time();
                    $locked_out = true;
                    $lockout_remaining = LOCKOUT_SECONDS;
                    $error = "Too many login attempts. Account locked for {$lockout_remaining} seconds.";
                } else {
                    $attempts_left = MAX_ATTEMPTS - $_SESSION['admin_login_attempts'];
                    $error = "Invalid email or password. {$attempts_left} attempt(s) remaining.";
                }
            }

        } catch (PDOException $e) {
            $error = "Server error. Please try again later.";
        }
    }
}

// --- Google OAuth login ---
if (!$locked_out && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idToken'])) {
    // Example JSON payload from JS: { idToken: "...", type: "admin" }
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['idToken']) || $input['type'] !== 'admin') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit();
    }

    try {
        // Verify token via Firebase Admin SDK or API call
        $idToken = $input['idToken'];
        // TODO: implement verification
        // $firebaseUser = verifyFirebaseIdToken($idToken);

        // Simulate verification success for example
        $firebaseUser = ['email' => 'admin@example.com', 'name' => 'Admin']; 

        // Successful Google login: reset attempts
        $_SESSION['admin_login_attempts'] = 0;
        $_SESSION['admin_lockout_time'] = 0;

        // Lookup admin in DB by email
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $firebaseUser['email']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized user']);
            exit();
        }

        $_SESSION['admin_id'] = $admin['adminId'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];

        echo json_encode(['success' => true, 'redirect' => '../admin/admin_dashboard.php']);
        exit();

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Google login failed']);
        exit();
    }
}

// --- Flash success for normal login ---
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// --- Pass lockout state to JS ---
$is_locked = $locked_out ? 'true' : 'false';
$lock_remaining = (int)$lockout_remaining;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login | Animal Bite Portal</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --primary: #0284c7;
  --primary-dark: #0369a1;
  --primary-light: #e0f2fe;
  --primary-bg: #0c4a6e;
  --accent: #38bdf8;
  --accent-light: #7dd3fc;
  --white: #ffffff;
  --gray-50: #f8fafc;
  --gray-100: #f1f5f9;
  --gray-200: #e2e8f0;
  --gray-300: #cbd5e1;
  --gray-400: #94a3b8;
  --gray-500: #64748b;
  --gray-600: #475569;
  --gray-700: #334155;
  --gray-800: #1e293b;
  --gray-900: #0f172a;
  --danger: #dc2626;
  --danger-light: #fef2f2;
  --danger-border: #fecaca;
  --success: #16a34a;
  --success-light: #f0fdf4;
  --success-border: #bbf7d0;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  min-height: 100vh;
  color: var(--gray-900);
  line-height: 1.5;
}

/* Split Screen Layout */
.login-wrapper {
  display: flex;
  min-height: 100vh;
}

.branding-panel {
  display: none;
  flex: 1;
  background: linear-gradient(135deg, var(--primary-bg) 0%, #075985 50%, #0369a1 100%);
  padding: 3rem;
  position: relative;
  overflow: hidden;
}

.branding-panel::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 80%;
  height: 150%;
  background: radial-gradient(circle, rgba(56, 189, 248, 0.2) 0%, transparent 70%);
  pointer-events: none;
}

.branding-panel::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: -10%;
  width: 60%;
  height: 100%;
  background: radial-gradient(circle, rgba(125, 211, 252, 0.15) 0%, transparent 70%);
  pointer-events: none;
}

.branding-content {
  position: relative;
  z-index: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  color: var(--white);
}

.branding-logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: auto;
}

.branding-logo-icon {
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.branding-logo-icon i {
  font-size: 1.5rem;
  color: var(--accent-light);
}

.branding-logo span {
  font-size: 1.125rem;
  font-weight: 600;
  letter-spacing: -0.02em;
}

.branding-main {
  margin: auto 0;
  max-width: 480px;
}

.branding-main h1 {
  font-size: 2.75rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.03em;
  margin-bottom: 1rem;
}

.feature-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.feature-item .feature-icon {
  opacity: 0.9;
  font-size: 1.1rem;
}

.feature-item span {
  font-size: 0.9rem;
  font-weight: 400;
}

.system-navigation {
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  padding-top: 1rem;
  text-align: center;
}

.system-navigation h3 {
  color: white;
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.system-buttons {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
  flex-wrap: nowrap;
}

.system-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: rgba(255, 255, 255, 0.15);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 10px;
  color: white;
  text-decoration: none;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  min-width: 140px;
  justify-content: center;
  flex: 1;
  max-width: 160px;
}

.system-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.system-btn-active {
  background: rgba(255, 255, 255, 0.9) !important;
  color: #0ea5e9 !important;
  border-color: rgba(255, 255, 255, 0.9) !important;
  box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
}

.system-btn i {
  font-size: 1.5rem;
  opacity: 0.9;
}

.system-btn span {
  font-size: 0.875rem;
  font-weight: 500;
  text-align: center;
  line-height: 1.3;
}

.system-btn small {
  display: block;
  font-size: 0.75rem;
  opacity: 0.8;
  font-weight: 400;
}

.feature-icon {
  width: 40px;
  height: 40px;
  background: rgba(56, 189, 248, 0.15);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid rgba(56, 189, 248, 0.2);
}

.feature-icon i {
  font-size: 1.125rem;
  color: var(--accent);
}

.feature-item span {
  font-size: 0.9375rem;
  color: rgba(255, 255, 255, 0.9);
}

.branding-footer {
  margin-top: auto;
  padding-top: 2rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.branding-footer p {
  font-size: 0.8125rem;
  color: rgba(255, 255, 255, 0.5);
}

/* Right Panel - Form */
.form-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: var(--white);
}

.form-container {
  width: 100%;
  max-width: 420px;
}

/* Mobile Header */
.mobile-header {
  text-align: center;
  margin-bottom: 2rem;
}

.mobile-logo {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.25rem;
  box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.35);
}

.mobile-logo i {
  font-size: 1.75rem;
  color: var(--white);
}

.mobile-header h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.375rem;
  letter-spacing: -0.02em;
}

.mobile-header p {
  font-size: 0.9375rem;
  color: var(--gray-500);
}

/* Desktop Form Header */
.form-header {
  display: none;
  margin-bottom: 2rem;
}

.form-header h2 {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.5rem;
  letter-spacing: -0.02em;
}

.form-header p {
  font-size: 0.9375rem;
  color: var(--gray-500);
}

/* Alert Styles */
.alert {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border-radius: 10px;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}

.alert i { font-size: 1.125rem; flex-shrink: 0; margin-top: 1px; }
.alert-danger { background: var(--danger-light); color: var(--danger); border: 1px solid var(--danger-border); }
.alert-success { background: var(--success-light); color: var(--success); border: 1px solid var(--success-border); }

/* Form Styles */
.form-group { margin-bottom: 1.25rem; }

.form-label {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: 0.5rem;
}

.input-wrapper { position: relative; }

.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  font-size: 1.125rem;
  pointer-events: none;
  transition: color 0.2s;
}

.form-control {
  width: 100%;
  padding: 0.875rem 0.875rem 0.875rem 2.75rem;
  font-size: 0.9375rem;
  font-family: inherit;
  color: var(--gray-900);
  background: var(--gray-50);
  border: 1.5px solid var(--gray-200);
  border-radius: 10px;
  transition: all 0.2s;
}

.form-control::placeholder { color: var(--gray-400); }
.form-control:hover { border-color: var(--gray-300); background: var(--white); }
.form-control:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-light);
  background: var(--white);
}
.input-wrapper:focus-within .input-icon { color: var(--primary); }

.password-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--gray-400);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.password-toggle:hover { color: var(--gray-600); background: var(--gray-100); }

/* Button Styles */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.875rem 1.25rem;
  font-size: 0.9375rem;
  font-weight: 600;
  font-family: inherit;
  border-radius: 10px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  color: var(--white);
  box-shadow: 0 4px 14px -3px rgba(2, 132, 199, 0.4);
}

.btn-primary:hover {
  box-shadow: 0 6px 20px -3px rgba(2, 132, 199, 0.5);
  transform: translateY(-1px);
}

.btn-primary:active { transform: translateY(0); }
.btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

/* Divider */
.divider {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 1.5rem 0;
}

.divider::before, .divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--gray-200);
}

.divider span {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Google Button */
.btn-google {
  background: var(--white);
  color: var(--gray-700);
  border: 1.5px solid var(--gray-200);
}

.btn-google:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
  transform: translateY(-1px);
}

.btn-google svg { flex-shrink: 0; }

/* Footer */
.form-footer {
  text-align: center;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-100);
}

.form-footer p {
  font-size: 0.8125rem;
  color: var(--gray-400);
}

/* Spinner */
.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: var(--white);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.d-none { display: none !important; }

/* Desktop Styles */
@media (min-width: 1024px) {
  .branding-panel { display: flex; }
  .mobile-header { display: none; }
  .form-header { display: block; }
  .form-panel { 
    flex: 0 0 50%; 
    max-width: 50%; 
  }
}

@media (min-width: 1280px) {
  .branding-main h1 { font-size: 3rem; }
  .form-panel {
    flex: 0 0 45%;
    max-width: 45%;
  }
}

/* Mobile responsive for system navigation */
@media (max-width: 640px) {
  .branding-main h1 {
    font-size: 2rem;
    margin-bottom: 0.75rem;
  }

  .feature-list {
    gap: 0.5rem;
    margin-bottom: 1rem;
  }

  .system-navigation {
    padding-top: 0.75rem;
  }

  .system-navigation h3 {
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
  }

  .system-buttons {
    gap: 0.5rem;
  }

  .system-btn {
    min-width: 120px;
    max-width: 140px;
    padding: 0.625rem 0.75rem;
  }

  .system-btn i {
    font-size: 1.1rem;
  }

  .system-btn span {
    font-size: 0.75rem;
  }
}
</style>
</head>
<body>

<div class="login-wrapper">
  
  <!-- Left Panel - Branding (Desktop Only) -->
  <div class="branding-panel">
    <div class="branding-content">
      
      <div class="branding-logo">
        <div class="branding-logo-icon">
          <i class="bi bi-shield-plus"></i>
        </div>
        <span>Animal Bite Portal</span>
      </div>
      
      <div class="branding-main">
        <h1>Talisay City Health Animal Bite Center</h1>
        
        <div class="feature-list">
          <div class="feature-item">
            <div class="feature-icon"><i class="bi bi-clipboard2-pulse"></i></div>
            <span>Case tracking and monitoring</span>
          </div>
          <div class="feature-item">
            <div class="feature-icon"><i class="bi bi-geo-alt"></i></div>
            <span>Geographic incident heat mapping</span>
          </div>
          <div class="feature-item">
            <div class="feature-icon"><i class="bi bi-file-earmark-medical"></i></div>
            <span>Digital patient record management</span>
          </div>
          <div class="feature-item">
            <div class="feature-icon"><i class="bi bi-graph-up"></i></div>
            <span>Decision support dashboard</span>
          </div>
        </div>

        <!-- System Access Navigation -->
        <div class="system-navigation">
          <h3>Choose Your System Access</h3>
          <div class="system-buttons">
            <a href="admin_login.php" class="system-btn system-btn-active">
              <i class="bi bi-building"></i>
              <span>City Health Office<br><small>Admin Dashboard</small></span>
            </a>
            <a href="staff_login.php" class="system-btn">
              <i class="bi bi-person-badge"></i>
              <span>Barangay Health Workers<br><small>Staff Portal</small></span>
            </a>
          </div>
        </div>
      </div>
      
      <div class="branding-footer">
        <p>&copy; <?php echo date('Y'); ?> City Health Office. All rights reserved.</p>
      </div>
      
    </div>
  </div>
  
  <!-- Right Panel - Login Form -->
  <div class="form-panel">
    <div class="form-container">
      
      <!-- Mobile Header -->
      <div class="mobile-header">
        <div class="mobile-logo">
          <i class="bi bi-shield-plus"></i>
        </div>
        <h1>Animal Bite Portal</h1>
        <p>City Health Office Administration</p>
      </div>
      
      <!-- Desktop Form Header -->
      <div class="form-header">
        <h2>Welcome back</h2>
        <p>Sign in to access your admin dashboard</p>
      </div>
      
      <?php if(!empty($error)): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>
      
      <?php if(!empty($success)): ?>
      <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <span><?php echo htmlspecialchars($success); ?> Redirecting to dashboard...</span>
      </div>
      <script>setTimeout(()=>{window.location.href='../admin/admin_dashboard.php';},1500);</script>
      <?php endif; ?>
      
      <form id="loginForm" method="POST" action="">
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-wrapper">
            <input type="email" name="email" id="email" class="form-control" placeholder="admin@example.com" required autofocus autocomplete="email">
            <i class="bi bi-envelope input-icon"></i>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
            <i class="bi bi-lock input-icon"></i>
            <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary" id="loginBtn">
          <span id="btn-text">Sign In</span>
          <div class="spinner d-none" id="btn-spinner"></div>
        </button>
      </form>
      
      <div class="divider"><span>or</span></div>
      
      <button type="button" class="btn btn-google" id="googleSignInBtn">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Continue with Google
      </button>
      
      <div class="form-footer">
        <p>Secure access for authorized personnel only</p>
      </div>
      
    </div>
  </div>
  
</div>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.13.0/firebase-app.js";
import { getAuth, signInWithPopup, GoogleAuthProvider, getIdToken } from "https://www.gstatic.com/firebasejs/10.13.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyCFBtZ_T_hNpqNyDRQjLy2aA_J6rxvVlhQ",
  authDomain: "animalbitemapping.firebaseapp.com",
  projectId: "animalbitemapping",
  storageBucket: "animalbitemapping.firebasestorage.app",
  messagingSenderId: "363708456089",
  appId: "1:363708456089:web:911890a45c177d0289ac08",
  measurementId: "G-KFCC50YQ7J"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const provider = new GoogleAuthProvider();

document.getElementById('googleSignInBtn').addEventListener('click', async function() {
  const btn = this;
  const originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="border-color: rgba(0,0,0,0.2); border-top-color: #374151;"></div><span>Connecting...</span>';
  
  try {
    const result = await signInWithPopup(auth, provider);
    const user = result.user;
    const idToken = await getIdToken(user, true);
    const res = await fetch('firebase_auth.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ idToken: idToken, type: 'admin' })
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = data.redirect || '../admin/admin_dashboard.php';
    } else {
      alert(data.error || 'Google login failed');
      btn.disabled = false;
      btn.innerHTML = originalHTML;
    }
  } catch(err) {
    console.error(err);
    alert('Google login failed. Please try again.');
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  }
});
</script>

<script>
// Password visibility toggle
document.getElementById('togglePassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('password');
  const icon = this.querySelector('i');
  const isPassword = passwordInput.type === 'password';
  passwordInput.type = isPassword ? 'text' : 'password';
  icon.classList.toggle('bi-eye', !isPassword);
  icon.classList.toggle('bi-eye-slash', isPassword);
});

// Form submission spinner
document.getElementById('loginForm').addEventListener('submit', function(e) {
  const btn = document.getElementById('loginBtn');
  if (btn.disabled) {
    e.preventDefault();
    return;
  }
  btn.disabled = true;
  document.getElementById('btn-text').classList.add('d-none');
  document.getElementById('btn-spinner').classList.remove('d-none');
});

// Rate limiting and lockout countdown
(function() {
  const isLocked = <?php echo $is_locked; ?>;
  let lockoutSeconds = <?php echo $lock_remaining; ?>;
  
  const loginBtn = document.getElementById('loginBtn');
  const googleBtn = document.getElementById('googleSignInBtn');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  
  // Function to disable all login controls
  function disableLoginControls() {
    loginBtn.disabled = true;
    googleBtn.disabled = true;
    emailInput.disabled = true;
    passwordInput.disabled = true;
  }
  
  // Function to enable all login controls
  function enableLoginControls() {
    loginBtn.disabled = false;
    googleBtn.disabled = false;
    emailInput.disabled = false;
    passwordInput.disabled = false;
  }
  
  // Function to update button text with countdown
  function updateButtonCountdown() {
    const countText = `Locked: ${lockoutSeconds}s`;
    loginBtn.textContent = countText;
    googleBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg> ${countText}`;
  }
  
  // If currently locked out, disable and start countdown
  if (isLocked && lockoutSeconds > 0) {
    disableLoginControls();
    updateButtonCountdown();
    
    const interval = setInterval(() => {
      lockoutSeconds--;
      updateButtonCountdown();
      
      if (lockoutSeconds <= 0) {
        clearInterval(interval);
        enableLoginControls();
        loginBtn.textContent = 'Sign In';
        googleBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg> Continue with Google`;
      }
    }, 1000);
  }
})();
</script>

</body>
</html>
