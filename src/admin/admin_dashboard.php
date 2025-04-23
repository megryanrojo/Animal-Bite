<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .dashboard {
      max-width: 960px;
      margin: auto;
      padding: 2rem;
    }
    .card {
      cursor: pointer;
      transition: 0.3s ease-in-out;
    }
    .card:hover {
      transform: scale(1.03);
    }
  </style>
</head>
<body>
  <div class="dashboard">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Welcome, Admin</h2>
      <a href="../logout/admin_logout.php" class="btn btn-danger">Logout</a>
    </div>

    <div class="row g-4">
      <div class="col-md-4">
        <a href="add_staff.html" class="text-decoration-none text-dark">
          <div class="card text-center p-4 shadow-sm">
            <h4>Add Staff</h4>
            <p class="text-muted">Register new barangay health workers</p>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="view_staff.php" class="text-decoration-none text-dark">
          <div class="card text-center p-4 shadow-sm">
            <h4>View Staff</h4>
            <p class="text-muted">Manage existing staff records</p>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="view_reports.php" class="text-decoration-none text-dark">
          <div class="card text-center p-4 shadow-sm">
            <h4>View Reports</h4>
            <p class="text-muted">Check user-submitted bite reports</p>
          </div>
        </a>
      </div>
    </div>
  </div>
</body>
</html>
