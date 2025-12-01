<?php
session_start();

if (!isset($_SESSION['staff_id'])) {
    header("Location: ../login/staff_login.php");
    exit;
}

require_once '../conn/conn.php';
require_once '../admin/includes/logging_helper.php';

// Initialize template state defaults
$selectedPatientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : null;
$selectedPatientInfo = null;
$existingReports = [];
$showForm = true;
$formSubmitted = false;
$formSuccess = false;
$errorMessage = '';

// Get all patients for dropdown
try {
    $patientsStmt = $pdo->query("SELECT patientId, firstName, lastName FROM patients ORDER BY lastName, firstName");
    $patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients = [];
}

// Fetch patient info and existing reports if a patient is pre-selected
if ($selectedPatientId) {
    try {
        $patientInfoStmt = $pdo->prepare("
            SELECT patientId, firstName, lastName, contactNumber, barangay
            FROM patients
            WHERE patientId = ?
        ");
        $patientInfoStmt->execute([$selectedPatientId]);
        $selectedPatientInfo = $patientInfoStmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedPatientInfo) {
            $reportsStmt = $pdo->prepare("
                SELECT r.*, p.firstName, p.lastName, p.contactNumber, p.barangay
                FROM reports r
                JOIN patients p ON r.patientId = p.patientId
                WHERE r.patientId = ?
                ORDER BY r.reportDate DESC
            ");
            $reportsStmt->execute([$selectedPatientId]);
            $existingReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $selectedPatientId = null;
        }
    } catch (PDOException $e) {
        $selectedPatientId = null;
        $selectedPatientInfo = null;
        $existingReports = [];
    }
}

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

// Process form submission (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $formSubmitted = true;
  try {
    $pdo->beginTransaction();

    // Patient duplication detection and creation
    $patientId = null;

    // Get patient information from form
    $firstName = trim($_POST['new_first_name'] ?? '');
    $lastName = trim($_POST['new_last_name'] ?? '');
    $gender = $_POST['new_gender'] ?? null;
    $dateOfBirth = !empty($_POST['new_dob']) ? $_POST['new_dob'] : null;
    $contactNumber = trim($_POST['new_contact'] ?? '');
    $address = trim($_POST['new_address'] ?? '');
    $barangay = trim($_POST['new_barangay'] ?? '');

    // Validate required patient information
    if (empty($firstName) || empty($lastName)) {
      throw new Exception('Please provide both first name and last name for the patient.');
    }

    // Check for similar existing patients
    $similarPatients = [];
    $highProbabilityMatch = null;

    if (!empty($contactNumber) || (!empty($firstName) && !empty($lastName))) {
      // Build search criteria
      $searchCriteria = [];
      $searchParams = [];

      if (!empty($firstName)) {
        $searchCriteria[] = "firstName LIKE ?";
        $searchParams[] = "%" . $firstName . "%";
      }

      if (!empty($lastName)) {
        $searchCriteria[] = "lastName LIKE ?";
        $searchParams[] = "%" . $lastName . "%";
      }

      if (!empty($contactNumber)) {
        $searchCriteria[] = "contactNumber = ?";
        $searchParams[] = $contactNumber;
      }

      if (!empty($address)) {
        $searchCriteria[] = "address LIKE ?";
        $searchParams[] = "%" . $address . "%";
      }

      if (!empty($barangay)) {
        $searchCriteria[] = "barangay LIKE ?";
        $searchParams[] = "%" . $barangay . "%";
      }

      if (!empty($gender)) {
        $searchCriteria[] = "gender = ?";
        $searchParams[] = $gender;
      }

      if (!empty($dateOfBirth)) {
        $searchCriteria[] = "dateOfBirth = ?";
        $searchParams[] = $dateOfBirth;
      }

      if (!empty($searchCriteria)) {
        $searchQuery = "SELECT *, 
                        (CASE 
                          WHEN contactNumber = ? THEN 50
                          WHEN firstName = ? AND lastName = ? THEN 40
                          WHEN firstName = ? AND lastName = ? AND address LIKE ? THEN 30
                          WHEN firstName = ? AND lastName = ? AND barangay = ? THEN 25
                          WHEN firstName = ? AND lastName = ? THEN 20
                          WHEN contactNumber LIKE ? THEN 15
                          ELSE 5
                        END) as match_score
                        FROM patients 
                        WHERE " . implode(" OR ", $searchCriteria) . " 
                        ORDER BY match_score DESC, patientId DESC 
                        LIMIT 10";

        // Add scoring parameters
        array_unshift($searchParams, $contactNumber, $firstName, $lastName, $firstName, $lastName, "%" . $address . "%", $firstName, $lastName, $barangay, $firstName, $lastName, "%" . $contactNumber . "%");

        $stmt = $pdo->prepare($searchQuery);
        $stmt->execute($searchParams);
        $similarPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check for high probability match (score >= 40)
        foreach ($similarPatients as $patient) {
          if ($patient['match_score'] >= 40) {
            $highProbabilityMatch = $patient;
            break;
          }
        }
      }
    }

    // Use existing patient if high probability match found, otherwise create new patient
    if ($highProbabilityMatch) {
      $patientId = $highProbabilityMatch['patientId'];
      // Log that we found a duplicate and used existing patient
      logActivity($pdo, 'PATIENT_DUPLICATE_DETECTED', 'staff', $_SESSION['staff_id'] ?? null, $patientId,
        "Used existing patient ID {$patientId} ({$highProbabilityMatch['firstName']} {$highProbabilityMatch['lastName']}) instead of creating duplicate. Match score: {$highProbabilityMatch['match_score']}");
    } else {
      // Create new patient
      $newPatientStmt = $pdo->prepare("
        INSERT INTO patients (firstName, lastName, gender, dateOfBirth, contactNumber, address, barangay)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $newPatientStmt->execute([
        $firstName,
        $lastName,
        $gender,
        $dateOfBirth,
        $contactNumber,
        $address,
        $barangay
      ]);
      $patientId = $pdo->lastInsertId();
    }

    // Validate that patient exists
    if ($patientId) {
      $checkPatient = $pdo->prepare("SELECT patientId FROM patients WHERE patientId = ?");
      $checkPatient->execute([$patientId]);
      if (!$checkPatient->fetch()) {
        throw new Exception('Selected patient not found. Please try again.');
      }
    }

    // Insert report (admin-created). Use NULL for staffId when created by admin.
    // Enhanced automated classification with improved risk assessment
    function classifyIncident($pdo, $patientId, $input) {
      $biteLocation = strtolower(trim($input['bite_location'] ?? ''));
      $multiple = !empty($input['multiple_bites']);
      $ownership = strtolower($input['animal_ownership'] ?? '');
      $animalVaccinated = strtolower($input['animal_vaccinated'] ?? '');
      $provoked = isset($input['provoked']) && $input['provoked'] !== '' ? $input['provoked'] : null;
      $animalType = strtolower($input['animal_type'] ?? '');
      $animalStatus = strtolower($input['animal_status'] ?? '');

      // Enhanced patient risk factors
      $age = null;
      $ageRisk = 'normal';
      if ($patientId) {
        try {
          $stmt = $pdo->prepare("SELECT dateOfBirth, gender FROM patients WHERE patientId = ?");
          $stmt->execute([$patientId]);
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if (!empty($row['dateOfBirth'])) {
            $dob = new DateTime($row['dateOfBirth']);
            $now = new DateTime();
            $age = (int)$now->diff($dob)->y;
            // Age-based risk assessment
            if ($age <= 5) $ageRisk = 'very_high'; // Children very vulnerable
            elseif ($age <= 12) $ageRisk = 'high'; // School age children
            elseif ($age >= 65) $ageRisk = 'moderate'; // Elderly more susceptible
          }
        } catch (PDOException $e) { /* ignore */ }
      }

      // Comprehensive location risk assessment
      $highRiskLocations = [
        'face', 'head', 'neck', 'scalp', 'eyes', 'mouth', 'lips', 'nose', 'ear',
        'hands', 'fingers', 'feet', 'toes', 'genitals', 'perineum'
      ];
      $moderateRiskLocations = [
        'arms', 'legs', 'torso', 'chest', 'back', 'shoulders'
      ];

      $locationRisk = 'low';
      foreach ($highRiskLocations as $frag) {
        if (strpos($biteLocation, $frag) !== false) {
          $locationRisk = 'high';
          break;
        }
      }
      if ($locationRisk === 'low') {
        foreach ($moderateRiskLocations as $frag) {
          if (strpos($biteLocation, $frag) !== false) {
            $locationRisk = 'moderate';
            break;
          }
        }
      }

      // Animal type risk assessment
      $highRiskAnimals = ['dog', 'cat', 'monkey', 'bat', 'raccoon', 'skunk'];
      $moderateRiskAnimals = ['pig', 'cow', 'horse', 'sheep', 'goat'];

      $animalRisk = 'low';
      foreach ($highRiskAnimals as $animal) {
        if (strpos($animalType, $animal) !== false) {
          $animalRisk = 'high';
          break;
        }
      }
      if ($animalRisk === 'low') {
        foreach ($moderateRiskAnimals as $animal) {
          if (strpos($animalType, $animal) !== false) {
            $animalRisk = 'moderate';
            break;
          }
        }
      }

      // Enhanced scoring system with weighted factors
      $score = 0;
      $riskFactors = [];

      // Location risk (highest weight)
      if ($locationRisk === 'high') {
        $score += 4;
        $riskFactors[] = "High-risk location";
      } elseif ($locationRisk === 'moderate') {
        $score += 2;
        $riskFactors[] = "Moderate-risk location";
      }

      // Multiple bites (high weight)
      if ($multiple) {
        $score += 3;
        $riskFactors[] = "Multiple bites";
      }

      // Animal ownership and vaccination (high weight)
      if ($ownership === 'stray' || $ownership === 'unknown') {
        $score += 3;
        $riskFactors[] = "Stray/unknown animal";
      }
      if ($animalVaccinated === 'no' || $animalVaccinated === 'unknown' || $animalVaccinated === '') {
        $score += 2;
        $riskFactors[] = "Unvaccinated animal";
      }

      // Animal type and status
      if ($animalRisk === 'high') {
        $score += 2;
        $riskFactors[] = "High-risk animal type";
      } elseif ($animalRisk === 'moderate') {
        $score += 1;
        $riskFactors[] = "Moderate-risk animal type";
      }

      if ($animalStatus === 'dead') {
        $score += 1;
        $riskFactors[] = "Dead animal (potential rabies)";
      }

      // Provocation and patient factors
      if ($provoked === 'no' || $provoked === '0' || $provoked === '') {
        $score += 1;
        $riskFactors[] = "Unprovoked bite";
      }

      // Age-based risk
      if ($ageRisk === 'very_high') {
        $score += 3;
        $riskFactors[] = "Very young patient (≤5 years)";
      } elseif ($ageRisk === 'high') {
        $score += 2;
        $riskFactors[] = "Young patient (6-12 years)";
      } elseif ($ageRisk === 'moderate') {
        $score += 1;
        $riskFactors[] = "Elderly patient (≥65 years)";
      }

      // Determine final category with improved thresholds
      if ($score >= 8) {
        $category = 'Category III';
        $severity = 'Critical';
        $recommendation = 'Immediate PEP required. Hospital referral strongly recommended.';
      } elseif ($score >= 5) {
        $category = 'Category II';
        $severity = 'High';
        $recommendation = 'PEP recommended. Close monitoring advised.';
      } else {
        $category = 'Category I';
        $severity = 'Low';
        $recommendation = 'PEP may not be required. Clinical assessment advised.';
      }

      // Comprehensive rationale
      $rationale = "Enhanced auto-classification: {$category} (Severity: {$severity})\n";
      $rationale .= "Risk Score: {$score}/15 | Risk Factors: " . implode(', ', $riskFactors) . "\n";
      $rationale .= "Assessment Details:\n";
      $rationale .= "- Location: {$biteLocation} (Risk: {$locationRisk})\n";
      $rationale .= "- Animal: {$animalType} (Risk: {$animalRisk}, Status: {$animalStatus}, Vaccinated: {$animalVaccinated})\n";
      $rationale .= "- Ownership: {$ownership} | Provoked: " . ($provoked === 'yes' ? 'Yes' : 'No') . "\n";
      if ($age !== null) $rationale .= "- Patient Age: {$age} years (Risk: {$ageRisk})\n";
      $rationale .= "- Multiple Bites: " . ($multiple ? 'Yes' : 'No') . "\n";
      $rationale .= "\nRecommendation: {$recommendation}";

      return [
        'biteType' => $category,
        'severity' => $severity,
        'score' => $score,
        'maxScore' => 15,
        'riskFactors' => $riskFactors,
        'recommendation' => $recommendation,
        'rationale' => $rationale
      ];
    }

    $classification = classifyIncident($pdo, $patientId, $_POST);
    // Allow admin override: if admin provided a manual override, respect it and record reason
    if (isset($_POST['category_override']) && !empty($_POST['category_override'])) {
      $overrideVal = $_POST['category_override'];
      $overrideReason = !empty($_POST['override_reason']) ? $_POST['override_reason'] : 'No reason provided';
      $classification['biteType'] = $overrideVal;
      $classification['rationale'] .= "\n\n[ADMIN OVERRIDE] Manual category: {$overrideVal}\nReason: {$overrideReason}";
    }

    // Enhanced notes with classification details
    $autoNotes = (!empty($_POST['notes']) ? $_POST['notes'] . "\n\n" : "") . "[CLASSIFICATION REPORT]\n" . $classification['rationale'];
    $reportStmt = $pdo->prepare("\n            INSERT INTO reports (\n                patientId, \n                staffId, \n                biteDate,\n                animalType,\n                animalOtherType,\n                animalOwnership,\n                ownerName,\n                ownerContact,\n                animalStatus,\n                animalVaccinated,\n                biteLocation,\n                biteType,\n                multipleBites,\n                provoked,\n                washWithSoap,\n                rabiesVaccine,\n                rabiesVaccineDate,\n                antiTetanus,\n                antiTetanusDate,\n                antibiotics,\n                antibioticsDetails,\n                referredToHospital,\n                hospitalName,\n                followUpDate,\n                notes,\n                status\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ");

    $reportStmt->execute([
      $patientId,
      null, // staffId (admin)
      $_POST['bite_date'] ?? null,
      $_POST['animal_type'] ?? null,
      (isset($_POST['animal_type']) && $_POST['animal_type'] === 'Other') ? ($_POST['animal_other_type'] ?? null) : null,
      $_POST['animal_ownership'] ?? null,
      !empty($_POST['owner_name']) ? $_POST['owner_name'] : null,
      !empty($_POST['owner_contact']) ? $_POST['owner_contact'] : null,
      $_POST['animal_status'] ?? null,
      $_POST['animal_vaccinated'] ?? null,
      $_POST['bite_location'] ?? null,
      $classification['biteType'], // AI-enhanced classification
      isset($_POST['multiple_bites']) ? 1 : 0,
      $_POST['provoked'] ?? null,
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
      $autoNotes,
      $_POST['status'] ?? 'pending'
    ]);

    $reportId = $pdo->lastInsertId();

    // Sync rabies vaccine checkbox to vaccination_records: insert Dose 1 for PEP if checked and not exists
    if (isset($_POST['rabies_vaccine']) && $patientId && $reportId) {
      // Check if dose 1 for this report already exists
      $checkStmt = $pdo->prepare("SELECT COUNT(*) as c FROM vaccination_records WHERE reportId = ? AND exposureType = 'PEP' AND doseNumber = 1");
      $checkStmt->execute([$reportId]);
      $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
      if ((int)$row['c'] === 0) {
        $dateGiven = !empty($_POST['rabies_vaccine_date']) ? $_POST['rabies_vaccine_date'] : null;
        $status = $dateGiven ? 'Completed' : 'Upcoming';
        $insertVacc = $pdo->prepare("\n                    INSERT INTO vaccination_records (patientId, reportId, exposureType, doseNumber, dateGiven, nextScheduledDate, status, vaccineName, batchNumber, administeredBy, remarks, created_at) \n                    VALUES (?, ?, 'PEP', ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)\n                ");
        $insertVacc->execute([
          $patientId,
          $reportId,
          1,
          $dateGiven,
          null,
          $status,
          $dateGiven ? 'Rabies Vaccine' : null,
          null,
          null,
          null
        ]);
      }
    }

    $pdo->commit();

    // Log report creation
    logActivity($pdo, 'CREATE', 'report', $reportId, null, "Created new report for patient ID: {$patientId}");

    $formSuccess = true;
  } catch (PDOException $e) {
    // Only rollback if transaction is still active
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $errorMessage = 'Error: ' . $e->getMessage();
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

    .main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1.5rem;
    }

    body {
      background-color: #f5f5f5;
      font-family: system-ui, -apple-system, sans-serif;
    }
    
    /* Simplified container with full width */
    .form-container {
      padding: 2rem;
      max-width: 100%;
    }
    
    /* Cleaner form card */
    .form-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      margin-bottom: 1.5rem;
    }
    
    .form-header {
      padding: 1.5rem;
      border-bottom: 1px solid #e5e5e5;
      background: #fafafa;
    }
    
    .form-header h2 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;
      color: #333;
    }
    
    .form-body {
      padding: 1.5rem;
    }
    
    /* Simplified section styling */
    .form-section {
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 6px;
      margin-bottom: 1rem;
    }
    
    .section-header {
      padding: 1rem 1.5rem;
      background: #f9fafb;
      border-bottom: 1px solid #e5e5e5;
      font-weight: 600;
      font-size: 1rem;
      color: #111;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      text-align: left;
      border: none;
    }
    
    .section-header:hover {
      background: #f3f4f6;
    }
    
    .section-header i.bi-chevron-down {
      transition: transform 0.2s ease;
      display: inline-block;
    }
    
    .section-header[aria-expanded="false"] i.bi-chevron-down {
      transform: rotate(-90deg);
    }
    
    .section-content {
      padding: 1.5rem;
    }
    
    /* Removed footer styling */
    
    .required-field::after {
      content: "*";
      color: #dc3545;
      margin-left: 0.25rem;
    }
    
    .success-message {
      background: #f0f9ff;
      border: 1px solid #38bdf8;
      border-radius: 8px;
      padding: 2rem;
      text-align: center;
    }
    
    .success-icon {
      font-size: 3rem;
      color: #0ea5e9;
      margin-bottom: 1rem;
    }
    
    /* Treatment section uses vertical flex layout */
    .treatment-container {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .treatment-row {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .treatment-row.checkbox-row {
      flex-direction: row;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .treatment-row.checkbox-row .form-check {
      flex: 1 1 calc(50% - 0.5rem);
      min-width: 200px;
    }

    /* Conditional fields: smooth reveal without grid layout */
    .conditional-field {
      display: none;
      width: 100%;
      overflow: hidden;
      box-sizing: border-box;
    }

    .conditional-field.show {
      display: block;
      animation: slideDown 0.3s ease forwards;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        max-height: 0;
      }
      to {
        opacity: 1;
        max-height: 600px;
      }
    }

    .conditional-field .form-group {
      margin-bottom: 0;
    }

    .conditional-field .form-label {
      margin-bottom: 0.5rem;
      display: block;
    }

    .conditional-field .form-control,
    .conditional-field .form-select {
      width: 100%;
    }

    @media (max-width: 768px) {
      .treatment-row.checkbox-row .form-check {
        flex: 1 1 100%;
      }
    }
    
    .category-info {
      background: #f9fafb;
      border-radius: 6px;
      padding: 1rem;
      margin-top: 1rem;
      font-size: 0.875rem;
    }
    
    .category-info h5 {
      font-size: 0.875rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    .category-info p {
      margin-bottom: 0.5rem;
      color: #666;
    }
    
    .category-info p:last-child {
      margin-bottom: 0;
    }
    
    
    .badge-status-referred {
      background: #e9d5ff;
      color: #6b21a8;
    }
    
    .badge-status-cancelled {
      background: #fecaca;
      color: #991b1b;
    }
    .table-card {
      background: #fff;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      overflow: hidden;
      margin-bottom: 1.5rem;
    }
    .table-responsive {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
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
        <a href="dashboard.php" class="btn-new">
          <i class="bi bi-arrow-left"></i>
          Back to Dashboard
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main">
    <div class="form-container">
    
    <?php if ($showForm): ?>
        <?php if ($formSubmitted && $formSuccess): ?>
        <div class="success-message">
          <div class="success-icon">
            <i class="bi bi-check-circle"></i>
          </div>
          <h3>Report Created Successfully!</h3>
          <p class="mb-4">The animal bite report has been added to the system.</p>
          <div class="d-flex justify-content-center gap-3 flex-wrap">
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
            <h2><i class="bi bi-file-earmark-plus me-2"></i>New Animal Bite Report</h2>
          </div>
          
          <div class="form-body">
            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="new_report.php" enctype="multipart/form-data" id="reportForm">
              <!-- Patient Information Section -->
              <div class="form-section">
                <button type="button" class="section-header" data-bs-toggle="collapse" data-bs-target="#patientSection" aria-expanded="true">
                  <span><i class="bi bi-person me-2"></i>Patient Information</span>
                  <i class="bi bi-chevron-down"></i>
                </button>
                <div class="section-content collapse show" id="patientSection">
                <!-- Patient Information -->
                <div class="row g-3">
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
                    <input type="text" class="form-control" id="new_contact" name="new_contact" maxlength="11" pattern="[0-9]{7,11}" placeholder="11-digit mobile number (e.g., 09123456789)">
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
                <button type="button" class="section-header" data-bs-toggle="collapse" data-bs-target="#biteDetailsSection" aria-expanded="true">
                  <span><i class="bi bi-exclamation-triangle me-2"></i>Animal Bite Details</span>
                  <i class="bi bi-chevron-down"></i>
                </button>
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
                  
                  <div class="conditional-field" id="otherAnimalSection">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="animal_other_type" class="form-label required-field">Specify Animal</label>
                        <input type="text" class="form-control" id="animal_other_type" name="animal_other_type">
                      </div>
                    </div>
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
                  
                  <div class="conditional-field" id="ownerSection">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="owner_name" class="form-label">Owner's Name</label>
                        <input type="text" class="form-control" id="owner_name" name="owner_name">
                      </div>
                    </div>
                  </div>
                  
                  <div class="conditional-field" id="ownerContactSection">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="owner_contact" class="form-label">Owner's Contact</label>
                        <input type="text" class="form-control" id="owner_contact" name="owner_contact">
                      </div>
                    </div>
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
                    <label for="animal_vaccinated" class="form-label required-field">Animal Vaccinated</label>
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
                <button type="button" class="section-header" data-bs-toggle="collapse" data-bs-target="#biteLocationSection" aria-expanded="true">
                  <span><i class="bi bi-bullseye me-2"></i>Bite Location & Classification</span>
                  <i class="bi bi-chevron-down"></i>
                </button>
                <div class="section-content collapse show" id="biteLocationSection">
                <div class="row g-3">
                  <div class="col-md-12">
                    <label for="bite_location" class="form-label required-field">Bite Location (Body Part)</label>
                    <input type="text" class="form-control" id="bite_location" name="bite_location" placeholder="e.g., right hand, left leg, face" required>
                  </div>
                  
                    <div class="col-md-12">
                    <label for="bite_type" class="form-label required-field">Bite Category</label>
                    <select class="form-select" id="bite_type" name="bite_type" required>
                      <option value="">-- Select Category --</option>
                      <option value="Category I">Category I</option>
                      <option value="Category II">Category II</option>
                      <option value="Category III">Category III</option>
                    </select>

                    <!-- Enhanced Auto-Classification Display -->
                    <div id="autoClassification" class="mt-3 p-3 rounded" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border: 2px solid #cbd5e1; display: none;">
                      <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-robot text-primary me-2"></i>
                        <strong class="text-primary">AI-Powered Risk Assessment</strong>
                      </div>
                      <div id="autoCategory" class="fw-bold mb-2 fs-5 text-success"></div>
                      <div id="autoSeverity" class="mb-2">
                        <span class="badge bg-info">Severity:</span>
                        <span id="severityText" class="fw-semibold"></span>
                      </div>
                      <div id="autoScore" class="mb-2">
                        <span class="badge bg-secondary">Risk Score:</span>
                        <span id="scoreText" class="fw-semibold"></span>
                      </div>
                      <div id="autoRiskFactors" class="mb-2">
                        <small class="text-muted">Risk Factors:</small>
                        <div id="riskFactorsList" class="mt-1"></div>
                      </div>
                      <div id="autoRecommendation" class="alert alert-light border mt-2 p-2">
                        <small class="fw-semibold text-primary">Recommendation:</small>
                        <div id="recommendationText" class="mt-1"></div>
                      </div>
                    </div>

                    <!-- Override Option -->
                    <div class="mt-3">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="overrideCheckbox">
                        <label class="form-check-label fw-semibold" for="overrideCheckbox">
                          <i class="bi bi-shield-exclamation-triangle text-warning me-1"></i>
                          Override AI classification (use with caution)
                        </label>
                      </div>
                    </div>

                    <!-- Override Section -->
                    <div id="overrideSection" class="mt-3 p-3 border rounded bg-light" style="display: none;">
                      <h6 class="text-warning mb-3">
                        <i class="bi bi-exclamation-triangle-fill"></i> Manual Override
                      </h6>
                      <div class="mb-3">
                        <label class="form-label fw-semibold">Override Category</label>
                        <select class="form-select" id="category_override" name="category_override">
                          <option value="">-- Select Override Category --</option>
                          <option value="Category I">Category I (Low Risk)</option>
                          <option value="Category II">Category II (Moderate Risk)</option>
                          <option value="Category III">Category III (High Risk)</option>
                        </select>
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-semibold">Override Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="override_reason" name="override_reason" rows="3"
                                  placeholder="Please provide detailed reasoning for overriding the AI classification. This will be documented in the report for audit purposes."></textarea>
                        <div class="form-text">
                          <i class="bi bi-info-circle"></i> Override reasons are logged for quality assurance and audit purposes.
                        </div>
                      </div>
                    </div>


                    <div class="category-info" style="margin-top:12px;">
                      <h5>Bite Categories:</h5>
                      <p><strong>Category I:</strong> Touching or feeding of animals, licks on intact skin</p>
                      <p><strong>Category II:</strong> Nibbling of uncovered skin, minor scratches or abrasions without bleeding</p>
                      <p><strong>Category III:</strong> Single or multiple transdermal bites or scratches, contamination of mucous membrane or broken skin with saliva from animal licks, exposures due to direct contact with bats</p>
                    </div>
                  </div>
                </div>
                </div>
              </div>
              
              <!-- Treatment Information -->
              <div class="form-section">
                <button type="button" class="section-header" data-bs-toggle="collapse" data-bs-target="#treatmentSection" aria-expanded="false">
                  <span><i class="bi bi-bandaid me-2"></i>Treatment Information</span>
                  <i class="bi bi-chevron-down"></i>
                </button>
                <div class="section-content collapse" id="treatmentSection">
                  <div class="treatment-container">
                    <!-- Wound washing and rabies vaccine row -->
                    <div class="treatment-row checkbox-row">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="wash_with_soap" name="wash_with_soap">
                        <label class="form-check-label" for="wash_with_soap">
                          Wound washed with soap and water
                        </label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rabies_vaccine" name="rabies_vaccine">
                        <label class="form-check-label" for="rabies_vaccine">
                          Rabies vaccine administered
                        </label>
                      </div>
                    </div>
                    
                    <!-- Rabies vaccine date (conditional) -->
                    <div class="conditional-field" id="rabiesVaccineDateSection">
                      <div class="form-group">
                        <label for="rabies_vaccine_date" class="form-label">Date of Rabies Vaccine</label>
                        <input type="date" class="form-control" id="rabies_vaccine_date" name="rabies_vaccine_date">
                      </div>
                    </div>
                    
                    <!-- Anti-tetanus row -->
                    <div class="treatment-row">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="anti_tetanus" name="anti_tetanus">
                        <label class="form-check-label" for="anti_tetanus">
                          Anti-tetanus administered
                        </label>
                      </div>
                    </div>
                    
                    <!-- Anti-tetanus date (conditional) -->
                    <div class="conditional-field" id="antiTetanusDateSection">
                      <div class="form-group">
                        <label for="anti_tetanus_date" class="form-label">Date of Anti-tetanus</label>
                        <input type="date" class="form-control" id="anti_tetanus_date" name="anti_tetanus_date">
                      </div>
                    </div>
                    
                    <!-- Antibiotics row -->
                    <div class="treatment-row">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="antibiotics" name="antibiotics">
                        <label class="form-check-label" for="antibiotics">
                          Antibiotics prescribed
                        </label>
                      </div>
                    </div>
                    
                    <!-- Antibiotics details (conditional) -->
                    <div class="conditional-field" id="antibioticsDetailsSection">
                      <div class="form-group">
                        <label for="antibiotics_details" class="form-label">Antibiotics Details</label>
                        <input type="text" class="form-control" id="antibiotics_details" name="antibiotics_details" placeholder="Type, dosage, etc.">
                      </div>
                    </div>
                    
                    <!-- Hospital referral row -->
                    <div class="treatment-row">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="referred_to_hospital" name="referred_to_hospital">
                        <label class="form-check-label" for="referred_to_hospital">
                          Referred to hospital
                        </label>
                      </div>
                    </div>
                    
                    <!-- Hospital name (conditional) -->
                    <div class="conditional-field" id="hospitalNameSection">
                      <div class="form-group">
                        <label for="hospital_name" class="form-label">Hospital Name</label>
                        <input type="text" class="form-control" id="hospital_name" name="hospital_name">
                      </div>
                    </div>
                    
                    <!-- Follow-up and status row -->
                    <div class="treatment-row checkbox-row">
                      <div class="form-group" style="flex: 1 1 calc(50% - 0.5rem); min-width: 200px;">
                        <label for="followup_date" class="form-label">Follow-up Date</label>
                        <input type="date" class="form-control" id="followup_date" name="followup_date">
                      </div>
                      <div class="form-group" style="flex: 1 1 calc(50% - 0.5rem); min-width: 200px;">
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
              </div>
              
              <!-- Additional Notes Section -->
              <div class="form-section">
                <button type="button" class="section-header" data-bs-toggle="collapse" data-bs-target="#notesSection" aria-expanded="false">
                  <span><i class="bi bi-journal me-2"></i>Additional Notes</span>
                  <i class="bi bi-chevron-down"></i>
                </button>
                <div class="section-content collapse" id="notesSection">
                <div class="mb-3">
                  <label for="notes" class="form-label">Notes</label>
                  <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Any additional information or observations about the case"></textarea>
                </div>
                </div>
              </div>
              
              <div class="d-flex justify-content-between mt-4">
                <a href="dashboard.php" class="btn btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Discard changes and return to dashboard">
                  <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Save the bite incident report">
                  <i class="bi bi-save me-2"></i>Save Report
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    
    document.addEventListener('DOMContentLoaded', function() {
      // Toggle between existing and new patient sections
      
      // Helper function to toggle conditional fields smoothly
      function toggleConditionalField(element, show) {
        if (show) {
          element.classList.add('show');
        } else {
          element.classList.remove('show');
        }
      }
      
      // Show/hide other animal type field
      const animalTypeSelect = document.getElementById('animal_type');
      const otherAnimalSection = document.getElementById('otherAnimalSection');
      
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
      
      // Form validation before submission
      const reportForm = document.getElementById('reportForm');
      reportForm.addEventListener('submit', function(e) {
        const patientIdInput = document.getElementById('patient_id');
        const newFirstNameInput = document.getElementById('new_first_name');
        const newLastNameInput = document.getElementById('new_last_name');

        // Validate patient selection - simplified logic
        const hasPatientSelected = patientIdInput.value.trim() ||
                                  (newFirstNameInput.value.trim() && newLastNameInput.value.trim());

        if (!hasPatientSelected) {
          e.preventDefault();
          alert('Please select an existing patient or provide new patient details (first and last name).');
          if (!patientIdInput.value.trim()) {
            // Focus on patient search if no patient selected
            document.getElementById('patient_search').focus();
          } else {
            // Focus on first name if trying to create new patient
            document.getElementById('new_first_name').focus();
          }
          return false;
        }

        // Additional validation for required fields
        const biteDateInput = document.getElementById('bite_date');
        const animalTypeInput = document.getElementById('animal_type');
        const biteLocationInput = document.getElementById('bite_location');
        const biteTypeInput = document.getElementById('bite_type');
        const statusInput = document.getElementById('status');

        if (!biteDateInput.value) {
          e.preventDefault();
          alert('Please select the date of the bite incident.');
          biteDateInput.focus();
          return false;
        }

        if (!animalTypeInput.value) {
          e.preventDefault();
          alert('Please select the animal type.');
          animalTypeInput.focus();
          return false;
        }

        if (!biteLocationInput.value.trim()) {
          e.preventDefault();
          alert('Please specify the bite location.');
          biteLocationInput.focus();
          return false;
        }

        if (!biteTypeInput.value) {
          e.preventDefault();
          alert('Please select the bite category.');
          biteTypeInput.focus();
          return false;
        }

        if (!statusInput.value) {
          e.preventDefault();
          alert('Please select the case status.');
          statusInput.focus();
          return false;
        }

        // Validate override section if override is selected
        const overrideCheckbox = document.getElementById('overrideCheckbox');
        if (overrideCheckbox && overrideCheckbox.checked) {
          const categoryOverrideSelect = document.getElementById('category_override');
          const overrideReasonInput = document.getElementById('override_reason');

          if (!categoryOverrideSelect.value) {
            e.preventDefault();
            alert('Please select an override category.');
            categoryOverrideSelect.focus();
            return false;
          }

          if (!overrideReasonInput.value.trim()) {
            e.preventDefault();
            alert('Please provide a reason for overriding the AI classification.');
            overrideReasonInput.focus();
            return false;
          }
        }

        // Show loading state
        const submitBtn = document.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';

        console.log('Form validation passed, submitting form...');
      });

      // Contact number validation - only allow numbers
      const contactInput = document.getElementById('new_contact');
      if (contactInput) {
        contactInput.addEventListener('input', function(e) {
          // Remove any non-numeric characters
          this.value = this.value.replace(/[^0-9]/g, '');
        });

        contactInput.addEventListener('keypress', function(e) {
          // Only allow numeric keys, backspace, delete, tab, escape, enter
          const charCode = e.which ? e.which : e.keyCode;
          if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            e.preventDefault();
            return false;
          }
          return true;
        });
      }

      // Set initial state
      togglePatientSections();

      // Initialize conditional fields visibility based on current values
      toggleConditionalField(otherAnimalSection, animalTypeSelect.value === 'Other');
      const ownershipVal = animalOwnershipSelect.value;
      toggleConditionalField(ownerSection, ownershipVal === 'Owned by neighbor' || ownershipVal === 'Owned by unknown person');
      toggleConditionalField(ownerContactSection, ownershipVal === 'Owned by neighbor' || ownershipVal === 'Owned by unknown person');
      toggleConditionalField(rabiesVaccineDateSection, rabiesVaccineCheckbox.checked);
      toggleConditionalField(antiTetanusDateSection, antiTetanusCheckbox.checked);
      toggleConditionalField(antibioticsDetailsSection, antibioticsCheckbox.checked);
      toggleConditionalField(hospitalNameSection, referredToHospitalCheckbox.checked);

      // Enhanced AI-Powered Auto-Classification System
      const biteLocationInput = document.getElementById('bite_location');
          patientSearchResults.classList.remove('show');
          return;
        }
        
        fetch(`search_patients.php?q=${encodeURIComponent(query)}&limit=20`)
          .then(response => response.json())
          .then(data => {
            if (!Array.isArray(data)) {
              throw new Error('Error searching patients');
            }
            
            currentResults = data;
            displayResults(data);
          })
          .catch(error => {
            console.error('Error searching patients:', error);
            patientSearchResults.innerHTML = '<div class="autocomplete-item">Error searching patients</div>';
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
              ${patient.barangay ? `<span class="ms-3"><i class="bi bi-geo-alt"></i> ${escapeHtml(patient.barangay)}</span>` : ''}
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
      const selectedPatient = {
        id: '<?php echo $selectedPatientInfo['patientId']; ?>',
        text: '<?php echo htmlspecialchars($selectedPatientInfo['lastName'] . ', ' . $selectedPatientInfo['firstName'], ENT_QUOTES); ?>',
        contactNumber: '<?php echo htmlspecialchars($selectedPatientInfo['contactNumber'] ?? '', ENT_QUOTES); ?>',
        barangay: '<?php echo htmlspecialchars($selectedPatientInfo['barangay'] ?? '', ENT_QUOTES); ?>'
      };
      patientIdInput.value = selectedPatient.id;
      patientSearchInput.value = selectedPatient.text;
      selectedPatientName.textContent = selectedPatient.text;
      selectedPatientInfo.style.display = 'block';
      <?php endif; ?>

      // Enhanced AI-Powered Auto-Classification System
      const biteLocationInput = document.getElementById('bite_location');
      const animalTypeInput = document.getElementById('animal_type');
      const animalOwnershipInput = document.getElementById('animal_ownership');
      const animalStatusInput = document.getElementById('animal_status');
      const animalVaccinatedInput = document.getElementById('animal_vaccinated');
      const provokedInput = document.getElementById('provoked');
      const multipleBitesInput = document.getElementById('multiple_bites');

      const autoBox = document.getElementById('autoClassification');
      const autoCategory = document.getElementById('autoCategory');
      const autoSeverity = document.getElementById('autoSeverity');
      const severityText = document.getElementById('severityText');
      const autoScore = document.getElementById('autoScore');
      const scoreText = document.getElementById('scoreText');
      const autoRiskFactors = document.getElementById('autoRiskFactors');
      const riskFactorsList = document.getElementById('riskFactorsList');
      const autoRecommendation = document.getElementById('autoRecommendation');
      const recommendationText = document.getElementById('recommendationText');
      const biteTypeSelect = document.getElementById('bite_type');

      const overrideCheckbox = document.getElementById('overrideCheckbox');
      const overrideSection = document.getElementById('overrideSection');
      const categoryOverrideSelect = document.getElementById('category_override');
      const overrideReasonInput = document.getElementById('override_reason');

      let classifyTimeout = null;

      function scheduleClassify() {
        if (classifyTimeout) clearTimeout(classifyTimeout);
        classifyTimeout = setTimeout(runClassify, 500); // Slightly longer delay for better UX
      }

      function runClassify() {
        // Only run classification if we have minimum required data
        const hasPatient = patientIdInput.value.trim() !== '';
        const hasLocation = biteLocationInput.value.trim().length >= 3;
        const hasAnimalType = animalTypeInput.value.trim() !== '';

        if (!hasPatient || !hasLocation || !hasAnimalType) {
          autoBox.style.display = 'none';
          return;
        }

        const formData = new FormData();
        formData.append('patient_id', patientIdInput.value || '');
        formData.append('bite_location', biteLocationInput.value || '');
        formData.append('animal_type', animalTypeInput.value || '');
        formData.append('animal_ownership', animalOwnershipInput ? animalOwnershipInput.value : '');
        formData.append('animal_status', animalStatusInput ? animalStatusInput.value : '');
        formData.append('animal_vaccinated', animalVaccinatedInput ? animalVaccinatedInput.value : '');
        formData.append('provoked', provokedInput ? provokedInput.value : '');
        formData.append('multiple_bites', multipleBitesInput && multipleBitesInput.checked ? '1' : '');

        fetch('classify_incident.php', { method: 'POST', body: formData })
          .then(resp => resp.json())
          .then(json => {
            if (json && json.success && json.data) {
              const d = json.data;
              autoBox.style.display = 'block';

              // Enhanced display with better visual hierarchy
              autoCategory.textContent = d.biteType;
              autoCategory.className = d.biteType === 'Category III' ? 'fw-bold mb-2 fs-5 text-danger' :
                                     d.biteType === 'Category II' ? 'fw-bold mb-2 fs-5 text-warning' :
                                     'fw-bold mb-2 fs-5 text-success';

              severityText.textContent = d.severity;

              // Enhanced score display with progress indication
              const scorePercentage = Math.round((d.score / d.maxScore) * 100);
              scoreText.innerHTML = `${d.score}/${d.maxScore} <small class="text-muted">(${scorePercentage}%)</small>`;

              // Risk factors as badges
              if (d.riskFactors && d.riskFactors.length > 0) {
                riskFactorsList.innerHTML = d.riskFactors.map(factor =>
                  `<span class="badge bg-light text-dark me-1 mb-1">${factor}</span>`
                ).join('');
              } else {
                riskFactorsList.innerHTML = '<small class="text-muted">No significant risk factors identified</small>';
              }

              // Enhanced recommendation with appropriate styling
              recommendationText.textContent = d.recommendation;
              recommendationText.className = d.biteType === 'Category III' ? 'text-danger fw-semibold' :
                                           d.biteType === 'Category II' ? 'text-warning fw-semibold' :
                                           'text-success fw-semibold';

              // Auto-populate category only if override is not active
              if (!overrideCheckbox.checked) {
                biteTypeSelect.value = d.biteType;
              }

              // Add visual feedback animation
              autoBox.style.animation = 'fadeInScale 0.3s ease-out';
            }
          }).catch(err => {
            console.warn('Classification error:', err);
            autoBox.style.display = 'none';
          });
      }

      // Monitor all relevant inputs for changes
      const inputsToMonitor = [
        biteLocationInput, animalTypeInput, animalOwnershipInput, animalStatusInput,
        animalVaccinatedInput, provokedInput, multipleBitesInput
      ];

      inputsToMonitor.forEach(el => {
        if (!el) return;
        el.addEventListener('change', scheduleClassify);
        el.addEventListener('input', scheduleClassify);
      });


      // Override functionality
      overrideCheckbox.addEventListener('change', function() {
        if (this.checked) {
          overrideSection.style.display = 'block';
          // Don't auto-populate when override is active
          biteTypeSelect.value = '';
        } else {
          overrideSection.style.display = 'none';
          categoryOverrideSelect.value = '';
          overrideReasonInput.value = '';
          // Restore AI classification
          runClassify();
        }
      });

      // Enhanced override validation
      categoryOverrideSelect.addEventListener('change', function() {
        if (this.value && !overrideReasonInput.value.trim()) {
          overrideReasonInput.focus();
          overrideReasonInput.style.borderColor = '#dc3545';
          setTimeout(() => overrideReasonInput.style.borderColor = '', 2000);
        }
      });

      // Initial classification when form loads (with delay for better UX)
      setTimeout(() => {
        if (patientIdInput.value && biteLocationInput.value) {
          runClassify();
        }
      }, 800);

      // Add CSS animation for smooth appearance
      const style = document.createElement('style');
      style.textContent = `
        @keyframes fadeInScale {
          from {
            opacity: 0;
            transform: scale(0.95);
          }
          to {
            opacity: 1;
            transform: scale(1);
          }
        }
        #autoClassification {
          animation-fill-mode: both;
        }
      `;
      document.head.appendChild(style);

    });
  </script>
  </main>
</body>
</html>
