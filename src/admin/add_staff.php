<?php
session_start();

require_once '../conn/conn.php';
require_once 'includes/logging_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $birthDate = $_POST['birthDate'];
        $address = trim($_POST['address']);
        $assignedBarangay = trim($_POST['assignedBarangay']);
        $contactNumber = trim($_POST['contactNumber']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO staff
            (firstName, lastName, birthDate, address, assignedBarangay, contactNumber, email, password)
            VALUES (:firstName, :lastName, :birthDate, :address, :assignedBarangay, :contactNumber, :email, :password)");

        $stmt->execute([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'birthDate' => $birthDate,
            'address' => $address,
            'assignedBarangay' => $assignedBarangay,
            'contactNumber' => $contactNumber,
            'email' => $email,
            'password' => $hashedPassword
        ]);

        $staffId = $pdo->lastInsertId();

        // Log staff creation
        logActivity($pdo, 'CREATE', 'staff', $staffId, null, "Added new staff: {$firstName} {$lastName}");

        $_SESSION['message'] = "Staff added successfully!";
        header("Location: add_staff_page.php?success=1");
        exit;

    } catch (PDOException $e) {
        die("Error adding staff: " . $e->getMessage());
    }
} else {
    header("Location: add_staff_page.php");
    exit;
}
?>
