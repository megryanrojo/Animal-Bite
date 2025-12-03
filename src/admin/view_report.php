<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}
require_once '../conn/conn.php';
// Use enhanced vaccination helper v2 for PEP/PrEP separation and improved features
require_once 'includes/vaccination_helper_v2.php';

// Get report ID from URL
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reportId <= 0) {
    header("Location: view_reports.php");
    exit;
}

// Get report details
try {
    $reportStmt = $pdo->prepare("
        SELECT r.*, 
               p.firstName, p.lastName, p.dateOfBirth, p.gender,
               p.contactNumber, p.email, p.address, p.barangay, p.city
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportId = ?
    ");
    $reportStmt->execute([$reportId]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        header("Location: view_reports.php");
        exit;
    }

    // Function to parse AI classification from notes
    function parseAiClassification($notes) {
        if (empty($notes) || strpos($notes, '[CLASSIFICATION REPORT]') === false) {
            return null;
        }

        $classificationText = substr($notes, strpos($notes, '[CLASSIFICATION REPORT]'));
        $classificationText = str_replace('[CLASSIFICATION REPORT]', '', $classificationText);

        // Parse the classification details
        $lines = explode("\n", trim($classificationText));
        $classification = [
            'riskScore' => null,
            'riskFactors' => [],
            'recommendation' => null,
            'rationale' => trim($classificationText)
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'Risk Score:') === 0) {
                // Extract score like "Risk Score: 8/15 | Factors: factor1, factor2"
                if (preg_match('/Risk Score:\s*(\d+)\/(\d+)\s*\|\s*Factors:\s*(.+)/', $line, $matches)) {
                    $classification['riskScore'] = $matches[1] . '/' . $matches[2];
                    $classification['riskFactors'] = array_map('trim', explode(',', $matches[3]));
                }
            } elseif (strpos($line, 'Immediate PEP') !== false || strpos($line, 'PEP required') !== false || strpos($line, 'Hospital referral') !== false) {
                $classification['recommendation'] = $line;
            }
        }

        return $classification;
    }

    // Parse AI classification from the report notes
    $report['aiClassification'] = parseAiClassification($report['notes']);

    // Function to clean notes by removing AI classification
    function cleanNotes($notes) {
        if (empty($notes) || strpos($notes, '[CLASSIFICATION REPORT]') === false) {
            return $notes;
        }
        return trim(substr($notes, 0, strpos($notes, '[CLASSIFICATION REPORT]')));
    }

    // Clean notes for display (remove AI classification)
    $report['cleanNotes'] = cleanNotes($report['notes']);

    // Get staff details if assigned
    $staffName = "Not assigned";
    if (!empty($report['staffId'])) {
        $staffStmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
        $staffStmt->execute([$report['staffId']]);
        $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
        if ($staff) {
            $staffName = $staff['firstName'] . ' ' . $staff['lastName'];
        }
    }

    // Get all staff for assignment dropdown
    $allStaffStmt = $pdo->prepare("SELECT staffId, firstName, lastName FROM staff ORDER BY lastName, firstName");
    $allStaffStmt->execute();
    $allStaff = $allStaffStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all PEP vaccinations (report-specific)
    $reportVaccinations = getPEPVaccinations($pdo, $reportId);
    
    // Get organized vaccinations with PEP/PrEP separation
    $organizetVaccinations = getOrganizedVaccinations($pdo, $report['patientId'], $reportId);
    $pepVaccinations = $organizetVaccinations['pep']['vaccinations'];
    $pepLockStatus = $organizetVaccinations['pep']['lockStatus'];

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Process form submission for updating report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['vaccination_action'])) {
        $vaction = $_POST['vaccination_action'];
        $vacc_error = null;
        
        try {
            if ($vaction === 'add') {
                // Get form inputs
                $doseNumber = isset($_POST['doseNumber']) ? (int)$_POST['doseNumber'] : getNextDoseNumber($pdo, $reportId);
                $dateGiven = !empty($_POST['dateGiven']) ? $_POST['dateGiven'] : null;
                $vaccineName = !empty($_POST['vaccineName']) ? $_POST['vaccineName'] : null;
                $administeredBy = !empty($_POST['administeredBy']) ? $_POST['administeredBy'] : null;
                $remarks = !empty($_POST['remarks']) ? $_POST['remarks'] : null;
                
                // Validation checks
                if (!requiresPEP($pdo, $reportId)) {
                    $vacc_error = "Category I: PEP vaccination not required for this report";
                } else {
                    $seqCheck = validateDoseSequence($pdo, $reportId, $doseNumber, 'add');
                    if (!$seqCheck['valid']) {
                        $vacc_error = $seqCheck['message'];
                    }
                }
                
                if (!$vacc_error) {
                    // Calculate suggested date if not provided
                    if (!$dateGiven && $report['biteDate']) {
                        $dateGiven = calculateSuggestedDoseDate($report['biteDate'], $doseNumber);
                    }
                    
                    $insert = $pdo->prepare("INSERT INTO vaccination_records (patientId, reportId, exposureType, doseNumber, dateGiven, vaccineName, administeredBy, remarks) VALUES (?, ?, 'PEP', ?, ?, ?, ?, ?)");
                    $insert->execute([$report['patientId'], $reportId, $doseNumber, $dateGiven, $vaccineName, $administeredBy, $remarks]);
                    
                    header("Location: view_report.php?id={$reportId}&vacc_updated=1");
                    exit;
                } else {
                    $error = "Cannot add dose: " . $vacc_error;
                }
                
            } elseif ($vaction === 'edit' && isset($_POST['vaccinationId']) && is_numeric($_POST['vaccinationId'])) {
                $vaccinationId = (int)$_POST['vaccinationId'];
                $doseNumber = isset($_POST['doseNumber']) ? (int)$_POST['doseNumber'] : 1;
                $dateGiven = !empty($_POST['dateGiven']) ? $_POST['dateGiven'] : null;
                $vaccineName = !empty($_POST['vaccineName']) ? $_POST['vaccineName'] : null;
                $administeredBy = !empty($_POST['administeredBy']) ? $_POST['administeredBy'] : null;
                $remarks = !empty($_POST['remarks']) ? $_POST['remarks'] : null;
                
                // Check if record is locked
                if (isRecordLocked($pdo, $reportId)) {
                    $vacc_error = "Record is locked: all 3 doses completed. Cannot edit.";
                }
                
                // Check if past date
                if (!$vacc_error && isPastDate($dateGiven)) {
                    $vacc_error = "Cannot edit: dose date is in the past";
                }
                
                if (!$vacc_error) {
                    $update = $pdo->prepare("UPDATE vaccination_records SET doseNumber = ?, dateGiven = ?, vaccineName = ?, administeredBy = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE vaccinationId = ? AND reportId = ?");
                    $update->execute([$doseNumber, $dateGiven, $vaccineName, $administeredBy, $remarks, $vaccinationId, $reportId]);
                    
                    header("Location: view_report.php?id={$reportId}&vacc_updated=1");
                    exit;
                } else {
                    $error = "Cannot edit dose: " . $vacc_error;
                }
                
            } elseif ($vaction === 'delete' && isset($_POST['vaccinationId']) && is_numeric($_POST['vaccinationId'])) {
                $vaccinationId = (int)$_POST['vaccinationId'];
                
                // Check if record is locked
                if (isRecordLocked($pdo, $reportId)) {
                    $vacc_error = "Record is locked: all 3 doses completed. Cannot delete.";
                } else {
                    $del = $pdo->prepare("DELETE FROM vaccination_records WHERE vaccinationId = ? AND reportId = ?");
                    $del->execute([$vaccinationId, $reportId]);
                    
                    header("Location: view_report.php?id={$reportId}&vacc_deleted=1");
                    exit;
                }
                
                if ($vacc_error) {
                    $error = "Cannot delete dose: " . $vacc_error;
                }
            }
        } catch (PDOException $e) {
            $error = "Vaccination action failed: " . $e->getMessage();
        }
    }

    if (isset($_POST['update_report'])) {
        $status = $_POST['status'];
        $notes = $_POST['notes'];
        $followUpDate = !empty($_POST['followUpDate']) ? $_POST['followUpDate'] : null;
        $staffId = !empty($_POST['staffId']) ? $_POST['staffId'] : null;

        try {
            $updateStmt = $pdo->prepare("
                UPDATE reports 
                SET status = ?, notes = ?, followUpDate = ?, staffId = ?
                WHERE reportId = ?
            ");
            $updateStmt->execute([$status, $notes, $followUpDate, $staffId, $reportId]);

            header("Location: view_report.php?id=$reportId&updated=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error updating report: " . $e->getMessage();
        }
    }
}

// Calculate patient age
$dob = new DateTime($report['dateOfBirth']);
$now = new DateTime();
$age = $now->diff($dob)->y;

// Days since bite
$biteDate = new DateTime($report['biteDate']);
$daysSinceBite = $now->diff($biteDate)->days;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report #<?php echo $reportId; ?> | Admin Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0f766e;
            --primary-dark: #0d9488;
            --primary-light: #f0fdfa;
            --success: #16a34a;
            --success-light: #dcfce7;
            --warning: #d97706;
            --warning-light: #fef3c7;
            --danger: #dc2626;
            --danger-light: #fee2e2;
            --purple: #7c3aed;
            --purple-light: #ede9fe;
            --blue: #2563eb;
            --blue-light: #dbeafe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            background: var(--gray-100);
            color: var(--gray-700);
            margin: 0;
            line-height: 1.5;
        }

        .main-content {
            margin: 0 auto;
            max-width: 900px;
            padding: 24px 16px;
        }

        /* Back link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary); }

        /* Hero Card - Patient & Status Overview */
        .hero-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .hero-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .patient-info h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .patient-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 13px;
            opacity: 0.9;
        }

        .patient-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .hero-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(4px);
        }

        .status-badge.pending { background: var(--warning-light); color: #92400e; }
        .status-badge.in_progress { background: var(--blue-light); color: #1e40af; }
        .status-badge.completed { background: var(--success-light); color: #166534; }
        .status-badge.referred { background: var(--purple-light); color: #5b21b6; }
        .status-badge.cancelled { background: var(--danger-light); color: #991b1b; }

        .category-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .category-badge.cat-i { background: var(--success-light); color: #166534; }
        .category-badge.cat-ii { background: var(--warning-light); color: #9a3412; }
        .category-badge.cat-iii { background: var(--danger-light); color: #991b1b; }

        /* Quick Stats Bar */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-bottom: 1px solid var(--gray-200);
        }

        .stat-item {
            padding: 16px 20px;
            text-align: center;
            border-right: 1px solid var(--gray-200);
        }
        .stat-item:last-child { border-right: none; }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
            margin-top: 2px;
        }

        /* Quick Actions */
        .quick-actions {
            padding: 16px 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            background: var(--gray-50);
        }

        .quick-actions .btn { flex: 1; min-width: 120px; }

        /* Section */
        .section {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 16px;
            overflow: hidden;
        }

        .section-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-50);
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i { color: var(--primary); font-size: 16px; }

        .section-body { padding: 20px; }

        /* Info List - Cleaner than grid */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .info-row:last-child { border-bottom: none; }

        .info-label {
            width: 140px;
            flex-shrink: 0;
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 500;
        }

        .info-value {
            flex: 1;
            font-size: 14px;
            color: var(--gray-800);
        }

        /* Two Column Info */
        .info-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0 32px;
        }

        /* Highlight boxes */
        .highlight-box {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 16px;
        }

        .highlight-box.warning {
            background: var(--warning-light);
            border-left: 4px solid var(--warning);
        }

        .highlight-box.danger {
            background: var(--danger-light);
            border-left: 4px solid var(--danger);
        }

        .highlight-box.success {
            background: var(--success-light);
            border-left: 4px solid var(--success);
        }

        .highlight-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            color: var(--gray-600);
        }

        .highlight-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
        }

        /* Treatment Checklist */
        .treatment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .treatment-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            background: var(--gray-50);
            border-radius: var(--radius-sm);
        }

        .treatment-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }

        .treatment-icon.yes {
            background: var(--success-light);
            color: var(--success);
        }

        .treatment-icon.no {
            background: var(--gray-200);
            color: var(--gray-400);
        }

        .treatment-content h4 {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0 0 2px 0;
        }

        .treatment-content p {
            font-size: 12px;
            color: var(--gray-500);
            margin: 0;
        }

        /* Vaccination Timeline */
        .vacc-timeline {
            position: relative;
        }

        .vacc-item {
            display: flex;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid var(--gray-100);
            position: relative;
        }
        .vacc-item:last-child { border-bottom: none; }

        .vacc-dose {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .vacc-dose.completed {
            background: var(--success-light);
            color: var(--success);
        }

        .vacc-details { flex: 1; }

        .vacc-date {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 4px;
        }

        .vacc-meta {
            font-size: 13px;
            color: var(--gray-500);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .vacc-actions {
            display: flex;
            gap: 4px;
            align-items: flex-start;
        }

        /* Form */
        .form-group { margin-bottom: 16px; }

        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 6px;
            display: block;
        }

        .form-control, .form-select {
            font-size: 14px;
            padding: 10px 14px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            width: 100%;
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
            outline: none;
        }

        textarea.form-control { min-height: 100px; resize: vertical; }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        /* Buttons */
        .btn {
            font-size: 13px;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            color: white;
        }

        .btn-outline {
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            color: var(--gray-700);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover { background: #b91c1c; color: white; }

        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-icon { padding: 8px; }

        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: var(--success-light);
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: var(--danger-light);
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 32px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 40px;
            margin-bottom: 12px;
            opacity: 0.4;
        }

        .empty-state h5 {
            font-size: 15px;
            color: var(--gray-700);
            margin: 0 0 4px 0;
        }

        .empty-state p {
            font-size: 13px;
            margin: 0;
        }

        /* Modal */
        .modal-content { border: none; border-radius: var(--radius); }
        .modal-header { padding: 18px 24px; border-bottom: 1px solid var(--gray-200); }
        .modal-title { font-size: 16px; font-weight: 600; }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--gray-200); }

        /* Contact Links */
        .contact-link {
            color: var(--primary);
            text-decoration: none;
        }
        .contact-link:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content { padding: 16px 12px; }

            .hero-header { flex-direction: column; }

            .quick-stats { grid-template-columns: repeat(2, 1fr); }
            .stat-item:nth-child(2) { border-right: none; }

            .quick-actions { flex-direction: column; }
            .quick-actions .btn { min-width: 100%; }

            .info-grid-2 { grid-template-columns: 1fr; }

            .info-row { flex-direction: column; gap: 4px; }
            .info-label { width: auto; }

            .treatment-grid { grid-template-columns: 1fr; }

            .form-row { grid-template-columns: 1fr; }

            .vacc-item { flex-wrap: wrap; }
            .vacc-actions { width: 100%; margin-top: 8px; }
        }

        @media (max-width: 480px) {
            .patient-info h1 { font-size: 1.25rem; }
            .quick-stats { grid-template-columns: 1fr; }
            .stat-item { border-right: none; border-bottom: 1px solid var(--gray-200); }
            .stat-item:last-child { border-bottom: none; }
        }

        /* Print */
        @media print {
            .main-content { max-width: 100%; padding: 0; }
            .no-print { display: none !important; }
            .section { box-shadow: none; border: 1px solid #ddd; }
            .hero-card { box-shadow: none; }
            .hero-header { background: #f3f4f6 !important; color: #111 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <?php include "includes/navbar.php"; ?>

    <div class="main-content">
        <!-- Back Link -->
        <a href="view_reports.php" class="back-link no-print">
            <i class="bi bi-arrow-left"></i> Back to Reports
        </a>

        <?php if (isset($_GET['updated']) || isset($_GET['vacc_updated'])): ?>
        <div class="alert alert-success no-print">
            <i class="bi bi-check-circle"></i>
            <?php echo isset($_GET['vacc_updated']) ? 'Vaccination record updated successfully.' : 'Report updated successfully.'; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['vacc_deleted'])): ?>
        <div class="alert alert-success no-print">
            <i class="bi bi-check-circle"></i> Vaccination record deleted successfully.
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger no-print">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Hero Card - Patient Overview -->
        <div class="hero-card">
            <div class="hero-header">
                <div class="patient-info">
                    <h1><?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></h1>
                    <div class="patient-meta">
                        <span><i class="bi bi-person"></i> <?php echo $age; ?> years old, <?php echo htmlspecialchars($report['gender']); ?></span>
                        <span><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($report['barangay']); ?></span>
                        <span><i class="bi bi-hash"></i> Report #<?php echo $reportId; ?></span>
                    </div>
                </div>
                <div class="hero-badges">
                    <span class="status-badge <?php echo $report['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                    </span>
                    <?php if ($report['biteType']): ?>
                    <span class="category-badge <?php 
                        echo $report['biteType'] === 'Category III' ? 'cat-iii' : 
                            ($report['biteType'] === 'Category II' ? 'cat-ii' : 'cat-i'); 
                    ?>">
                        <?php echo $report['biteType']; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('M d', strtotime($report['biteDate'])); ?></div>
                    <div class="stat-label">Bite Date</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $daysSinceBite; ?></div>
                    <div class="stat-label">Days Ago</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($reportVaccinations); ?></div>
                    <div class="stat-label">PEP Doses</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo !empty($report['followUpDate']) ? date('M d', strtotime($report['followUpDate'])) : '—'; ?></div>
                    <div class="stat-label">Follow-up</div>
                </div>
            </div>

            <!-- AI Classification Section -->
            <?php if (!empty($report['aiClassification'])): ?>
            <div class="ai-classification-hero" style="margin-top: 16px; padding: 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <i class="bi bi-robot" style="color: #6b7280;"></i>
                    <h3 style="font-size: 14px; font-weight: 600; color: #374151; margin: 0;">AI Risk Assessment</h3>
                    <?php if (!empty($report['aiClassification']['riskScore'])): ?>
                    <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 0.75rem;">
                        Risk Score: <?php echo htmlspecialchars($report['aiClassification']['riskScore']); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($report['aiClassification']['riskFactors'])): ?>
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 0.75rem; font-weight: 600; color: #6b7280; margin-bottom: 6px;">Identified Risk Factors:</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <?php foreach ($report['aiClassification']['riskFactors'] as $factor): ?>
                        <span class="badge" style="background: #fee2e2; color: #991b1b; font-size: 0.7rem;">
                            <?php echo htmlspecialchars($factor); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($report['aiClassification']['recommendation'])): ?>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 600; color: #6b7280; margin-bottom: 4px;">AI Recommendation:</div>
                    <div style="font-size: 0.85rem; color: #374151; line-height: 1.4;">
                        <?php echo htmlspecialchars($report['aiClassification']['recommendation']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions no-print">
                <button class="btn btn-outline" onclick="window.print();">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Incident Summary - What Happened -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="bi bi-exclamation-triangle"></i> Incident Summary</h2>
            </div>
            <div class="section-body">
                <!-- Alert Box for Category III -->
                <?php if ($report['biteType'] === 'Category III'): ?>
                <div class="highlight-box danger">
                    <div class="highlight-title">⚠️ High Risk Exposure</div>
                    <div class="highlight-value">Category III - Immediate PEP Required</div>
                </div>
                <?php endif; ?>

                <div class="info-grid-2">
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Animal</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($report['animalType']); ?>
                                <?php if ($report['animalType'] === 'Other' && !empty($report['animalOtherType'])): ?>
                                    (<?php echo htmlspecialchars($report['animalOtherType']); ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ownership</span>
                            <span class="info-value"><?php echo htmlspecialchars($report['animalOwnership']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Animal Status</span>
                            <span class="info-value"><?php echo htmlspecialchars($report['animalStatus']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Vaccinated</span>
                            <span class="info-value"><?php echo htmlspecialchars($report['animalVaccinated']); ?></span>
                        </div>
                    </div>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Bite Location</span>
                            <span class="info-value"><?php echo htmlspecialchars($report['biteLocation']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Multiple Bites</span>
                            <span class="info-value"><?php echo $report['multipleBites'] ? 'Yes' : 'No'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Provoked</span>
                            <span class="info-value"><?php echo htmlspecialchars($report['provoked']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Washed w/ Soap</span>
                            <span class="info-value"><?php echo $report['washWithSoap'] ? 'Yes ✓' : 'No'; ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($report['ownerName']) || !empty($report['ownerContact'])): ?>
                <div class="highlight-box" style="margin-top: 16px; margin-bottom: 0;">
                    <div class="highlight-title">Animal Owner Info</div>
                    <div class="info-value">
                        <?php if (!empty($report['ownerName'])): ?>
                            <?php echo htmlspecialchars($report['ownerName']); ?>
                        <?php endif; ?>
                        <?php if (!empty($report['ownerContact'])): ?>
                            — <a href="tel:<?php echo htmlspecialchars($report['ownerContact']); ?>" class="contact-link"><?php echo htmlspecialchars($report['ownerContact']); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Treatment Given -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="bi bi-heart-pulse"></i> Treatment Given</h2>
            </div>
            <div class="section-body">
                <div class="treatment-grid">
                    <div class="treatment-item">
                        <div class="treatment-icon <?php echo $report['rabiesVaccine'] ? 'yes' : 'no'; ?>">
                            <i class="bi <?php echo $report['rabiesVaccine'] ? 'bi-check' : 'bi-x'; ?>"></i>
                        </div>
                        <div class="treatment-content">
                            <h4>Rabies Vaccine</h4>
                            <p><?php echo $report['rabiesVaccine'] ? 
                                (!empty($report['rabiesVaccineDate']) ? date('M d, Y', strtotime($report['rabiesVaccineDate'])) : 'Given') 
                                : 'Not given'; ?></p>
                        </div>
                    </div>
                    <div class="treatment-item">
                        <div class="treatment-icon <?php echo $report['antiTetanus'] ? 'yes' : 'no'; ?>">
                            <i class="bi <?php echo $report['antiTetanus'] ? 'bi-check' : 'bi-x'; ?>"></i>
                        </div>
                        <div class="treatment-content">
                            <h4>Anti-Tetanus</h4>
                            <p><?php echo $report['antiTetanus'] ? 
                                (!empty($report['antiTetanusDate']) ? date('M d, Y', strtotime($report['antiTetanusDate'])) : 'Given') 
                                : 'Not given'; ?></p>
                        </div>
                    </div>
                    <div class="treatment-item">
                        <div class="treatment-icon <?php echo $report['antibiotics'] ? 'yes' : 'no'; ?>">
                            <i class="bi <?php echo $report['antibiotics'] ? 'bi-check' : 'bi-x'; ?>"></i>
                        </div>
                        <div class="treatment-content">
                            <h4>Antibiotics</h4>
                            <p><?php echo $report['antibiotics'] ? 
                                (!empty($report['antibioticsDetails']) ? htmlspecialchars($report['antibioticsDetails']) : 'Prescribed') 
                                : 'Not prescribed'; ?></p>
                        </div>
                    </div>
                    <div class="treatment-item">
                        <div class="treatment-icon <?php echo $report['referredToHospital'] ? 'yes' : 'no'; ?>">
                            <i class="bi <?php echo $report['referredToHospital'] ? 'bi-check' : 'bi-x'; ?>"></i>
                        </div>
                        <div class="treatment-content">
                            <h4>Hospital Referral</h4>
                            <p><?php echo $report['referredToHospital'] ? 
                                (!empty($report['hospitalName']) ? htmlspecialchars($report['hospitalName']) : 'Referred') 
                                : 'Not referred'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PEP Vaccination Schedule - Enhanced with Status Tracking -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="bi bi-shield-plus"></i> PEP Vaccination Schedule</h2>
                <div class="section-header-meta">
                    <?php if ($pepLockStatus): ?>
                    <span class="badge bg-<?php echo $pepLockStatus['is_locked'] ? 'danger' : 'info'; ?>" title="<?php echo htmlspecialchars($pepLockStatus['message']); ?>">
                        <i class="bi bi-<?php echo $pepLockStatus['is_locked'] ? 'lock-fill' : 'info-circle'; ?>"></i>
                        <?php echo htmlspecialchars($pepLockStatus['message']); ?>
                    </span>
                    <?php endif; ?>
                    <button class="btn btn-primary btn-sm no-print" data-bs-toggle="modal" data-bs-target="#pepModal" <?php echo ($pepLockStatus && $pepLockStatus['is_locked']) ? 'disabled' : ''; ?>>
                        <i class="bi bi-plus"></i> Add Dose
                    </button>
                </div>
            </div>
            <div class="section-body">
                <?php if (!empty($pepVaccinations)): ?>
                <div class="vacc-timeline">
                    <?php foreach ($pepVaccinations as $pv): 
                        $statusInfo = $pv['statusInfo'];
                    ?>
                    <div class="vacc-item" data-vaccine-id="<?php echo (int)$pv['id']; ?>">
                        <div class="vacc-dose <?php echo strtolower($pv['status']); ?>" title="<?php echo htmlspecialchars($statusInfo['label']); ?>">
                            <?php echo (int)$pv['dose']; ?>
                        </div>
                        <div class="vacc-details">
                            <div class="vacc-date">
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 4px;">
                                    <span><?php echo $pv['dateGiven'] ? date('F d, Y', strtotime($pv['dateGiven'])) : 'Scheduled for: ' . ($pv['nextScheduledDate'] ? date('F d, Y', strtotime($pv['nextScheduledDate'])) : 'Not yet scheduled'); ?></span>
                                    <span class="badge <?php echo htmlspecialchars($statusInfo['badge_class']); ?>" title="<?php echo htmlspecialchars($statusInfo['label']); ?>">
                                        <i class="bi bi-<?php echo htmlspecialchars($statusInfo['icon']); ?>"></i>
                                        <?php echo htmlspecialchars($statusInfo['label']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="vacc-meta">
                                <?php if (!empty($pv['vaccineName'])): ?>
                                <span><i class="bi bi-capsule"></i> <?php echo htmlspecialchars($pv['vaccineName']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($pv['administeredBy'])): ?>
                                <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($pv['administeredBy']); ?></span>
                                <?php endif; ?>
                                <?php if ($pv['workload']['level'] !== 'N/A'): ?>
                                <span class="badge <?php echo htmlspecialchars($pv['workload']['badge_css']); ?>" title="<?php echo htmlspecialchars($pv['workload']['message']); ?>">
                                    <i class="bi bi-graph-up"></i> <?php echo htmlspecialchars($pv['workload']['level']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($pv['remarks'])): ?>
                            <div class="vacc-meta" style="margin-top: 4px; font-style: italic; color: var(--gray-600);">
                                <i class="bi bi-chat-quote"></i> <?php echo htmlspecialchars($pv['remarks']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="vacc-actions no-print">
                            <button class="btn btn-outline btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#pepModal"
                                data-vaccination-id="<?php echo (int)$pv['id']; ?>"
                                data-dose-number="<?php echo (int)$pv['dose']; ?>"
                                data-date-given="<?php echo htmlspecialchars($pv['dateGiven'] ?? ''); ?>"
                                data-vaccine-name="<?php echo htmlspecialchars($pv['vaccineName'] ?? ''); ?>"
                                data-administered-by="<?php echo htmlspecialchars($pv['administeredBy'] ?? ''); ?>"
                                data-remarks="<?php echo htmlspecialchars($pv['remarks'] ?? ''); ?>"
                                <?php echo $pv['isLocked'] ? 'disabled' : ''; ?>>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this PEP dose? This action cannot be undone.');">
                                <input type="hidden" name="vaccination_action" value="delete">
                                <input type="hidden" name="vaccinationId" value="<?php echo (int)$pv['id']; ?>">
                                <button type="submit" class="btn btn-outline btn-icon btn-sm" style="color: var(--danger);" <?php echo $pv['isLocked'] ? 'disabled' : ''; ?>>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-plus"></i>
                    <h5>No PEP Doses Recorded</h5>
                    <p>Click "Add Dose" to record vaccination doses for this patient.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Patient Contact Info -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="bi bi-person-lines-fill"></i> Patient Contact</h2>
            </div>
            <div class="section-body">
                <div class="info-grid-2">
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Phone</span>
                            <span class="info-value">
                                <a href="tel:<?php echo htmlspecialchars($report['contactNumber']); ?>" class="contact-link">
                                    <?php echo htmlspecialchars($report['contactNumber']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value">
                                <a href="mailto:<?php echo htmlspecialchars($report['email']); ?>" class="contact-link">
                                    <?php echo htmlspecialchars($report['email']); ?>
                                </a>
                            </span>
                        </div>
                    </div>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value"><?php echo date('F d, Y', strtotime($report['dateOfBirth'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($report['address'] . ', ' . $report['barangay'] . ', ' . $report['city']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Actions -->
        <div class="section no-print">
            <div class="section-header">
                <h2 class="section-title"><i class="bi bi-gear"></i> Case Management</h2>
            </div>
            <div class="section-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $report['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $report['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="referred" <?php echo $report['status'] === 'referred' ? 'selected' : ''; ?>>Referred</option>
                                <option value="cancelled" <?php echo $report['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="staffId" class="form-label">Assigned Staff</label>
                            <select class="form-select" id="staffId" name="staffId">
                                <option value="">— Not Assigned —</option>
                                <?php foreach ($allStaff as $s): ?>
                                <option value="<?php echo $s['staffId']; ?>" <?php echo $report['staffId'] == $s['staffId'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['firstName'] . ' ' . $s['lastName']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="followUpDate" class="form-label">Follow-up Date</label>
                            <input type="date" class="form-control" id="followUpDate" name="followUpDate" 
                                value="<?php echo !empty($report['followUpDate']) ? date('Y-m-d', strtotime($report['followUpDate'])) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label">Case Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                            placeholder="Add treatment notes, follow-up instructions, or observations..."><?php echo htmlspecialchars($report['cleanNotes'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 8px;">
                        <button type="submit" name="update_report" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 24px 0; font-size: 12px; color: var(--gray-400);">
            Report created <?php echo date('M d, Y g:i A', strtotime($report['reportDate'])); ?>
            <?php if (!empty($report['updated_at'])): ?>
            · Last updated <?php echo date('M d, Y g:i A', strtotime($report['updated_at'])); ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- PEP Modal with Smart Scheduling -->
    <div class="modal fade" id="pepModal" tabindex="-1" aria-labelledby="pepModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="pepModalLabel">Add PEP Dose</h5>
                            <small id="pepLockStatus" class="text-muted"></small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="vaccination_action" id="pepVaccAction" value="add">
                        <input type="hidden" name="vaccinationId" id="pepVaccinationId" value="">
                        <input type="hidden" name="exposureType" value="PEP">
                        
                        <!-- Status & Warnings -->
                        <div id="pepValidations" class="mb-3"></div>
                        
                        <!-- Dose Selection & Info -->
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Dose Number</label>
                                <input type="number" min="1" max="3" class="form-control" name="doseNumber" id="pepDoseNumber" required>
                                <small class="text-muted" id="pepDoseInfo"></small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Date Given</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" name="dateGiven" id="pepDateGiven" required>
                                    <button type="button" class="btn btn-outline-secondary" id="pepSuggestDateBtn" title="Use suggested date">
                                        <i class="bi bi-calendar-check"></i>
                                    </button>
                                </div>
                                <small class="text-muted" id="pepDateSuggestion"></small>
                                <small class="d-block text-muted" id="pepWorkloadInfo"></small>
                            </div>
                        </div>
                        
                        <!-- Vaccine Details -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Vaccine Name</label>
                                <input type="text" class="form-control" name="vaccineName" id="pepVaccineName" 
                                    placeholder="e.g., Verorab, Rabipur">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Administered By</label>
                                <input type="text" class="form-control" name="administeredBy" id="pepAdminBy">
                            </div>
                        </div>
                        
                        <!-- Remarks -->
                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" id="pepRemarks" rows="3" 
                                    placeholder="Notes, adverse reactions, next schedule..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="pepSaveBtn">Save Dose</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const PEP_INTERVALS = {1: 0, 2: 7, 3: 21};
        const BITE_DATE = '<?php echo isset($report['biteDate']) ? htmlspecialchars($report['biteDate']) : ''; ?>';
        const REPORT_ID = <?php echo $reportId; ?>;
        
        // Calculate suggested dose date
        function calculateSuggestedDate(doseNum) {
            if (!BITE_DATE || !doseNum) return null;
            const interval = PEP_INTERVALS[parseInt(doseNum)] || 0;
            const date = new Date(BITE_DATE);
            date.setDate(date.getDate() + interval);
            return date.toISOString().split('T')[0];
        }
        
        // Fetch and display workload for a date
        function updateWorkloadInfo(dateStr) {
            if (!dateStr) {
                document.getElementById('pepWorkloadInfo').textContent = '';
                return;
            }
            
            fetch('get_workload.php?date=' + encodeURIComponent(dateStr))
                .then(r => r.json())
                .then(data => {
                    if (data.level && data.level !== 'UNKNOWN') {
                        const colors = {
                            'LOW': 'success',
                            'MODERATE': 'warning',
                            'HIGH': 'danger',
                            'VERY HIGH': 'dark'
                        };
                        const color = colors[data.level] || 'secondary';
                        document.getElementById('pepWorkloadInfo').innerHTML = 
                            '<span class="badge bg-' + color + '">' + data.message + '</span>' +
                            (data.level !== 'LOW' ? ' <small>(You can still schedule here)</small>' : '');
                    }
                })
                .catch(e => console.log('Workload fetch error:', e));
        }
        
        // Update dose information
        function updateDoseInfo(doseNum) {
            const doseNumInt = parseInt(doseNum);
            if (doseNumInt < 1 || doseNumInt > 3) {
                document.getElementById('pepDoseInfo').textContent = '';
                return;
            }
            
            let infoText = `Day ${PEP_INTERVALS[doseNumInt]} after bite`;
            document.getElementById('pepDoseInfo').textContent = infoText;
            
            // Update suggested date
            const suggested = calculateSuggestedDate(doseNum);
            if (suggested) {
                document.getElementById('pepDateSuggestion').textContent = 
                    'Suggested: ' + new Date(suggested).toLocaleDateString();
            }
        }
        
        // Modal show event - populate form with existing data or defaults
        const pepModal = document.getElementById('pepModal');
        if (pepModal) {
            pepModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const vaccId = button ? button.getAttribute('data-vaccination-id') : '';
                const actionInput = document.getElementById('pepVaccAction');
                const idInput = document.getElementById('pepVaccinationId');
                const doseInput = document.getElementById('pepDoseNumber');
                const dateInput = document.getElementById('pepDateGiven');
                const vacName = document.getElementById('pepVaccineName');
                const adminBy = document.getElementById('pepAdminBy');
                const remarks = document.getElementById('pepRemarks');
                const modalTitle = document.getElementById('pepModalLabel');
                const lockStatus = document.getElementById('pepLockStatus');
                
                // Fetch lock status
                fetch('get_pep_status.php?reportId=' + REPORT_ID)
                    .then(r => r.json())
                    .then(data => {
                        lockStatus.textContent = data.message;
                        if (data.is_locked) {
                            document.getElementById('pepSaveBtn').disabled = true;
                            document.getElementById('pepSaveBtn').textContent = 'Record Locked';
                        } else {
                            document.getElementById('pepSaveBtn').disabled = false;
                            document.getElementById('pepSaveBtn').textContent = 'Save Dose';
                        }
                    })
                    .catch(e => console.log('Status fetch error:', e));

                if (vaccId) {
                    actionInput.value = 'edit';
                    idInput.value = vaccId;
                    doseInput.value = button.getAttribute('data-dose-number') || '';
                    dateInput.value = button.getAttribute('data-date-given') || '';
                    vacName.value = button.getAttribute('data-vaccine-name') || '';
                    adminBy.value = button.getAttribute('data-administered-by') || '';
                    remarks.value = button.getAttribute('data-remarks') || '';
                    modalTitle.textContent = 'Edit PEP Dose';
                    updateWorkloadInfo(dateInput.value);
                } else {
                    actionInput.value = 'add';
                    idInput.value = '';
                    doseInput.value = '';
                    dateInput.value = '';
                    vacName.value = '';
                    adminBy.value = '';
                    remarks.value = '';
                    modalTitle.textContent = 'Add PEP Dose';
                    document.getElementById('pepDateSuggestion').textContent = '';
                    document.getElementById('pepDoseInfo').textContent = '';
                    document.getElementById('pepWorkloadInfo').textContent = '';
                }
                
                // Clear validations
                document.getElementById('pepValidations').innerHTML = '';
            });
        }
        
        // Event listeners for smart updates
        document.getElementById('pepDoseNumber').addEventListener('change', function() {
            updateDoseInfo(this.value);
            // Suggest date if available
            const suggested = calculateSuggestedDate(this.value);
            if (suggested && !document.getElementById('pepDateGiven').value) {
                document.getElementById('pepDateGiven').value = suggested;
                updateWorkloadInfo(suggested);
            }
        });
        
        document.getElementById('pepDateGiven').addEventListener('change', function() {
            updateWorkloadInfo(this.value);
        });
        
        // Suggest date button
        document.getElementById('pepSuggestDateBtn').addEventListener('click', function() {
            const doseNum = document.getElementById('pepDoseNumber').value;
            const suggested = calculateSuggestedDate(doseNum);
            if (suggested) {
                document.getElementById('pepDateGiven').value = suggested;
                updateWorkloadInfo(suggested);
            }
        });

        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '../logout/admin_logout.php';
            }
        }
    </script>
</body>
</html>