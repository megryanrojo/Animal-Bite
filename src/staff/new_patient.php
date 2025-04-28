<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login/staff_login.html");
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

// Initialize variables
$success = false;
$error = '';
$formData = [
    'firstName' => '',
    'lastName' => '',
    'middleName' => '',
    'dateOfBirth' => '',
    'gender' => '',
    'contactNumber' => '',
    'email' => '',
    'address' => '',
    'barangay' => '',
    'city' => '',
    'province' => '',
    'emergencyContact' => '',
    'emergencyPhone' => '',
    'allergies' => '',
    'medicalHistory' => '',
    'previousRabiesVaccine' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    foreach ($formData as $key => $value) {
        $formData[$key] = isset($_POST[$key]) ? trim($_POST[$key]) : '';
    }
    
    // Validate required fields
    $requiredFields = ['firstName', 'lastName', 'dateOfBirth', 'gender', 'contactNumber', 'address', 'barangay', 'city'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            $missingFields[] = ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $field));
        }
    }
    
    if (!empty($missingFields)) {
        $error = 'Please fill in the following required fields: ' . implode(', ', $missingFields);
    } else {
        try {
            // Check if patient already exists
            $checkStmt = $pdo->prepare("SELECT patientId FROM patients WHERE firstName = ? AND lastName = ? AND dateOfBirth = ?");
            $checkStmt->execute([$formData['firstName'], $formData['lastName'], $formData['dateOfBirth']]);
            $existingPatient = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPatient) {
                $error = 'A patient with this name and date of birth already exists. Please check existing records.';
            } else {
                // Insert new patient
                $insertStmt = $pdo->prepare("
                    INSERT INTO patients (
                        firstName, lastName, middleName, dateOfBirth, gender, 
                        contactNumber, email, address, barangay, city, province,
                        emergencyContact, emergencyPhone, allergies, medicalHistory, previousRabiesVaccine,
                        registeredBy, registrationDate
                    ) VALUES (
                        ?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, NOW()
                    )
                ");
                
                $insertStmt->execute([
                    $formData['firstName'], 
                    $formData['lastName'], 
                    $formData['middleName'], 
                    $formData['dateOfBirth'], 
                    $formData['gender'],
                    $formData['contactNumber'], 
                    $formData['email'], 
                    $formData['address'], 
                    $formData['barangay'], 
                    $formData['city'], 
                    $formData['province'],
                    $formData['emergencyContact'], 
                    $formData['emergencyPhone'], 
                    $formData['allergies'], 
                    $formData['medicalHistory'], 
                    $formData['previousRabiesVaccine'],
                    $_SESSION['staffId']
                ]);
                
                $patientId = $pdo->lastInsertId();
                $success = true;
                
                // Reset form data after successful submission
                foreach ($formData as $key => $value) {
                    $formData[$key] = '';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register New Patient | Animal Bite Center</title>
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
        
        .patient-container {
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
        
        .required-field::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }
        
        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .patient-container {
                padding: 1rem;
            }
            
            .form-header, .form-body {
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
                        <a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-text me-1"></i> Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="patients.php"><i class="bi bi-people me-1"></i> Patients</a>
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

    <div class="patient-container">
        <!-- Success Alert -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Success!</strong> Patient has been registered successfully.
            <div class="mt-2">
                <a href="new_report.php?patient_id=<?php echo $patientId; ?>" class="btn btn-sm btn-success me-2">
                    <i class="bi bi-file-earmark-plus me-1"></i> Create Bite Report
                </a>
                <a href="patients.php" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-people me-1"></i> View All Patients
                </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error!</strong> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="form-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0"><i class="bi bi-person-plus me-2"></i>Register New Patient</h2>
                </div>
                <p class="text-muted mb-0 mt-2">Fields marked with <span class="text-danger">*</span> are required</p>
            </div>
            
            <div class="form-body">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-person"></i> Personal Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="firstName" class="form-label required-field">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($formData['firstName']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="lastName" class="form-label required-field">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($formData['lastName']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="middleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middleName" name="middleName" value="<?php echo htmlspecialchars($formData['middleName']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="dateOfBirth" class="form-label required-field">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($formData['dateOfBirth']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="gender" class="form-label required-field">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="" <?php echo empty($formData['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                                    <option value="Male" <?php echo $formData['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $formData['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $formData['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-telephone"></i> Contact Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contactNumber" class="form-label required-field">Contact Number</label>
                                <input type="tel" class="form-control" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($formData['contactNumber']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergencyContact" class="form-label">Emergency Contact Person</label>
                                <input type="text" class="form-control" id="emergencyContact" name="emergencyContact" value="<?php echo htmlspecialchars($formData['emergencyContact']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergencyPhone" class="form-label">Emergency Contact Number</label>
                                <input type="tel" class="form-control" id="emergencyPhone" name="emergencyPhone" value="<?php echo htmlspecialchars($formData['emergencyPhone']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Information Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-geo-alt"></i> Address Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="address" class="form-label required-field">Street Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($formData['address']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="barangay" class="form-label required-field">Barangay</label>
                                <input type="text" class="form-control" id="barangay" name="barangay" value="<?php echo htmlspecialchars($formData['barangay']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="city" class="form-label required-field">City/Municipality</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($formData['city']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($formData['province']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Information Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-clipboard2-pulse"></i> Medical Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="allergies" class="form-label">Known Allergies</label>
                                <textarea class="form-control" id="allergies" name="allergies" rows="2"><?php echo htmlspecialchars($formData['allergies']); ?></textarea>
                                <div class="form-text">List any known allergies, especially to vaccines or medications.</div>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="medicalHistory" class="form-label">Relevant Medical History</label>
                                <textarea class="form-control" id="medicalHistory" name="medicalHistory" rows="2"><?php echo htmlspecialchars($formData['medicalHistory']); ?></textarea>
                                <div class="form-text">Include any conditions that may affect treatment (e.g., immunocompromised, pregnancy).</div>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label">Previous Rabies Vaccination</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineYes" value="Yes" <?php echo $formData['previousRabiesVaccine'] === 'Yes' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rabiesVaccineYes">
                                        Yes, patient has received rabies vaccination before
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineNo" value="No" <?php echo $formData['previousRabiesVaccine'] === 'No' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rabiesVaccineNo">
                                        No, patient has not received rabies vaccination before
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineUnknown" value="Unknown" <?php echo $formData['previousRabiesVaccine'] === 'Unknown' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rabiesVaccineUnknown">
                                        Unknown/Not sure
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="patients.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-2"></i>Back to Patients
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house-door me-2"></i>Dashboard
                            </a>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-x-circle me-2"></i>Clear Form
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>Register Patient
                            </button>
                        </div>
                    </div>
                </form>
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
    <script>
        // Calculate age based on date of birth
        document.getElementById('dateOfBirth').addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            // You can display the age somewhere if needed
            // document.getElementById('age').textContent = age + ' years';
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(event) {
            const requiredFields = ['firstName', 'lastName', 'dateOfBirth', 'gender', 'contactNumber', 'address', 'barangay', 'city'];
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
            
            if (!isValid) {
                event.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>
