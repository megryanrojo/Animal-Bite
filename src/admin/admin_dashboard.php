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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root {
      --bs-primary: #0d6efd;
      --bs-primary-rgb: 13, 110, 253;
      --bs-secondary: #f8f9fa;
      --bs-secondary-rgb: 248, 249, 250;
    }
    
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }
    
    .navbar {
      background-color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .welcome-section {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .dashboard-card {
      height: 100%;
      border: none;
      border-radius: 10px;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
      overflow: hidden;
    }
    
    .dashboard-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .card-icon {
      font-size: 2rem;
      color: var(--bs-primary);
      margin-bottom: 1rem;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    .card-title {
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    .card-footer {
      background-color: rgba(var(--bs-primary-rgb), 0.05);
      border-top: none;
      padding: 1rem 1.5rem;
    }
    
    .btn-logout {
      background-color: #dc3545;
      color: white;
      border: none;
      padding: 0.5rem 1.25rem;
      border-radius: 5px;
      transition: all 0.2s;
    }
    
    .btn-logout:hover {
      background-color: #bb2d3b;
      color: white;
    }
    
    .stats-card {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-top: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .stats-number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--bs-primary);
    }
    
    @media (max-width: 768px) {
      .dashboard-container {
        padding: 1rem;
      }
      
      .welcome-section {
        padding: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light sticky-top mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="#">BHW Admin Portal</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" href="#"><i class="bi bi-house-door"></i> Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="admin_settings.php"><i class="bi bi-gear"></i> Settings</a>
          </li>
          <li class="nav-item">
            <a class="nav-link btn-logout ms-2" href="../logout/admin_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="dashboard-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="mb-0">Welcome, Admin</h2>
          <p class="text-muted mb-0">Barangay Health Workers Management System</p>
        </div>
      </div>
    </div>

    <!-- Main Cards -->
    <div class="row g-4">
      <div class="col-md-4">
        <a href="add_staff.html" class="text-decoration-none text-dark">
          <div class="card dashboard-card">
            <div class="card-body text-center">
              <div class="card-icon">
                <i class="bi bi-person-plus"></i>
              </div>
              <h4 class="card-title">Add Staff</h4>
              <p class="card-text text-muted">Register new barangay health workers</p>
            </div>
            <div class="card-footer text-center">
              <span class="fw-medium">Add New Staff <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="view_staff.php" class="text-decoration-none text-dark">
          <div class="card dashboard-card">
            <div class="card-body text-center">
              <div class="card-icon">
                <i class="bi bi-people"></i>
              </div>
              <h4 class="card-title">View Staff</h4>
              <p class="card-text text-muted">Manage existing staff records</p>
            </div>
            <div class="card-footer text-center">
              <span class="fw-medium">View All Staff <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="view_reports.php" class="text-decoration-none text-dark">
          <div class="card dashboard-card">
            <div class="card-body text-center">
              <div class="card-icon">
                <i class="bi bi-file-earmark-text"></i>
              </div>
              <h4 class="card-title">View Reports</h4>
              <p class="card-text text-muted">Check user-submitted bite reports</p>
            </div>
            <div class="card-footer text-center">
              <span class="fw-medium">View All Reports <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
          </div>
        </a>
      </div>
    </div>

    <!-- Stats Section -->
    <div class="row g-4">
      <div class="col-md-4">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Recent Activity</h5>
              <div class="stats-number">24</div>
              <p class="text-muted mb-0">New reports this week</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-activity"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Staff Members</h5>
              <div class="stats-number">12</div>
              <p class="text-muted mb-0">Active health workers</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-person-badge"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="stats-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Urgent Cases</h5>
              <div class="stats-number">5</div>
              <p class="text-muted mb-0">Require immediate attention</p>
            </div>
            <div class="card-icon">
              <i class="bi bi-exclamation-triangle"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>