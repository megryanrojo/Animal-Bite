<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: view_patients.php');
    exit;
}
$patientId = (int)$_GET['id'];
$error = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patientId = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        header('Location: view_patients.php');
        exit;
    }
    // Get patient's reports
    $reportsStmt = $pdo->prepare("
        SELECT r.*, CONCAT(s.firstName, ' ', s.lastName) as staffName
        FROM reports r
        LEFT JOIN staff s ON r.staffId = s.staffId
        WHERE r.patientId = ?
        ORDER BY r.reportDate DESC
    ");
    $reportsStmt->execute([$patientId]);
    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $reports = [];
}

function calculateAge($dateOfBirth) {
    if (empty($dateOfBirth)) return 'N/A';
    $dob = new DateTime($dateOfBirth);
    $now = new DateTime();
    $interval = $now->diff($dob);
    return $interval->y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Details | Animal Bite Center Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-rgb: 13, 110, 253;
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
        .patient-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
            flex-grow: 1;
        }
        .content-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .content-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .content-card-body {
            padding: 1.5rem;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--bs-primary);
            display: flex;
            align-items: center;
        }
        .section-title i { margin-right: 0.5rem; }
        .info-label { font-weight: 600; color: #6c757d; }
        .info-value { margin-bottom: 0.5rem; }
        .table th {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            font-weight: 600;
            border-top: none;
        }
        .table td, .table th { padding: 1rem; vertical-align: middle; }
        .table tbody tr { transition: all 0.2s; }
        .table tbody tr:hover { background-color: rgba(var(--bs-primary-rgb), 0.03); }
        .badge-category-i { background-color: #6c757d; color: white; }
        .badge-category-ii { background-color: #ffc107; color: #212529; }
        .badge-category-iii { background-color: #dc3545; color: white; }
        .badge-pending { background-color: #17a2b8; color: white; }
        .badge-in-progress { background-color: #007bff; color: white; }
        .badge-completed { background-color: #28a745; color: white; }
        .badge-referred { background-color: #6c757d; color: white; }
        .empty-state { text-align: center; padding: 2rem 1rem; }
        .empty-state-icon { font-size: 2.5rem; color: #adb5bd; margin-bottom: 1rem; }
        @media (max-width: 768px) {
            .patient-container { padding: 1rem; }
            .content-card-header, .content-card-body { padding: 1rem; }
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="patient-container">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error!</strong> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <!-- Patient Header -->
    <div class="content-card mb-4">
        <div class="content-card-header">
            <div>
                <h2 class="mb-0"><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></h2>
                <p class="text-muted mb-0">
                    Patient ID: <?php echo $patient['patientId']; ?> |
                    Age: <?php echo calculateAge($patient['dateOfBirth']); ?> |
                    Gender: <?php echo htmlspecialchars($patient['gender'] ?? 'Not specified'); ?>
                </p>
            </div>
        </div>
    </div>
    <!-- Patient Information -->
    <div class="row g-4">
        <!-- Personal Information -->
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5 class="mb-0"><i class="bi bi-person me-2"></i>Personal Information</h5>
                </div>
                <div class="content-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-label">First Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($patient['firstName']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Last Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($patient['lastName']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?php echo !empty($patient['dateOfBirth']) ? date('M d, Y', strtotime($patient['dateOfBirth'])) : 'Not provided'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Gender</div>
                            <div class="info-value"><?php echo htmlspecialchars($patient['gender'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Contact Information -->
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5 class="mb-0"><i class="bi bi-telephone me-2"></i>Contact Information</h5>
                </div>
                <div class="content-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value"><?php echo !empty($patient['contactNumber']) ? htmlspecialchars($patient['contactNumber']) : 'Not provided'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Barangay</div>
                            <div class="info-value"><?php echo htmlspecialchars($patient['barangay']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Patient Reports -->
    <div class="content-card mt-4">
        <div class="content-card-header">
            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Bite Reports</h5>
        </div>
        <div class="content-card-body p-0">
            <?php if (count($reports) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Date Reported</th>
                            <th>Animal Type</th>
                            <th>Bite Date</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo $report['reportId']; ?></td>
                            <td><?php echo isset($report['reportDate']) ? date('M d, Y', strtotime($report['reportDate'])) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($report['animalType'] ?? 'Unknown'); ?></td>
                            <td><?php echo isset($report['biteDate']) ? date('M d, Y', strtotime($report['biteDate'])) : 'N/A'; ?></td>
                            <td>
                                <?php 
                                    $biteType = $report['biteType'] ?? 'Category II';
                                    $biteTypeClass = 'badge-' . strtolower(str_replace(' ', '-', $biteType));
                                    echo '<span class="badge ' . $biteTypeClass . '">' . $biteType . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $status = $report['status'] ?? 'pending';
                                    $statusClass = 'badge-' . str_replace('_', '-', strtolower($status));
                                    echo '<span class="badge ' . $statusClass . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($report['staffName'] ?? 'Unknown'); ?></td>
                            <td>
                                <a href="view_report.php?id=<?php echo $report['reportId']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <h5>No Reports Found</h5>
                <p class="text-muted">This patient has no bite reports on record</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Action Buttons -->
    <div class="d-flex justify-content-between mt-4">
        <a href="view_patients.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Patients
        </a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 