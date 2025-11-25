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
    if (array_key_exists('search', $newParams) || array_key_exists('gender', $newParams) || array_key_exists('barangay', $newParams)) {
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
    <style>
        * { box-sizing: border-box; }
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }
        .page-container {
            padding: 24px 32px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0;
        }
        .record-count {
            color: #666;
            font-size: 0.9rem;
        }
        .filters-row {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filters-row input,
        .filters-row select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fff;
        }
        .filters-row input { flex: 1; min-width: 200px; }
        .filters-row select { min-width: 140px; }
        .filters-row input:focus,
        .filters-row select:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .btn-search {
            background: #3b82f6;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-search:hover { background: #2563eb; }
        .btn-clear {
            background: #fff;
            color: #666;
            border: 1px solid #ddd;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-clear:hover { background: #f5f5f5; color: #333; }
        .table-card {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8fafc;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        th a {
            color: #64748b;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        th a:hover { color: #3b82f6; }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.9rem;
        }
        tr:hover td { background: #f8fafc; }
        .badge-reports {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .btn-view {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view:hover { background: #e2e8f0; color: #1e293b; }
        .empty-state {
            text-align: center;
            padding: 48px;
            color: #94a3b8;
        }
        .pagination-row {
            display: flex;
            justify-content: center;
            gap: 4px;
            padding: 20px;
        }
        .pagination-row a,
        .pagination-row span {
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .pagination-row a {
            background: #fff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .pagination-row a:hover { background: #f1f5f9; }
        .pagination-row .active {
            background: #3b82f6;
            color: #fff;
            border: 1px solid #3b82f6;
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h1>Patients</h1>
        <span class="record-count"><?php echo $totalRecords; ?> total records</span>
    </div>

    <form class="filters-row" method="get" action="view_patients.php">
        <input type="text" name="search" placeholder="Search name, contact, or ID..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="gender">
            <option value="">All Genders</option>
            <option value="Male" <?php if ($gender === 'Male') echo 'selected'; ?>>Male</option>
            <option value="Female" <?php if ($gender === 'Female') echo 'selected'; ?>>Female</option>
        </select>
        <select name="barangay">
            <option value="">All Barangays</option>
            <?php foreach ($barangays as $b): ?>
                <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($barangay === $b) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-search"><i class="bi bi-search"></i> Search</button>
        <?php if (!empty($search) || !empty($gender) || !empty($barangay)): ?>
            <a href="view_patients.php" class="btn-clear">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-card">
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
                            <td><strong><?php echo htmlspecialchars($patient['lastName'] . ', ' . $patient['firstName']); ?></strong></td>
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

        <?php if ($totalPages > 1): ?>
        <div class="pagination-row">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
