<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: ../login/staff_login.php");
    exit;
}

require_once '../conn/conn.php';

// Get staff information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName, assignedBarangay FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staff_id']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    $assignedBarangay = $staff['assignedBarangay'] ?? '';
} catch (PDOException $e) {
    $staff = ['firstName' => 'User', 'lastName' => '', 'assignedBarangay' => ''];
    $assignedBarangay = '';
}

// Staff dashboard - simplified for encoding only
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | Animal Bite Center</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/BHW/bhw-dashboard.css" >
</head>
<body>

  <!-- Header -->
  <header class="header">
    <div class="header-content">
      <div class="logo">
        <i class="bi bi-heart-pulse"></i>
        <span>Animal Bite Center</span>
      </div>
      <div class="header-actions">
        <a href="new_report.php" class="btn-new">
          <i class="bi bi-plus"></i>
          New Report
        </a>
        <button class="btn-logout" onclick="confirmLogout()">
          <i class="bi bi-box-arrow-right"></i>
        </button>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main">
    
    <!-- Welcome -->
    <div class="welcome">
      <h1>Welcome back, <?php echo htmlspecialchars($staff['firstName']); ?></h1>
      <p>Report and document animal bite incidents in your barangay</p>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <div class="action-card primary">
        <div class="action-icon">
          <i class="bi bi-plus-circle"></i>
        </div>
        <div class="action-content">
          <h3>Create New Report</h3>
          <p>Document a new animal bite incident</p>
          <a href="new_report.php" class="btn-action">
            <i class="bi bi-plus me-1"></i>
            Start New Report
          </a>
        </div>
      </div>
    </div>

    <!-- Instructions -->
    <div class="instructions">
      <h2>How to Report</h2>
      <div class="steps">
        <div class="step">
          <div class="step-number">1</div>
          <div class="step-content">
            <h4>Find the Patient</h4>
            <p>Use the patient search to locate existing records or create a new patient entry.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-number">2</div>
          <div class="step-content">
            <h4>Record Incident Details</h4>
            <p>Document the bite location, animal type, circumstances, and patient information.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-number">3</div>
          <div class="step-content">
            <h4>Submit Report</h4>
            <p>The system will automatically classify the case and provide treatment recommendations.</p>
          </div>
        </div>
      </div>
    </div>

  </main>

  <script>
    function confirmLogout() {
      if (confirm('Are you sure you want to log out?')) {
        window.location.href = '../logout/staff_logout.php';
      }
    }
  </script>

</body>
</html>
