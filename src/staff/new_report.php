<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header("Location: ../staff_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get staff information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff = ['firstName' => 'User', 'lastName' => ''];
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
$existingReports = [];
$showForm = false;

if (isset($_GET['patient_id'])) {
    $selectedPatientId = $_GET['patient_id'];
    try {
        $reportsStmt = $pdo->prepare("
            SELECT r.*, p.firstName, p.lastName, p.contactNumber, p.barangay
            FROM reports r
            JOIN patients p ON r.patientId = p.patientId
            WHERE r.patientId = ?
            ORDER BY r.reportDate DESC
        ");
        $reportsStmt->execute([$selectedPatientId]);
        $existingReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Show form if explicitly requested with new=true
        $showForm = isset($_GET['new']) && $_GET['new'] === 'true';
    } catch (PDOException $e) {
        $existingReports = [];
    }
}

// Process form submission
$formSubmitted = false;
$formSuccess = false;
$errorMessage = '';

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
            $_SESSION['staffId'],
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
  <title>New Animal Bite Report | BHW Portal</title>
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
      margin-bottom: 2rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .form-section:last-child {
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
      background-color: rgba(40, 167, 69, 0.1);
      border: 1px solid rgba(40, 167, 69, 0.2);
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
    
    @media (max-width: 768px) {
      .form-container {
        padding: 1rem;
      }
      
      .form-header, .form-body {
        padding: 1rem;
      }
    }
  </style>
</head>
<body>
  <?php $activePage = 'reports'; include 'navbar.php'; ?>

  <div class="form-container">
    <?php if (!empty($existingReports) && !$showForm): ?>
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
                            <span class="status-badge <?php 
                                switch($report['biteType']) {
                                    case 'Category I': echo 'bite-category-1'; break;
                                    case 'Category II': echo 'bite-category-2'; break;
                                    case 'Category III': echo 'bite-category-3'; break;
                                    default: echo '';
                                }
                            ?>">
                                <?php echo $report['biteType']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php 
                                switch($report['status']) {
                                    case 'pending': echo 'status-pending'; break;
                                    case 'in_progress': echo 'status-in-progress'; break;
                                    case 'completed': echo 'status-completed'; break;
                                    case 'referred': echo 'status-referred'; break;
                                    case 'cancelled': echo 'status-cancelled'; break;
                                    default: echo 'status-pending';
                                }
                            ?>">
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
    
    <div class="d-flex justify-content-between mt-4">
        <a href="new_report.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to New Report
        </a>
        <a href="new_report.php?patient_id=<?php echo $selectedPatientId; ?>&new=true" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Create Another Report
        </a>
    </div>
    <?php else: ?>
    <div class="form-container">
        <?php if ($formSubmitted && $formSuccess): ?>
        <div class="success-message">
          <div class="success-icon">
            <i class="bi bi-check-circle"></i>
          </div>
          <h3>Animal Bite Report Created Successfully!</h3>
          <p class="mb-4">The animal bite report has been added to the system.</p>
          <div class="d-flex justify-content-center gap-3">
            <a href="dashboard.php" class="btn btn-outline-primary">
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
                <h3 class="section-title"><i class="bi bi-person"></i> Patient Information</h3>
                
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
                  <label for="patient_id" class="form-label required-field">Select Patient</label>
                  <select class="form-select" id="patient_id" name="patient_id" required>
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $patient): ?>
                    <option value="<?php echo $patient['patientId']; ?>">
                      <?php echo htmlspecialchars($patient['lastName'] . ', ' . $patient['firstName']); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
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
                    <input type="text" class="form-control" id="new_barangay" name="new_barangay">
                  </div>
                  <div class="col-12">
                    <label for="new_address" class="form-label">Address</label>
                    <textarea class="form-control" id="new_address" name="new_address" rows="2"></textarea>
                  </div>
                </div>
              </div>
              
              <!-- Animal Bite Details Section -->
              <div class="form-section">
                <h3 class="section-title"><i class="bi bi-exclamation-triangle"></i> Animal Bite Details</h3>
                
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
                      <option value="Monkey">Monkey</option>
                      <option value="Bat">Bat</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  
                  <div class="col-md-6" id="otherAnimalSection" style="display: none;">
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
                  
                  <div class="col-md-6" id="ownerSection" style="display: none;">
                    <label for="owner_name" class="form-label">Owner's Name</label>
                    <input type="text" class="form-control" id="owner_name" name="owner_name">
                  </div>
                  
                  <div class="col-md-6" id="ownerContactSection" style="display: none;">
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
              
              <!-- Bite Location and Classification -->
              <div class="form-section">
                <h3 class="section-title"><i class="bi bi-bullseye"></i> Bite Location & Classification</h3>
                
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
              
              <!-- Bite Images Section -->
              <div class="form-section">
                <h3 class="section-title"><i class="bi bi-camera"></i> Bite Images</h3>
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
              
              <!-- Treatment Information -->
              <div class="form-section">
                <h3 class="section-title"><i class="bi bi-bandaid"></i> Treatment Information</h3>
                
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
                  
                  <div class="col-md-6" id="rabiesVaccineDateSection" style="display: none;">
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
                  
                  <div class="col-md-6" id="antiTetanusDateSection" style="display: none;">
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
                  
                  <div class="col-md-6" id="antibioticsDetailsSection" style="display: none;">
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
                  
                  <div class="col-md-6" id="hospitalNameSection" style="display: none;">
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
              
              <!-- Additional Notes Section -->
              <div class="form-section">
                <h3 class="section-title"><i class="bi bi-journal"></i> Additional Notes</h3>
                
                <div class="mb-3">
                  <label for="notes" class="form-label">Notes</label>
                  <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
                  <div class="form-text">Any additional information or observations about the case</div>
                </div>
              </div>
              
              <div class="d-flex justify-content-between mt-4">
                <a href="dashboard.php" class="btn btn-outline-secondary">
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
          <small class="text-muted">&copy; <?php echo date('Y'); ?> Barangay Health Workers Management System</small>
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
      const patientIdSelect = document.getElementById('patient_id');
      const newFirstNameInput = document.getElementById('new_first_name');
      const newLastNameInput = document.getElementById('new_last_name');
      
      function togglePatientSections() {
        if (existingPatientRadio.checked) {
          existingPatientSection.style.display = 'block';
          newPatientSection.style.display = 'none';
          patientIdSelect.required = true;
          newFirstNameInput.required = false;
          newLastNameInput.required = false;
        } else {
          existingPatientSection.style.display = 'none';
          newPatientSection.style.display = 'block';
          patientIdSelect.required = false;
          newFirstNameInput.required = true;
          newLastNameInput.required = true;
        }
      }
      
      existingPatientRadio.addEventListener('change', togglePatientSections);
      newPatientRadio.addEventListener('change', togglePatientSections);
      
      // Show/hide other animal type field
      const animalTypeSelect = document.getElementById('animal_type');
      const otherAnimalSection = document.getElementById('otherAnimalSection');
      const animalOtherTypeInput = document.getElementById('animal_other_type');
      
      animalTypeSelect.addEventListener('change', function() {
        if (this.value === 'Other') {
          otherAnimalSection.style.display = 'block';
          animalOtherTypeInput.required = true;
        } else {
          otherAnimalSection.style.display = 'none';
          animalOtherTypeInput.required = false;
        }
      });
      
      // Show/hide owner information fields
      const animalOwnershipSelect = document.getElementById('animal_ownership');
      const ownerSection = document.getElementById('ownerSection');
      const ownerContactSection = document.getElementById('ownerContactSection');
      
      animalOwnershipSelect.addEventListener('change', function() {
        if (this.value === 'Owned by patient' || this.value === 'Owned by neighbor' || this.value === 'Owned by unknown person') {
          ownerSection.style.display = 'block';
          ownerContactSection.style.display = 'block';
        } else {
          ownerSection.style.display = 'none';
          ownerContactSection.style.display = 'none';
        }
      });
      
      // Show/hide treatment date fields
      const rabiesVaccineCheckbox = document.getElementById('rabies_vaccine');
      const rabiesVaccineDateSection = document.getElementById('rabiesVaccineDateSection');
      
      rabiesVaccineCheckbox.addEventListener('change', function() {
        rabiesVaccineDateSection.style.display = this.checked ? 'block' : 'none';
      });
      
      const antiTetanusCheckbox = document.getElementById('anti_tetanus');
      const antiTetanusDateSection = document.getElementById('antiTetanusDateSection');
      
      antiTetanusCheckbox.addEventListener('change', function() {
        antiTetanusDateSection.style.display = this.checked ? 'block' : 'none';
      });
      
      const antibioticsCheckbox = document.getElementById('antibiotics');
      const antibioticsDetailsSection = document.getElementById('antibioticsDetailsSection');
      
      antibioticsCheckbox.addEventListener('change', function() {
        antibioticsDetailsSection.style.display = this.checked ? 'block' : 'none';
      });
      
      const referredToHospitalCheckbox = document.getElementById('referred_to_hospital');
      const hospitalNameSection = document.getElementById('hospitalNameSection');
      
      referredToHospitalCheckbox.addEventListener('change', function() {
        hospitalNameSection.style.display = this.checked ? 'block' : 'none';
      });
      
      // Set initial state
      togglePatientSections();

      const patientSelect = document.getElementById('patient_id');
      if (patientSelect) {
        patientSelect.addEventListener('change', function() {
          if (this.value) {
            window.location.href = 'new_report.php?patient_id=' + this.value;
          }
        });
      }

      // If we're showing the form for a new report, pre-select the patient
      <?php if ($showForm && $selectedPatientId): ?>
      if (patientSelect) {
        patientSelect.value = '<?php echo $selectedPatientId; ?>';
      }
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
