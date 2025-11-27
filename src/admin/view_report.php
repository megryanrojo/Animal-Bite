<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

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
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Process form submission for updating report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Report #<?php echo $reportId; ?> | Admin Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --purple: #7c3aed;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --radius: 8px;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            background: var(--gray-50);
            color: var(--gray-700);
            margin: 0;
            line-height: 1.5;
        }
        
        .main-content {
            min-height: 100vh;
            padding: 20px;
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .page-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .page-header .report-id {
            font-size: 0.85rem;
            color: var(--gray-500);
            font-weight: 400;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
        }
        
        /* Buttons */
        .btn {
            font-size: 13px;
            padding: 8px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        .btn-outline-secondary {
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
        }
        
        .btn-outline-secondary:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: 13px;
            margin-bottom: 16px;
        }
        
        /* Cards */
        .card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        
        .card-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .card-body {
            padding: 18px;
        }
        
        /* Status badges */
        .badge {
            font-size: 11px;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-in-progress { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-referred { background: #ede9fe; color: #5b21b6; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        
        .badge-cat-i { background: #dcfce7; color: #166534; }
        .badge-cat-ii { background: #ffedd5; color: #9a3412; }
        .badge-cat-iii { background: #fee2e2; color: #991b1b; }
        
        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .info-item label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
            font-weight: 500;
        }
        
        .info-item span {
            font-size: 13px;
            color: var(--gray-900);
        }
        
        /* Form styles */
        .form-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 4px;
        }
        
        .form-control, .form-select {
            font-size: 13px;
            padding: 8px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-text {
            font-size: 11px;
            color: var(--gray-500);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Two column layout */
        .two-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        /* Footer */
        .footer {
            margin-top: 24px;
            padding: 16px 0;
            border-top: 1px solid var(--gray-200);
            text-align: center;
            font-size: 12px;
            color: var(--gray-500);
        }
        
        /* Mobile responsive */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions .btn {
                flex: 1;
                justify-content: center;
            }
            
            .two-col {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .card-body {
                padding: 14px;
            }
            
            .form-row-3 {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 12px;
            }
            
            .page-header h1 {
                font-size: 1.1rem;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
        
        /* Form row for 3 columns */
        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }
        
        /* Action buttons at bottom */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid var(--gray-200);
            margin-top: 16px;
        }
        
        @media (max-width: 480px) {
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Print styles */
        @media print {
            .main-content { margin-left: 0; }
            .no-print { display: none !important; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <?php include "includes/navbar.php"; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="bi bi-file-earmark-medical"></i>
                Report Details
                <span class="report-id">#<?php echo $reportId; ?></span>
            </h1>
            <div class="header-actions no-print">
                <a href="view_reports.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
        
        <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
        <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
            <i class="bi bi-check-circle me-2"></i> Report updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="two-col">
            <!-- Left Column -->
            <div>
                <!-- Report Status -->
                <div class="card">
                    <div class="card-header">
                        <h2>Report Status</h2>
                        <div>
                            <span class="badge <?php 
                                switch($report['status']) {
                                    case 'pending': echo 'badge-pending'; break;
                                    case 'in_progress': echo 'badge-in-progress'; break;
                                    case 'completed': echo 'badge-completed'; break;
                                    case 'referred': echo 'badge-referred'; break;
                                    case 'cancelled': echo 'badge-cancelled'; break;
                                    default: echo 'badge-pending';
                                }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                            </span>
                            <?php if ($report['biteType']): ?>
                            <span class="badge ms-1 <?php 
                                switch($report['biteType']) {
                                    case 'Category III': echo 'badge-cat-iii'; break;
                                    case 'Category II': echo 'badge-cat-ii'; break;
                                    case 'Category I': echo 'badge-cat-i'; break;
                                }
                            ?>">
                                <?php echo $report['biteType']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Report Date</label>
                                <span><?php echo date('M d, Y g:i A', strtotime($report['reportDate'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Bite Date</label>
                                <span><?php echo date('M d, Y', strtotime($report['biteDate'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Assigned Staff</label>
                                <span><?php echo htmlspecialchars($staffName); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Follow-up Date</label>
                                <span><?php echo !empty($report['followUpDate']) ? date('M d, Y', strtotime($report['followUpDate'])) : 'Not scheduled'; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Multiple Bites</label>
                                <span><?php echo $report['multipleBites'] ? 'Yes' : 'No'; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Last Updated</label>
                                <span><?php echo !empty($report['updated_at']) ? date('M d, Y g:i A', strtotime($report['updated_at'])) : 'Not updated'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Patient Information -->
                <div class="card">
                    <div class="card-header">
                        <h2>Patient Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Full Name</label>
                                <span><?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Date of Birth</label>
                                <span><?php echo date('M d, Y', strtotime($report['dateOfBirth'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Gender</label>
                                <span><?php echo htmlspecialchars($report['gender']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Contact Number</label>
                                <span><?php echo htmlspecialchars($report['contactNumber']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Email</label>
                                <span><?php echo htmlspecialchars($report['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Barangay</label>
                                <span><?php echo htmlspecialchars($report['barangay']); ?></span>
                            </div>
                            <div class="info-item" style="grid-column: span 2;">
                                <label>Address</label>
                                <span><?php echo htmlspecialchars($report['address'] . ', ' . $report['city']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- Bite Information -->
                <div class="card">
                    <div class="card-header">
                        <h2>Bite Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Animal Type</label>
                                <span><?php echo htmlspecialchars($report['animalType']); ?><?php if ($report['animalType'] === 'Other' && !empty($report['animalOtherType'])): ?> (<?php echo htmlspecialchars($report['animalOtherType']); ?>)<?php endif; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Animal Ownership</label>
                                <span><?php echo htmlspecialchars($report['animalOwnership']); ?></span>
                            </div>
                            <?php if (!empty($report['ownerName'])): ?>
                            <div class="info-item">
                                <label>Owner Name</label>
                                <span><?php echo htmlspecialchars($report['ownerName']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($report['ownerContact'])): ?>
                            <div class="info-item">
                                <label>Owner Contact</label>
                                <span><?php echo htmlspecialchars($report['ownerContact']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <label>Animal Status</label>
                                <span><?php echo htmlspecialchars($report['animalStatus']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Animal Vaccinated</label>
                                <span><?php echo htmlspecialchars($report['animalVaccinated']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Bite Location</label>
                                <span><?php echo htmlspecialchars($report['biteLocation']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Bite Type</label>
                                <span><?php echo htmlspecialchars($report['biteType']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Provoked</label>
                                <span><?php echo htmlspecialchars($report['provoked']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Washed with Soap</label>
                                <span><?php echo $report['washWithSoap'] ? 'Yes' : 'No'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Treatment Information -->
                <div class="card">
                    <div class="card-header">
                        <h2>Treatment Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Rabies Vaccine</label>
                                <span><?php echo $report['rabiesVaccine'] ? 'Yes' : 'No'; ?><?php if ($report['rabiesVaccine'] && !empty($report['rabiesVaccineDate'])): ?> (<?php echo date('M d, Y', strtotime($report['rabiesVaccineDate'])); ?>)<?php endif; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Anti-Tetanus</label>
                                <span><?php echo $report['antiTetanus'] ? 'Yes' : 'No'; ?><?php if ($report['antiTetanus'] && !empty($report['antiTetanusDate'])): ?> (<?php echo date('M d, Y', strtotime($report['antiTetanusDate'])); ?>)<?php endif; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Antibiotics</label>
                                <span><?php echo $report['antibiotics'] ? 'Yes' : 'No'; ?><?php if ($report['antibiotics'] && !empty($report['antibioticsDetails'])): ?> - <?php echo htmlspecialchars($report['antibioticsDetails']); ?><?php endif; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Referred to Hospital</label>
                                <span><?php echo $report['referredToHospital'] ? 'Yes' : 'No'; ?><?php if ($report['referredToHospital'] && !empty($report['hospitalName'])): ?> (<?php echo htmlspecialchars($report['hospitalName']); ?>)<?php endif; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Admin Notes and Actions -->
        <div class="card no-print">
            <div class="card-header">
                <h2>Admin Notes & Actions</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                        <div class="form-text">Add treatment notes, follow-up instructions, or other important information.</div>
                    </div>
                    
                    <div class="form-row-3">
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $report['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $report['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="referred" <?php echo $report['status'] === 'referred' ? 'selected' : ''; ?>>Referred</option>
                                <option value="cancelled" <?php echo $report['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label for="staffId" class="form-label">Assign Staff</label>
                            <select class="form-select" id="staffId" name="staffId">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach ($allStaff as $s): ?>
                                <option value="<?php echo $s['staffId']; ?>" <?php echo $report['staffId'] == $s['staffId'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['firstName'] . ' ' . $s['lastName']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="followUpDate" class="form-label">Follow-up Date</label>
                            <input type="date" class="form-control" id="followUpDate" name="followUpDate" value="<?php echo !empty($report['followUpDate']) ? date('Y-m-d', strtotime($report['followUpDate'])) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_report" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            &copy; <?php echo date('Y'); ?> Barangay Health Workers Management System
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
