<?php
// Initialize variables
$success = false;
$error = '';
$reference_number = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'conn/conn.php';
    
    // Collect form data
    $firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
    $lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : '';
    $dateOfBirth = isset($_POST['dateOfBirth']) ? trim($_POST['dateOfBirth']) : '';
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $contactNumber = isset($_POST['contactNumber']) ? trim($_POST['contactNumber']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $province = isset($_POST['province']) ? trim($_POST['province']) : '';
    
    $animalType = isset($_POST['animalType']) ? trim($_POST['animalType']) : '';
    $otherAnimal = isset($_POST['otherAnimal']) ? trim($_POST['otherAnimal']) : '';
    if ($animalType === 'Other' && !empty($otherAnimal)) {
        $animalType = $otherAnimal;
    }
    
    $biteDate = isset($_POST['biteDate']) ? trim($_POST['biteDate']) : '';
    $biteTime = isset($_POST['biteTime']) ? trim($_POST['biteTime']) : '';
    $biteLocation = isset($_POST['biteLocation']) ? trim($_POST['biteLocation']) : '';
    $biteDescription = isset($_POST['biteDescription']) ? trim($_POST['biteDescription']) : '';
    $firstAid = isset($_POST['firstAid']) ? trim($_POST['firstAid']) : '';
    $previousRabiesVaccine = isset($_POST['previousRabiesVaccine']) ? trim($_POST['previousRabiesVaccine']) : 'Unknown';
    $urgency = isset($_POST['urgency']) ? trim($_POST['urgency']) : 'Normal';
    
    // Validate required fields
    $requiredFields = ['firstName', 'lastName', 'contactNumber', 'address', 'barangay', 'city', 'animalType', 'biteDate', 'biteLocation'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($$field)) {
            $missingFields[] = ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $field));
        }
    }
    
    if (!empty($missingFields)) {
        $error = 'Please fill in the following required fields: ' . implode(', ', $missingFields);
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // First, check if patient already exists
            $checkPatientStmt = $pdo->prepare("
                SELECT patientId FROM patients 
                WHERE firstName = ? AND lastName = ? AND contactNumber = ?
            ");
            $checkPatientStmt->execute([$firstName, $lastName, $contactNumber]);
            $existingPatient = $checkPatientStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPatient) {
                $patientId = $existingPatient['patientId'];
                
                // Update patient information
                $updatePatientStmt = $pdo->prepare("
                    UPDATE patients SET
                        dateOfBirth = ?,
                        gender = ?,
                        email = ?,
                        address = ?,
                        barangay = ?,
                        city = ?,
                        province = ?,
                        previousRabiesVaccine = ?
                    WHERE patientId = ?
                ");
                
                $updatePatientStmt->execute([
                    $dateOfBirth,
                    $gender,
                    $email,
                    $address,
                    $barangay,
                    $city,
                    $province,
                    $previousRabiesVaccine,
                    $patientId
                ]);
            } else {
                // Insert new patient
                $insertPatientStmt = $pdo->prepare("
                    INSERT INTO patients (
                        firstName, lastName, middleName, dateOfBirth, gender,
                        contactNumber, email, address, barangay, city, province,
                        previousRabiesVaccine, registrationDate
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, NOW()
                    )
                ");
                
                $insertPatientStmt->execute([
                    $firstName,
                    $lastName,
                    $middleName,
                    $dateOfBirth,
                    $gender,
                    $contactNumber,
                    $email,
                    $address,
                    $barangay,
                    $city,
                    $province,
                    $previousRabiesVaccine
                ]);
                
                $patientId = $pdo->lastInsertId();
            }
            
            // Generate a reference number
            $reference_number = 'BITE-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insert report
            $insertReportStmt = $pdo->prepare("
                INSERT INTO reports (
                    patientId, animalType, biteDate, biteTime, biteLocation,
                    biteDescription, firstAid, reportDate, status, referenceNumber,
                    urgency, source
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, NOW(), 'pending', ?,
                    ?, 'online'
                )
            ");
            
            $insertReportStmt->execute([
                $patientId,
                $animalType,
                $biteDate,
                $biteTime,
                $biteLocation,
                $biteDescription,
                $firstAid,
                $reference_number,
                $urgency
            ]);
            
            $reportId = $pdo->lastInsertId();
            
            // Handle image uploads
            if (!empty($_FILES['biteImages']['name'][0])) {
                $uploadDir = 'uploads/bites/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['biteImages']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['biteImages']['error'][$key] === 0) {
                        $fileName = $reportId . '_' . time() . '_' . $key . '.jpg';
                        $filePath = $uploadDir . $fileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($tmp_name, $filePath)) {
                            // Insert image record
                            $insertImageStmt = $pdo->prepare("
                                INSERT INTO report_images (reportId, imagePath, uploadDate)
                                VALUES (?, ?, NOW())
                            ");
                            $insertImageStmt->execute([$reportId, $fileName]);
                        }
                    }
                }
            }
            
            // Send notification to health workers (this would be implemented based on your notification system)
            // For example, you might send an SMS, email, or push notification
            
            // Commit transaction
            $pdo->commit();
            $success = true;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get list of barangays in Talisay City for dropdown
$barangays = [
    'Biasong', 'Bulacao', 'Cansojong', 'Dumlog', 'Jaclupan', 
    'Lagtang', 'Lawaan I', 'Lawaan II', 'Lawaan III', 'Linao', 
    'Maghaway', 'Manipis', 'Mohon', 'Poblacion', 'Pooc', 
    'San Isidro', 'San Roque', 'Tabunok', 'Tangke', 'Tapul'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Bite Report - Talisay City Health</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bs-primary: #28a745;
            --bs-primary-rgb: 40, 167, 69;
            --bs-danger: #dc3545;
            --bs-danger-rgb: 220, 53, 69;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f8f9fa;
        }
        
        .header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo i {
            color: var(--bs-primary);
            font-size: 2rem;
            margin-right: 0.75rem;
        }
        
        .logo-text {
            line-height: 1.2;
        }
        
        .logo-title {
            font-weight: 700;
            font-size: 1.25rem;
            margin: 0;
        }
        
        .logo-subtitle {
            font-size: 0.875rem;
            margin: 0;
            color: #6c757d;
        }
        
        .emergency-banner {
            background-color: var(--bs-danger);
            color: white;
            padding: 0.75rem 0;
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
        
        .form-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 2rem;
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
        
        .required-field::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }
        
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        
        .urgency-high {
            background-color: rgba(var(--bs-danger-rgb), 0.1);
            border-left: 4px solid var(--bs-danger);
        }
        
        .footer {
            background-color: #343a40;
            color: white;
            padding: 1.5rem 0;
            margin-top: 3rem;
            font-size: 0.875rem;
        }
        
        .footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        
        .footer a:hover {
            color: white;
        }
        
        @media (max-width: 768px) {
            .form-header, .form-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo">
                    <i class="bi bi-heart-pulse"></i>
                    <div class="logo-text">
                        <h1 class="logo-title">Animal Bite Center</h1>
                        <p class="logo-subtitle">Talisay City Health Office</p>
                    </div>
                </div>
                <div class="d-none d-md-block">
                    <p class="mb-0"><strong>Emergency Hotline:</strong> (032) 123-4567</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Emergency Banner -->
    <div class="emergency-banner">
        <div class="container text-center">
            <strong>URGENT:</strong> If the bite is severe or bleeding heavily, seek immediate medical attention before completing this form.
        </div>
    </div>

    <div class="container my-4">
        <?php if ($success): ?>
        <!-- Success Message -->
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex">
                <div class="me-3">
                    <i class="bi bi-check-circle-fill fs-1"></i>
                </div>
                <div>
                    <h4 class="alert-heading">Report Submitted Successfully!</h4>
                    <p>Thank you for reporting the animal bite incident. Your report has been received and will be reviewed immediately by our healthcare team.</p>
                    <hr>
                    <p class="mb-0">Your reference number is: <strong><?php echo $reference_number; ?></strong></p>
                    <p>Please keep this reference number for future inquiries.</p>
                    <p><strong>What happens next?</strong></p>
                    <ul>
                        <li>A healthcare worker will contact you shortly via the phone number you provided</li>
                        <li>You may be asked to visit the nearest animal bite center for treatment</li>
                        <li>Continue to follow first aid measures while waiting</li>
                    </ul>
                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-success" onclick="window.location.reload()">Submit Another Report</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php else: ?>
        
        <!-- Introduction -->
        <div class="alert alert-info mb-4" role="alert">
            <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Online Animal Bite Reporting</h4>
            <p>This form allows you to report an animal bite incident directly to the Talisay City Health Office. Your report will be immediately forwarded to barangay health workers and the Animal Bite Center.</p>
            <hr>
            <p class="mb-0">Fields marked with <span class="text-danger">*</span> are required.</p>
        </div>
        
        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error!</strong> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Report Form -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
            <!-- Urgency Selection -->
            <div class="form-card mb-4">
                <div class="form-header bg-danger text-white">
                    <h2 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Urgency Assessment</h2>
                </div>
                <div class="form-body">
                    <p>Please select the urgency level of this bite incident:</p>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="urgency" id="urgencyHigh" value="High">
                        <label class="form-check-label" for="urgencyHigh">
                            <strong>High Urgency</strong> - Severe bite, deep wound, heavy bleeding, bite to face/head, or from a suspected rabid animal
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="urgency" id="urgencyNormal" value="Normal" checked>
                        <label class="form-check-label" for="urgencyNormal">
                            <strong>Normal</strong> - Minor bite with minimal bleeding, from a known/domestic animal
                        </label>
                    </div>
                    
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong>Important:</strong> If this is a high urgency case, please also call our emergency hotline at <strong>(032) 123-4567</strong> after submitting this form.
                    </div>
                </div>
            </div>
            
            <!-- Personal Information -->
            <div class="form-card mb-4">
                <div class="form-header">
                    <h2 class="mb-0"><i class="bi bi-person me-2"></i>Personal Information</h2>
                </div>
                <div class="form-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="firstName" class="form-label required-field">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="lastName" class="form-label required-field">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="middleName" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middleName" name="middleName">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="dateOfBirth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="" selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="contactNumber" class="form-label required-field">Contact Number</label>
                            <input type="tel" class="form-control" id="contactNumber" name="contactNumber" required>
                            <div class="form-text">Health workers will contact you at this number</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="address" class="form-label required-field">Street Address</label>
                            <input type="text" class="form-control" id="address" name="address" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="barangay" class="form-label required-field">Barangay</label>
                            <select class="form-select" id="barangay" name="barangay" required>
                                <option value="" selected>Select Barangay</option>
                                <?php foreach ($barangays as $brgy): ?>
                                <option value="<?php echo $brgy; ?>"><?php echo $brgy; ?></option>
                                <?php endforeach; ?>
                                <option value="Other">Other (Not in Talisay)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="city" class="form-label required-field">City/Municipality</label>
                            <input type="text" class="form-control" id="city" name="city" value="Talisay City" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="province" class="form-label">Province</label>
                            <input type="text" class="form-control" id="province" name="province" value="Cebu">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bite Incident Information -->
            <div class="form-card mb-4">
                <div class="form-header">
                    <h2 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Bite Incident Information</h2>
                </div>
                <div class="form-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="animalType" class="form-label required-field">Animal Type</label>
                            <select class="form-select" id="animalType" name="animalType" required>
                                <option value="" selected>Select Animal Type</option>
                                <option value="Dog">Dog</option>
                                <option value="Cat">Cat</option>
                                <option value="Rat">Rat</option>
                                <option value="Bat">Bat</option>
                                <option value="Monkey">Monkey</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6" id="otherAnimalContainer" style="display: none;">
                            <label for="otherAnimal" class="form-label">Specify Animal</label>
                            <input type="text" class="form-control" id="otherAnimal" name="otherAnimal">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="biteDate" class="form-label required-field">Date of Bite</label>
                            <input type="date" class="form-control" id="biteDate" name="biteDate" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="biteTime" class="form-label">Time of Bite (Approximate)</label>
                            <input type="time" class="form-control" id="biteTime" name="biteTime">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="biteLocation" class="form-label required-field">Location of Bite on Body</label>
                            <select class="form-select" id="biteLocation" name="biteLocation" required>
                                <option value="" selected>Select Bite Location</option>
                                <option value="Head/Face">Head/Face</option>
                                <option value="Neck">Neck</option>
                                <option value="Arm">Arm</option>
                                <option value="Hand">Hand</option>
                                <option value="Torso">Torso</option>
                                <option value="Leg">Leg</option>
                                <option value="Foot">Foot</option>
                                <option value="Multiple">Multiple Locations</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="biteDescription" class="form-label">Description of Incident</label>
                            <textarea class="form-control" id="biteDescription" name="biteDescription" rows="3" placeholder="Please describe how the bite occurred and any details about the animal (e.g., stray or owned, vaccinated or not, animal's behavior)"></textarea>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="firstAid" class="form-label">First Aid Applied</label>
                            <textarea class="form-control" id="firstAid" name="firstAid" rows="2" placeholder="Describe any first aid measures you've taken (e.g., washing with soap and water)"></textarea>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Previous Rabies Vaccination</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineYes" value="Yes">
                                <label class="form-check-label" for="rabiesVaccineYes">
                                    Yes, I have received rabies vaccination before
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineNo" value="No">
                                <label class="form-check-label" for="rabiesVaccineNo">
                                    No, I have not received rabies vaccination before
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineUnknown" value="Unknown" checked>
                                <label class="form-check-label" for="rabiesVaccineUnknown">
                                    I don't know/Not sure
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upload Photos -->
            <div class="form-card mb-4">
                <div class="form-header">
                    <h2 class="mb-0"><i class="bi bi-camera me-2"></i>Upload Photos</h2>
                </div>
                <div class="form-body">
                    <div class="mb-3">
                        <label for="biteImages" class="form-label">Upload Photos of the Bite (Optional)</label>
                        <input class="form-control" type="file" id="biteImages" name="biteImages[]" accept="image/*" multiple>
                        <div class="form-text">You can upload up to 3 photos. Clear images help healthcare professionals assess the bite better.</div>
                    </div>
                    
                    <div class="image-preview" id="imagePreview"></div>
                </div>
            </div>
            
            <!-- First Aid Information -->
            <div class="form-card mb-4">
                <div class="form-header">
                    <h2 class="mb-0"><i class="bi bi-bandaid me-2"></i>First Aid Information</h2>
                </div>
                <div class="form-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>Immediate First Aid Steps</h5>
                        <p>While waiting for healthcare professionals to contact you, please follow these first aid steps:</p>
                        <ol class="mb-0">
                            <li><strong>Wash the wound</strong> thoroughly with soap and running water for at least 15 minutes</li>
                            <li><strong>Apply antiseptic</strong> after washing (povidone-iodine, alcohol, or hydrogen peroxide)</li>
                            <li><strong>Do not bandage</strong> the wound unless bleeding is severe</li>
                            <li><strong>Do not apply</strong> herbs, lime, chili, or other substances to the wound</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <!-- Consent and Submission -->
            <div class="form-card">
                <div class="form-header">
                    <h2 class="mb-0"><i class="bi bi-check-circle me-2"></i>Consent and Submission</h2>
                </div>
                <div class="form-body">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="consentCheck" required>
                            <label class="form-check-label" for="consentCheck">
                                I consent to the collection and processing of my personal information for the purpose of animal bite reporting and treatment. I understand that a healthcare professional may contact me regarding this report.
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" role="alert">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i>Important Reminder</h5>
                        <p class="mb-0">After submitting this report, a healthcare worker will contact you as soon as possible. Please keep your phone available. If your condition worsens before you are contacted, please go to the nearest animal bite center or hospital immediately.</p>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="reset" class="btn btn-outline-secondary me-md-2">Clear Form</button>
                        <button type="submit" class="btn btn-primary btn-lg">Submit Report</button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Talisay City Health Office - Animal Bite Center</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">Emergency Hotline: (032) 123-4567 | <a href="mailto:animalbitecenter@talisaycity.gov.ph">animalbitecenter@talisaycity.gov.ph</a></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide "Other Animal" field based on selection
        document.getElementById('animalType').addEventListener('change', function() {
            const otherAnimalContainer = document.getElementById('otherAnimalContainer');
            if (this.value === 'Other') {
                otherAnimalContainer.style.display = 'block';
            } else {
                otherAnimalContainer.style.display = 'none';
            }
        });
        
        // Image preview functionality
        document.getElementById('biteImages').addEventListener('change', function() {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (this.files) {
                const maxFiles = 3;
                const files = Array.from(this.files).slice(0, maxFiles);
                
                files.forEach((file, index) => {
                    if (!file.type.match('image.*')) {
                        return;
                    }
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '×';
                        removeBtn.addEventListener('click', function() {
                            div.remove();
                        });
                        
                        div.appendChild(img);
                        div.appendChild(removeBtn);
                        preview.appendChild(div);
                    };
                    
                    reader.readAsDataURL(file);
                });
                
                if (this.files.length > maxFiles) {
                    alert(`You can only upload up to ${maxFiles} images. Only the first ${maxFiles} have been selected.`);
                }
            }
        });
        
        // Set today's date as default for bite date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('biteDate').value = today;
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(event) {
            const requiredFields = [
                'firstName', 'lastName', 'contactNumber', 
                'address', 'barangay', 'city', 'animalType', 
                'biteDate', 'biteLocation'
            ];
            
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!document.getElementById('consentCheck').checked) {
                document.getElementById('consentCheck').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
                alert('Please fill in all required fields and check the consent box.');
                window.scrollTo(0, 0);
            }
        });
        
        // Change form styling based on urgency selection
        document.querySelectorAll('input[name="urgency"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const formCards = document.querySelectorAll('.form-card');
                if (this.value === 'High') {
                    formCards.forEach(card => {
                        if (!card.querySelector('.form-header.bg-danger')) {
                            card.classList.add('urgency-high');
                        }
                    });
                } else {
                    formCards.forEach(card => {
                        card.classList.remove('urgency-high');
                    });
                }
            });
        });
    </script>
</body>
</html>
