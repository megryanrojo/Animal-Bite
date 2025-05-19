<?php
// Start output buffering to prevent "headers already sent" issues
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Debug information - uncomment to see what's happening
// echo "Session data: "; print_r($_SESSION); echo "<br>";
// echo "GET data: "; print_r($_GET); echo "<br>";

// Store the current URL for debugging
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
// echo "Current URL: " . $current_url . "<br>";

// Check if user is logged in - CRITICAL CHECK
if (!isset($_SESSION['staffId']) || empty($_SESSION['staffId'])) {
    // Log the redirect for debugging
    // error_log("Redirecting to login from edit_patient.php - No staffId in session");
    
    // Clear output buffer before redirect
    ob_end_clean();
    
    // Redirect to login page
    header("Location: ../login/staff_login.html");
    exit;
}

// Include database connection
require_once '../conn/conn.php';

// Get staff information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        // Staff ID exists in session but not in database
        // This could cause a redirect loop
        // error_log("Staff ID {$_SESSION['staffId']} exists in session but not in database");
        session_destroy();
        ob_end_clean();
        header("Location: ../login/staff_login.html");
        exit;
    }
} catch (PDOException $e) {
    $staff = ['firstName' => 'User', 'lastName' => ''];
    // error_log("Database error fetching staff: " . $e->getMessage());
}

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // error_log("No valid patient ID provided, redirecting to patients.php");
    ob_end_clean();
    header("Location: patients.php");
    exit;
}

$patientId = (int)$_GET['id'];

// Initialize variables
$success = false;
$error = '';
$patient = [];

// Get patient information
try {
    $patientStmt = $pdo->prepare("SELECT * FROM patients WHERE patientId = ?");
    $patientStmt->execute([$patientId]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        // error_log("Patient ID $patientId not found, redirecting to patients.php");
        ob_end_clean();
        header("Location: patients.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    // error_log("Database error fetching patient: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
    $lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $dateOfBirth = isset($_POST['dateOfBirth']) ? trim($_POST['dateOfBirth']) : '';
    $contactNumber = isset($_POST['contactNumber']) ? trim($_POST['contactNumber']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $province = isset($_POST['province']) ? trim($_POST['province']) : '';
    $medicalHistory = isset($_POST['medicalHistory']) ? trim($_POST['medicalHistory']) : '';
    $previousRabiesVaccine = isset($_POST['previousRabiesVaccine']) ? trim($_POST['previousRabiesVaccine']) : 'Unknown';
    $emergencyContact = isset($_POST['emergencyContact']) ? trim($_POST['emergencyContact']) : '';
    $emergencyPhone = isset($_POST['emergencyPhone']) ? trim($_POST['emergencyPhone']) : '';
    
    // Validate required fields
    $requiredFields = ['firstName', 'lastName', 'dateOfBirth'];
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
            // Update patient information
            $updateStmt = $pdo->prepare("
                UPDATE patients SET
                    firstName = ?,
                    lastName = ?,
                    gender = ?,
                    dateOfBirth = ?,
                    contactNumber = ?,
                    email = ?,
                    address = ?,
                    barangay = ?,
                    city = ?,
                    province = ?,
                    medicalHistory = ?,
                    emergencyContact = ?,
                    emergencyPhone = ?,
                    previousRabiesVaccine = ?
                WHERE patientId = ?
            ");
            
            $updateStmt->execute([
                $firstName,
                $lastName,
                $gender,
                $dateOfBirth,
                $contactNumber,
                $email,
                $address,
                $barangay,
                $city,
                $province,
                $medicalHistory,
                $emergencyContact,
                $emergencyPhone,
                $previousRabiesVaccine,
                $patientId
            ]);
            
            $success = true;
            
            // Refresh patient data
            $patientStmt = $pdo->prepare("SELECT * FROM patients WHERE patientId = ?");
            $patientStmt->execute([$patientId]);
            $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
            
            // IMPORTANT: Do not redirect after successful update
            // Just show the success message on the same page
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            // error_log("Database error updating patient: " . $e->getMessage());
        }
    }
}

// Now we can safely output the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Patient | Animal Bite Center</title>
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
  <?php $activePage = 'patients'; include 'navbar.php'; ?>

    <div class="patient-container">
        <!-- Success Alert -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Success!</strong> Patient information has been updated successfully.
            <div class="mt-2">
                <a href="view_patient.php?id=<?php echo $patientId; ?>" class="btn btn-sm btn-success me-2">
                    <i class="bi bi-eye me-1"></i> View Patient
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
                    <h2 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Patient</h2>
                </div>
                <p class="text-muted mb-0 mt-2">Fields marked with <span class="text-danger">*</span> are required</p>
            </div>
            
            <div class="form-body">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $patientId); ?>">
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-person"></i> Personal Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="firstName" class="form-label required-field">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($patient['firstName']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="lastName" class="form-label required-field">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($patient['lastName']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="dateOfBirth" class="form-label required-field">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($patient['dateOfBirth']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="" <?php echo empty($patient['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                                    <option value="Male" <?php echo $patient['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $patient['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $patient['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-telephone"></i> Contact Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contactNumber" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($patient['contactNumber'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergencyContact" class="form-label">Emergency Contact Person</label>
                                <input type="text" class="form-control" id="emergencyContact" name="emergencyContact" value="<?php echo htmlspecialchars($patient['emergencyContact'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergencyPhone" class="form-label">Emergency Contact Number</label>
                                <input type="tel" class="form-control" id="emergencyPhone" name="emergencyPhone" value="<?php echo htmlspecialchars($patient['emergencyPhone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-geo-alt"></i> Address Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="address" class="form-label">Street Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="barangay" class="form-label">Barangay</label>
                                <input type="text" class="form-control" id="barangay" name="barangay" value="<?php echo htmlspecialchars($patient['barangay'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="city" class="form-label">City/Municipality</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($patient['city'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($patient['province'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-clipboard2-pulse"></i> Medical Information</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="medicalHistory" class="form-label">Medical History</label>
                                <textarea class="form-control" id="medicalHistory" name="medicalHistory" rows="3"><?php echo htmlspecialchars($patient['medicalHistory'] ?? ''); ?></textarea>
                                <div class="form-text">Include any conditions that may affect treatment (e.g., immunocompromised, pregnancy).</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-syringe"></i> Vaccination Information</h3>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Previous Rabies Vaccination</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineYes" value="Yes" <?php echo ($patient['previousRabiesVaccine'] ?? '') === 'Yes' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rabiesVaccineYes">
                                        Yes, patient has received rabies vaccination before
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineNo" value="No" <?php echo ($patient['previousRabiesVaccine'] ?? '') === 'No' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rabiesVaccineNo">
                                        No, patient has not received rabies vaccination before
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="previousRabiesVaccine" id="rabiesVaccineUnknown" value="Unknown" <?php echo ($patient['previousRabiesVaccine'] ?? '') === 'Unknown' || empty($patient['previousRabiesVaccine']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rabiesVaccineUnknown">
                                        Unknown/Not sure
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="view_patient.php?id=<?php echo $patientId; ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-2"></i>Back to Patient
                            </a>
                            <a href="patients.php" class="btn btn-outline-secondary">
                                <i class="bi bi-people me-2"></i>All Patients
                            </a>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Reset Changes
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Changes
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
        document.querySelector('form').addEventListener('submit', function(event) {
            const requiredFields = ['firstName', 'lastName', 'dateOfBirth'];
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
<?php
// End output buffering and send output to browser
ob_end_flush();
?>
