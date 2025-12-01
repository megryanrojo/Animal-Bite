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
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #eff6ff;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --radius: 8px;
            --sidebar-width: 250px;
        }
        
        * { box-sizing: border-box; }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        
        .main-content {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .page-container {
            flex: 1;
            padding: 24px;
            width: 100%;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: var(--text);
        }
        
        .page-header p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .btn-outline:hover {
            background: var(--bg);
            border-color: var(--secondary);
        }
        
        .btn-sm {
            padding: 6px 10px;
            font-size: 0.75rem;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            color: white;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert .btn-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            opacity: 0.5;
            padding: 0;
        }
        
        .alert .btn-close:hover {
            opacity: 1;
        }
        
        /* Filter/Search Card */
        .filter-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .filter-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-form .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-form label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-form input {
            width: 100%;
            padding: 8px 12px;
            font-size: 0.875rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: white;
            color: var(--text);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .filter-form input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        
        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .stat-icon.blue { background: var(--primary-light); color: var(--primary); }
        .stat-icon.green { background: #ecfdf5; color: var(--success); }
        .stat-icon.orange { background: #fffbeb; color: var(--warning); }
        
        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: var(--text);
        }
        
        .stat-info p {
            margin: 0;
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Table Card */
        .table-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: var(--bg);
            padding: 12px 16px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        
        table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
            color: var(--text);
        }
        
        table tbody tr:last-child td {
            border-bottom: none;
        }
        
        table tbody tr:hover {
            background: var(--primary-light);
        }
        
        .staff-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .staff-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .staff-details {
            display: flex;
            flex-direction: column;
        }
        
        .staff-details span {
            font-weight: 500;
        }
        
        .staff-details small {
            color: var(--text-muted);
            font-size: 0.75rem;
        }
        
        .actions-cell {
            display: flex;
            gap: 6px;
        }
        
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            margin: 0;
        }
        
        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .pagination-info {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        
        .pagination {
            display: flex;
            gap: 4px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .pagination a, .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            font-size: 0.875rem;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text);
            background: white;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        
        .pagination a:hover {
            background: var(--bg);
            border-color: var(--secondary);
        }
        
        .pagination .active span {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Footer */
        .page-footer {
            background: var(--card-bg);
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .page-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .page-footer a:hover {
            text-decoration: underline;
        }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .page-container {
                padding: 16px;
            }
        }
        
        @media (max-width: 768px) {
            .page-container {
                padding: 12px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header h1 {
                font-size: 1.25rem;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-form .form-group {
                width: 100%;
                min-width: unset;
            }
            
            .filter-actions {
                width: 100%;
            }
            
            .filter-actions .btn {
                flex: 1;
                justify-content: center;
            }
            
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
            
            /* Mobile card view for table */
            .table-wrapper table,
            .table-wrapper thead,
            .table-wrapper tbody,
            .table-wrapper th,
            .table-wrapper td,
            .table-wrapper tr {
                display: block;
            }
            
            .table-wrapper thead {
                display: none;
            }
            
            .table-wrapper tr {
                padding: 16px;
                border-bottom: 1px solid var(--border);
            }
            
            .table-wrapper tr:hover {
                background: var(--primary-light);
            }
            
            .table-wrapper td {
                padding: 6px 0;
                border: none;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .table-wrapper td::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 0.75rem;
                color: var(--text-muted);
                text-transform: uppercase;
            }
            
            .table-wrapper td:first-child {
                padding-bottom: 12px;
                margin-bottom: 8px;
                border-bottom: 1px solid var(--border);
            }
            
            .table-wrapper td:first-child::before {
                display: none;
            }
            
            .staff-name {
                width: 100%;
            }
            
            .actions-cell {
                justify-content: flex-end;
                width: 100%;
                padding-top: 12px;
                margin-top: 8px;
                border-top: 1px solid var(--border);
            }
            
            .pagination-wrapper {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 14px 16px;
            }
            
            .stat-info h3 {
                font-size: 1.25rem;
            }
        }
    </style>
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
