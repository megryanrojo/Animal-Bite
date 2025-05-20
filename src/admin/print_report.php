<?php
// Start output buffering to prevent "headers already sent" issues
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['staffId']) || empty($_SESSION['staffId'])) {
    ob_end_clean();
    header("Location: ../login/admin_login.php");
    exit;
}

// Include database connection
require_once '../conn/conn.php';

// Get admin information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        session_destroy();
        ob_end_clean();
        header("Location: ../login/admin_login.php");
        exit;
    }
} catch (PDOException $e) {
    $admin = ['firstName' => 'User', 'lastName' => ''];
}

// Check if report ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    ob_end_clean();
    header("Location: reports.php");
    exit;
}

$reportId = (int)$_GET['id'];

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
        header("Location: reports.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Report | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .container {
                width: 100%;
                max-width: none;
                padding: 0;
                margin: 0;
            }
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .print-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: white;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #0d6efd;
        }
        
        .header h1 {
            color: #0d6efd;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            color: #0d6efd;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .info-item {
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            font-weight: 500;
            color: #495057;
        }
        
        .info-value {
            color: #212529;
        }
        
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
        }
        
        .print-actions {
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .print-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Actions -->
        <div class="print-actions no-print">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="view_report.php?id=<?php echo $reportId; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Report
                    </a>
                </div>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer me-2"></i>Print Report
                    </button>
                </div>
            </div>
        </div>

        <div class="print-container">
            <!-- Header -->
            <div class="header">
                <h1>Animal Bite Treatment Center</h1>
                <p>Animal Bite Report</p>
            </div>

            <!-- Patient Information -->
            <div class="section">
                <h2 class="section-title">Patient Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['contactNumber'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Barangay:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['barangay']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Address:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['address'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Animal Bite Details -->
            <div class="section">
                <h2 class="section-title">Animal Bite Details</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Date of Bite:</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($report['biteDate'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Animal Type:</span>
                        <span class="info-value">
                            <?php 
                            echo htmlspecialchars($report['animalType']);
                            if ($report['animalType'] === 'Other' && !empty($report['animalOtherType'])) {
                                echo ' (' . htmlspecialchars($report['animalOtherType']) . ')';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Animal Ownership:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['animalOwnership']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Animal Status:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['animalStatus']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Animal Vaccinated:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['animalVaccinated']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Provoked:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['provoked']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Multiple Bites:</span>
                        <span class="info-value"><?php echo $report['multipleBites'] ? 'Yes' : 'No'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Bite Location and Classification -->
            <div class="section">
                <h2 class="section-title">Bite Location & Classification</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Bite Location:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['biteLocation']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Bite Category:</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['biteType']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Treatment Information -->
            <div class="section">
                <h2 class="section-title">Treatment Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Wound Washed with Soap:</span>
                        <span class="info-value"><?php echo $report['washWithSoap'] ? 'Yes' : 'No'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Rabies Vaccine:</span>
                        <span class="info-value">
                            <?php 
                            echo $report['rabiesVaccine'] ? 'Yes' : 'No';
                            if ($report['rabiesVaccine'] && !empty($report['rabiesVaccineDate'])) {
                                echo ' (Date: ' . date('F d, Y', strtotime($report['rabiesVaccineDate'])) . ')';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Anti-tetanus:</span>
                        <span class="info-value">
                            <?php 
                            echo $report['antiTetanus'] ? 'Yes' : 'No';
                            if ($report['antiTetanus'] && !empty($report['antiTetanusDate'])) {
                                echo ' (Date: ' . date('F d, Y', strtotime($report['antiTetanusDate'])) . ')';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Antibiotics:</span>
                        <span class="info-value">
                            <?php 
                            echo $report['antibiotics'] ? 'Yes' : 'No';
                            if ($report['antibiotics'] && !empty($report['antibioticsDetails'])) {
                                echo ' (' . htmlspecialchars($report['antibioticsDetails']) . ')';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Referred to Hospital:</span>
                        <span class="info-value">
                            <?php 
                            echo $report['referredToHospital'] ? 'Yes' : 'No';
                            if ($report['referredToHospital'] && !empty($report['hospitalName'])) {
                                echo ' (' . htmlspecialchars($report['hospitalName']) . ')';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Follow-up Date:</span>
                        <span class="info-value">
                            <?php echo !empty($report['followUpDate']) ? date('F d, Y', strtotime($report['followUpDate'])) : 'N/A'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Case Status:</span>
                        <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <?php if (!empty($report['notes'])): ?>
            <div class="section">
                <h2 class="section-title">Additional Notes</h2>
                <div class="info-item">
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($report['notes'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer">
                <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
                <p>Animal Bite Treatment Center - Official Report</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// End output buffering and send output to browser
ob_end_flush();
?> 