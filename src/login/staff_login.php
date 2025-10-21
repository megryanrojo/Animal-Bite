<?php
session_start();
require '../conn/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        header("Location: staff_login.html?error=empty");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: staff_login.html?error=invalid_email");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff && password_verify($password, $staff['password'])) {
            $_SESSION['staffId'] = $staff['staffId'];
            $_SESSION['staffName'] = $staff['firstName'] . ' ' . $staff['lastName'];
            $_SESSION['staff_email'] = $staff['email'];
            $_SESSION['login_time'] = time();
            header("Location: ../staff/dashboard.php");
            exit;
        } else {
            // Log failed login attempt
            error_log("Failed staff login attempt for email: " . $email . " from IP: " . $_SERVER['REMOTE_ADDR']);
            header("Location: staff_login.html?error=invalid");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error during staff login: " . $e->getMessage());
        header("Location: staff_login.html?error=database_error");
        exit;
    }
} else {
    header("Location: staff_login.html");
    exit;
}
