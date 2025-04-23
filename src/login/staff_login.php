<?php
session_start();
require '../conn/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'];
  $password = $_POST['password'];

  $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = :email");
  $stmt->execute(['email' => $email]);
  $staff = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($staff && password_verify($password, $staff['password'])) {
    $_SESSION['staffId'] = $staff['staffId'];
    $_SESSION['staffName'] = $staff['firstName'] . ' ' . $staff['lastName'];
    header("Location: dashboard.php");
    exit;
  } else {
    echo "Invalid login credentials.";
  }
}
?>
