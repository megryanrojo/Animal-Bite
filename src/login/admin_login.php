<?php
session_start();
require_once '../conn/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        header("Location: admin_login.html?error=empty");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: admin_login.html?error=invalid_email");
        exit;
    }

    try {
        // Query for admin using the email
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $admin['adminId'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['login_time'] = time();

            // Redirect to dashboard
            header("Location: ../admin/admin_dashboard.php");
            exit;
        } else {
            // Log failed login attempt
            error_log("Failed admin login attempt for email: " . $email . " from IP: " . $_SERVER['REMOTE_ADDR']);
            header("Location: admin_login.html?error=invalid");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error during admin login: " . $e->getMessage());
        header("Location: admin_login.html?error=database_error");
        exit;
    }
} else {
    header("Location: admin_login.html");
    exit;
}
