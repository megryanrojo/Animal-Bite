<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login/admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get admin information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM admin WHERE adminId = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin = ['firstName' => 'Admin', 'lastName' => ''];
}

// Get all patients for dropdown
try {
    $patientsStmt = $pdo->query("SELECT patientId, firstName, lastName FROM patients ORDER BY lastName, firstName");
    $patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients = [];
}

// Check if a patient is selected and has existing reports
$selectedPatientId = null;
$selectedPatientInfo = null;
$existingReports = [];
$showForm = true; // Always show the form by default

if (isset($_GET['patient_id'])) {
    $selectedPatientId = $_GET['patient_id'];
    try {
        // Get patient info
        $patientInfoStmt = $pdo->prepare("
            SELECT patientId, firstName, lastName, contactNumber, barangay
            FROM patients
            WHERE patientId = ?
        ");
        $patientInfoStmt->execute([$selectedPatientId]);
        $selectedPatientInfo = $patientInfoStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get existing reports
        $reportsStmt = $pdo->prepare("
            SELECT r.*, p.firstName, p.lastName, p.contactNumber, p.barangay
            FROM reports r
            JOIN patients p ON r.patientId = p.patientId
            WHERE r.patientId = ?
            ORDER BY r.reportDate DESC
        ");
        $reportsStmt->execute([$selectedPatientId]);
        $existingReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $existingReports = [];
        $selectedPatientInfo = null;
    }
}

// Process form submission
$formSubmitted = false;
$formSuccess = false;
$errorMessage = '';

// Fetch barangay options from the barangay_coordinates table
$barangayOptions = [];
try {
    $stmt = $pdo->query("SELECT barangay FROM barangay_coordinates ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $barangayOptions[] = $row['barangay'];
    }
} catch (PDOException $e) {
    // Optionally handle error
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formSubmitted = true;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if we need to create a new patient first
        if ($_POST['patient_option'] === 'new' && !empty($_POST['new_first_name']) && !empty($_POST['new_last_name'])) {
            $newPatientStmt = $pdo->prepare("
                INSERT INTO patients (firstName, lastName, gender, dateOfBirth, contactNumber, address, barangay) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $newPatientStmt->execute([
                $_POST['new_first_name'],
                $_POST['new_last_name'],
                $_POST['new_gender'] ?? null,
                !empty($_POST['new_dob']) ? $_POST['new_dob'] : null,
                $_POST['new_contact'] ?? null,
                $_POST['new_address'] ?? null,
                $_POST['new_barangay'] ?? null
            ]);
            
            $patientId = $pdo->lastInsertId();
        } else {
            // Use existing patient
            $patientId = $_POST['patient_id'];
        }
        
        // Insert the animal bite report
        $reportStmt = $pdo->prepare("
            INSERT INTO reports (
                patientId, 
                staffId, 
                biteDate,
                animalType,
                animalOtherType,
                animalOwnership,
                ownerName,
                ownerContact,
                animalStatus,
                animalVaccinated,
                biteLocation,
                biteType,
                multipleBites,
                provoked,
                washWithSoap,
                rabiesVaccine,
                rabiesVaccineDate,
                antiTetanus,
                antiTetanusDate,
                antibiotics,
                antibioticsDetails,
                referredToHospital,
                hospitalName,
                followUpDate,
                notes,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $reportStmt->execute([
            $patientId,
            null, // Admin-created reports don't have a staffId
            $_POST['bite_date'],
            $_POST['animal_type'],
            $_POST['animal_type'] === 'Other' ? $_POST['animal_other_type'] : null,
            $_POST['animal_ownership'],
            !empty($_POST['owner_name']) ? $_POST['owner_name'] : null,
            !empty($_POST['owner_contact']) ? $_POST['owner_contact'] : null,
            $_POST['animal_status'],
            $_POST['animal_vaccinated'],
            $_POST['bite_location'],
            $_POST['bite_type'],
            isset($_POST['multiple_bites']) ? 1 : 0,
            $_POST['provoked'],
            isset($_POST['wash_with_soap']) ? 1 : 0,
            isset($_POST['rabies_vaccine']) ? 1 : 0,
            !empty($_POST['rabies_vaccine_date']) ? $_POST['rabies_vaccine_date'] : null,
            isset($_POST['anti_tetanus']) ? 1 : 0,
            !empty($_POST['anti_tetanus_date']) ? $_POST['anti_tetanus_date'] : null,
            isset($_POST['antibiotics']) ? 1 : 0,
            !empty($_POST['antibiotics_details']) ? $_POST['antibiotics_details'] : null,
            isset($_POST['referred_to_hospital']) ? 1 : 0,
            !empty($_POST['hospital_name']) ? $_POST['hospital_name'] : null,
            !empty($_POST['followup_date']) ? $_POST['followup_date'] : null,
            !empty($_POST['notes']) ? $_POST['notes'] : null,
            $_POST['status']
        ]);
        
        $reportId = $pdo->lastInsertId();
        
        // Handle image uploads
        if (!empty($_FILES['bite_images']['name'][0])) {
            // Create uploads directory if it doesn't exist
            $uploadDir = '../uploads/bites/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Process each uploaded file
            $fileCount = count($_FILES['bite_images']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['bite_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['bite_images']['tmp_name'][$i];
                    $originalName = $_FILES['bite_images']['name'][$i];
                    $fileType = $_FILES['bite_images']['type'][$i];
                    $fileSize = $_FILES['bite_images']['size'][$i];
                    
                    // Generate a unique filename
                    $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $newFileName = 'bite_' . $reportId . '_' . uniqid() . '.' . $fileExtension;
                    $destination = $uploadDir . $newFileName;
                    
                    // Move the uploaded file
                    if (move_uploaded_file($tmpName, $destination)) {
                        // Insert image record into database
                        $imageStmt = $pdo->prepare("
                            INSERT INTO report_images (
                                report_id, 
                                image_path, 
                                file_name, 
                                file_type, 
                                file_size, 
                                is_primary, 
                                description
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $imageStmt->execute([
                            $reportId,
                            $newFileName,
                            $originalName,
                            $fileType,
                            $fileSize,
                            $i === 0 ? 1 : 0, // First image is primary
                            !empty($_POST['image_descriptions'][$i]) ? $_POST['image_descriptions'][$i] : null
                        ]);
                    }
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        $formSuccess = true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $errorMessage = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Animal Bite Report | Admin Portal</title>
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
      background-color: #0b5ed7;
      border-color: #0a58ca;
    }
    
    .btn-outline-primary {
      color: var(--bs-primary);
      border-color: var(--bs-primary);
    }
    
    .btn-outline-primary:hover, .btn-outline-primary:focus {
      background-color: var(--bs-primary);
      color: #fff;
      border-color: var(--bs-primary);
    }
    
    .form-container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 2rem 1rem;
      flex-grow: 1;
    }
    
    .form-card {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      overflow: hidden;
    }
    
    .form-header {
      padding: 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      background-color: rgba(var(--bs-primary-rgb), 0.03);
    }
    
    .form-body {
      padding: 1.5rem;
    }
    
    .form-section {
      margin-bottom: 1rem;
      border: 1px solid rgba(0, 0, 0, 0.1);
      border-radius: 8px;
      overflow: hidden;
      background-color: #fff;
      transition: box-shadow 0.2s;
    }
    
    .form-section:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .section-header {
      padding: 1rem 1.5rem;
      background-color: rgba(var(--bs-primary-rgb), 0.05);
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background-color 0.2s;
    }
    
    .section-header:hover {
      background-color: rgba(var(--bs-primary-rgb), 0.08);
    }
    
    .section-header h3 {
      font-size: 1.1rem;
      font-weight: 600;
      margin: 0;
      color: var(--bs-primary);
      display: flex;
      align-items: center;
    }
    
    .section-header h3 i {
      margin-right: 0.5rem;
    }
    
    .section-header .bi-chevron-down {
      transition: transform 0.3s ease;
    }
    
    .section-header[aria-expanded="false"] .bi-chevron-down {
      transform: rotate(-90deg);
    }
    
    .section-content {
      padding: 1.5rem;
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
    
    /* Smooth transitions for conditional fields */
    .conditional-field {
      transition: all 0.3s ease;
      overflow: hidden;
      max-height: 0;
      opacity: 0;
      margin: 0 !important;
      padding: 0 !important;
    }
    
    .conditional-field.show {
      max-height: 200px;
      opacity: 1;
      margin-top: 1rem !important;
      padding: 0 !important;
    }
    
    .conditional-field.show > * {
      padding: 0;
    }
    
    .form-label {
      font-weight: 500;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--bs-primary);
      box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
    }
    
    .form-text {
      font-size: 0.875rem;
    }
    
    .required-field::after {
      content: "*";
      color: #dc3545;
      margin-left: 0.25rem;
    }
    
    .success-message {
      background-color: rgba(13, 110, 253, 0.1);
      border: 1px solid rgba(13, 110, 253, 0.2);
      border-radius: 10px;
      padding: 2rem;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    
    .success-icon {
      font-size: 3rem;
      color: var(--bs-primary);
      margin-bottom: 1rem;
    }
    
    .footer {
      background-color: white;
      padding: 1rem 0;
      margin-top: auto;
      border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .category-info {
      background-color: #f8f9fa;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1rem;
      font-size: 0.9rem;
    }
    
    .category-info h5 {
      font-size: 1rem;
      margin-bottom: 0.5rem;
      color: var(--bs-primary);
    }
    
    .human-body-map {
      max-width: 100%;
      height: auto;
      margin-bottom: 1rem;
    }
    
    .image-preview-container {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
    }
    
    .image-preview {
      position: relative;
      width: 150px;
      height: 150px;
      border: 1px solid #ddd;
      border-radius: 4px;
      overflow: hidden;
      background-color: #f8f9fa;
    }
    
    .image-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .image-preview .remove-image {
      position: absolute;
      top: 5px;
      right: 5px;
      background-color: rgba(255, 255, 255, 0.8);
      border-radius: 50%;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: #dc3545;
    }
    
    .image-description {
      width: 150px;
      margin-top: 5px;
    }
    
    .file-upload-container {
      position: relative;
      overflow: hidden;
      display: inline-block;
    }
    
    .file-upload-container input[type=file] {
      position: absolute;
      left: 0;
      top: 0;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }
    
    /* Patient Autocomplete Styles */
    .patient-autocomplete-wrapper {
      position: relative;
    }
    
    .autocomplete-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ced4da;
      border-top: none;
      border-radius: 0 0 0.375rem 0.375rem;
      max-height: 300px;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      display: none;
    }
    
    .autocomplete-results.show {
      display: block;
    }
    
    .autocomplete-item {
      padding: 0.75rem 1rem;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.2s;
    }
    
    .autocomplete-item:last-child {
      border-bottom: none;
    }
    
    .autocomplete-item:hover,
    .autocomplete-item.highlighted {
      background-color: rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .autocomplete-item .patient-name {
      font-weight: 500;
      color: #212529;
    }
    
    .autocomplete-item .patient-details {
      font-size: 0.875rem;
      color: #6c757d;
      margin-top: 0.25rem;
    }
    
    .autocomplete-item .patient-details span {
      margin-right: 1rem;
    }
    
    .autocomplete-loading {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ced4da;
      border-top: none;
      border-radius: 0 0 0.375rem 0.375rem;
      padding: 0.75rem 1rem;
      z-index: 1001;
      text-align: center;
      color: #6c757d;
    }
    
    .spin {
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    
    .selected-patient-info {
      animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .table-card {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      overflow: hidden;
      margin-bottom: 1.5rem;
    }
    
    .status-badge {
      font-size: 0.75rem;
      padding: 0.35em 0.65em;
      border-radius: 0.25rem;
      font-weight: 600;
    }
    
    .status-pending {
      background-color: #ffc107;
      color: #212529;
    }
    
    .status-in-progress {
      background-color: #17a2b8;
      color: white;
    }
    
    .status-completed {
      background-color: #28a745;
      color: white;
    }
    
    .status-referred {
      background-color: #6f42c1;
      color: white;
    }
    
    .status-cancelled {
      background-color: #dc3545;
      color: white;
    }
    
    .bite-category-1 {
      background-color: #28a745;
      color: white;
    }
    
    .bite-category-2 {
      background-color: #fd7e14;
      color: white;
    }
    
    .bite-category-3 {
      background-color: #dc3545;
      color: white;
    }
    
    @media (max-width: 768px) {
      .form-container {
        padding: 1rem;
      }
      
      .form-header, .form-body {
        padding: 1rem;
      }
    }
  </style>
  <link rel="stylesheet" href="../css/system-theme.css">
</head>
<body class="theme-admin">
  <?php include 'includes/navbar.php'; ?>

  <div class="form-container app-shell">
    <?php if (!empty($existingReports)): ?>
    <div class="alert alert-info">
        <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Existing Reports Found</h4>
        <p>This patient already has <?php echo count($existingReports); ?> report(s).</p>
    </div>
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Report Date</th>
                        <th>Bite Date</th>
                        <th>Animal</th>
                        <th>Bite Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingReports as $report): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($report['reportDate'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($report['biteDate'])); ?></td>
                        <td><?php echo htmlspecialchars($report['animalType']); ?></td>
                        <td>
                            <?php
                                switch ($report['biteType']) {
                                    case 'Category I':
                                        $existingBiteClass = 'badge-category-i';
                                        break;
                                    case 'Category II':
                                        $existingBiteClass = 'badge-category-ii';
                                        break;
                                    case 'Category III':
                                        $existingBiteClass = 'badge-category-iii';
                                        break;
                                    default:
                                        $existingBiteClass = 'badge-category-ii';
                                }
                            ?>
                            <span class="badge <?php echo $existingBiteClass; ?>">
                                <?php echo $report['biteType']; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                switch ($report['status']) {
                                    case 'pending':
                                        $existingStatusClass = 'badge-status-pending';
                                        break;
                                    case 'in_progress':
                                        $existingStatusClass = 'badge-status-inprogress';
                                        break;
                                    case 'completed':
                                        $existingStatusClass = 'badge-status-completed';
                                        break;
                                    case 'referred':
                                        $existingStatusClass = 'badge-status-referred';
                                        break;
                                    case 'cancelled':
                                        $existingStatusClass = 'badge-status-cancelled';
                                        break;
                                    default:
                                        $existingStatusClass = 'badge-status-pending';
                                }
                            ?>
                            <span class="badge <?php echo $existingStatusClass; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_report.php?id=<?php echo $report['reportId']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($showForm): ?>
    <div class="form-container">
        <?php if ($formSubmitted && $formSuccess): ?>
        <div class="success-message">
          <div class="success-icon">
            <i class="bi bi-check-circle"></i>
          </div>
          <h3>Animal Bite Report Created Successfully!</h3>
          <p class="mb-4">The animal bite report has been added to the system.</p>
          <div class="d-flex justify-content-center gap-3">
            <a href="admin_dashboard.php" class="btn btn-outline-primary">
              <i class="bi bi-house-door me-2"></i>Back to Dashboard
            </a>
            <a href="new_report.php" class="btn btn-primary">
              <i class="bi bi-plus-circle me-2"></i>Create Another Report
            </a>
          </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$formSubmitted || !$formSuccess): ?>
        <div class="form-card">
          <div class="form-header">
            <h2 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>New Animal Bite Report</h2>
          </div>
          
          <div class="form-body">
            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="new_report.php" enctype="multipart/form-data">
              <!-- Patient Information Section -->
              <div class="form-section">
                <div class="section-header" data-bs-toggle="collapse" data-bs-target="#patientSection" aria-expanded="true">
                  <h3><i class="bi bi-person"></i> Patient Information</h3>
                  <i class="bi bi-chevron-down"></i>
                </div>
                <div class="section-content collapse show" id="patientSection">
                <div class="mb-3">
                  <label class="form-label d-block">Patient Selection</label>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="patient_option" id="existingPatient" value="existing" checked>
                    <label class="form-check-label" for="existingPatient">Existing Patient</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="patient_option" id="newPatient" value="new">
                    <label class="form-check-label" for="newPatient">New Patient</label>
                  </div>
                </div>
                
                <!-- Existing Patient Selection -->
                <div id="existingPatientSection" class="mb-3">
                  <label for="patient_search" class="form-label required-field">Search Patient</label>
                  <div class="patient-autocomplete-wrapper position-relative">
                    <input 
                      type="text" 
                      class="form-control" 
                      id="patient_search" 
                      name="patient_search" 
                      placeholder="Type to search for a patient (name, contact number, etc.)"
                      autocomplete="off"
                      required
                    >
                    <input type="hidden" id="patient_id" name="patient_id" required>
                    <div id="patient_search_results" class="autocomplete-results"></div>
                    <div id="patient_search_loading" class="autocomplete-loading" style="display: none;">
                      <i class="bi bi-arrow-repeat spin"></i> Searching...
                    </div>
                  </div>
                  <div class="form-text">Start typing to search for a patient by name, contact number, or ID</div>
                  <div id="selected_patient_info" class="selected-patient-info mt-2" style="display: none;">
                    <div class="alert alert-info mb-0 py-2">
                      <i class="bi bi-check-circle me-2"></i>
                      <strong>Selected:</strong> <span id="selected_patient_name"></span>
                      <button type="button" class="btn btn-sm btn-link p-0 ms-2" id="clear_patient_selection">
                        <i class="bi bi-x-circle"></i> Clear
                      </button>
                    </div>
                  </div>
                </div>
                
                <!-- New Patient Form -->
                <div id="newPatientSection" class="row g-3" style="display: none;">
                  <div class="col-md-6">
                    <label for="new_first_name" class="form-label required-field">First Name</label>
                    <input type="text" class="form-control" id="new_first_name" name="new_first_name">
                  </div>
                  <div class="col-md-6">
                    <label for="new_last_name" class="form-label required-field">Last Name</label>
                    <input type="text" class="form-control" id="new_last_name" name="new_last_name">
                  </div>
                  <div class="col-md-6">
                    <label for="new_gender" class="form-label">Gender</label>
                    <select class="form-select" id="new_gender" name="new_gender">
                      <option value="">-- Select Gender --</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="new_dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="new_dob" name="new_dob">
                  </div>
                  <div class="col-md-6">
                    <label for="new_contact" class="form-label">Contact Number</label>
                    <input type="text" class="form-control" id="new_contact" name="new_contact">
                  </div>
                  <div class="col-md-6">
                    <label for="new_barangay" class="form-label">Barangay</label>
                    <select class="form-select" id="new_barangay" name="new_barangay">
                      <option value="">Select Barangay</option>
                      <?php foreach ($barangayOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12">
                    <label for="new_address" class="form-label">Address</label>
                    <textarea class="form-control" id="new_address" name="new_address" rows="2"></textarea>
                  </div>
                </div>
                </div>
              </div>
              
              <!-- Animal Bite Details Section -->
              <div class="form-section">
                <div class="section-header" data-bs-toggle="collapse" data-bs-target="#biteDetailsSection" aria-expanded="true">
                  <h3><i class="bi bi-exclamation-triangle"></i> Animal Bite Details</h3>
                  <i class="bi bi-chevron-down"></i>
                </div>
                <div class="section-content collapse show" id="biteDetailsSection">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="bite_date" class="form-label required-field">Date of Bite</label>
                    <input type="date" class="form-control" id="bite_date" name="bite_date" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>
                  
                  <div class="col-md-6">
                    <label for="animal_type" class="form-label required-field">Animal Type</label>
                    <select class="form-select" id="animal_type" name="animal_type" required>
                      <option value="">-- Select Animal --</option>
                      <option value="Dog">Dog</option>
                      <option value="Cat">Cat</option>
                      <option value="Rat">Rat</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  
                  <div class="col-md-6 conditional-field" id="otherAnimalSection">
                    <label for="animal_other_type" class="form-label required-field">Specify Animal</label>
                    <input type="text" class="form-control" id="animal_other_type" name="animal_other_type">
                  </div>
                  
                  <div class="col-md-6">
                    <label for="animal_ownership" class="form-label required-field">Animal Ownership</label>
                    <select class="form-select" id="animal_ownership" name="animal_ownership" required>
                      <option value="">-- Select Ownership --</option>
                      <option value="Stray">Stray</option>
                      <option value="Owned by patient">Owned by patient</option>
                      <option value="Owned by neighbor">Owned by neighbor</option>
                      <option value="Owned by unknown person">Owned by unknown person</option>
                      <option value="Unknown">Unknown</option>
                    </select>
                  </div>
                  
                  <div class="col-md-6 conditional-field" id="ownerSection">
                    <label for="owner_name" class="form-label">Owner's Name</label>
                    <input type="text" class="form-control" id="owner_name" name="owner_name">
                  </div>
                  
                  <div class="col-md-6 conditional-field" id="ownerContactSection">
                    <label for="owner_contact" class="form-label">Owner's Contact</label>
                    <input type="text" class="form-control" id="owner_contact" name="owner_contact">
                  </div>
                  
                  <div class="col-md-6">
                    <label for="animal_status" class="form-label required-field">Animal Status</label>
                    <select class="form-select" id="animal_status" name="animal_status" required>
                      <option value="Alive">Alive</option>
                      <option value="Dead">Dead</option>
                      <option value="Unknown">Unknown</option>
                    </select>
                  </div>
                  
                  <div class="col-md-6">
                    <label for="animal_vaccinated" class="form-label required-field">Animal Vaccinated Against Rabies</label>
                    <select class="form-select" id="animal_vaccinated" name="animal_vaccinated" required>
                      <option value="Yes">Yes</option>
                      <option value="No">No</option>
                      <option value="Unknown" selected>Unknown</option>
                    </select>
                  </div>
                  
                  <div class="col-md-6">
                    <label for="provoked" class="form-label required-field">Was the bite provoked?</label>
                    <select class="form-select" id="provoked" name="provoked" required>
                      <option value="Yes">Yes</option>
                      <option value="No">No</option>
                      <option value="Unknown" selected>Unknown</option>
                    </select>
                  </div>
                  
                  <div class="col-md-6">
                    <div class="form-check mt-4">
                      <input class="form-check-input" type="checkbox" id="multiple_bites" name="multiple_bites">
                      <label class="form-check-label" for="multiple_bites">
                        Multiple Bites
                      </label>
                    </div>
                  </div>
                </div>
                </div>
              </div>
              
              <!-- Bite Location and Classification -->
              <div class="form-section">
                <div class="section-header" data-bs-toggle="collapse" data-bs-target="#biteLocationSection" aria-expanded="true">
                  <h3><i class="bi bi-bullseye"></i> Bite Location & Classification</h3>
                  <i class="bi bi-chevron-down"></i>
                </div>
                <div class="section-content collapse show" id="biteLocationSection">
                <div class="row g-3">
                  <div class="col-md-12">
                    <label for="bite_location" class="form-label required-field">Bite Location (Body Part)</label>
                    <input type="text" class="form-control" id="bite_location" name="bite_location" required>
                    <div class="form-text">Specify the body part(s) where the bite occurred (e.g., right hand, left leg, face)</div>
                  </div>
                  
                  <div class="col-md-12">
                    <label for="bite_type" class="form-label required-field">Bite Category</label>
                    <select class="form-select" id="bite_type" name="bite_type" required>
                      <option value="">-- Select Category --</option>
                      <option value="Category I">Category I</option>
                      <option value="Category II">Category II</option>
                      <option value="Category III">Category III</option>
                    </select>
                    
                    <div class="category-info mt-3">
                      <h5>Bite Categories:</h5>
                      <p><strong>Category I:</strong> Touching or feeding of animals, licks on intact skin</p>
                      <p><strong>Category II:</strong> Nibbling of uncovered skin, minor scratches or abrasions without bleeding</p>
                      <p><strong>Category III:</strong> Single or multiple transdermal bites or scratches, contamination of mucous membrane or broken skin with saliva from animal licks, exposures due to direct contact with bats</p>
                    </div>
                  </div>
                </div>
                </div>
              </div>
              
              <!-- Bite Images Section -->
              <div class="form-section">
                <div class="section-header" data-bs-toggle="collapse" data-bs-target="#biteImagesSection" aria-expanded="false">
                  <h3><i class="bi bi-camera"></i> Bite Images</h3>
                  <i class="bi bi-chevron-down"></i>
                </div>
                <div class="section-content collapse" id="biteImagesSection">
                <div class="mb-3">
                  <label class="form-label">Upload Images of the Bite</label>
                  <label for="bite_images" class="btn btn-outline-primary" style="cursor:pointer;">
                    <i class="bi bi-upload me-2"></i>Select Images
                  </label>
                  <input type="file" id="bite_images" name="bite_images[]" accept="image/*" multiple style="display:none;" onchange="previewImages(this)">
                  <div class="form-text">Upload clear images of the bite area. You can upload multiple images.</div>
                  <div id="imagePreviewContainer" class="image-preview-container mt-3"></div>
                </div>
                </div>
              </div>
              
              <!-- Treatment Information -->
              <div class="form-section">
                <div class="section-header" data-bs-toggle="collapse" data-bs-target="#treatmentSection" aria-expanded="false">
                  <h3><i class="bi bi-bandaid"></i> Treatment Information</h3>
                  <i class="bi bi-chevron-down"></i>
                </div>
                <div class="section-content collapse" id="treatmentSection">
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="wash_with_soap" name="wash_with_soap">
                      <label class="form-check-label" for="wash_with_soap">
                        Wound washed with soap and water
                      </label>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="rabies_vaccine" name="rabies_vaccine">
                      <label class="form-check-label" for="rabies_vaccine">
                        Rabies vaccine administered
                      </label>
                    </div>
                  </div>
                  
                  <div class="col-md-6 conditional-field" id="rabiesVaccineDateSection">
                    <label for="rabies_vaccine_date" class="form-label">Date of Rabies Vaccine</label>
                    <input type="date" class="form-control" id="rabies_vaccine_date" name="rabies_vaccine_date">
                  </div>
                  
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="anti_tetanus" name="anti_tetanus">
                      <label class="form-check-label" for="anti_tetanus">
                        Anti-tetanus administered
                      </label>
                    </div>
                  </div>
                  
                  <div class="col-md-6 conditional-field" id="antiTetanusDateSection">
                    <label for="anti_tetanus_date" class="form-label">Date of Anti-tetanus</label>
                    <input type="date" class="form-control" id="anti_tetanus_date" name="anti_tetanus_date">
                  </div>
                  
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="antibiotics" name="antibiotics">
                      <label class="form-check-label" for="antibiotics">
                        Antibiotics prescribed
                      </label>
                    </div>
                  </div>
                  
                  <div class="col-md-6 conditional-field" id="antibioticsDetailsSection">
                    <label for="antibiotics_details" class="form-label">Antibiotics Details</label>
                    <input type="text" class="form-control" id="antibiotics_details" name="antibiotics_details" placeholder="Type, dosage, etc.">
                  </div>
                  
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="referred_to_hospital" name="referred_to_hospital">
                      <label class="form-check-label" for="referred_to_hospital">
                        Referred to hospital
                      </label>
                    </div>
                  </div>
                  
                  <div class="col-md-6 conditional-field" id="hospitalNameSection">
                    <label for="hospital_name" class="form-label">Hospital Name</label>
                    <input type="text" class="form-control" id="hospital_name" name="hospital_name">
                  </div>
                  
                  <div class="col-md-6">
                    <label for="followup_date" class="form-label">Follow-up Date</label>
                    <input type="date" class="form-control" id="followup_date" name="followup_date">
                  </div>
                  
                  <div class="col-md-6">
                    <label for="status" class="form-label required-field">Case Status</label>
                    <select class="form-select" id="status" name="status" required>
                      <option value="pending" selected>Pending</option>
                      <option value="in_progress">In Progress</option>
                      <option value="completed">Completed</option>
                      <option value="referred">Referred</option>
                    </select>
                  </div>
                </div>
                </div>
              </div>
              
              <!-- Additional Notes Section -->
              <div class="form-section">
                <div class="section-header" data-bs-toggle="collapse" data-bs-target="#notesSection" aria-expanded="false">
                  <h3><i class="bi bi-journal"></i> Additional Notes</h3>
                  <i class="bi bi-chevron-down"></i>
                </div>
                <div class="section-content collapse" id="notesSection">
                <div class="mb-3">
                  <label for="notes" class="form-label">Notes</label>
                  <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
                  <div class="form-text">Any additional information or observations about the case</div>
                </div>
                </div>
              </div>
              
              <div class="d-flex justify-content-between mt-4">
                <a href="view_reports.php" class="btn btn-outline-secondary">
                  <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-save me-2"></i>Save Report
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <small class="text-muted">&copy; 2025 City Health Office · Barangay Health Workers Management System</small>
        </div>
        <div>
          <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
        </div>
      </div>
    </div>
  </footer>

  <!-- Image Preview Modal -->
  <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Image Preview</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImage" src="/placeholder.svg" alt="Preview" style="max-width: 100%; max-height: 70vh;">
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Toggle between existing and new patient sections
      const existingPatientRadio = document.getElementById('existingPatient');
      const newPatientRadio = document.getElementById('newPatient');
      const existingPatientSection = document.getElementById('existingPatientSection');
      const newPatientSection = document.getElementById('newPatientSection');
      const patientIdInput = document.getElementById('patient_id');
      const patientSearchInput = document.getElementById('patient_search');
      const newFirstNameInput = document.getElementById('new_first_name');
      const newLastNameInput = document.getElementById('new_last_name');
      
      function togglePatientSections() {
        if (existingPatientRadio.checked) {
          existingPatientSection.style.display = 'block';
          newPatientSection.style.display = 'none';
          patientIdInput.required = true;
          patientSearchInput.required = true;
          newFirstNameInput.required = false;
          newLastNameInput.required = false;
        } else {
          existingPatientSection.style.display = 'none';
          newPatientSection.style.display = 'block';
          patientIdInput.required = false;
          patientSearchInput.required = false;
          newFirstNameInput.required = true;
          newLastNameInput.required = true;
        }
      }
      
      existingPatientRadio.addEventListener('change', togglePatientSections);
      newPatientRadio.addEventListener('change', togglePatientSections);
      
      // Helper function to toggle conditional fields smoothly
      function toggleConditionalField(element, show) {
        if (show) {
          element.classList.add('show');
          // Enable required fields
          const requiredInput = element.querySelector('[required]');
          if (requiredInput) requiredInput.required = true;
        } else {
          element.classList.remove('show');
          // Disable required fields
          const requiredInput = element.querySelector('[required]');
          if (requiredInput) requiredInput.required = false;
        }
      }
      
      // Show/hide other animal type field
      const animalTypeSelect = document.getElementById('animal_type');
      const otherAnimalSection = document.getElementById('otherAnimalSection');
      const animalOtherTypeInput = document.getElementById('animal_other_type');
      
      animalTypeSelect.addEventListener('change', function() {
        toggleConditionalField(otherAnimalSection, this.value === 'Other');
      });
      
      // Show/hide owner information fields
      const animalOwnershipSelect = document.getElementById('animal_ownership');
      const ownerSection = document.getElementById('ownerSection');
      const ownerContactSection = document.getElementById('ownerContactSection');
      
      animalOwnershipSelect.addEventListener('change', function() {
        const shouldShow = (this.value === 'Owned by neighbor' || this.value === 'Owned by unknown person');
        toggleConditionalField(ownerSection, shouldShow);
        toggleConditionalField(ownerContactSection, shouldShow);
      });
      
      // Show/hide treatment date fields
      const rabiesVaccineCheckbox = document.getElementById('rabies_vaccine');
      const rabiesVaccineDateSection = document.getElementById('rabiesVaccineDateSection');
      
      rabiesVaccineCheckbox.addEventListener('change', function() {
        toggleConditionalField(rabiesVaccineDateSection, this.checked);
      });
      
      const antiTetanusCheckbox = document.getElementById('anti_tetanus');
      const antiTetanusDateSection = document.getElementById('antiTetanusDateSection');
      
      antiTetanusCheckbox.addEventListener('change', function() {
        toggleConditionalField(antiTetanusDateSection, this.checked);
      });
      
      const antibioticsCheckbox = document.getElementById('antibiotics');
      const antibioticsDetailsSection = document.getElementById('antibioticsDetailsSection');
      
      antibioticsCheckbox.addEventListener('change', function() {
        toggleConditionalField(antibioticsDetailsSection, this.checked);
      });
      
      const referredToHospitalCheckbox = document.getElementById('referred_to_hospital');
      const hospitalNameSection = document.getElementById('hospitalNameSection');
      
      referredToHospitalCheckbox.addEventListener('change', function() {
        toggleConditionalField(hospitalNameSection, this.checked);
      });
      
      // Set initial state
      togglePatientSections();

      // Patient Autocomplete Functionality
      const patientSearchResults = document.getElementById('patient_search_results');
      const patientSearchLoading = document.getElementById('patient_search_loading');
      const selectedPatientInfo = document.getElementById('selected_patient_info');
      const selectedPatientName = document.getElementById('selected_patient_name');
      const clearPatientSelection = document.getElementById('clear_patient_selection');
      
      let searchTimeout;
      let currentHighlightIndex = -1;
      let currentResults = [];
      
      // Debounced search function
      function searchPatients(query) {
        if (query.length < 2) {
          patientSearchResults.classList.remove('show');
          patientSearchLoading.style.display = 'none';
          return;
        }
        
        patientSearchLoading.style.display = 'block';
        patientSearchResults.classList.remove('show');
        
        fetch(`search_patients.php?q=${encodeURIComponent(query)}&limit=20`)
          .then(response => response.json())
          .then(data => {
            patientSearchLoading.style.display = 'none';
            
            if (!Array.isArray(data)) {
              const message = data && typeof data === 'object' && data.error
                ? data.error
                : 'Error searching patients. Please try again.';
              throw new Error(message);
            }
            
            currentResults = data;
            displayResults(data);
          })
          .catch(error => {
            console.error('Error searching patients:', error);
            patientSearchLoading.style.display = 'none';
            patientSearchResults.innerHTML = `<div class="autocomplete-item">${escapeHtml(error.message || 'Error searching patients. Please try again.')}</div>`;
            patientSearchResults.classList.add('show');
            currentResults = [];
          });
      }
      
      // Display search results
      function displayResults(results) {
        if (results.length === 0) {
          patientSearchResults.innerHTML = '<div class="autocomplete-item">No patients found</div>';
          patientSearchResults.classList.add('show');
          return;
        }
        
        patientSearchResults.innerHTML = '';
        results.forEach((patient, index) => {
          const item = document.createElement('div');
          item.className = 'autocomplete-item';
          item.dataset.index = index;
          item.innerHTML = `
            <div class="patient-name">${escapeHtml(patient.text)}</div>
            <div class="patient-details">
              ${patient.contactNumber ? `<span><i class="bi bi-telephone"></i> ${escapeHtml(patient.contactNumber)}</span>` : ''}
              ${patient.barangay ? `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(patient.barangay)}</span>` : ''}
            </div>
          `;
          
          item.addEventListener('click', () => selectPatient(patient));
          item.addEventListener('mouseenter', () => {
            document.querySelectorAll('.autocomplete-item').forEach(el => el.classList.remove('highlighted'));
            item.classList.add('highlighted');
            currentHighlightIndex = index;
          });
          
          patientSearchResults.appendChild(item);
        });
        
        patientSearchResults.classList.add('show');
        currentHighlightIndex = -1;
      }
      
      // Select a patient
      function selectPatient(patient) {
        patientIdInput.value = patient.id;
        patientSearchInput.value = patient.text;
        patientSearchResults.classList.remove('show');
        selectedPatientName.textContent = patient.text;
        selectedPatientInfo.style.display = 'block';
        
        // Redirect to show existing reports if any
        if (patient.id) {
          window.location.href = 'new_report.php?patient_id=' + patient.id;
        }
      }
      
      // Clear patient selection
      if (clearPatientSelection) {
        clearPatientSelection.addEventListener('click', function() {
          patientIdInput.value = '';
          patientSearchInput.value = '';
          selectedPatientInfo.style.display = 'none';
          patientSearchInput.focus();
        });
      }
      
      // Handle input events
      patientSearchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (!query) {
          patientIdInput.value = '';
          selectedPatientInfo.style.display = 'none';
          patientSearchResults.classList.remove('show');
          return;
        }
        
        searchTimeout = setTimeout(() => {
          searchPatients(query);
        }, 300);
      });
      
      // Handle keyboard navigation
      patientSearchInput.addEventListener('keydown', function(e) {
        if (!patientSearchResults.classList.contains('show') || currentResults.length === 0) {
          return;
        }
        
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          currentHighlightIndex = Math.min(currentHighlightIndex + 1, currentResults.length - 1);
          updateHighlight();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          currentHighlightIndex = Math.max(currentHighlightIndex - 1, -1);
          updateHighlight();
        } else if (e.key === 'Enter') {
          e.preventDefault();
          if (currentHighlightIndex >= 0 && currentHighlightIndex < currentResults.length) {
            selectPatient(currentResults[currentHighlightIndex]);
          }
        } else if (e.key === 'Escape') {
          patientSearchResults.classList.remove('show');
          currentHighlightIndex = -1;
        }
      });
      
      function updateHighlight() {
        const items = patientSearchResults.querySelectorAll('.autocomplete-item');
        items.forEach((item, index) => {
          if (index === currentHighlightIndex) {
            item.classList.add('highlighted');
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
          } else {
            item.classList.remove('highlighted');
          }
        });
      }
      
      // Close results when clicking outside
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.patient-autocomplete-wrapper')) {
          patientSearchResults.classList.remove('show');
        }
      });
      
      // Escape HTML helper
      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }
      
      // If we're showing the form for a new report, pre-select the patient
      <?php if ($showForm && $selectedPatientId && $selectedPatientInfo): ?>
      // Pre-populate with selected patient
      const selectedPatient = {
        id: '<?php echo $selectedPatientInfo['patientId']; ?>',
        text: '<?php echo htmlspecialchars($selectedPatientInfo['lastName'] . ', ' . $selectedPatientInfo['firstName'], ENT_QUOTES); ?>',
        firstName: '<?php echo htmlspecialchars($selectedPatientInfo['firstName'], ENT_QUOTES); ?>',
        lastName: '<?php echo htmlspecialchars($selectedPatientInfo['lastName'], ENT_QUOTES); ?>',
        contactNumber: '<?php echo htmlspecialchars($selectedPatientInfo['contactNumber'] ?? '', ENT_QUOTES); ?>',
        barangay: '<?php echo htmlspecialchars($selectedPatientInfo['barangay'] ?? '', ENT_QUOTES); ?>'
      };
      patientIdInput.value = selectedPatient.id;
      patientSearchInput.value = selectedPatient.text;
      selectedPatientName.textContent = selectedPatient.text;
      selectedPatientInfo.style.display = 'block';
      <?php endif; ?>
    });
    
    // Image preview functionality
    function previewImages(input) {
      const previewContainer = document.getElementById('imagePreviewContainer');
      previewContainer.innerHTML = '';
      
      if (input.files) {
        const filesAmount = input.files.length;
        
        for (let i = 0; i < filesAmount; i++) {
          const reader = new FileReader();
          
          reader.onload = function(event) {
            const previewDiv = document.createElement('div');
            previewDiv.className = 'image-preview';
            
            const img = document.createElement('img');
            img.src = event.target.result;
            img.alt = 'Preview';
            img.onclick = function() {
              document.getElementById('modalImage').src = this.src;
              new bootstrap.Modal(document.getElementById('imagePreviewModal')).show();
            };
            
            const removeBtn = document.createElement('div');
            removeBtn.className = 'remove-image';
            removeBtn.innerHTML = '<i class="bi bi-x"></i>';
            removeBtn.onclick = function() {
              this.parentElement.remove();
              // Note: This doesn't actually remove the file from the input
              // For that, we would need a more complex solution with a custom file list
            };
            
            previewDiv.appendChild(img);
            previewDiv.appendChild(removeBtn);
            
            const descriptionInput = document.createElement('input');
            descriptionInput.type = 'text';
            descriptionInput.className = 'form-control image-description';
            descriptionInput.name = 'image_descriptions[]';
            descriptionInput.placeholder = 'Description';
            
            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex flex-column align-items-center';
            wrapper.appendChild(previewDiv);
            wrapper.appendChild(descriptionInput);
            
            previewContainer.appendChild(wrapper);
          }
          
          reader.readAsDataURL(input.files[i]);
        }
      }
    }
  </script>
</body>
</html>
