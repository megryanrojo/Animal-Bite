<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Check if staff ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid staff ID.";
    header("Location: view_staff.php");
    exit;
}

$staffId = $_GET['id'];

// Get staff details
try {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staffId = ?");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        $_SESSION['error'] = "Staff member not found.";
        header("Location: view_staff.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving staff details: " . $e->getMessage();
    header("Location: view_staff.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Details | BHW Admin Portal</title>
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
    
    .content-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }
    
    .navbar {
      background-color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .page-header {
      background-color: white;
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .content-card {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 1.5rem;
      overflow: hidden;
      border: none;
    }
    
    .content-card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      background-color: rgba(var(--bs-primary-rgb), 0.03);
    }
    
    .content-card-body {
      padding: 1.5rem;
    }
    
    .content-card-footer {
      padding: 1.25rem 1.5rem;
      background-color: rgba(0, 0, 0, 0.02);
      border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .btn-back {
      color: #6c757d;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
    }
    
    .btn-back:hover {
      color: #495057;
    }
    
    .btn-back i {
      margin-right: 0.5rem;
    }
    
    .detail-section {
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .detail-section:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    
    .detail-section-title {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 1.25rem;
      color: var(--bs-primary);
    }
    
    .detail-item {
      margin-bottom: 1rem;
    }
    
    .detail-label {
      font-weight: 500;
      color: #6c757d;
      margin-bottom: 0.25rem;
    }
    
    .detail-value {
      font-size: 1rem;
    }
    
    .profile-header {
      display: flex;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    
    .profile-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background-color: rgba(var(--bs-primary-rgb), 0.1);
      color: var(--bs-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin-right: 1.5rem;
    }
    
    .profile-info h3 {
      margin-bottom: 0.25rem;
    }
    
    .profile-info p {
      margin-bottom: 0;
      color: #6c757d;
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
    
    @media (max-width: 768px) {
      .content-container {
        padding: 1rem;
      }
      
      .page-header {
        padding: 1rem;
      }
      
      .content-card-header, .content-card-body, .content-card-footer {
        padding: 1rem;
      }
      
      .profile-header {
        flex-direction: column;
        text-align: center;
      }
      
      .profile-avatar {
        margin-right: 0;
        margin-bottom: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light sticky-top mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">BHW Admin Portal</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="geomapping.php"><i class="bi bi-geo-alt me-1"></i> Geomapping</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="view_reports.php"><i class="bi bi-file-earmark-text"></i> Reports</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="decisionSupport.php"><i class="bi bi-graph-up me-1"></i> Decision Support</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="view_staff.php"><i class="bi bi-people"></i> Staff</a>
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

  <div class="content-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="mb-0">Staff Details</h2>
          <p class="text-muted mb-0">View detailed information about the staff member</p>
        </div>
        <a href="view_staff.php" class="btn-back">
          <i class="bi bi-arrow-left"></i> Back to Staff List
        </a>
      </div>
    </div>

    <!-- Staff Details Card -->
    <div class="content-card">
      <div class="content-card-header">
        <h5 class="mb-0"><i class="bi bi-person me-2"></i>Staff Information</h5>
      </div>
      <div class="content-card-body">
        <!-- Profile Header -->
        <div class="profile-header">
          <div class="profile-avatar">
            <i class="bi bi-person"></i>
          </div>
          <div class="profile-info">
            <h3><?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?></h3>
            <p>Staff ID: <?php echo $staff['staffId']; ?></p>
          </div>
        </div>

        <!-- Personal Information Section -->
        <div class="detail-section">
          <div class="detail-section-title">
            <i class="bi bi-person me-2"></i>Personal Information
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="detail-item">
                <div class="detail-label">First Name</div>
                <div class="detail-value"><?php echo htmlspecialchars($staff['firstName']); ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="detail-item">
                <div class="detail-label">Last Name</div>
                <div class="detail-value"><?php echo htmlspecialchars($staff['lastName']); ?></div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="detail-item">
                <div class="detail-label">Birth Date</div>
                <div class="detail-value"><?php echo date('F d, Y', strtotime($staff['birthDate'])); ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="detail-item">
                <div class="detail-label">Age</div>
                <div class="detail-value">
                  <?php 
                    $birthDate = new DateTime($staff['birthDate']);
                    $today = new DateTime('today');
                    $age = $birthDate->diff($today)->y;
                    echo $age . ' years';
                  ?>
                </div>
              </div>
            </div>
          </div>

          <div class="detail-item">
            <div class="detail-label">Address</div>
            <div class="detail-value"><?php echo htmlspecialchars($staff['address']); ?></div>
          </div>
        </div>

        <!-- Contact Information Section -->
        <div class="detail-section">
          <div class="detail-section-title">
            <i class="bi bi-telephone me-2"></i>Contact Information
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="detail-item">
                <div class="detail-label">Contact Number</div>
                <div class="detail-value"><?php echo htmlspecialchars($staff['contactNumber']); ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="detail-item">
                <div class="detail-label">Email Address</div>
                <div class="detail-value"><?php echo htmlspecialchars($staff['email']); ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Account Information Section -->
        <div class="detail-section">
          <div class="detail-section-title">
            <i class="bi bi-shield-lock me-2"></i>Account Information
          </div>
          <div class="detail-item">
            <div class="detail-label">Account Status</div>
            <div class="detail-value">
              <span class="badge bg-success">Active</span>
            </div>
          </div>
        </div>
      </div>
      <div class="content-card-footer">
        <div class="d-flex justify-content-between align-items-center">
          <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $staff['staffId']; ?>, '<?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?>')">
            <i class="bi bi-trash me-2"></i>Delete Staff
          </button>
          <a href="edit_staff.php?id=<?php echo $staff['staffId']; ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-2"></i>Edit Staff
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete <span id="staffName" class="fw-bold"></span>? This action cannot be undone.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function confirmDelete(staffId, staffName) {
      document.getElementById('staffName').textContent = staffName;
      document.getElementById('confirmDeleteBtn').href = 'view_staff.php?delete=' + staffId;
      
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      deleteModal.show();
    }
  </script>
</body>
</html>