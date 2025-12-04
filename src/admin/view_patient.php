<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/vaccination_helper_v2.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: view_patients.php');
    exit;
}
$patientId = (int)$_GET['id'];
$error = '';
$success = '';

// Handle Vaccination CRUD actions (PrEP only on patient view)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vaccination_action'])) {
    $action = $_POST['vaccination_action'];

    try {
        if ($action === 'add' || $action === 'edit') {
            // On patient view only allow Pre-Exposure entries (PrEP)
            $exposureType   = 'PrEP';
            $doseNumber     = isset($_POST['doseNumber']) ? (int)$_POST['doseNumber'] : 1;
            $dateGiven      = !empty($_POST['dateGiven']) ? $_POST['dateGiven'] : null;
            $vaccineName    = !empty($_POST['vaccineName']) ? $_POST['vaccineName'] : null;
            $administeredBy = !empty($_POST['administeredBy']) ? $_POST['administeredBy'] : null;
            $remarks        = !empty($_POST['remarks']) ? $_POST['remarks'] : null;
            // Ensure patient-level vaccinations created here are not linked to a report
            $reportId       = null;

            if ($action === 'add') {
                $insertStmt = $pdo->prepare("
                    INSERT INTO vaccination_records
                        (patientId, reportId, exposureType, doseNumber, dateGiven, vaccineName, administeredBy, remarks)
                    VALUES
                        (:patientId, :reportId, :exposureType, :doseNumber, :dateGiven, :vaccineName, :administeredBy, :remarks)
                ");
                $insertStmt->execute([
                    ':patientId'      => $patientId,
                    ':reportId'       => $reportId,
                    ':exposureType'   => $exposureType,
                    ':doseNumber'     => $doseNumber,
                    ':dateGiven'      => $dateGiven,
                    ':vaccineName'    => $vaccineName,
                    ':administeredBy' => $administeredBy,
                    ':remarks'        => $remarks,
                ]);
                $success = 'Vaccination dose added successfully.';
            } elseif ($action === 'edit' && isset($_POST['vaccinationId']) && is_numeric($_POST['vaccinationId'])) {
                $vaccinationId = (int)$_POST['vaccinationId'];
                $updateStmt = $pdo->prepare("
                    UPDATE vaccination_records
                    SET exposureType = :exposureType,
                        doseNumber = :doseNumber,
                        dateGiven = :dateGiven,
                        vaccineName = :vaccineName,
                        administeredBy = :administeredBy,
                        remarks = :remarks,
                        reportId = :reportId,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE vaccinationId = :vaccinationId AND patientId = :patientId
                ");
                $updateStmt->execute([
                    ':exposureType'   => $exposureType,
                    ':doseNumber'     => $doseNumber,
                    ':dateGiven'      => $dateGiven,
                    ':vaccineName'    => $vaccineName,
                    ':administeredBy' => $administeredBy,
                    ':remarks'        => $remarks,
                    ':reportId'       => $reportId,
                    ':vaccinationId'  => $vaccinationId,
                    ':patientId'      => $patientId,
                ]);
                $success = 'Vaccination dose updated successfully.';
            }
        } elseif ($action === 'delete' && isset($_POST['vaccinationId']) && is_numeric($_POST['vaccinationId'])) {
            $vaccinationId = (int)$_POST['vaccinationId'];
            $deleteStmt = $pdo->prepare("
                DELETE FROM vaccination_records
                WHERE vaccinationId = :vaccinationId AND patientId = :patientId
            ");
            $deleteStmt->execute([
                ':vaccinationId' => $vaccinationId,
                ':patientId'     => $patientId,
            ]);
            $success = 'Vaccination dose deleted successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Error saving vaccination record: ' . $e->getMessage();
    }

    header("Location: view_patient.php?id=" . $patientId . ($success ? "&vacc_updated=1" : "&vacc_error=1"));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patientId = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        header('Location: view_patients.php');
        exit;
    }
    $reportsStmt = $pdo->prepare("
        SELECT r.*, CONCAT(s.firstName, ' ', s.lastName) as staffName
        FROM reports r
        LEFT JOIN staff s ON r.staffId = s.staffId
        WHERE r.patientId = ?
        ORDER BY r.reportDate DESC
    ");
    $reportsStmt->execute([$patientId]);
    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

    $vaccStmt = $pdo->prepare("
        SELECT vr.*, r.biteDate
        FROM vaccination_records vr
        LEFT JOIN reports r ON vr.reportId = r.reportId
        WHERE vr.patientId = ?
        ORDER BY vr.dateGiven DESC, vr.doseNumber ASC
    ");
    $vaccStmt->execute([$patientId]);
    $vaccinations = $vaccStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $reports = [];
    $vaccinations = [];
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
    <title>Patient Details | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin/view-patient.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <a href="view_patients.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Patients
        </a>
    </div>

    <!-- Alerts -->
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
        <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['vacc_updated']) && $_GET['vacc_updated'] == 1): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i>
        <span>Vaccination record saved successfully.</span>
        <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Patient Header - removed Edit Patient button -->
    <div class="patient-header">
        <div class="patient-avatar">
            <?php echo strtoupper(substr($patient['firstName'], 0, 1) . substr($patient['lastName'], 0, 1)); ?>
        </div>
        <div class="patient-info">
            <h1 class="patient-name"><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></h1>
            <div class="patient-meta">
                <span><i class="bi bi-hash"></i> ID: <?php echo $patient['patientId']; ?></span>
                <span><i class="bi bi-calendar"></i> Age: <?php echo calculateAge($patient['dateOfBirth']); ?></span>
                <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($patient['gender'] ?? 'Not specified'); ?></span>
                <span><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($patient['barangay']); ?></span>
            </div>
        </div>
        <!-- Removed the edit button from here as it's not part of the update -->
    </div>

    <!-- Two column layout for info cards -->
    <div class="info-cards-row">
        <!-- Personal Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="bi bi-person"></i> Personal Information</h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">First Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['firstName']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['lastName']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo !empty($patient['dateOfBirth']) ? date('M d, Y', strtotime($patient['dateOfBirth'])) : 'Not provided'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['gender'] ?? 'Not specified'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Contact Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="bi bi-telephone"></i> Contact Information</h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Contact Number</div>
                        <div class="info-value"><?php echo !empty($patient['contactNumber']) ? htmlspecialchars($patient['contactNumber']) : 'Not provided'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Barangay</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['barangay']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vaccination Records - Separated PEP and PrEP -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="bi bi-syringe"></i> Vaccination Records</h2>
        </div>

        <!-- PrEP Section (Patient-Level Preventive Vaccinations) -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title"><i class="bi bi-shield-check"></i> PrEP (Preventive)</h3>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vaccinationModal"
                        data-vaccination-id="" data-exposure-type="PrEP" data-dose-number="1"
                        data-date-given="" data-vaccine-name=""
                        data-administered-by="" data-remarks="" data-report-id="">
                    <i class="bi bi-plus"></i> Add PrEP Dose
                </button>
            </div>

            <?php 
            $prepVaccinations = !empty($vaccinations) ? array_filter($vaccinations, fn($v) => $v['exposureType'] === 'PrEP') : [];
            ?>

            <?php if (!empty($prepVaccinations)): ?>
            <div class="table-container mobile-cards" style="margin-bottom: 20px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Dose</th>
                            <th>Vaccine</th>
                            <th>Administered By</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prepVaccinations as $vacc): ?>
                        <tr>
                            <td data-label="Date"><?php echo $vacc['dateGiven'] ? date('M d, Y', strtotime($vacc['dateGiven'])) : 'N/A'; ?></td>
                            <td data-label="Dose"><?php echo (int)$vacc['doseNumber']; ?></td>
                            <td data-label="Vaccine"><?php echo htmlspecialchars($vacc['vaccineName'] ?? '-'); ?></td>
                            <td data-label="Administered By"><?php echo htmlspecialchars($vacc['administeredBy'] ?? '-'); ?></td>
                            <td data-label="Remarks" style="max-width: 180px;">
                                <span style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($vacc['remarks'] ?? '-'); ?>
                                </span>
                            </td>
                            <td data-label="">
                                <div class="action-cell">
                                    <button class="btn btn-outline btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#vaccinationModal"
                                        data-vaccination-id="<?php echo (int)$vacc['vaccinationId']; ?>"
                                        data-exposure-type="<?php echo htmlspecialchars($vacc['exposureType']); ?>"
                                        data-dose-number="<?php echo (int)$vacc['doseNumber']; ?>"
                                        data-date-given="<?php echo htmlspecialchars($vacc['dateGiven']); ?>"
                                        data-vaccine-name="<?php echo htmlspecialchars($vacc['vaccineName'] ?? ''); ?>"
                                        data-administered-by="<?php echo htmlspecialchars($vacc['administeredBy'] ?? ''); ?>"
                                        data-remarks="<?php echo htmlspecialchars($vacc['remarks'] ?? ''); ?>"
                                        data-report-id="<?php echo htmlspecialchars($vacc['reportId'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this PrEP dose?');" style="display:inline-block; margin-left:6px;">
                                        <input type="hidden" name="vaccination_action" value="delete">
                                        <input type="hidden" name="vaccinationId" value="<?php echo (int)$vacc['vaccinationId']; ?>">
                                        <button type="submit" class="btn btn-danger btn-icon btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="background: #fffbeb; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                <i class="bi bi-info-circle" style="color: #f59e0b;"></i>
                <p style="color: #78350f; margin-bottom: 10px;">No PrEP doses recorded.</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vaccinationModal"
                        data-vaccination-id="" data-exposure-type="PrEP" data-dose-number="1"
                        data-date-given="" data-vaccine-name=""
                        data-administered-by="" data-remarks="" data-report-id="">
                    <i class="bi bi-plus"></i> Add First PrEP Dose
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- PEP Section (Report-Linked Post-Exposure Prophylaxis) -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title"><i class="bi bi-shield-exclamation"></i> PEP (Post-Exposure)</h3>
                <small style="color: #666;">Linked to bite reports - managed per incident</small>
            </div>

            <?php 
            $pepVaccinations = !empty($vaccinations) ? array_filter($vaccinations, fn($v) => $v['exposureType'] === 'PEP') : [];
            ?>

            <?php if (!empty($pepVaccinations)): ?>
            <div class="table-container mobile-cards">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Dose</th>
                            <th>Vaccine</th>
                            <th>By</th>
                            <th>Linked Report</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pepVaccinations as $vacc): ?>
                        <tr>
                            <td data-label="Date"><?php echo $vacc['dateGiven'] ? date('M d, Y', strtotime($vacc['dateGiven'])) : 'N/A'; ?></td>
                            <td data-label="Dose"><?php echo (int)$vacc['doseNumber']; ?></td>
                            <td data-label="Vaccine"><?php echo htmlspecialchars($vacc['vaccineName'] ?? '-'); ?></td>
                            <td data-label="By"><?php echo htmlspecialchars($vacc['administeredBy'] ?? '-'); ?></td>
                            <td data-label="Report">
                                <?php if (!empty($vacc['reportId'])): ?>
                                    <a href="view_report.php?id=<?php echo (int)$vacc['reportId']; ?>" style="color: var(--primary); font-weight: 500;">#<?php echo (int)$vacc['reportId']; ?></a>
                                <?php else: ?>
                                    <span style="color: var(--secondary);">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Remarks" style="max-width: 180px;">
                                <span style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($vacc['remarks'] ?? '-'); ?>
                                </span>
                            </td>
                            <td data-label="">
                                <div class="action-cell">
                                    <?php if (!empty($vacc['reportId'])): ?>
                                        <a href="view_report.php?id=<?php echo (int)$vacc['reportId']; ?>" class="btn btn-outline btn-sm" title="Manage PEP on linked report">
                                            <i class="bi bi-box-arrow-up-right"></i> Manage on Report
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Managed on report</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="background: #fee2e2; border-left: 4px solid #dc2626; margin-top: 0;">
                <i class="bi bi-info-circle" style="color: #dc2626;"></i>
                <p style="color: #7f1d1d;">No PEP doses recorded. PEP vaccinations are managed from bite reports.</p>
                <small style="color: #991b1b; display: block; margin-top: 8px;">
                    <i class="bi bi-arrow-right"></i> Create a bite report to start PEP vaccination schedule
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- Bite Reports -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="bi bi-file-earmark-text"></i> Bite Reports</h2>
        </div>
        <?php if (count($reports) > 0): ?>
        <div class="table-container mobile-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date Reported</th>
                        <th>Animal</th>
                        <th>Bite Date</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Recorded By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                    <tr>
                        <!-- Bold report ID -->
                        <td data-label="ID"><strong>#<?php echo $report['reportId']; ?></strong></td>
                        <td data-label="Reported"><?php echo isset($report['reportDate']) ? date('M d, Y', strtotime($report['reportDate'])) : 'N/A'; ?></td>
                        <td data-label="Animal"><?php echo htmlspecialchars($report['animalType'] ?? 'Unknown'); ?></td>
                        <td data-label="Bite Date"><?php echo isset($report['biteDate']) ? date('M d, Y', strtotime($report['biteDate'])) : 'N/A'; ?></td>
                        <td data-label="Category">
                            <?php 
                                $biteType = $report['biteType'] ?? 'Category II';
                                $catClass = 'badge-cat-ii';
                                if (stripos($biteType, 'III') !== false) $catClass = 'badge-cat-iii';
                                elseif (stripos($biteType, 'I') !== false && stripos($biteType, 'II') === false) $catClass = 'badge-cat-i';
                            ?>
                            <span class="badge <?php echo $catClass; ?>"><?php echo $biteType; ?></span>
                        </td>
                        <td data-label="Status">
                            <?php 
                                $status = $report['status'] ?? 'pending';
                                $statusClass = 'badge-' . str_replace('_', '-', strtolower($status));
                            ?>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                        </td>
                        <td data-label="Recorded By"><?php echo htmlspecialchars($report['staffName'] ?? 'Unknown'); ?></td>
                        <td data-label="">
                            <a href="view_report.php?id=<?php echo $report['reportId']; ?>" class="btn btn-outline btn-icon btn-sm">
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
            <i class="bi bi-file-earmark-text"></i>
            <h5>No Reports Found</h5>
            <p>This patient has no bite reports on record.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Vaccination Modal -->
<div class="modal fade" id="vaccinationModal" tabindex="-1" aria-labelledby="vaccinationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="vaccinationModalLabel">Add Vaccination Dose</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="vaccination_action" id="vaccinationAction" value="add">
                    <input type="hidden" name="vaccinationId" id="vaccinationId" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Exposure Type</label>
                            <div class="form-control" style="background:#fff;">Pre-Exposure (PrEP)</div>
                            <input type="hidden" name="exposureType" id="exposureType" value="PrEP">
                            <div class="form-text">Post-Exposure (PEP) vaccinations are managed on individual reports.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dose Number</label>
                            <input type="number" min="1" class="form-control" name="doseNumber" id="doseNumber" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date Given</label>
                            <input type="date" class="form-control" name="dateGiven" id="dateGiven" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vaccine Name</label>
                            <input type="text" class="form-control" name="vaccineName" id="vaccineName" placeholder="e.g., Verorab, Rabipur">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Administered By</label>
                            <input type="text" class="form-control" name="administeredBy" id="administeredBy" placeholder="Health worker name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Linked Report (Not applicable)</label>
                            <div class="form-control" style="background:#fff;">Patient-level PrEP (not linked to reports)</div>
                            <input type="hidden" name="reportId" id="reportId" value="">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="remarks" rows="3" placeholder="Schedule, adverse reactions, notes"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Dose</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const vaccinationModal = document.getElementById('vaccinationModal');
    if (vaccinationModal) {
        vaccinationModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const vaccinationId = button.getAttribute('data-vaccination-id');

            const modalTitle = vaccinationModal.querySelector('.modal-title');
            const actionInput = document.getElementById('vaccinationAction');
            const idInput = document.getElementById('vaccinationId');

            const exposureType = document.getElementById('exposureType');
            const doseNumber = document.getElementById('doseNumber');
            const dateGiven = document.getElementById('dateGiven');
            const vaccineName = document.getElementById('vaccineName');
            const administeredBy = document.getElementById('administeredBy');
            const reportId = document.getElementById('reportId');
            const remarks = document.getElementById('remarks');

            if (vaccinationId) {
                modalTitle.textContent = 'Edit Vaccination Dose';
                actionInput.value = 'edit';
                idInput.value = vaccinationId;

                exposureType.value = button.getAttribute('data-exposure-type') || 'PrEP';
                doseNumber.value = button.getAttribute('data-dose-number') || '1';
                dateGiven.value = button.getAttribute('data-date-given') || '';
                vaccineName.value = button.getAttribute('data-vaccine-name') || '';
                administeredBy.value = button.getAttribute('data-administered-by') || '';
                remarks.value = button.getAttribute('data-remarks') || '';

                const linkedReportId = button.getAttribute('data-report-id') || '';
                if (reportId) {
                    reportId.value = linkedReportId;
                }
            } else {
                modalTitle.textContent = 'Add Vaccination Dose';
                actionInput.value = 'add';
                idInput.value = '';

                exposureType.value = button.getAttribute('data-exposure-type') || 'PrEP';
                doseNumber.value = button.getAttribute('data-dose-number') || '1';
                dateGiven.value = button.getAttribute('data-date-given') || '';
                vaccineName.value = button.getAttribute('data-vaccine-name') || '';
                administeredBy.value = button.getAttribute('data-administered-by') || '';
                remarks.value = button.getAttribute('data-remarks') || '';

                if (reportId) {
                    reportId.value = button.getAttribute('data-report-id') || '';
                }
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
