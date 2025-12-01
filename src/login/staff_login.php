<?php
session_start();
require_once '../conn/conn.php';

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
if (!$locked_out && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

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

                // Set session variables
                $_SESSION['staff_id'] = $staff['staffId'];
                $_SESSION['staff_name'] = $staff['firstName'] . ' ' . $staff['lastName'];
                $_SESSION['staff_email'] = $staff['email'];
                $_SESSION['staff_barangay'] = $staff['assignedBarangay'];

                // Redirect to staff dashboard
                header("Location: ../staff/dashboard.php");
                exit();
            } else {
                // Failed login: increment attempts
                $_SESSION['staff_login_attempts']++;
                if ($_SESSION['staff_login_attempts'] >= MAX_ATTEMPTS) {
                    $_SESSION['staff_lockout_time'] = time();
                }
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay Health Worker Login | Animal Bite Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
            position: relative;
        }

        .login-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 0.875rem;
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
            color: #10b981 !important;
            border-color: rgba(255, 255, 255, 0.9) !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
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

        .login-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: #f9fafb;
        }

        .form-control:focus {
            outline: none;
            border-color: #10b981;
            background: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1rem;
        }

        .form-control:focus + .input-icon {
            color: #10b981;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
        }

        .password-toggle:hover {
            color: #10b981;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }

        .btn-login:hover:not(:disabled) {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
        }

        .alert-warning {
            background: #fefce8;
            color: #d97706;
        }

        .lockout-timer {
            font-weight: 600;
            color: #dc2626;
        }

        @media (max-width: 640px) {
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

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
                max-width: none;
            }

            .login-header {
                padding: 1.5rem;
            }

            .system-navigation {
                padding-top: 0.75rem;
            }

            .system-navigation h3 {
                font-size: 0.9rem;
                margin-bottom: 0.75rem;
            }

            .login-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <i class="bi bi-person-badge" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
            <h1>Barangay Health Worker</h1>
            <p>Community Health Monitoring Portal</p>

            <!-- System Access Navigation -->
            <div class="system-navigation">
                <h3>Choose Your System Access</h3>
                <div class="system-buttons">
                    <a href="admin_login.php" class="system-btn">
                        <i class="bi bi-building"></i>
                        <span>City Health Office<br><small>Admin Dashboard</small></span>
                    </a>
                    <a href="staff_login.php" class="system-btn system-btn-active">
                        <i class="bi bi-person-badge"></i>
                        <span>Barangay Health Workers<br><small>Staff Portal</small></span>
                    </a>
                </div>
            </div>
        </div>

        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-<?php echo $locked_out ? 'warning' : 'danger'; ?>">
                    <i class="bi <?php echo $locked_out ? 'bi-clock' : 'bi-exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <?php if ($locked_out): ?>
                        <div class="lockout-timer" id="lockout-timer"></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="email" class="form-control" placeholder="staff@barangay.gov.ph" required autofocus autocomplete="email" <?php echo $locked_out ? 'disabled' : ''; ?>>
                        <i class="bi bi-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password" <?php echo $locked_out ? 'disabled' : ''; ?>>
                        <i class="bi bi-lock input-icon"></i>
                        <button type="button" class="password-toggle" id="togglePassword" <?php echo $locked_out ? 'disabled' : ''; ?>>
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" <?php echo $locked_out ? 'disabled' : ''; ?>>
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Sign In as Staff
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($locked_out): ?>
        // Lockout timer
        let remainingTime = <?php echo $lockout_remaining; ?>;
        const timerElement = document.getElementById('lockout-timer');

        function updateTimer() {
            const minutes = Math.floor(remainingTime / 60);
            const seconds = remainingTime % 60;
            timerElement.textContent = `Time remaining: ${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (remainingTime > 0) {
                remainingTime--;
                setTimeout(updateTimer, 1000);
            } else {
                location.reload(); // Auto-refresh when lockout expires
            }
        }

        updateTimer();

        // Disable form during lockout
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
        });
        <?php endif; ?>

        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isPassword);
            icon.classList.toggle('bi-eye-slash', isPassword);
        });

        // Auto-focus email field if not locked out
        <?php if (!$locked_out): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
        <?php endif; ?>
    </script>

</body>
</html>
