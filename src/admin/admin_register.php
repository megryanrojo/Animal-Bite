<?php
require_once '../conn/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // Check if email already exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = :email");
        $check->execute(['email' => $email]);
        if ($check->fetchColumn() > 0) {
            echo "<script>alert('Email already registered.'); window.location.href='admin_register.html';</script>";
            exit;
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert into DB
        $stmt = $pdo->prepare("INSERT INTO admin (name, email, password) VALUES (:name, :email, :password)");
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword
        ]);

        echo "<script>alert('Admin registered successfully!'); window.location.href='../login/admin_login.html';</script>";
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: admin_register.html");
    exit;
}
