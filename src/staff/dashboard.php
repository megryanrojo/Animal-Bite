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
  <style>
    * {
      box-sizing: border-box;
    }
    
    body {
      background-color: #f5f7fa;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh;
      margin: 0;
    }
    
    /* Header */
    .header {
      background: white;
      border-bottom: 1px solid #e5e7eb;
      padding: 1rem 1.5rem;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      font-size: 1.1rem;
      color: #1a1a1a;
    }

    .logo i {
      color: #16a34a;
      font-size: 1.25rem;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .btn-new {
      background: #16a34a;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      transition: background 0.2s;
    }

    .btn-new:hover {
      background: #15803d;
      color: white;
    }

    .btn-logout {
      background: #dc2626;
      color: white;
      border: none;
      padding: 0.5rem;
      border-radius: 6px;
      font-size: 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-logout:hover {
      background: #b91c1c;
      color: white;
    }
    
    /* Main Content */
    .main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1.5rem;
    }
    
    /* Welcome */
    .welcome {
      margin-bottom: 1.5rem;
    }
    
    .welcome h1 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1a1a1a;
      margin: 0 0 0.25rem 0;
    }
    
    .welcome p {
      color: #6b7280;
      margin: 0;
      font-size: 0.9rem;
    }
    
    /* Quick Actions */
    .quick-actions {
      margin-bottom: 3rem;
    }

    .action-card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      border-left: 4px solid #10b981;
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    .action-card.primary {
      border-left-color: #10b981;
    }

    .action-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
      flex-shrink: 0;
    }

    .action-content h3 {
      font-size: 1.25rem;
      font-weight: 600;
      color: #111827;
      margin-bottom: 0.5rem;
    }

    .action-content p {
      color: #6b7280;
      margin-bottom: 1rem;
    }

    .btn-action {
      background: #10b981;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: background 0.2s;
    }

    .btn-action:hover {
      background: #059669;
      color: white;
    }

    /* Instructions */
    .instructions {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .instructions h2 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #111827;
      margin-bottom: 1.5rem;
      text-align: center;
    }

    .steps {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .step {
      display: flex;
      gap: 1rem;
      align-items: flex-start;
    }

    .step-number {
      width: 40px;
      height: 40px;
      background: #10b981;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 1.1rem;
      flex-shrink: 0;
    }

    .step-content h4 {
      font-size: 1rem;
      font-weight: 600;
      color: #111827;
      margin-bottom: 0.5rem;
    }

    .step-content p {
      color: #6b7280;
      font-size: 0.875rem;
      line-height: 1.5;
      margin: 0;
    }
    
    /* Responsive */
    @media (max-width: 600px) {
      .header-content {
        flex-wrap: wrap;
      }
      
      .main {
        padding: 1rem;
      }
      
      .table th,
      .table td {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
      }
    }
  </style>
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
