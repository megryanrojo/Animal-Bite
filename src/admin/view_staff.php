<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';
require_once 'includes/notification_helper.php';

// Handle notification read
if (isset($_GET['read_notification']) && is_numeric($_GET['read_notification'])) {
    markNotificationAsRead($pdo, $_GET['read_notification']);
    header("Location: view_staff.php");
    exit;
}

// Handle staff deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $staffId = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM staff WHERE staffId = ?");
        $stmt->execute([$staffId]);
        $_SESSION['message'] = "Staff member deleted successfully!";
        header("Location: view_staff.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting staff: " . $e->getMessage();
    }
}

// Pagination settings
$recordsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$params = [];

if (!empty($search)) {
    $searchCondition = "WHERE firstName LIKE ? OR lastName LIKE ? OR email LIKE ? OR contactNumber LIKE ?";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Get total records for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM staff $searchCondition");
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get staff records
    $stmt = $pdo->prepare("SELECT staffId, firstName, lastName, birthDate, contactNumber, email, assignedBarangay FROM staff $searchCondition ORDER BY lastName, firstName LIMIT $offset, $recordsPerPage");
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Management | Admin Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin/view-staff.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="main-content">
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Staff Management</h1>
                    <p>View and manage barangay health workers</p>
                </div>
                <a href="add_staff.php" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Add a new barangay health worker to the system">
                    <i class="bi bi-plus-lg"></i> Add New Staff
                </a>
            </div>
            
            <!-- Alerts -->
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalRecords; ?></h3>
                        <p>Total Staff</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalRecords; ?></h3>
                        <p>Active Members</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="bi bi-list-ul"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalPages; ?></h3>
                        <p>Total Pages</p>
                    </div>
                </div>
            </div>
            
            <!-- Search Filter -->
            <div class="filter-card">
                <form method="GET" action="view_staff.php" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search Staff</label>
                        <input type="text" id="search" name="search" placeholder="Search by name, email, or contact number" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="view_staff.php" class="btn btn-outline">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Staff Table -->
            <div class="table-card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Contact Number</th>
                                <th>Email</th>
                                <th>Barangay</th>
                                <th>Birth Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffMembers)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="bi bi-people"></i>
                                        <p>No staff members found.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($staffMembers as $staff): ?>
                                <tr>
                                    <td data-label="">
                                        <div class="staff-name">
                                            <div class="staff-avatar">
                                                <?php echo strtoupper(substr($staff['firstName'], 0, 1) . substr($staff['lastName'], 0, 1)); ?>
                                            </div>
                                            <div class="staff-details">
                                                <span><?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?></span>
                                                <small>ID: <?php echo $staff['staffId']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Contact"><?php echo htmlspecialchars($staff['contactNumber']); ?></td>
                                    <td data-label="Email"><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td data-label="Barangay"><?php echo htmlspecialchars($staff['assignedBarangay'] ?? 'Not assigned'); ?></td>
                                    <td data-label="Birth Date"><?php echo date('M d, Y', strtotime($staff['birthDate'])); ?></td>
                                    <td data-label="">
                                        <div class="actions-cell">
                                            <a href="edit_staff.php?id=<?php echo $staff['staffId']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="Edit staff member information">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="view_staff.php?delete=<?php echo $staff['staffId']; ?>" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="Remove staff member from the system" onclick="return confirm('Are you sure you want to delete this staff member?');">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 0): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?php echo min(($page - 1) * $recordsPerPage + 1, $totalRecords); ?> to 
                        <?php echo min($page * $recordsPerPage, $totalRecords); ?> of 
                        <?php echo $totalRecords; ?> staff members
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li>
                            <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                        </li>
                        <li>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <li class="<?php echo $i === (int)$page ? 'active' : ''; ?>">
                            <?php if ($i === (int)$page): ?>
                            <span><?php echo $i; ?></span>
                            <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endif; ?>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <li>
                            <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="page-footer">
            <span>&copy; <?php echo date('Y'); ?> Barangay Health Workers Management System</span>
            <a href="help.php">Help & Support</a>
        </footer>
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
