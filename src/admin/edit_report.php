<?php
// Start output buffering to prevent "headers already sent" issues
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    ob_end_clean();
    header("Location: ../login/admin_login.php");
    exit;
}

// Include database connection
require_once '../conn/conn.php';

// Admin info is not required here; skip fetching profile

// Check if report ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    ob_end_clean();
    header("Location: view_reports.php");
    exit;
}

$reportId = (int)$_GET['id'];

// Initialize variables
$success = false;
$error = '';
$report = [];
$patient = [];

// Get report and patient information
try {
    $reportStmt = $pdo->prepare("
        SELECT r.*, p.firstName, p.lastName, p.dateOfBirth, p.gender, p.barangay, p.contactNumber, p.address
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportId = ?
    ");
    $reportStmt->execute([$reportId]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        ob_end_clean();
        header("Location: view_reports.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Server-side classification helper (same heuristic used elsewhere)
function classifyIncidentServer($pdo, $patientId, $input) {
    $biteLocation = strtolower(trim($input['bite_location'] ?? $input['biteLocation'] ?? ''));
    $multiple = !empty($input['multiple_bites']) || (!empty($input['multipleBites']));
    $ownership = strtolower($input['animal_ownership'] ?? $input['animalOwnership'] ?? '');
    $animalVaccinated = strtolower($input['animal_vaccinated'] ?? $input['animalVaccinated'] ?? '');
    $provoked = isset($input['provoked']) ? $input['provoked'] : null;

    $age = null;
    if ($patientId) {
        try {
            $stmt = $pdo->prepare("SELECT dateOfBirth FROM patients WHERE patientId = ?");
            $stmt->execute([$patientId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['dateOfBirth'])) {
                $dob = new DateTime($row['dateOfBirth']);
                $now = new DateTime();
                $age = (int)$now->diff($dob)->y;
            }
        } catch (PDOException $e) { }
    }

    $highRiskLocations = ['face','head','neck','scalp','eyes','mouth','lips','nose','ear','hands','fingers'];
    $isHighLocation = false;
    foreach ($highRiskLocations as $frag) {
        if ($frag !== '' && strpos($biteLocation, $frag) !== false) { $isHighLocation = true; break; }
    }

    $score = 0;
    if ($isHighLocation) $score += 3;
    if ($multiple) $score += 3;
    if ($ownership === 'stray' || $ownership === 'unknown') $score += 2;
    if ($animalVaccinated === 'no' || $animalVaccinated === 'unknown' || $animalVaccinated === '') $score += 2;
    if ($provoked === '0' || strtolower($provoked) === 'no') $score += 1;
    if ($age !== null && $age <= 5) $score += 2;

    if ($score >= 6) {
        $category = 'Category III'; $severity = 'High';
    } elseif ($score >= 3) {
        $category = 'Category II'; $severity = 'Moderate';
    } else {
        $category = 'Category I'; $severity = 'Low';
    }

    $rationale = "Auto-classified as $category (Severity: $severity) — score=$score;";
    $rationale .= $isHighLocation ? " location_high;" : " location_low;";
    $rationale .= $multiple ? " multiple_bites;" : " single_bite;";
    $rationale .= " ownership={$ownership}; vaccinated={$animalVaccinated};";
    if ($age !== null) $rationale .= " age={$age};";

    return ['biteType' => $category, 'severity' => $severity, 'rationale' => $rationale, 'score' => $score];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $biteDate = isset($_POST['bite_date']) ? trim($_POST['bite_date']) : '';
    $animalType = isset($_POST['animal_type']) ? trim($_POST['animal_type']) : '';
    $animalOtherType = isset($_POST['animal_other_type']) ? trim($_POST['animal_other_type']) : null;
    $animalOwnership = isset($_POST['animal_ownership']) ? trim($_POST['animal_ownership']) : '';
    $ownerName = isset($_POST['owner_name']) ? trim($_POST['owner_name']) : null;
    $ownerContact = isset($_POST['owner_contact']) ? trim($_POST['owner_contact']) : null;
    $animalStatus = isset($_POST['animal_status']) ? trim($_POST['animal_status']) : '';
    $animalVaccinated = isset($_POST['animal_vaccinated']) ? trim($_POST['animal_vaccinated']) : '';
    $provoked = isset($_POST['provoked']) ? trim($_POST['provoked']) : '';
    $multipleBites = isset($_POST['multiple_bites']) ? 1 : 0;
    $biteLocation = isset($_POST['bite_location']) ? trim($_POST['bite_location']) : '';
    $biteType = isset($_POST['bite_type']) ? trim($_POST['bite_type']) : '';
    $washWithSoap = isset($_POST['wash_with_soap']) ? 1 : 0;
    $rabiesVaccine = isset($_POST['rabies_vaccine']) ? 1 : 0;
    $rabiesVaccineDate = isset($_POST['rabies_vaccine_date']) ? trim($_POST['rabies_vaccine_date']) : null;
    $antiTetanus = isset($_POST['anti_tetanus']) ? 1 : 0;
    $antiTetanusDate = isset($_POST['anti_tetanus_date']) ? trim($_POST['anti_tetanus_date']) : null;
    $antibiotics = isset($_POST['antibiotics']) ? 1 : 0;
    $antibioticsDetails = isset($_POST['antibiotics_details']) ? trim($_POST['antibiotics_details']) : null;
    $referredToHospital = isset($_POST['referred_to_hospital']) ? 1 : 0;
    $hospitalName = isset($_POST['hospital_name']) ? trim($_POST['hospital_name']) : null;
    $followUpDate = isset($_POST['followup_date']) ? trim($_POST['followup_date']) : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate required fields
    $requiredFields = [
        'bite_date' => $biteDate,
        'animal_type' => $animalType,
        'bite_type' => $biteType,
        'bite_location' => $biteLocation
    ];
    $missingFields = [];
    
    // (classification helper is defined above to avoid duplicate function declarations)
    foreach ($requiredFields as $label => $value) {
        if (empty($value)) {
            $missingFields[] = ucfirst(str_replace('_', ' ', $label));
        }
    }
    
    if (!empty($missingFields)) {
        $error = 'Please fill in the following required fields: ' . implode(', ', $missingFields);
    } else {
        try {
            // Determine automated classification (unless override provided)
            $classification = classifyIncidentServer($pdo, $report['patientId'], $_POST);
            if (isset($_POST['category_override']) && !empty($_POST['category_override'])) {
                $overrideVal = $_POST['category_override'];
                $overrideReason = !empty($_POST['override_reason']) ? $_POST['override_reason'] : 'No reason provided';
                $classification['biteType'] = $overrideVal;
                $classification['rationale'] .= " [OVERRIDE by admin: {$overrideVal}; reason={$overrideReason}]";
            }

            // Append classification rationale to notes for audit trail
            $notes = trim(($notes ? $notes . "\n\n" : "") . "[Classification] " . $classification['rationale']);

            // Use classification['biteType'] as the biteType to store
            $biteType = $classification['biteType'];

            // Update report information
            $updateStmt = $pdo->prepare("
                UPDATE reports SET
                    biteDate = ?,
                    animalType = ?,
                    animalOtherType = ?,
                    animalOwnership = ?,
                    ownerName = ?,
                    ownerContact = ?,
                    animalStatus = ?,
                    animalVaccinated = ?,
                    provoked = ?,
                    multipleBites = ?,
                    biteLocation = ?,
                    biteType = ?,
                    washWithSoap = ?,
                    rabiesVaccine = ?,
                    rabiesVaccineDate = ?,
                    antiTetanus = ?,
                    antiTetanusDate = ?,
                    antibiotics = ?,
                    antibioticsDetails = ?,
                    referredToHospital = ?,
                    hospitalName = ?,
                    followUpDate = ?,
                    status = ?,
                    notes = ?
                WHERE reportId = ?
            ");
            
            $updateStmt->execute([
                $biteDate,
                $animalType,
                $animalOtherType,
                $animalOwnership,
                $ownerName,
                $ownerContact,
                $animalStatus,
                $animalVaccinated,
                $provoked,
                $multipleBites,
                $biteLocation,
                $biteType,
                $washWithSoap,
                $rabiesVaccine,
                $rabiesVaccineDate,
                $antiTetanus,
                $antiTetanusDate,
                $antibiotics,
                $antibioticsDetails,
                $referredToHospital,
                $hospitalName,
                $followUpDate,
                $status,
                $notes,
                $reportId
            ]);
            
            $success = true;
            
            // Refresh report data
            $reportStmt = $pdo->prepare("
                SELECT r.*, p.firstName, p.lastName, p.dateOfBirth, p.gender, p.barangay, p.contactNumber, p.address
                FROM reports r
                JOIN patients p ON r.patientId = p.patientId
                WHERE r.reportId = ?
            ");
            $reportStmt->execute([$reportId]);
            $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
            
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
    <title>Edit Report | Animal Bite Center</title>
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
            .report-container {
                padding: 1rem;
            }
            
            .form-header, .form-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php $activePage = 'reports'; include 'includes/navbar.php'; ?>

    <div class="report-container">
        <!-- Success Alert -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Success!</strong> Report has been updated successfully.
            <div class="mt-2">
                <a href="view_report.php?id=<?php echo $reportId; ?>" class="btn btn-sm btn-success me-2">
                    <i class="bi bi-eye me-1"></i> View Report
                </a>
                <a href="view_reports.php" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-text me-1"></i> View All Reports
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
                    <h2 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Report</h2>
                </div>
                <p class="text-muted mb-0 mt-2">Fields marked with <span class="text-danger">*</span> are required</p>
            </div>
            
            <div class="form-body">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $reportId); ?>">
                    <!-- Patient Information Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-person"></i> Patient Information</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($report['contactNumber'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Barangay</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($report['barangay']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($report['address'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Animal Bite Details Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-exclamation-triangle"></i> Animal Bite Details</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="bite_date" class="form-label required-field">Date of Bite</label>
                                <input type="date" class="form-control" id="bite_date" name="bite_date" value="<?php echo htmlspecialchars($report['biteDate']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="animal_type" class="form-label required-field">Animal Type</label>
                                <select class="form-select" id="animal_type" name="animal_type" required>
                                    <option value="">-- Select Animal --</option>
                                    <option value="Dog" <?php echo $report['animalType'] === 'Dog' ? 'selected' : ''; ?>>Dog</option>
                                    <option value="Cat" <?php echo $report['animalType'] === 'Cat' ? 'selected' : ''; ?>>Cat</option>
                                    <option value="Rat" <?php echo $report['animalType'] === 'Rat' ? 'selected' : ''; ?>>Rat</option>
                                    <option value="Other" <?php echo $report['animalType'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6" id="otherAnimalSection" style="display: <?php echo $report['animalType'] === 'Other' ? 'block' : 'none'; ?>;">
                                <label for="animal_other_type" class="form-label required-field">Specify Animal</label>
                                <input type="text" class="form-control" id="animal_other_type" name="animal_other_type" value="<?php echo htmlspecialchars($report['animalOtherType'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="animal_ownership" class="form-label required-field">Animal Ownership</label>
                                <select class="form-select" id="animal_ownership" name="animal_ownership" required>
                                    <option value="">-- Select Ownership --</option>
                                    <option value="Stray" <?php echo $report['animalOwnership'] === 'Stray' ? 'selected' : ''; ?>>Stray</option>
                                    <option value="Owned by patient" <?php echo $report['animalOwnership'] === 'Owned by patient' ? 'selected' : ''; ?>>Owned by patient</option>
                                    <option value="Owned by neighbor" <?php echo $report['animalOwnership'] === 'Owned by neighbor' ? 'selected' : ''; ?>>Owned by neighbor</option>
                                    <option value="Owned by unknown person" <?php echo $report['animalOwnership'] === 'Owned by unknown person' ? 'selected' : ''; ?>>Owned by unknown person</option>
                                    <option value="Unknown" <?php echo $report['animalOwnership'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6" id="ownerSection" style="display: <?php echo in_array($report['animalOwnership'], ['Owned by patient', 'Owned by neighbor', 'Owned by unknown person']) ? 'block' : 'none'; ?>;">
                                <label for="owner_name" class="form-label">Owner's Name</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($report['ownerName'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6" id="ownerContactSection" style="display: <?php echo in_array($report['animalOwnership'], ['Owned by patient', 'Owned by neighbor', 'Owned by unknown person']) ? 'block' : 'none'; ?>;">
                                <label for="owner_contact" class="form-label">Owner's Contact</label>
                                <input type="text" class="form-control" id="owner_contact" name="owner_contact" value="<?php echo htmlspecialchars($report['ownerContact'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="animal_status" class="form-label required-field">Animal Status</label>
                                <select class="form-select" id="animal_status" name="animal_status" required>
                                    <option value="Alive" <?php echo $report['animalStatus'] === 'Alive' ? 'selected' : ''; ?>>Alive</option>
                                    <option value="Dead" <?php echo $report['animalStatus'] === 'Dead' ? 'selected' : ''; ?>>Dead</option>
                                    <option value="Unknown" <?php echo $report['animalStatus'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="animal_vaccinated" class="form-label required-field">Animal Vaccinated Against Rabies</label>
                                <select class="form-select" id="animal_vaccinated" name="animal_vaccinated" required>
                                    <option value="Yes" <?php echo $report['animalVaccinated'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?php echo $report['animalVaccinated'] === 'No' ? 'selected' : ''; ?>>No</option>
                                    <option value="Unknown" <?php echo $report['animalVaccinated'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="provoked" class="form-label required-field">Was the bite provoked?</label>
                                <select class="form-select" id="provoked" name="provoked" required>
                                    <option value="Yes" <?php echo $report['provoked'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?php echo $report['provoked'] === 'No' ? 'selected' : ''; ?>>No</option>
                                    <option value="Unknown" <?php echo $report['provoked'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="multiple_bites" name="multiple_bites" <?php echo $report['multipleBites'] ? 'checked' : ''; ?>>
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
                                <input type="text" class="form-control" id="bite_location" name="bite_location" value="<?php echo htmlspecialchars($report['biteLocation']); ?>" required>
                                <div class="form-text">Specify the body part(s) where the bite occurred (e.g., right hand, left leg, face)</div>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="bite_type" class="form-label required-field">Bite Category</label>
                                <select class="form-select" id="bite_type" name="bite_type" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Category I" <?php echo $report['biteType'] === 'Category I' ? 'selected' : ''; ?>>Category I</option>
                                    <option value="Category II" <?php echo $report['biteType'] === 'Category II' ? 'selected' : ''; ?>>Category II</option>
                                    <option value="Category III" <?php echo $report['biteType'] === 'Category III' ? 'selected' : ''; ?>>Category III</option>
                                </select>
                                
                                                                <div id="autoClassification" style="margin-top:10px; padding:10px; border-radius:6px; background:#f8fafc; border:1px solid #e6eef9; display:none;">
                                                                    <strong>Auto-classification:</strong>
                                                                    <div id="autoCategory" style="margin-top:6px; font-size:1.05rem;"></div>
                                                                    <div id="autoRationale" style="margin-top:6px; font-size:0.9rem; color:#475569;"></div>
                                                                </div>

                                                                <div style="margin-top:10px;">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" id="overrideCheckbox">
                                                                        <label class="form-check-label" for="overrideCheckbox">Override auto-classification</label>
                                                                    </div>
                                                                </div>

                                                                <div id="overrideSection" style="display:none; margin-top:10px;">
                                                                    <label class="form-label">Manual Category (if overriding)</label>
                                                                    <select class="form-select" id="category_override" name="category_override">
                                                                        <option value="">-- Select Override Category --</option>
                                                                        <option value="Category I">Category I</option>
                                                                        <option value="Category II">Category II</option>
                                                                        <option value="Category III">Category III</option>
                                                                    </select>
                                                                    <label class="form-label" style="margin-top:8px;">Override Reason</label>
                                                                    <textarea class="form-control" id="override_reason" name="override_reason" rows="2" placeholder="Explain why you are overriding the automated classification (required when overriding)"></textarea>
                                                                </div>

                                                                <div class="category-info mt-3">
                                    <h5>Bite Categories:</h5>
                                    <p><strong>Category I:</strong> Touching or feeding of animals, licks on intact skin</p>
                                    <p><strong>Category II:</strong> Nibbling of uncovered skin, minor scratches or abrasions without bleeding</p>
                                    <p><strong>Category III:</strong> Single or multiple transdermal bites or scratches, contamination of mucous membrane or broken skin with saliva from animal licks, exposures due to direct contact with bats</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Treatment Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-bandaid"></i> Treatment Information</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="wash_with_soap" name="wash_with_soap" <?php echo $report['washWithSoap'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="wash_with_soap">
                                        Wound washed with soap and water
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rabies_vaccine" name="rabies_vaccine" <?php echo $report['rabiesVaccine'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rabies_vaccine">
                                        Rabies vaccine administered
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6" id="rabiesVaccineDateSection" style="display: <?php echo $report['rabiesVaccine'] ? 'block' : 'none'; ?>;">
                                <label for="rabies_vaccine_date" class="form-label">Date of Rabies Vaccine</label>
                                <input type="date" class="form-control" id="rabies_vaccine_date" name="rabies_vaccine_date" value="<?php echo htmlspecialchars($report['rabiesVaccineDate'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="anti_tetanus" name="anti_tetanus" <?php echo $report['antiTetanus'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="anti_tetanus">
                                        Anti-tetanus administered
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6" id="antiTetanusDateSection" style="display: <?php echo $report['antiTetanus'] ? 'block' : 'none'; ?>;">
                                <label for="anti_tetanus_date" class="form-label">Date of Anti-tetanus</label>
                                <input type="date" class="form-control" id="anti_tetanus_date" name="anti_tetanus_date" value="<?php echo htmlspecialchars($report['antiTetanusDate'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="antibiotics" name="antibiotics" <?php echo $report['antibiotics'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="antibiotics">
                                        Antibiotics prescribed
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6" id="antibioticsDetailsSection" style="display: <?php echo $report['antibiotics'] ? 'block' : 'none'; ?>;">
                                <label for="antibiotics_details" class="form-label">Antibiotics Details</label>
                                <input type="text" class="form-control" id="antibiotics_details" name="antibiotics_details" value="<?php echo htmlspecialchars($report['antibioticsDetails'] ?? ''); ?>" placeholder="Type, dosage, etc.">
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="referred_to_hospital" name="referred_to_hospital" <?php echo $report['referredToHospital'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="referred_to_hospital">
                                        Referred to hospital
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6" id="hospitalNameSection" style="display: <?php echo $report['referredToHospital'] ? 'block' : 'none'; ?>;">
                                <label for="hospital_name" class="form-label">Hospital Name</label>
                                <input type="text" class="form-control" id="hospital_name" name="hospital_name" value="<?php echo htmlspecialchars($report['hospitalName'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="followup_date" class="form-label">Follow-up Date</label>
                                <input type="date" class="form-control" id="followup_date" name="followup_date" value="<?php echo htmlspecialchars($report['followUpDate'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="status" class="form-label required-field">Case Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $report['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $report['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="referred" <?php echo $report['status'] === 'referred' ? 'selected' : ''; ?>>Referred</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="bi bi-journal"></i> Additional Notes</h3>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                            <div class="form-text">Any additional information or observations about the case</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="view_report.php?id=<?php echo $reportId; ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-2"></i>Back to Report
                            </a>
                            <a href="view_reports.php" class="btn btn-outline-secondary">
                                <i class="bi bi-file-text me-2"></i>All Reports
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
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PATIENT_ID = <?php echo json_encode($report['patientId'] ?? null); ?>;

        // Simple form validation and override enforcement
        document.querySelector('form').addEventListener('submit', function(event) {
            const requiredFields = ['bite_date', 'animal_type', 'bite_type', 'bite_location'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input || !input.value || !input.value.toString().trim()) {
                    if (input) input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    if (input) input.classList.remove('is-invalid');
                }
            });
            
            const overrideCheckbox = document.getElementById('overrideCheckbox');
            const overrideReason = document.getElementById('override_reason');
            if (overrideCheckbox && overrideCheckbox.checked) {
                if (!overrideReason || !overrideReason.value.trim()) {
                    alert('Please provide a reason for overriding the automated classification.');
                    event.preventDefault();
                    return;
                }
            }

            if (!isValid) {
                event.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sections based on checkbox states
            const rabiesVaccineCheckbox = document.getElementById('rabies_vaccine');
            const rabiesVaccineDateSection = document.getElementById('rabiesVaccineDateSection');
            const antiTetanusCheckbox = document.getElementById('anti_tetanus');
            const antiTetanusDateSection = document.getElementById('antiTetanusDateSection');
            const antibioticsCheckbox = document.getElementById('antibiotics');
            const antibioticsDetailsSection = document.getElementById('antibioticsDetailsSection');
            const referredToHospitalCheckbox = document.getElementById('referred_to_hospital');
            const hospitalNameSection = document.getElementById('hospitalNameSection');

            function toggleSection(checkbox, section) {
                if (!checkbox || !section) return;
                section.style.display = checkbox.checked ? 'block' : 'none';
            }

            if (rabiesVaccineCheckbox && rabiesVaccineDateSection) rabiesVaccineCheckbox.addEventListener('change', () => toggleSection(rabiesVaccineCheckbox, rabiesVaccineDateSection));
            if (antiTetanusCheckbox && antiTetanusDateSection) antiTetanusCheckbox.addEventListener('change', () => toggleSection(antiTetanusCheckbox, antiTetanusDateSection));
            if (antibioticsCheckbox && antibioticsDetailsSection) antibioticsCheckbox.addEventListener('change', () => toggleSection(antibioticsCheckbox, antibioticsDetailsSection));
            if (referredToHospitalCheckbox && hospitalNameSection) referredToHospitalCheckbox.addEventListener('change', () => toggleSection(referredToHospitalCheckbox, hospitalNameSection));

            // Classification UI elements
            const autoDiv = document.getElementById('autoClassification');
            const autoCategory = document.getElementById('autoCategory');
            const autoRationale = document.getElementById('autoRationale');
            const overrideCheckbox = document.getElementById('overrideCheckbox');
            const overrideSection = document.getElementById('overrideSection');
            const biteLocationInput = document.getElementById('bite_location');
            const animalOwnershipInput = document.getElementById('animal_ownership');
            const animalVaccinatedInput = document.getElementById('animal_vaccinated');
            const provokedInput = document.getElementById('provoked');
            const multipleBitesInput = document.getElementById('multiple_bites');
            const biteTypeSelect = document.getElementById('bite_type');

            let classifyTimer = null;
            function scheduleClassification() {
                if (classifyTimer) clearTimeout(classifyTimer);
                classifyTimer = setTimeout(runClassification, 450);
            }

            function runClassification() {
                if (!biteLocationInput) return;
                const payload = new URLSearchParams();
                payload.append('patient_id', PATIENT_ID);
                payload.append('bite_location', biteLocationInput.value || '');
                if (animalOwnershipInput) payload.append('animal_ownership', animalOwnershipInput.value || '');
                if (animalVaccinatedInput) payload.append('animal_vaccinated', animalVaccinatedInput.value || '');
                if (provokedInput) payload.append('provoked', provokedInput.value || '');
                if (multipleBitesInput) payload.append('multiple_bites', multipleBitesInput.checked ? '1' : '0');

                fetch('classify_incident.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload.toString()
                }).then(r => r.json()).then(data => {
                    if (!data) return;
                    // Accept both shapes: {biteType, rationale, score} or {success:true,data:{...}}
                    const res = data.data ? data.data : data;
                    if (!res) return;
                    autoDiv.style.display = 'block';
                    autoCategory.textContent = res.biteType + (res.severity ? (' — ' + res.severity) : '');
                    autoRationale.textContent = res.rationale || ('Score: ' + (res.score ?? 'N/A'));

                    if (overrideCheckbox && !overrideCheckbox.checked) {
                        if (biteTypeSelect) biteTypeSelect.value = res.biteType;
                    }
                }).catch(err => {
                    console.error('Classification error', err);
                });
            }

            // Wire inputs to classification
            if (biteLocationInput) biteLocationInput.addEventListener('input', scheduleClassification);
            if (animalOwnershipInput) animalOwnershipInput.addEventListener('change', scheduleClassification);
            if (animalVaccinatedInput) animalVaccinatedInput.addEventListener('change', scheduleClassification);
            if (provokedInput) provokedInput.addEventListener('change', scheduleClassification);
            if (multipleBitesInput) multipleBitesInput.addEventListener('change', scheduleClassification);

            if (overrideCheckbox) {
                overrideCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        overrideSection.style.display = 'block';
                    } else {
                        overrideSection.style.display = 'none';
                        const overrideReason = document.getElementById('override_reason');
                        if (overrideReason) overrideReason.value = '';
                    }
                });
            }

            // Initial run
            scheduleClassification();
        });
    </script>
</body>
</html>
<?php
// End output buffering and send output to browser
ob_end_flush();
?> 