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
require_once 'includes/logging_helper.php';

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

// Server-side classification helper
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
    
    $requiredFields = [
        'bite_date' => $biteDate,
        'animal_type' => $animalType,
        'bite_type' => $biteType,
        'bite_location' => $biteLocation
    ];
    $missingFields = [];
    
    foreach ($requiredFields as $label => $value) {
        if (empty($value)) {
            $missingFields[] = ucfirst(str_replace('_', ' ', $label));
        }
    }
    
    if (!empty($missingFields)) {
        $error = 'Please fill in the following required fields: ' . implode(', ', $missingFields);
    } else {
        try {
            $classification = classifyIncidentServer($pdo, $report['patientId'], $_POST);
            if (isset($_POST['category_override']) && !empty($_POST['category_override'])) {
                $overrideVal = $_POST['category_override'];
                $overrideReason = !empty($_POST['override_reason']) ? $_POST['override_reason'] : 'No reason provided';
                $classification['biteType'] = $overrideVal;
                $classification['rationale'] .= " [OVERRIDE by admin: {$overrideVal}; reason={$overrideReason}]";
            }

            $notes = trim(($notes ? $notes . "\n\n" : "") . "[Classification] " . $classification['rationale']);
            $biteType = $classification['biteType'];

            $updateStmt = $pdo->prepare("
                UPDATE reports SET
                    biteDate = ?, animalType = ?, animalOtherType = ?, animalOwnership = ?,
                    ownerName = ?, ownerContact = ?, animalStatus = ?, animalVaccinated = ?,
                    provoked = ?, multipleBites = ?, biteLocation = ?, biteType = ?,
                    washWithSoap = ?, rabiesVaccine = ?, rabiesVaccineDate = ?,
                    antiTetanus = ?, antiTetanusDate = ?, antibiotics = ?, antibioticsDetails = ?,
                    referredToHospital = ?, hospitalName = ?, followUpDate = ?, status = ?, notes = ?
                WHERE reportId = ?
            ");
            
            $updateStmt->execute([
                $biteDate, $animalType, $animalOtherType, $animalOwnership,
                $ownerName, $ownerContact, $animalStatus, $animalVaccinated,
                $provoked, $multipleBites, $biteLocation, $biteType,
                $washWithSoap, $rabiesVaccine, $rabiesVaccineDate,
                $antiTetanus, $antiTetanusDate, $antibiotics, $antibioticsDetails,
                $referredToHospital, $hospitalName, $followUpDate, $status, $notes,
                $reportId
            ]);

            $success = true;
            logActivity($pdo, 'UPDATE', 'report', $reportId, null, "Updated report details");
            
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-light: #38bdf8;
            --primary-dark: #0284c7;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 14px;
            background: var(--bg-color);
            color: var(--text-primary);
        }

        .main-content {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .page-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 20px 32px;
        }

        /* Fixed header layout - simple left-aligned with back button and title */
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: var(--bg-color);
            color: var(--primary);
            border-color: var(--primary-light);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .header-title h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }

        .header-title p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 2px 0 0 0;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            padding: 32px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Patient Card */
        .patient-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .patient-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .patient-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .patient-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .form-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-card-header i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .form-card-header h2 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .form-card-body {
            padding: 24px;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-grid.single {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .form-group label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: white;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Checkbox Group */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .checkbox-group label {
            font-size: 0.9rem;
            margin: 0;
            cursor: pointer;
        }

        /* AI Classification Box */
        .ai-classification {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 18px;
            margin-top: 16px;
            display: none;
        }

        .ai-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .ai-header i {
            color: var(--primary);
        }

        .ai-header span {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.9rem;
        }

        .ai-result {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .ai-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .ai-item {
            background: white;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .ai-item-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }

        .ai-item-value {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .risk-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .risk-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Override Section */
        .override-section {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 18px;
            margin-top: 16px;
            display: none;
        }

        .override-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: #b45309;
            font-weight: 600;
        }

        /* Category Info */
        .category-info {
            background: var(--bg-color);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .category-info h4 {
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0 0 10px 0;
        }

        .category-info p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 6px 0;
        }

        .category-info strong {
            color: var(--text-primary);
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            justify-content: flex-end; /* Changed to flex-end since we removed left back button */
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header { padding: 16px 20px; }
            .header-content { flex-direction: column; gap: 16px; align-items: flex-start; }
            .content-area { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .patient-card { flex-direction: column; text-align: center; }
            .form-actions { flex-direction: column; gap: 12px; justify-content: center; } /* Adjusted for mobile */
            .btn-group { width: 100%; }
            .btn-group .btn { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <!-- Single back button on left, title next to it, removed duplicate view button -->
                <a href="view_reports.php" class="back-btn" title="Back to Reports">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div class="header-title">
                    <div class="header-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h1>Edit Report #<?php echo $reportId; ?></h1>
                        <p>Modify animal bite incident details</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <!-- Alerts -->
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <span>Report updated successfully!</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <!-- Patient Card -->
            <div class="patient-card">
                <div class="patient-avatar">
                    <i class="bi bi-person"></i>
                </div>
                <div class="patient-info">
                    <h3><?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></h3>
                    <div class="patient-meta">
                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($report['contactNumber'] ?? 'No contact'); ?> &nbsp;|&nbsp;
                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($report['barangay']); ?>
                        <?php if (!empty($report['address'])): ?>, <?php echo htmlspecialchars($report['address']); ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $reportId); ?>">
                
                <!-- Animal Bite Details -->
                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-exclamation-triangle"></i>
                        <h2>Animal Bite Details</h2>
                    </div>
                    <div class="form-card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Date of Bite</label>
                                <input type="date" name="bite_date" value="<?php echo htmlspecialchars($report['biteDate'] && $report['biteDate'] !== '0000-00-00' ? $report['biteDate'] : date('Y-m-d')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Animal Type</label>
                                <select name="animal_type" id="animal_type" required>
                                    <option value="">-- Select Animal --</option>
                                    <option value="Dog" <?php echo $report['animalType'] === 'Dog' ? 'selected' : ''; ?>>Dog</option>
                                    <option value="Cat" <?php echo $report['animalType'] === 'Cat' ? 'selected' : ''; ?>>Cat</option>
                                    <option value="Rat" <?php echo $report['animalType'] === 'Rat' ? 'selected' : ''; ?>>Rat</option>
                                    <option value="Other" <?php echo $report['animalType'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group" id="otherAnimalSection" style="display: <?php echo $report['animalType'] === 'Other' ? 'flex' : 'none'; ?>;">
                                <label class="required">Specify Animal</label>
                                <input type="text" name="animal_other_type" value="<?php echo htmlspecialchars($report['animalOtherType'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="required">Animal Ownership</label>
                                <select name="animal_ownership" id="animal_ownership" required>
                                    <option value="">-- Select Ownership --</option>
                                    <option value="Stray" <?php echo $report['animalOwnership'] === 'Stray' ? 'selected' : ''; ?>>Stray</option>
                                    <option value="Owned by patient" <?php echo $report['animalOwnership'] === 'Owned by patient' ? 'selected' : ''; ?>>Owned by patient</option>
                                    <option value="Owned by neighbor" <?php echo $report['animalOwnership'] === 'Owned by neighbor' ? 'selected' : ''; ?>>Owned by neighbor</option>
                                    <option value="Owned by unknown person" <?php echo $report['animalOwnership'] === 'Owned by unknown person' ? 'selected' : ''; ?>>Owned by unknown person</option>
                                    <option value="Unknown" <?php echo $report['animalOwnership'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            <div class="form-group" id="ownerSection" style="display: <?php echo in_array($report['animalOwnership'], ['Owned by neighbor', 'Owned by unknown person']) ? 'flex' : 'none'; ?>;">
                                <label>Owner's Name</label>
                                <input type="text" name="owner_name" value="<?php echo htmlspecialchars($report['ownerName'] ?? ''); ?>">
                            </div>
                            <div class="form-group" id="ownerContactSection" style="display: <?php echo in_array($report['animalOwnership'], ['Owned by neighbor', 'Owned by unknown person']) ? 'flex' : 'none'; ?>;">
                                <label>Owner's Contact</label>
                                <input type="text" name="owner_contact" value="<?php echo htmlspecialchars($report['ownerContact'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Animal Status & Behavior -->
                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-activity"></i>
                        <h2>Animal Status & Behavior</h2>
                    </div>
                    <div class="form-card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Animal Status</label>
                                <select name="animal_status" id="animal_status" required>
                                    <option value="Alive" <?php echo $report['animalStatus'] === 'Alive' ? 'selected' : ''; ?>>Alive</option>
                                    <option value="Dead" <?php echo $report['animalStatus'] === 'Dead' ? 'selected' : ''; ?>>Dead</option>
                                    <option value="Unknown" <?php echo $report['animalStatus'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Animal Vaccinated</label>
                                <select name="animal_vaccinated" id="animal_vaccinated" required>
                                    <option value="Yes" <?php echo $report['animalVaccinated'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?php echo $report['animalVaccinated'] === 'No' ? 'selected' : ''; ?>>No</option>
                                    <option value="Unknown" <?php echo $report['animalVaccinated'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Was the bite provoked?</label>
                                <select name="provoked" id="provoked" required>
                                    <option value="Yes" <?php echo $report['provoked'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?php echo $report['provoked'] === 'No' ? 'selected' : ''; ?>>No</option>
                                    <option value="Unknown" <?php echo $report['provoked'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="multiple_bites" name="multiple_bites" <?php echo $report['multipleBites'] ? 'checked' : ''; ?>>
                                    <label for="multiple_bites">Multiple Bites</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bite Location & Classification -->
                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-bullseye"></i>
                        <h2>Bite Location & Classification</h2>
                    </div>
                    <div class="form-card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="required">Bite Location</label>
                                <input type="text" name="bite_location" id="bite_location" placeholder="e.g., right hand, left leg, face" value="<?php echo htmlspecialchars($report['biteLocation']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="required">Bite Category</label>
                                <select name="bite_type" id="bite_type" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Category I" <?php echo $report['biteType'] === 'Category I' ? 'selected' : ''; ?>>Category I</option>
                                    <option value="Category II" <?php echo $report['biteType'] === 'Category II' ? 'selected' : ''; ?>>Category II</option>
                                    <option value="Category III" <?php echo $report['biteType'] === 'Category III' ? 'selected' : ''; ?>>Category III</option>
                                </select>
                            </div>
                        </div>

                        <!-- AI Classification -->
                        <div id="autoClassification" class="ai-classification">
                            <div class="ai-header">
                                <i class="bi bi-robot"></i>
                                <span>AI-Powered Risk Assessment</span>
                            </div>
                            <div id="autoCategory" class="ai-result">Analyzing...</div>
                            <div class="ai-grid">
                                <div class="ai-item">
                                    <div class="ai-item-label">Severity</div>
                                    <div class="ai-item-value" id="severityText">-</div>
                                </div>
                                <div class="ai-item">
                                    <div class="ai-item-label">Risk Score</div>
                                    <div class="ai-item-value" id="scoreText">-</div>
                                </div>
                            </div>
                            <div class="ai-item-label">Risk Factors:</div>
                            <div id="riskFactorsList" class="risk-badges"></div>
                            <div style="margin-top: 12px;">
                                <div class="ai-item-label">Recommendation:</div>
                                <div id="recommendationText" style="font-weight: 600; margin-top: 4px;">-</div>
                            </div>
                        </div>

                        <!-- Override Checkbox -->
                        <div class="checkbox-group" style="margin-top: 16px;">
                            <input type="checkbox" id="overrideCheckbox">
                            <label for="overrideCheckbox"><i class="bi bi-shield-exclamation text-warning"></i> Override AI classification</label>
                        </div>

                        <!-- Override Section -->
                        <div id="overrideSection" class="override-section">
                            <div class="override-header">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>Manual Override</span>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Override Category</label>
                                    <select name="category_override" id="category_override">
                                        <option value="">-- Select Override Category --</option>
                                        <option value="Category I">Category I (Low Risk)</option>
                                        <option value="Category II">Category II (Moderate Risk)</option>
                                        <option value="Category III">Category III (High Risk)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="required">Override Reason</label>
                                    <textarea name="override_reason" id="override_reason" placeholder="Please provide reasoning for overriding the AI classification."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Category Info -->
                        <div class="category-info">
                            <h4>Bite Categories Reference:</h4>
                            <p><strong>Category I:</strong> Touching or feeding of animals, licks on intact skin</p>
                            <p><strong>Category II:</strong> Nibbling of uncovered skin, minor scratches or abrasions without bleeding</p>
                            <p><strong>Category III:</strong> Single or multiple transdermal bites or scratches, contamination of mucous membrane or broken skin with saliva</p>
                        </div>
                    </div>
                </div>

                <!-- Treatment Information -->
                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-bandaid"></i>
                        <h2>Treatment Information</h2>
                    </div>
                    <div class="form-card-body">
                        <div class="form-grid">
                            <div class="checkbox-group">
                                <input type="checkbox" id="wash_with_soap" name="wash_with_soap" <?php echo $report['washWithSoap'] ? 'checked' : ''; ?>>
                                <label for="wash_with_soap">Wound washed with soap and water</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="rabies_vaccine" name="rabies_vaccine" <?php echo $report['rabiesVaccine'] ? 'checked' : ''; ?>>
                                <label for="rabies_vaccine">Rabies vaccine administered</label>
                            </div>
                            <div class="form-group" id="rabiesVaccineDateSection" style="display: <?php echo $report['rabiesVaccine'] ? 'flex' : 'none'; ?>;">
                                <label>Date of Rabies Vaccine</label>
                                <input type="date" name="rabies_vaccine_date" value="<?php echo htmlspecialchars($report['rabiesVaccineDate'] && $report['rabiesVaccineDate'] !== '0000-00-00' ? $report['rabiesVaccineDate'] : ''); ?>">
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="anti_tetanus" name="anti_tetanus" <?php echo $report['antiTetanus'] ? 'checked' : ''; ?>>
                                <label for="anti_tetanus">Anti-tetanus administered</label>
                            </div>
                            <div class="form-group" id="antiTetanusDateSection" style="display: <?php echo $report['antiTetanus'] ? 'flex' : 'none'; ?>;">
                                <label>Date of Anti-tetanus</label>
                                <input type="date" name="anti_tetanus_date" value="<?php echo htmlspecialchars($report['antiTetanusDate'] && $report['antiTetanusDate'] !== '0000-00-00' ? $report['antiTetanusDate'] : ''); ?>">
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="antibiotics" name="antibiotics" <?php echo $report['antibiotics'] ? 'checked' : ''; ?>>
                                <label for="antibiotics">Antibiotics prescribed</label>
                            </div>
                            <div class="form-group" id="antibioticsDetailsSection" style="display: <?php echo $report['antibiotics'] ? 'flex' : 'none'; ?>;">
                                <label>Antibiotics Details</label>
                                <input type="text" name="antibiotics_details" value="<?php echo htmlspecialchars($report['antibioticsDetails'] ?? ''); ?>" placeholder="Type, dosage, etc.">
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="referred_to_hospital" name="referred_to_hospital" <?php echo $report['referredToHospital'] ? 'checked' : ''; ?>>
                                <label for="referred_to_hospital">Referred to hospital</label>
                            </div>
                            <div class="form-group" id="hospitalNameSection" style="display: <?php echo $report['referredToHospital'] ? 'flex' : 'none'; ?>;">
                                <label>Hospital Name</label>
                                <input type="text" name="hospital_name" value="<?php echo htmlspecialchars($report['hospitalName'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Follow-up Date</label>
                                <input type="date" name="followup_date" value="<?php echo htmlspecialchars($report['followUpDate'] && $report['followUpDate'] !== '0000-00-00' ? $report['followUpDate'] : ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="required">Case Status</label>
                                <select name="status" required>
                                    <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $report['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $report['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="referred" <?php echo $report['status'] === 'referred' ? 'selected' : ''; ?>>Referred</option>
                                    <option value="cancelled" <?php echo $report['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-journal-text"></i>
                        <h2>Additional Notes</h2>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" placeholder="Any additional information or observations about the case"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <!-- Removed duplicate back button from form actions -->
                <div class="form-actions">
                    <div class="btn-group">
                        <button type="reset" class="btn btn-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const patientId = <?php echo json_encode($report['patientId'] ?? null); ?>;

            // Form field visibility toggles
            const toggleFields = [
                { checkbox: 'rabies_vaccine', section: 'rabiesVaccineDateSection' },
                { checkbox: 'anti_tetanus', section: 'antiTetanusDateSection' },
                { checkbox: 'antibiotics', section: 'antibioticsDetailsSection' },
                { checkbox: 'referred_to_hospital', section: 'hospitalNameSection' }
            ];

            toggleFields.forEach(({ checkbox, section }) => {
                const checkboxEl = document.getElementById(checkbox);
                const sectionEl = document.getElementById(section);
                if (checkboxEl && sectionEl) {
                    checkboxEl.addEventListener('change', function() {
                        sectionEl.style.display = this.checked ? 'flex' : 'none';
                    });
                }
            });

            // Animal type handler
            document.getElementById('animal_type').addEventListener('change', function() {
                document.getElementById('otherAnimalSection').style.display = this.value === 'Other' ? 'flex' : 'none';
            });

            // Animal ownership handler
            document.getElementById('animal_ownership').addEventListener('change', function() {
                const show = this.value === 'Owned by neighbor' || this.value === 'Owned by unknown person';
                document.getElementById('ownerSection').style.display = show ? 'flex' : 'none';
                document.getElementById('ownerContactSection').style.display = show ? 'flex' : 'none';
            });

            // Override toggle
            document.getElementById('overrideCheckbox').addEventListener('change', function() {
                document.getElementById('overrideSection').style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    document.getElementById('category_override').value = '';
                    document.getElementById('override_reason').value = '';
                    runClassify();
                }
            });

            // AI Classification
            let classifyTimeout = null;
            const inputs = ['bite_location', 'animal_type', 'animal_ownership', 'animal_status', 'animal_vaccinated', 'provoked', 'multiple_bites'];
            
            function scheduleClassify() {
                if (classifyTimeout) clearTimeout(classifyTimeout);
                classifyTimeout = setTimeout(runClassify, 600);
            }

            function runClassify() {
                const biteLocation = document.getElementById('bite_location').value;
                const animalType = document.getElementById('animal_type').value;
                
                if (!patientId || !biteLocation.trim() || !animalType) {
                    document.getElementById('autoClassification').style.display = 'none';
                    return;
                }

                const formData = new FormData();
                formData.append('patient_id', patientId);
                formData.append('bite_location', biteLocation);
                formData.append('animal_type', animalType);
                formData.append('animal_ownership', document.getElementById('animal_ownership').value);
                formData.append('animal_status', document.getElementById('animal_status').value);
                formData.append('animal_vaccinated', document.getElementById('animal_vaccinated').value);
                formData.append('provoked', document.getElementById('provoked').value);
                formData.append('multiple_bites', document.getElementById('multiple_bites').checked ? '1' : '');

                fetch('classify_incident.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success && json.data) {
                            const d = json.data;
                            const box = document.getElementById('autoClassification');
                            box.style.display = 'block';
                            
                            const cat = document.getElementById('autoCategory');
                            cat.textContent = d.biteType;
                            cat.className = 'ai-result ' + (d.biteType === 'Category III' ? 'text-danger' : d.biteType === 'Category II' ? 'text-warning' : 'text-success');
                            
                            document.getElementById('severityText').textContent = d.severity;
                            document.getElementById('scoreText').innerHTML = d.score + '/' + d.maxScore;
                            
                            if (d.riskFactors) {
                                document.getElementById('riskFactorsList').innerHTML = d.riskFactors.map(f => '<span class="risk-badge">' + f + '</span>').join(' ');
                            }
                            
                            const rec = document.getElementById('recommendationText');
                            rec.textContent = d.recommendation || '-';
                            rec.className = d.biteType === 'Category III' ? 'text-danger' : d.biteType === 'Category II' ? 'text-warning' : 'text-success';
                            
                            if (!document.getElementById('overrideCheckbox').checked) {
                                document.getElementById('bite_type').value = d.biteType;
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Classification error:', err);
                        document.getElementById('autoClassification').style.display = 'none';
                    });
            }

            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', scheduleClassify);
                    el.addEventListener('input', scheduleClassify);
                }
            });

            // Initial classification
            setTimeout(() => {
                if (document.getElementById('bite_location').value && document.getElementById('animal_type').value) {
                    runClassify();
                }
            }, 800);
        });

        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '../logout/admin_logout.php';
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
