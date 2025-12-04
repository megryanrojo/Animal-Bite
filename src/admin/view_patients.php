<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Initialize filter variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$minAge = isset($_GET['min_age']) ? (int)$_GET['min_age'] : '';
$maxAge = isset($_GET['max_age']) ? (int)$_GET['max_age'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$hasReports = isset($_GET['has_reports']) ? $_GET['has_reports'] : '';
$hasContact = isset($_GET['has_contact']) ? $_GET['has_contact'] : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 15;
$offset = ($page - 1) * $recordsPerPage;

// Build the query
$query = "
    SELECT p.*, 
           (SELECT COUNT(*) FROM reports r WHERE r.patientId = p.patientId) as reportCount,
           (SELECT MAX(reportDate) FROM reports r WHERE r.patientId = p.patientId) as lastVisit
    FROM patients p
    WHERE 1=1
";

$countQuery = "SELECT COUNT(*) FROM patients p WHERE 1=1";
$params = [];

// Add filters to query
if (!empty($search)) {
    $searchTerm = "%$search%";
    $query .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR p.patientId LIKE ? )";
    $countQuery .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR p.patientId LIKE ? )";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if (!empty($gender)) {
    $query .= " AND p.gender = ?";
    $countQuery .= " AND p.gender = ?";
    $params[] = $gender;
}
if (!empty($barangay)) {
    $query .= " AND p.barangay = ?";
    $countQuery .= " AND p.barangay = ?";
    $params[] = $barangay;
}

// Age range filter
if (!empty($minAge) && is_numeric($minAge)) {
    $query .= " AND TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) >= ?";
    $countQuery .= " AND TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) >= ?";
    $params[] = $minAge;
}
if (!empty($maxAge) && is_numeric($maxAge)) {
    $query .= " AND TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) <= ?";
    $countQuery .= " AND TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) <= ?";
    $params[] = $maxAge;
}

// Date range filter
if (!empty($dateFrom)) {
    $query .= " AND DATE(p.created_at) >= ?";
    $countQuery .= " AND DATE(p.created_at) >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $query .= " AND DATE(p.created_at) <= ?";
    $countQuery .= " AND DATE(p.created_at) <= ?";
    $params[] = $dateTo;
}

// Has reports filter
if ($hasReports === 'yes') {
    $query .= " AND (SELECT COUNT(*) FROM reports r WHERE r.patientId = p.patientId) > 0";
    $countQuery .= " AND (SELECT COUNT(*) FROM reports r WHERE r.patientId = p.patientId) > 0";
} elseif ($hasReports === 'no') {
    $query .= " AND (SELECT COUNT(*) FROM reports r WHERE r.patientId = p.patientId) = 0";
    $countQuery .= " AND (SELECT COUNT(*) FROM reports r WHERE r.patientId = p.patientId) = 0";
}

// Has contact filter
if ($hasContact === 'yes') {
    $query .= " AND p.contactNumber IS NOT NULL AND p.contactNumber != ''";
    $countQuery .= " AND p.contactNumber IS NOT NULL AND p.contactNumber != ''";
} elseif ($hasContact === 'no') {
    $query .= " AND (p.contactNumber IS NULL OR p.contactNumber = '')";
    $countQuery .= " AND (p.contactNumber IS NULL OR p.contactNumber = '')";
}

// Get sort parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
$allowedColumns = ['patientId', 'firstName', 'lastName', 'gender', 'dateOfBirth', 'contactNumber', 'barangay', 'reportCount', 'lastVisit', 'created_at', 'updated_at'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'created_at';
}
$query .= " ORDER BY $sortColumn $sortOrder LIMIT $offset, $recordsPerPage";

// Execute queries
try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $barangaysStmt = $pdo->query("SELECT DISTINCT barangay FROM patients WHERE barangay != '' ORDER BY barangay");
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $patients = [];
    $totalRecords = 0;
    $totalPages = 1;
    $barangays = [];
}

function buildUrl($newParams = []) {
    $params = $_GET;
    foreach ($newParams as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    if (array_key_exists('search', $newParams) || array_key_exists('gender', $newParams) || array_key_exists('barangay', $newParams) ||
        array_key_exists('min_age', $newParams) || array_key_exists('max_age', $newParams) ||
        array_key_exists('date_from', $newParams) || array_key_exists('date_to', $newParams) ||
        array_key_exists('has_reports', $newParams) || array_key_exists('has_contact', $newParams)) {
        $params['page'] = 1;
    }
    return 'view_patients.php?' . http_build_query($params);
}
function buildSortUrl($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    $newOrder = ($currentSort === $column && $currentOrder === 'desc') ? 'asc' : 'desc';
    return buildUrl(['sort' => $column, 'order' => $newOrder]);
}
function getSortIcon($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    if ($currentSort === $column) {
        return $currentOrder === 'asc' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>';
    }
    return '';
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
    <title>Patients | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin/view-patients.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h1>Patients</h1>
        <span class="record-count"><?php echo $totalRecords; ?> total records</span>
    </div>

    <form class="filters-container" method="get" action="view_patients.php">
        <div class="filters-row">
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, contact, or ID..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Gender</label>
                <select name="gender">
                    <option value="">All</option>
                    <option value="Male" <?php if ($gender === 'Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if ($gender === 'Female') echo 'selected'; ?>>Female</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Barangay</label>
                <select name="barangay">
                    <option value="">All</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($barangay === $b) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Has Reports</label>
                <select name="has_reports">
                    <option value="">All Patients</option>
                    <option value="yes" <?php if ($hasReports === 'yes') echo 'selected'; ?>>Has Reports</option>
                    <option value="no" <?php if ($hasReports === 'no') echo 'selected'; ?>>No Reports</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Has Contact</label>
                <select name="has_contact">
                    <option value="">All</option>
                    <option value="yes" <?php if ($hasContact === 'yes') echo 'selected'; ?>>Has Contact</option>
                    <option value="no" <?php if ($hasContact === 'no') echo 'selected'; ?>>No Contact</option>
                </select>
            </div>
        </div>

        <div class="filters-row">
            <div class="filter-group">
                <label>Age Range</label>
                <div class="input-group">
                    <input type="number" name="min_age" placeholder="Min" min="0" max="120" value="<?php echo htmlspecialchars($minAge); ?>">
                    <span>to</span>
                    <input type="number" name="max_age" placeholder="Max" min="0" max="120" value="<?php echo htmlspecialchars($maxAge); ?>">
                </div>
            </div>
            <div class="filter-group">
                <label>Registration Date</label>
                <div class="input-group">
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    <span>to</span>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-search"><i class="bi bi-search"></i> Filter</button>
                <?php if (!empty($search) || !empty($gender) || !empty($barangay) || !empty($minAge) || !empty($maxAge) || !empty($dateFrom) || !empty($dateTo) || !empty($hasReports) || !empty($hasContact)): ?>
                    <a href="view_patients.php" class="btn-clear">Clear All</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="table-card">
        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><a href="<?php echo buildSortUrl('lastName'); ?>">Name <?php echo getSortIcon('lastName'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('gender'); ?>">Gender <?php echo getSortIcon('gender'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('dateOfBirth'); ?>">Age <?php echo getSortIcon('dateOfBirth'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('contactNumber'); ?>">Contact <?php echo getSortIcon('contactNumber'); ?></a></th>
                    <th><a href="<?php echo buildSortUrl('barangay'); ?>">Barangay <?php echo getSortIcon('barangay'); ?></a></th>
                    <th>Reports</th>
                    <th><a href="<?php echo buildSortUrl('lastVisit'); ?>">Last Visit <?php echo getSortIcon('lastVisit'); ?></a></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($patients) > 0): ?>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(strtoupper($patient['lastName'] . ', ' . $patient['firstName'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                            <td><?php echo calculateAge($patient['dateOfBirth']); ?></td>
                            <td><?php echo htmlspecialchars($patient['contactNumber']); ?></td>
                            <td><?php echo htmlspecialchars($patient['barangay']); ?></td>
                            <td><span class="badge-reports"><?php echo $patient['reportCount']; ?></span></td>
                            <td><?php echo $patient['lastVisit'] ? date('M d, Y', strtotime($patient['lastVisit'])) : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td>
                                <a href="view_patient.php?id=<?php echo $patient['patientId']; ?>" class="btn-view">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">No patients found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-row">
            <?php
            $showPages = 5; // Total pages to show around current page
            $halfShow = floor($showPages / 2);

            // Calculate start and end pages to show
            $startPage = max(1, $page - $halfShow);
            $endPage = min($totalPages, $page + $halfShow);

            // Adjust if we're near the beginning or end
            if ($endPage - $startPage + 1 < $showPages) {
                if ($startPage == 1) {
                    $endPage = min($totalPages, $startPage + $showPages - 1);
                } elseif ($endPage == $totalPages) {
                    $startPage = max(1, $endPage - $showPages + 1);
                }
            }

            // Previous button
            if ($page > 1): ?>
                <a href="<?php echo buildUrl(['page' => $page - 1]); ?>" title="Previous page">&laquo;</a>
            <?php endif; ?>

            <?php
            // First page + ellipsis if needed
            if ($startPage > 1): ?>
                <a href="<?php echo buildUrl(['page' => 1]); ?>">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="ellipsis">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // Page range
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php
            // Last page + ellipsis if needed
            if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="ellipsis">...</span>
                <?php endif; ?>
                <a href="<?php echo buildUrl(['page' => $totalPages]); ?>"><?php echo $totalPages; ?></a>
            <?php endif; ?>

            <?php
            // Next button
            if ($page < $totalPages): ?>
                <a href="<?php echo buildUrl(['page' => $page + 1]); ?>" title="Next page">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmLogout() {
        if (confirm('Are you sure you want to log out?')) {
            window.location.href = '../logout/admin_logout.php';
        }
    }
</script>
</body>
</html>
