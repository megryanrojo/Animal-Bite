<?php
session_start();

// Log logout before destroying session
if (isset($_SESSION['admin_id'])) {
    require_once '../conn/conn.php';
    require_once '../admin/includes/logging_helper.php';
    logActivity($pdo, 'LOGOUT', 'admin', $_SESSION['admin_id'], $_SESSION['admin_id'], "Admin logged out");
}

session_unset();
session_destroy();
header("Location: ../login/admin_login.php");
exit;
