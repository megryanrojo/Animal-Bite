<?php
session_start();
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login/staff_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get staff information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff = ['firstName' => 'User', 'lastName' => ''];
}

// Initialize filter variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
// Remove the hasRabiesVaccine filter since it doesn't exist in the table

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
    $query .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR p.patientId LIKE ?)";
    $countQuery .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR p.patientId LIKE ?)";
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
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'registrationDate';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Validate sort column to prevent SQL injection
$allowedColumns = ['patientId', 'firstName', 'lastName', 'gender', 'dateOfBirth', 'contactNumber', 'barangay', 'reportCount', 'lastVisit', 'created_at', 'updated_at'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'created_at'; // Default sort by creation date instead of registrationDate
}

$query .= " ORDER BY $sortColumn $sortOrder LIMIT $offset, $recordsPerPage";

// Execute queries
try {
    // Get total records count
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Calculate total pages
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Get patients for current page
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique barangays for filter dropdown
    $barangaysStmt = $pdo->query("SELECT DISTINCT barangay FROM patients WHERE barangay != '' ORDER BY barangay");
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $patients = [];
    $totalRecords = 0;
    $totalPages = 1;
    $barangays = [];
}

// Function to build URL with updated parameters
function buildUrl($newParams = []) {
    $params = $_GET;
    
    foreach ($newParams as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    
    // Reset page to 1 when filters change
    if (array_key_exists('search', $newParams) || 
        array_key_exists('gender', $newParams) || 
        array_key_exists('barangay', $newParams)) {
    $params['page'] = 1;
}
    
    return 'patients.php?' . http_build_query($params);
}

// Function to build sort URL
function buildSortUrl($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'registrationDate';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    
    $newOrder = ($currentSort === $column && $currentOrder === 'desc') ? 'asc' : 'desc';
    
    return buildUrl(['sort' => $column, 'order' => $newOrder]);
}

// Function to get sort icon
function getSortIcon($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'registrationDate';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    
    if ($currentSort === $column) {
        return $currentOrder === 'asc' ? '<i class="bi bi-sort-up-alt ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>';
    }
    
    return '<i class="bi bi-sort ms-1 text-muted"></i>';
}

// Function to calculate age from date of birth
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
            --bs-primary: #28a745;
            --bs-primary-rgb: 40, 167, 69;
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
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .btn-outline-primary {
            color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
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
        
        .filter-form {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table th {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            font-weight: 600;
            border-top: none;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .table td, .table th {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr {
            transition: all 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .page-link {
            color: var(--bs-primary);
        }
        
        .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #adb5bd;
            margin-bottom: 1rem;
        }
        
        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .badge-yes {
            background-color: #28a745;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-no {
            background-color: #dc3545;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-unknown {
            background-color: #6c757d;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .patients-container {
                padding: 1rem;
            }
            
            .content-card-header, .content-card-body {
                padding: 1rem;
            }
            
            .filter-form {
                padding: 1rem;
            }
            
            .table td, .table th {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
  <?php $activePage = 'patients'; include 'navbar.php'; ?>

    <div class="patients-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Patient Management</h2>
                <p class="text-muted mb-0">View and manage patient records</p>
            </div>
            <div>
                <a href="new_patient.php" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2"></i>New Patient
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-form">
            <form method="GET" action="patients.php">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by name, ID, or contact number" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex justify-content-end">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                            <i class="bi bi-funnel me-2"></i>Advanced Filters
                        </button>
                    </div>
                    
                    <div class="col-12 collapse <?php echo (!empty($gender) || !empty($barangay)) ? 'show' : ''; ?>" id="advancedFilters">
                        <div class="card card-body border-0 p-0 pt-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">All Genders</option>
                                        <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="barangay" class="form-label">Barangay</label>
                                    <select class="form-select" id="barangay" name="barangay">
                                        <option value="">All Barangays</option>
                                        <?php foreach ($barangays as $brgy): ?>
                                        <option value="<?php echo htmlspecialchars($brgy); ?>" <?php echo $barangay === $brgy ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brgy); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-12 d-flex justify-content-end">
                                    <a href="patients.php" class="btn btn-outline-secondary me-2">Clear Filters</a>
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Patients Table -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Patient Records</h5>
                <span class="badge bg-secondary"><?php echo $totalRecords; ?> Patients</span>
            </div>
            <div class="content-card-body p-0">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger m-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (count($patients) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th onclick="window.location.href='<?php echo buildSortUrl('patientId'); ?>'">
                                    ID <?php echo getSortIcon('patientId'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('firstName'); ?>'">
                                    First Name <?php echo getSortIcon('firstName'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('lastName'); ?>'">
                                    Last Name <?php echo getSortIcon('lastName'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('gender'); ?>'">
                                    Gender <?php echo getSortIcon('gender'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('dateOfBirth'); ?>'">
                                    Age <?php echo getSortIcon('dateOfBirth'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('contactNumber'); ?>'">
                                    Contact <?php echo getSortIcon('contactNumber'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('barangay'); ?>'">
                                    Barangay <?php echo getSortIcon('barangay'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('reportCount'); ?>'">
                                    Reports <?php echo getSortIcon('reportCount'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo buildSortUrl('lastVisit'); ?>'">
                                    Last Visit <?php echo getSortIcon('lastVisit'); ?>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><?php echo $patient['patientId']; ?></td>
                                <td><?php echo htmlspecialchars($patient['firstName']); ?></td>
                                <td><?php echo htmlspecialchars($patient['lastName']); ?></td>
                                <td><?php echo htmlspecialchars($patient['gender'] ?? 'Not specified'); ?></td>
                                <td><?php echo calculateAge($patient['dateOfBirth']); ?></td>
                                <td><?php echo htmlspecialchars($patient['contactNumber'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($patient['barangay'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($patient['reportCount'] > 0): ?>
                                    <a href="reports.php?patient_id=<?php echo $patient['patientId']; ?>" class="badge bg-primary text-decoration-none">
                                        <?php echo $patient['reportCount']; ?> report<?php echo $patient['reportCount'] > 1 ? 's' : ''; ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">No reports</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo !empty($patient['lastVisit']) ? date('M d, Y', strtotime($patient['lastVisit'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_patient.php?id=<?php echo $patient['patientId']; ?>" class="btn btn-sm btn-outline-primary" title="View Patient">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit_patient.php?id=<?php echo $patient['patientId']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Patient">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="new_report.php?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-sm btn-outline-success" title="New Report">
                                            <i class="bi bi-file-earmark-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <div>
                        <span class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> patients</span>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrl(['page' => $page - 1]); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="' . buildUrl(['page' => 1]) . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="' . buildUrl(['page' => $i]) . '">' . $i . '</a></li>';
                            }
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="' . buildUrl(['page' => $totalPages]) . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrl(['page' => $page + 1]); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h4>No Patients Found</h4>
                    <p class="text-muted">No patients match your search criteria</p>
                    <a href="new_patient.php" class="btn btn-primary mt-3">
                        <i class="bi bi-person-plus me-2"></i>Register New Patient
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">&copy; <?php echo date('Y'); ?> Animal Bite Treatment Center</small>
                </div>
                <div>
                    <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
