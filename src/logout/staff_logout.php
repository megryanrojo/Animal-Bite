<?php
session_start();
require_once '../conn/conn.php';
require_once '../admin/includes/logging_helper.php';

// Log logout before destroying session
if (isset($_SESSION['staff_id'])) {
    logActivity($pdo, 'LOGOUT', 'staff', $_SESSION['staff_id'], $_SESSION['staff_id'], "Staff member logged out");
}

session_unset();
session_destroy();
header("Location: ../login/staff_login.php");
exit;
