<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login/staff_login.html");
    exit;
}

require_once '../conn/conn.php';

// Check if report ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$reportId = $_GET['id'];

// Get staff information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff = ['firstName' => 'User', 'lastName' => ''];
}

// Get report details with patient information
try {
    $reportStmt = $pdo->prepare("
        SELECT r.*, 
               p.firstName as patientFirstName, 
               p.lastName as patientLastName,
               p.gender as patientGender,
               p.dateOfBirth as patientDOB,
               p.contactNumber as patientContact,
               p.address as patientAddress,
               p.barangay as patientBarangay,
               s.firstName as staffFirstName,
               s.lastName as staffLastName
        FROM reports r
        LEFT JOIN patients p ON r.patientId = p.patientId
        LEFT JOIN staff s ON r.staffId = s.staffId
        WHERE r.reportId = ?
    ");
    $reportStmt->execute([$reportId]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        header("Location: dashboard.php");
        exit;
    }
    
    // Calculate patient age
    $patientAge = '';
    if (!empty($report['patientDOB'])) {
        $birthDate = new DateTime($report['patientDOB']);
        $today = new DateTime();
        $patientAge = $birthDate->diff($today)->y;
    }
    
} catch (PDOException $e) {
    // Handle error
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Animal Bite Report | Animal Bite Center</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root {
      --bs-primary: #28a745;
      --bs-primary-rgb: 40, 167, 69;
      --bs-secondary: #f8f9fa;
      --bs-secondary-rgb: 248, 249, 250;
    }
    
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    
    .navbar {
      background-color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .navbar-brand {
      display: flex;
      align-items: center;
    }
    
    .navbar-brand i {
      color: var(--bs-primary);
      margin-right: 0.5rem;
      font-size: 1.5rem;
    }
    
    .nav-link.active {
      color: var(--bs-primary) !important;
      font-weight: 500;
    }
    
    .btn-primary {
      background-color: var(--bs-primary);
      border-color: var(--bs-primary);
    }
    
    .btn-primary:hover {
      background-color: #218838;
      border-color: #1e7e34;
    }
    
    .btn-outline-primary {
      color: var(--bs-primary);
      border-color: var(--bs-primary);
    }
    
    .btn-outline-primary:hover {
      background-color: var(--bs-primary);
      border-color: var(--bs-primary);
    }
    
    .report-container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 2rem 1rem;
      flex-grow: 1;
    }
    
    .report-card {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      overflow: hidden;
    }
    
    .report-header {
      padding: 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      background-color: rgba(var(--bs-primary-rgb), 0.03);
    }
    
    .report-body {
      padding: 1.5rem;
    }
    
    .report-section {
      margin-bottom: 2rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .report-section:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    
    .section-title {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: var(--bs-primary);
      display: flex;
      align-items: center;
    }
    
    .section-title i {
      margin-right: 0.5rem;
    }
    
    .info-group {
      margin-bottom: 1.5rem;
    }
    
    .info-label {
      font-weight: 600;
      margin-bottom: 0.25rem;
      color: #495057;
    }
    
    .info-value {
      margin-bottom: 0;
    }
    
    .badge-category-i {
      background-color: #6c757d;
      color: white;
      padding: 0.35em 0.65em;
      font-size: 0.75em;
      font-weight: 700;
      border-radius: 0.25rem;
    }
    
    .badge-category-ii {
      background-color: #ffc107;
      color: #212529;
      padding: 0.35em 0.65em;
      font-size: 0.75em;
      font-weight: 700;
      border-radius: 0.25rem;
    }
    
    .badge-category-iii {
      background-color: #dc3545;
      color: white;
      padding: 0.35em 0.65em;
      font-size: 0.75em;
      font-weight: 700;
      border-radius: 0.25rem;
    }
    
    .badge-pending {
      background-color: #17a2b8;
      color: white;
      padding: 0.35em 0.65em;
      font-size: 0.75em;
      font-weight: 700;
      border-radius: 0.25rem;
    }
    
    .badge-in-progress {
      background-color: #007bff;
      color: white;
      padding: 0.35em 0.65em;
      font-size: 0.75em;
      font-weight: 700;
      border-radius: 0.25rem;
    }
    
    .badge-completed {
      background-color: #28a745;
      color: white;
      padding: 0.35em 0.65em;
      font-size: 0.75em;
      font-weight: 700;
      border-radius: 0.25rem;
    }
    
    .badge-referred {
      background-color: #6c757d;
      color: white;
      padding: 0.35em 0.65em;
      font-size: 0.75em;
      font-weight: 700;
      border-radius: 0.25rem;
    }
    
    .footer {
      background-color: white;
      padding: 1rem 0;
      margin-top: auto;
      border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .treatment-check {
      display: inline-block;
      width: 1.25rem;
      height: 1.25rem;
      line-height: 1.25rem;
      text-align: center;
      border-radius: 50%;
      margin-right: 0.5rem;
      font-size: 0.75rem;
    }
    
    .treatment-check.yes {
      background-color: #28a745;
      color: white;
    }
    
    .treatment-check.no {
      background-color: #dc3545;
      color: white;
    }
    
    @media print {
      .navbar, .footer, .action-buttons {
        display: none !important;
      }
      
      body {
        background-color: white;
      }
      
      .report-container {
        padding: 0;
      }
      
      .report-card {
        box-shadow: none;
        border: none;
      }
      
      .report-header {
        background-color: white !important;
      }
    }
    
    @media (max-width: 768px) {
      .report-container {
        padding: 1rem;
      }
      
      .report-header, .report-body {
        padding: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
      <a class="navbar-brand" href="dashboard.php">
        <i class="bi bi-heart-pulse"></i>
        <span class="fw-bold">Animal Bite Center</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item">
            <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door me-1"></i> Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="reports.php"><i class="bi bi-file-earmark-text me-1"></i> Reports</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="patients.php"><i class="bi bi-people me-1"></i> Patients</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="resources.php"><i class="bi bi-journal-medical me-1"></i> Resources</a>
          </li>
        </ul>
        <div class="d-flex align-items-center">
          <div class="dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
              <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
              <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="../logout/staff_logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <div class="report-container">
    <div class="report-card">
      <div class="report-header">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Animal Bite Report #<?php echo $reportId; ?></h2>
          <span class="badge badge-<?php echo str_replace('_', '-', strtolower($report['status'])); ?>">
            <?php echo ucfirst($report['status']); ?>
          </span>
        </div>
        <div class="d-flex flex-wrap gap-3">
          <div>
            <small class="text-muted d-block">Report Date:</small>
            <strong><?php echo date('F d, Y', strtotime($report['reportDate'])); ?></strong>
          </div>
          <div>
            <small class="text-muted d-block">Bite Date:</small>
            <strong><?php echo date('F d, Y', strtotime($report['biteDate'])); ?></strong>
          </div>
          <div>
            <small class="text-muted d-block">Recorded By:</small>
            <strong><?php echo htmlspecialchars($report['staffFirstName'] . ' ' . $report['staffLastName']); ?></strong>
          </div>
          <div>
            <small class="text-muted d-block">Bite Category:</small>
            <span class="badge badge-category-<?php echo strtolower(str_replace('Category ', '', $report['biteType'])); ?>">
              <?php echo $report['biteType']; ?>
            </span>
          </div>
        </div>
      </div>
      
      <div class="report-body">
        <!-- Patient Information Section -->
        <div class="report-section">
          <h3 class="section-title"><i class="bi bi-person"></i> Patient Information</h3>
          
          <div class="row">
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Patient Name</div>
                <div class="info-value"><?php echo htmlspecialchars($report['patientFirstName'] . ' ' . $report['patientLastName']); ?></div>
              </div>
            </div>
            
            <div class="col-md-3">
              <div class="info-group">
                <div class="info-label">Gender</div>
                <div class="info-value"><?php echo htmlspecialchars($report['patientGender'] ?? 'Not specified'); ?></div>
              </div>
            </div>
            
            <div class="col-md-3">
              <div class="info-group">
                <div class="info-label">Age</div>
                <div class="info-value"><?php echo $patientAge ? $patientAge . ' years' : 'Not specified'; ?></div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Contact Number</div>
                <div class="info-value"><?php echo htmlspecialchars($report['patientContact'] ?? 'Not provided'); ?></div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Barangay</div>
                <div class="info-value"><?php echo htmlspecialchars($report['patientBarangay'] ?? 'Not provided'); ?></div>
              </div>
            </div>
            
            <div class="col-md-12">
              <div class="info-group">
                <div class="info-label">Address</div>
                <div class="info-value"><?php echo htmlspecialchars($report['patientAddress'] ?? 'Not provided'); ?></div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Animal Information Section -->
        <div class="report-section">
          <h3 class="section-title"><i class="bi bi-exclamation-triangle"></i> Animal Information</h3>
          
          <div class="row">
            <div class="col-md-4">
              <div class="info-group">
                <div class="info-label">Animal Type</div>
                <div class="info-value">
                  <?php 
                    echo htmlspecialchars($report['animalType']);
                    if ($report['animalType'] === 'Other' && !empty($report['animalOtherType'])) {
                      echo ' (' . htmlspecialchars($report['animalOtherType']) . ')';
                    }
                  ?>
                </div>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="info-group">
                <div class="info-label">Animal Ownership</div>
                <div class="info-value"><?php echo htmlspecialchars($report['animalOwnership']); ?></div>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="info-group">
                <div class="info-label">Animal Status</div>
                <div class="info-value"><?php echo htmlspecialchars($report['animalStatus']); ?></div>
              </div>
            </div>
            
            <?php if (in_array($report['animalOwnership'], ['Owned by patient', 'Owned by neighbor', 'Owned by unknown person'])): ?>
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Owner's Name</div>
                <div class="info-value"><?php echo htmlspecialchars($report['ownerName'] ?? 'Not provided'); ?></div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Owner's Contact</div>
                <div class="info-value"><?php echo htmlspecialchars($report['ownerContact'] ?? 'Not provided'); ?></div>
              </div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-4">
              <div class="info-group">
                <div class="info-label">Animal Vaccinated Against Rabies</div>
                <div class="info-value"><?php echo htmlspecialchars($report['animalVaccinated']); ?></div>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="info-group">
                <div class="info-label">Was the bite provoked?</div>
                <div class="info-value"><?php echo htmlspecialchars($report['provoked']); ?></div>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="info-group">
                <div class="info-label">Multiple Bites</div>
                <div class="info-value"><?php echo $report['multipleBites'] ? 'Yes' : 'No'; ?></div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Bite Information Section -->
        <div class="report-section">
          <h3 class="section-title"><i class="bi bi-bullseye"></i> Bite Information</h3>
          
          <div class="row">
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Bite Location (Body Part)</div>
                <div class="info-value"><?php echo htmlspecialchars($report['biteLocation']); ?></div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Bite Category</div>
                <div class="info-value">
                  <span class="badge badge-category-<?php echo strtolower(str_replace('Category ', '', $report['biteType'])); ?>">
                    <?php echo $report['biteType']; ?>
                  </span>
                  
                  <div class="mt-2 small">
                    <?php if ($report['biteType'] === 'Category I'): ?>
                      <em>Touching or feeding of animals, licks on intact skin</em>
                    <?php elseif ($report['biteType'] === 'Category II'): ?>
                      <em>Nibbling of uncovered skin, minor scratches or abrasions without bleeding</em>
                    <?php elseif ($report['biteType'] === 'Category III'): ?>
                      <em>Single or multiple transdermal bites or scratches, contamination of mucous membrane or broken skin with saliva from animal licks, exposures due to direct contact with bats</em>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Treatment Information Section -->
        <div class="report-section">
          <h3 class="section-title"><i class="bi bi-bandaid"></i> Treatment Information</h3>
          
          <div class="row">
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Wound washed with soap and water</div>
                <div class="info-value">
                  <span class="treatment-check <?php echo $report['washWithSoap'] ? 'yes' : 'no'; ?>">
                    <?php echo $report['washWithSoap'] ? '✓' : '✗'; ?>
                  </span>
                  <?php echo $report['washWithSoap'] ? 'Yes' : 'No'; ?>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Rabies vaccine administered</div>
                <div class="info-value">
                  <span class="treatment-check <?php echo $report['rabiesVaccine'] ? 'yes' : 'no'; ?>">
                    <?php echo $report['rabiesVaccine'] ? '✓' : '✗'; ?>
                  </span>
                  <?php echo $report['rabiesVaccine'] ? 'Yes' : 'No'; ?>
                  <?php if ($report['rabiesVaccine'] && !empty($report['rabiesVaccineDate'])): ?>
                    <div class="small text-muted mt-1">Date: <?php echo date('F d, Y', strtotime($report['rabiesVaccineDate'])); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Anti-tetanus administered</div>
                <div class="info-value">
                  <span class="treatment-check <?php echo $report['antiTetanus'] ? 'yes' : 'no'; ?>">
                    <?php echo $report['antiTetanus'] ? '✓' : '✗'; ?>
                  </span>
                  <?php echo $report['antiTetanus'] ? 'Yes' : 'No'; ?>
                  <?php if ($report['antiTetanus'] && !empty($report['antiTetanusDate'])): ?>
                    <div class="small text-muted mt-1">Date: <?php echo date('F d, Y', strtotime($report['antiTetanusDate'])); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Antibiotics prescribed</div>
                <div class="info-value">
                  <span class="treatment-check <?php echo $report['antibiotics'] ? 'yes' : 'no'; ?>">
                    <?php echo $report['antibiotics'] ? '✓' : '✗'; ?>
                  </span>
                  <?php echo $report['antibiotics'] ? 'Yes' : 'No'; ?>
                  <?php if ($report['antibiotics'] && !empty($report['antibioticsDetails'])): ?>
                    <div class="small text-muted mt-1">Details: <?php echo htmlspecialchars($report['antibioticsDetails']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Referred to hospital</div>
                <div class="info-value">
                  <span class="treatment-check <?php echo $report['referredToHospital'] ? 'yes' : 'no'; ?>">
                    <?php echo $report['referredToHospital'] ? '✓' : '✗'; ?>
                  </span>
                  <?php echo $report['referredToHospital'] ? 'Yes' : 'No'; ?>
                  <?php if ($report['referredToHospital'] && !empty($report['hospitalName'])): ?>
                    <div class="small text-muted mt-1">Hospital: <?php echo htmlspecialchars($report['hospitalName']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="info-group">
                <div class="info-label">Follow-up Date</div>
                <div class="info-value">
                  <?php echo !empty($report['followUpDate']) ? date('F d, Y', strtotime($report['followUpDate'])) : 'Not scheduled'; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Additional Notes Section -->
        <?php if (!empty($report['notes'])): ?>
        <div class="report-section">
          <h3 class="section-title"><i class="bi bi-journal"></i> Additional Notes</h3>
          
          <div class="row">
            <div class="col-12">
              <div class="info-group">
                <div class="info-value"><?php echo nl2br(htmlspecialchars($report['notes'])); ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="d-flex justify-content-between mt-4 action-buttons">
          <div>
            <a href="reports.php" class="btn btn-outline-secondary me-2">
              <i class="bi bi-arrow-left me-2"></i>Back to Reports
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary">
              <i class="bi bi-house-door me-2"></i>Dashboard
            </a>
          </div>
          <div>
            <button onclick="window.print()" class="btn btn-outline-primary me-2">
              <i class="bi bi-printer me-2"></i>Print
            </button>
            <a href="edit_report.php?id=<?php echo $reportId; ?>" class="btn btn-primary">
              <i class="bi bi-pencil me-2"></i>Edit Report
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <small class="text-muted">&copy; <?php echo date('Y'); ?> Animal Bite Treatment Center</small>
        </div>
        <div>
          <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>