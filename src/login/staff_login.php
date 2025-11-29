<?php
session_start();
require '../conn/conn.php';

// --- Rate Limiting Settings ---
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 5);
define('LOCKOUT_SECONDS', LOCKOUT_MINUTES * 60); // 300 seconds = 5 minutes

// --- Initialize session variables ---
if (!isset($_SESSION['staff_login_attempts'])) $_SESSION['staff_login_attempts'] = 0;
if (!isset($_SESSION['staff_lockout_time'])) $_SESSION['staff_lockout_time'] = 0;

// --- Check lockout status ---
$locked_out = false;
$lockout_remaining = 0;
$error = '';

if ($_SESSION['staff_login_attempts'] >= MAX_ATTEMPTS) {
    $time_elapsed = time() - $_SESSION['staff_lockout_time'];
    if ($time_elapsed < LOCKOUT_SECONDS) {
        $lockout_remaining = LOCKOUT_SECONDS - $time_elapsed;
        $locked_out = true;
        $error = "Too many login attempts. Please try again in {$lockout_remaining} seconds.";
    } else {
        // Auto-reset after lockout period expires
        $_SESSION['staff_login_attempts'] = 0;
        $_SESSION['staff_lockout_time'] = 0;
        $locked_out = false;
        $lockout_remaining = 0;
    }
}

// --- Email/password login ---
if (!$locked_out && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($staff && password_verify($password, $staff['password'])) {
                // Successful login: reset attempts
                $_SESSION['staff_login_attempts'] = 0;
                $_SESSION['staff_lockout_time'] = 0;
                session_regenerate_id(true);
                
                $_SESSION['staffId'] = $staff['staffId'];
                $_SESSION['staffName'] = $staff['firstName'] . ' ' . $staff['lastName'];
                $_SESSION['staff_email'] = $staff['email'];
                $_SESSION['login_time'] = time();
                
                header("Location: ../staff/dashboard.php");
                exit;
            } else {
                // Failed login attempt: increment counter
                $_SESSION['staff_login_attempts']++;
                
                if ($_SESSION['staff_login_attempts'] >= MAX_ATTEMPTS) {
                    // Trigger lockout
                    $_SESSION['staff_lockout_time'] = time();
                    $locked_out = true;
                    $lockout_remaining = LOCKOUT_SECONDS;
                    $error = "Too many login attempts. Account locked for {$lockout_remaining} seconds.";
                    error_log("Staff login lockout triggered for email: " . $email . " from IP: " . $_SERVER['REMOTE_ADDR']);
                } else {
                    $attempts_left = MAX_ATTEMPTS - $_SESSION['staff_login_attempts'];
                    $error = "Invalid email or password. {$attempts_left} attempt(s) remaining.";
                    error_log("Failed staff login attempt for email: " . $email . " from IP: " . $_SERVER['REMOTE_ADDR']);
                }
            }

        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
            error_log("Database error during staff login: " . $e->getMessage());
        }
    }
}

// --- Pass lockout state to JS ---
$is_locked = $locked_out ? 'true' : 'false';
$lock_remaining = (int)$lockout_remaining;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BHW Staff Portal | Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root {
      --bs-primary: #28a745;
      --bs-primary-rgb: 40, 167, 69;
    }
    
    body {
      background-color: #f0f2f5;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.05) 0%, rgba(var(--bs-primary-rgb), 0.1) 100%);
    }
    
    .login-container {
      width: 100%;
      max-width: 900px;
      padding: 1rem;
    }
    
    .login-card {
      background-color: white;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: row;
    }
    
    .login-sidebar {
      background-color: var(--bs-primary);
      color: white;
      padding: 3rem 2rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      width: 40%;
      position: relative;
      overflow: hidden;
    }
    
    .login-sidebar::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 60%);
      z-index: 0;
    }
    
    .login-sidebar-content {
      position: relative;
      z-index: 1;
    }
    
    .login-form {
      padding: 3rem 2rem;
      width: 60%;
    }
    
    .login-logo {
      font-size: 2.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
    }
    
    .login-logo i {
      margin-right: 0.75rem;
    }
    
    .login-title {
      font-size: 1.75rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    .login-subtitle {
      margin-bottom: 2rem;
      opacity: 0.8;
      font-size: 1rem;
    }
    
    .form-floating {
      margin-bottom: 1.5rem;
    }
    
    .form-floating > .form-control {
      padding-top: 1.625rem;
      padding-bottom: 0.625rem;
    }
    
    .form-floating > label {
      color: var(--bs-gray-500);
    }
    
    .form-control {
      border-radius: 10px;
      border: 1px solid #ddd;
    }
    
    .form-control:focus {
      border-color: var(--bs-primary);
      box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15);
    }
    
    .btn-login {
      padding: 0.75rem;
      font-size: 1rem;
      border-radius: 10px;
      font-weight: 500;
    }
    
    .password-container {
      position: relative;
    }
    
    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #999;
      cursor: pointer;
      padding: 0;
      font-size: 1.2rem;
    }
    
    .password-toggle:hover {
      color: var(--bs-primary);
    }
    
    .help-text {
      background-color: #f8f9fa;
      padding: 1rem;
      border-radius: 10px;
      margin-top: 2rem;
      font-size: 0.875rem;
    }
    
    .login-footer {
      text-align: center;
      margin-top: 2rem;
      font-size: 0.875rem;
      color: #999;
    }
    
    @media (max-width: 768px) {
      .login-card {
        flex-direction: column;
      }
      
      .login-sidebar {
        width: 100%;
        padding: 2rem;
      }
      
      .login-form {
        width: 100%;
        padding: 2rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-sidebar">
        <div class="login-sidebar-content">
          <div class="login-logo">
            <i class="bi bi-heart-pulse"></i>
            <span>BHW Portal</span>
          </div>
          <h2 class="login-title">Welcome Back!</h2>
          <p class="login-subtitle">Log in to access the Barangay Health Workers Portal.</p>
          <div class="d-none d-md-block">
            <p class="mb-1"><i class="bi bi-check-circle-fill me-2"></i> Submit health reports</p>
            <p class="mb-1"><i class="bi bi-check-circle-fill me-2"></i> Track patient records</p>
            <p class="mb-1"><i class="bi bi-check-circle-fill me-2"></i> Access health resources</p>
          </div>
        </div>
      </div>
      
      <div class="login-form">
        <h3 class="mb-4 text-center">Staff Login</h3>
        
        <!-- Error message container -->
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <span><?php echo htmlspecialchars($error); ?></span>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form action="staff_login.php" method="POST" id="loginForm">
          <div class="form-floating mb-4">
            <input type="email" name="email" id="email" class="form-control" placeholder="Email Address" required autofocus <?php echo $locked_out ? 'disabled' : ''; ?>>
            <label for="email"><i class="bi bi-envelope me-2"></i>Email Address</label>
          </div>
          
          <div class="form-floating mb-4 password-container">
            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required <?php echo $locked_out ? 'disabled' : ''; ?>>
            <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
            <button type="button" class="password-toggle" id="togglePassword" <?php echo $locked_out ? 'disabled' : ''; ?>>
              <i class="bi bi-eye"></i>
            </button>
          </div>
          
          <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-login" id="loginBtn" <?php echo $locked_out ? 'disabled' : ''; ?>>
              <i class="bi bi-box-arrow-in-right me-2"></i><span class="btn-text">Log In</span>
            </button>
          </div>
        </form>
        
        <!-- Google Sign-In Button -->
        <div class="text-center mb-3">
          <div class="position-relative">
            <hr>
            <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">or</span>
          </div>
        </div>
        
        <div class="d-grid">
          <button type="button" class="btn btn-outline-danger" id="googleSignInBtn" <?php echo $locked_out ? 'disabled' : ''; ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" class="me-2">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <span class="btn-text">Continue with Google</span>
          </button>
        </div>
        
        <div class="help-text">
          <div class="d-flex align-items-center mb-2">
            <i class="bi bi-question-circle me-2 text-primary"></i>
            <strong>Need Help?</strong>
          </div>
          <p class="mb-0">If you're having trouble logging in, please contact your administrator for assistance.</p>
        </div>
        
        <div class="login-footer">
          <p>© <?php echo date('Y'); ?> Barangay Health Workers Management System</p>
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

    // Initialize Firebase
    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const googleProvider = new GoogleAuthProvider();

    // Make auth, provider and functions available globally
    window.firebaseAuth = auth;
    window.googleProvider = googleProvider;
    window.getIdToken = getIdToken;
    window.signInWithPopup = signInWithPopup;
  </script>

  <script>
    // Rate limiter countdown and button disabling
    (function() {
      const isLocked = <?php echo $is_locked; ?>;
      let lockoutSeconds = <?php echo $lock_remaining; ?>;
      
      const loginBtn = document.getElementById('loginBtn');
      const googleBtn = document.getElementById('googleSignInBtn');
      const emailInput = document.getElementById('email');
      const passwordInput = document.getElementById('password');
      const toggleBtn = document.getElementById('togglePassword');
      
      // If locked out, disable all controls and show countdown
      if (isLocked && lockoutSeconds > 0) {
        emailInput.disabled = true;
        passwordInput.disabled = true;
        loginBtn.disabled = true;
        googleBtn.disabled = true;
        toggleBtn.disabled = true;
        
        // Update button text with countdown
        function updateCountdown() {
          const countText = `Locked: ${lockoutSeconds}s`;
          loginBtn.textContent = countText;
          googleBtn.textContent = countText;
        }
        
        updateCountdown();
        
        const interval = setInterval(() => {
          lockoutSeconds--;
          if (lockoutSeconds > 0) {
            updateCountdown();
          } else {
            clearInterval(interval);
            // Re-enable all controls
            emailInput.disabled = false;
            passwordInput.disabled = false;
            loginBtn.disabled = false;
            googleBtn.disabled = false;
            toggleBtn.disabled = false;
            loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i><span class="btn-text">Log In</span>';
            googleBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" class="me-2"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg> <span class="btn-text">Continue with Google</span>';
          }
        }, 1000);
      }
    })();

    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    });

    // Form submission
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const btn = document.getElementById('loginBtn');
      if (btn.disabled) {
        e.preventDefault();
      }
    });

    // Google Sign-In functionality with Firebase
    document.getElementById('googleSignInBtn').addEventListener('click', async function() {
      const btn = this;
      
      if (btn.disabled) {
        return;
      }
      
      const originalHTML = btn.innerHTML;
      
      // Disable button and show loading
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Signing in...';
      
      try {
        // Wait for Firebase to be initialized
        if (!window.firebaseAuth || !window.googleProvider) {
          await new Promise(resolve => setTimeout(resolve, 500));
        }

        // Sign in with Google using Firebase
        const result = await window.signInWithPopup(window.firebaseAuth, window.googleProvider);
        const user = result.user;
        
        // Get ID token
        const idToken = await window.getIdToken(user, true);
        
        // Send token to backend for verification
        const response = await fetch('firebase_auth.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            idToken: idToken,
            type: 'staff'
          })
        });

        const data = await response.json();

        if (data.success) {
          // Redirect to dashboard
          window.location.href = data.redirect || '../staff/dashboard.php';
        } else {
          alert(data.error || 'Google authentication failed. Please try again.');
          btn.disabled = false;
          btn.innerHTML = originalHTML;
        }
      } catch (error) {
        console.error('Google sign-in error:', error);
        
        let errorText = 'Google authentication failed. Please try again.';
        if (error.code === 'auth/popup-closed-by-user') {
          errorText = 'Sign-in was cancelled. Please try again.';
        } else if (error.code === 'auth/popup-blocked') {
          errorText = 'Popup was blocked. Please allow popups and try again.';
        }
        
        alert(errorText);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }
    });
  </script>
</body>
</html>


// --- Pass lockout state to JS ---
$is_locked = $locked_out ? 'true' : 'false';
$lock_remaining = (int)$lockout_remaining;
?>
