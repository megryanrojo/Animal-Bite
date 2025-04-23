<?php
session_start();
require_once '../conn/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // Query for admin using the email
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $admin['adminId'];
            $_SESSION['admin_name'] = $admin['name'];

            // Redirect to dashboard
            header("Location: ../admin/admin_dashboard.php");
            exit;
        } else {
            echo "<script>alert('Invalid email or password.'); window.location.href='admin_login.html';</script>";
        }
    } catch (PDOException $e) {
        die("Login error: " . $e->getMessage());
    }
} else {
    header("Location: admin_login.html");
    exit;
}
