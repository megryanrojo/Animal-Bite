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
$recordsPerPage = 10;
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
        return $currentOrder === 'asc' ? '<i class="bi bi-sort-up-alt ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>';
    }
    return '<i class="bi bi-sort ms-1 text-muted"></i>';
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
    <title>Patient Management | Animal Bite Center</title>
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
            color: #fff;
        }
        .btn-logout {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 5px;
            transition: all 0.2s;
        }
        
        .btn-logout:hover {
            background-color: #bb2d3b;
            color: white;
        }
        .patients-container {
            max-width: 1200px;
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
        .table th {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            font-weight: 600;
            border-top: none;
        }
        .table td, .table th {
            padding: 1rem;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        .pagination .page-link {
            color: var(--bs-primary);
        }
        .pagination .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        .badge.bg-primary {
            background-color: var(--bs-primary) !important;
        }
        .badge.bg-primary.bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.1) !important;
            color: var(--bs-primary) !important;
        }
        @media (max-width: 768px) {
            .patients-container {
                padding: 1rem;
            }
            .content-card-header, .content-card-body {
                padding: 1rem;
            }
            .table td, .table th {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="patients-container">
    <div class="content-card mb-4">
        <div class="content-card-header">
            <h2 class="mb-0">Patients</h2>
        </div>
        <div class="content-card-body">
            <form class="row g-3 mb-3" method="get" action="view_patients.php">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by name, contact, or ID" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="gender">
                        <option value="">All Genders</option>
                        <option value="Male" <?php if ($gender === 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if ($gender === 'Female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="barangay">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($barangay === $b) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th><a href="<?php echo buildSortUrl('patientId'); ?>" class="text-decoration-none text-dark">ID <?php echo getSortIcon('patientId'); ?></a></th>
                            <th><a href="<?php echo buildSortUrl('lastName'); ?>" class="text-decoration-none text-dark">Name <?php echo getSortIcon('lastName'); ?></a></th>
                            <th><a href="<?php echo buildSortUrl('gender'); ?>" class="text-decoration-none text-dark">Gender <?php echo getSortIcon('gender'); ?></a></th>
                            <th><a href="<?php echo buildSortUrl('dateOfBirth'); ?>" class="text-decoration-none text-dark">Age <?php echo getSortIcon('dateOfBirth'); ?></a></th>
                            <th><a href="<?php echo buildSortUrl('contactNumber'); ?>" class="text-decoration-none text-dark">Contact <?php echo getSortIcon('contactNumber'); ?></a></th>
                            <th><a href="<?php echo buildSortUrl('barangay'); ?>" class="text-decoration-none text-dark">Barangay <?php echo getSortIcon('barangay'); ?></a></th>
                            <th>Reports</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($patients) > 0): ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><?php echo $patient['patientId']; ?></td>
                                    <td><?php echo htmlspecialchars($patient['lastName'] . ', ' . $patient['firstName']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                                    <td><?php echo calculateAge($patient['dateOfBirth']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['contactNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['barangay']); ?></td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary fw-semibold"><?php echo $patient['reportCount']; ?></span></td>
                                    <td><?php echo $patient['lastVisit'] ? date('M d, Y', strtotime($patient['lastVisit'])) : '<span class="text-muted">N/A</span>'; ?></td>
                                    <td>
                                        <a href="view_patient.php?id=<?php echo $patient['patientId']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center text-muted">No patients found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-3">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 