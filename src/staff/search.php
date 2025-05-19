<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header("Location: ../staff_login.html");
    exit;
}

require_once '../conn/conn.php';

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$patients = [];
$reports = [];

// Pagination
$patientsPage = isset($_GET['patients_page']) ? max(1, (int)$_GET['patients_page']) : 1;
$reportsPage = isset($_GET['reports_page']) ? max(1, (int)$_GET['reports_page']) : 1;
$perPage = 10;
$patientsOffset = ($patientsPage - 1) * $perPage;
$reportsOffset = ($reportsPage - 1) * $perPage;
$totalPatients = 0;
$totalReports = 0;

function highlight($text, $term) {
    if (!$term) return htmlspecialchars($text);
    return preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', htmlspecialchars($text));
}

if ($search !== '') {
    // Filtered search
    $patientStmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM patients WHERE firstName LIKE ? OR lastName LIKE ? OR contactNumber LIKE ? OR barangay LIKE ? ORDER BY lastName, firstName LIMIT $patientsOffset, $perPage");
    $like = "%$search%";
    $patientStmt->execute([$like, $like, $like, $like]);
    $patients = $patientStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalPatients = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    $reportStmt = $pdo->prepare("
        SELECT SQL_CALC_FOUND_ROWS r.*, p.firstName, p.lastName, p.contactNumber, p.barangay
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportId LIKE ?
           OR p.firstName LIKE ?
           OR p.lastName LIKE ?
           OR p.contactNumber LIKE ?
           OR r.animalType LIKE ?
           OR r.biteType LIKE ?
        ORDER BY r.reportDate DESC
        LIMIT $reportsOffset, $perPage
    ");
    $reportStmt->execute([
        $like, $like, $like, $like, $like, $like
    ]);
    $reports = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalReports = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
} else {
    // Show all (paginated)
    $patientStmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM patients ORDER BY lastName, firstName LIMIT $patientsOffset, $perPage");
    $patientStmt->execute();
    $patients = $patientStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalPatients = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    $reportStmt = $pdo->prepare("
        SELECT SQL_CALC_FOUND_ROWS r.*, p.firstName, p.lastName, p.contactNumber, p.barangay
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        ORDER BY r.reportDate DESC
        LIMIT $reportsOffset, $perPage
    ");
    $reportStmt->execute();
    $reports = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalReports = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
}

$totalPatientsPages = max(1, ceil($totalPatients / $perPage));
$totalReportsPages = max(1, ceil($totalReports / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search | BHW Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .search-hero {
            background: linear-gradient(90deg, #28a745 0%, #218838 100%);
            color: #fff;
            padding: 2.5rem 0 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(40,167,69,0.08);
        }
        .search-hero .bi {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .search-bar-sticky {
            /* No sticky, just normal spacing */
            background: #f8f9fa;
            padding-top: 1rem;
        }
        .summary-stats {
            margin-bottom: 2rem;
        }
        .summary-stats .stat-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            padding: 1.25rem 1rem;
            text-align: center;
        }
        .result-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: #28a745;
        }
        .result-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            padding: 1.25rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: box-shadow 0.2s;
        }
        .result-card:hover {
            box-shadow: 0 6px 24px rgba(40,167,69,0.12);
        }
        .result-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #28a745;
        }
        .result-info {
            flex: 1;
        }
        .result-actions {
            min-width: 100px;
            text-align: right;
        }
        .no-results {
            color: #888;
            font-style: italic;
            text-align: center;
            margin: 2rem 0;
        }
        .empty-illustration {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 1rem;
        }
        .show-all-btn {
            margin-bottom: 2rem;
        }
        .pagination { justify-content: center; }
        @media (max-width: 768px) {
            .result-card { flex-direction: column; align-items: flex-start; }
            .result-actions { text-align: left; margin-top: 1rem; }
        }
    </style>
</head>
<body>
<?php $activePage = 'search'; include 'navbar.php'; ?>
<div class="search-hero text-center">
    <div class="container">
        <i class="bi bi-search"></i>
        <h1 class="fw-bold mb-2">Search Patients & Reports</h1>
        <p class="mb-0">Find patient records or animal bite reports quickly and easily.</p>
    </div>
</div>
<div class="container">
    <div class="search-bar-sticky">
        <form method="get" class="mb-4">
            <div class="input-group input-group-lg">
                <input type="text" class="form-control" name="q" placeholder="Search patients or reports..." value="<?php echo htmlspecialchars($search); ?>" autofocus>
                <button class="btn btn-success" type="submit"><i class="bi bi-search"></i> Search</button>
                <?php if ($search !== ''): ?>
                <a href="search.php" class="btn btn-outline-secondary show-all-btn"><i class="bi bi-x-circle"></i> Show All</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="summary-stats row g-3 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="fw-bold fs-4 text-success"><i class="bi bi-people me-2"></i><?php echo $totalPatients; ?></div>
                <div class="text-muted"><?php echo $search !== '' ? 'Patients Found' : 'All Patients'; ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="fw-bold fs-4 text-success"><i class="bi bi-file-earmark-text me-2"></i><?php echo $totalReports; ?></div>
                <div class="text-muted"><?php echo $search !== '' ? 'Reports Found' : 'All Reports'; ?></div>
            </div>
        </div>
    </div>

    <div class="result-section-title"><i class="bi bi-people me-2"></i><?php echo $search !== '' ? 'Patients' : 'All Patients'; ?></div>
    <?php if (count($patients) > 0): ?>
        <div class="row g-3">
        <?php foreach ($patients as $p): ?>
            <div class="col-md-6 col-lg-4">
                <div class="result-card">
                    <div class="result-avatar bg-success bg-opacity-10">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="result-info">
                        <div class="fw-bold fs-5 mb-1"><?php echo highlight($p['lastName'] . ', ' . $p['firstName'], $search); ?></div>
                        <div class="mb-1"><i class="bi bi-telephone me-1"></i> <?php echo highlight($p['contactNumber'], $search); ?></div>
                        <div class="mb-1"><i class="bi bi-geo-alt me-1"></i> <?php echo highlight($p['barangay'], $search); ?></div>
                    </div>
                    <div class="result-actions">
                        <a href="view_patient.php?id=<?php echo $p['patientId']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i> View</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php if ($totalPatientsPages > 1): ?>
        <nav>
            <ul class="pagination mt-3">
                <?php for ($i = 1; $i <= $totalPatientsPages; $i++): ?>
                    <li class="page-item <?php echo $i === $patientsPage ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['patients_page' => $i, 'reports_page' => $reportsPage])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-results">
            <div class="empty-illustration"><i class="bi bi-person-x"></i></div>
            No patients found.
        </div>
    <?php endif; ?>

    <div class="result-section-title"><i class="bi bi-file-earmark-text me-2"></i><?php echo $search !== '' ? 'Reports' : 'All Reports'; ?></div>
    <?php if (count($reports) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
                <thead class="table-light">
                    <tr>
                        <th>Report #</th>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Barangay</th>
                        <th>Animal</th>
                        <th>Bite Type</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td><?php echo highlight($r['reportId'], $search); ?></td>
                        <td><?php echo highlight($r['lastName'] . ', ' . $r['firstName'], $search); ?></td>
                        <td><?php echo highlight($r['contactNumber'], $search); ?></td>
                        <td><?php echo highlight($r['barangay'], $search); ?></td>
                        <td><?php echo highlight($r['animalType'], $search); ?></td>
                        <td><span class="badge bg-success bg-opacity-25 text-success"><?php echo highlight($r['biteType'], $search); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($r['reportDate'])); ?></td>
                        <td><a href="view_report.php?id=<?php echo $r['reportId']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i> View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalReportsPages > 1): ?>
        <nav>
            <ul class="pagination mt-3">
                <?php for ($i = 1; $i <= $totalReportsPages; $i++): ?>
                    <li class="page-item <?php echo $i === $reportsPage ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['patients_page' => $patientsPage, 'reports_page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-results">
            <div class="empty-illustration"><i class="bi bi-file-earmark-x"></i></div>
            No reports found.
        </div>
    <?php endif; ?>
</div>
</body>
</html> 