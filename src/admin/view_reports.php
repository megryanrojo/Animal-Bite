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
    header("Location: view_reports.php");
    exit;
}

// Default filter values
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$biteType = isset($_GET['bite_type']) ? $_GET['bite_type'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Build the query
$whereConditions = [];
$params = [];

if (!empty($status)) {
   $whereConditions[] = "r.status = ?";
   $params[] = $status;
}

if (!empty($dateFrom)) {
   $whereConditions[] = "r.reportDate >= ?";
   $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
   $whereConditions[] = "r.reportDate <= ?";
   $params[] = $dateTo . ' 23:59:59';
}

if (!empty($animalType)) {
   $whereConditions[] = "r.animalType = ?";
   $params[] = $animalType;
}

if (!empty($biteType)) {
   $whereConditions[] = "r.biteType = ?";
   $params[] = $biteType;
}

if (!empty($barangay)) {
   $whereConditions[] = "p.barangay = ?";
   $params[] = $barangay;
}

if (!empty($search)) {
   $whereConditions[] = "(p.firstName LIKE ? OR p.lastName LIKE ? OR p.contactNumber LIKE ? OR r.referenceNumber LIKE ?)";
   $searchParam = "%$search%";
   $params[] = $searchParam;
   $params[] = $searchParam;
   $params[] = $searchParam;
   $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total records for pagination
try {
   // Debug: Check if there's any data in the tables
   $debugQuery = "SELECT COUNT(*) FROM reports";
   $debugStmt = $pdo->query($debugQuery);
   $totalReports = $debugStmt->fetchColumn();
   
   $debugQuery2 = "SELECT COUNT(*) FROM patients";
   $debugStmt2 = $pdo->query($debugQuery2);
   $totalPatients = $debugStmt2->fetchColumn();
   
   // Debug: Check patientId values in reports
   $debugQuery3 = "SELECT reportId, patientId FROM reports";
   $debugStmt3 = $pdo->query($debugQuery3);
   $reportPatientIds = $debugStmt3->fetchAll(PDO::FETCH_ASSOC);
   
   // Debug: Check patientId values in patients
   $debugQuery4 = "SELECT patientId FROM patients";
   $debugStmt4 = $pdo->query($debugQuery4);
   $patientIds = $debugStmt4->fetchAll(PDO::FETCH_ASSOC);
   
   echo "<!-- Debug: Total reports: $totalReports, Total patients: $totalPatients -->";
   echo "<!-- Debug: Report patientIds: " . print_r($reportPatientIds, true) . " -->";
   echo "<!-- Debug: Patient IDs: " . print_r($patientIds, true) . " -->";
   
   $countQuery = "
       SELECT COUNT(*) 
       FROM reports r
       LEFT JOIN patients p ON r.patientId = p.patientId
       $whereClause
   ";
   
   // Debug: Print the query and parameters
   echo "<!-- Debug: Query: $countQuery -->";
   echo "<!-- Debug: Parameters: " . print_r($params, true) . " -->";
   
   $countStmt = $pdo->prepare($countQuery);
   if (!empty($params)) {
       $countStmt->execute($params);
   } else {
       $countStmt->execute();
   }
   
   $totalRecords = $countStmt->fetchColumn();
   $totalPages = ceil($totalRecords / $recordsPerPage);
} catch (PDOException $e) {
   $error = "Database error: " . $e->getMessage();
   echo "<!-- Debug: SQL Error: " . $e->getMessage() . " -->";
   $totalRecords = 0;
   $totalPages = 0;
}

// Get reports
try {
   $query = "
       SELECT r.reportId, r.reportDate, r.biteDate, r.animalType, r.biteType, r.status, r.updated_at,
              p.firstName, p.lastName, p.contactNumber, p.barangay
       FROM reports r
       LEFT JOIN patients p ON r.patientId = p.patientId
       $whereClause
       ORDER BY r.reportDate DESC
       LIMIT $offset, $recordsPerPage
   ";
   
   $stmt = $pdo->prepare($query);
   if (!empty($params)) {
       $stmt->execute($params);
   } else {
       $stmt->execute();
   }
   
   $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
   // Debug: Print the first report to check data
   if (!empty($reports)) {
       echo "<!-- Debug: First report data: " . print_r($reports[0], true) . " -->";
   }
} catch (PDOException $e) {
   $error = "Database error: " . $e->getMessage();
   echo "<!-- Debug: SQL Error in reports query: " . $e->getMessage() . " -->";
   $reports = [];
}

// Get counts for dashboard cards
try {
   $pendingQuery = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
   $pendingCount = $pendingQuery->fetchColumn();
   
   $inProgressQuery = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'in_progress'");
   $inProgressCount = $inProgressQuery->fetchColumn();
   
   $completedQuery = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'completed'");
   $completedCount = $completedQuery->fetchColumn();
   
   $highRiskQuery = $pdo->query("SELECT COUNT(*) FROM reports WHERE biteType = 'Category III'");
   $highRiskCount = $highRiskQuery->fetchColumn();
} catch (PDOException $e) {
   $pendingCount = 0;
   $inProgressCount = 0;
   $completedCount = 0;
   $highRiskCount = 0;
}

// Get unique barangays for the filter dropdown
try {
    $barangaysStmt = $pdo->query("SELECT DISTINCT barangay FROM barangay_coordinates ORDER BY barangay");
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $barangays = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>View Reports | Admin Portal</title>
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
       }
       
       .navbar {
           background-color: white;
           box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
       
       .reports-container {
           max-width: 1200px;
           margin: 0 auto;
           padding: 2rem 1rem;
       }
       
       .card {
           border: none;
           border-radius: 10px;
           box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
           transition: all 0.3s ease;
       }
       
       .card:hover {
           transform: translateY(-5px);
           box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
       }
       
       .card-icon {
           font-size: 2rem;
           margin-bottom: 0.5rem;
       }
       
       .stats-number {
           font-size: 2rem;
           font-weight: 700;
           color: var(--bs-primary);
       }
       
       .filter-card {
           background-color: white;
           border-radius: 10px;
           padding: 1.5rem;
           margin-bottom: 2rem;
           box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
       }
       
       .table-card {
           background-color: white;
           border-radius: 10px;
           overflow: hidden;
           box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
       }
       
       .table-responsive {
           padding: 0;
       }
       
       .table {
           margin-bottom: 0;
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
       
       .status-badge {
           font-size: 0.75rem;
           padding: 0.35em 0.65em;
           border-radius: 0.25rem;
       }
       
       .status-pending {
           background-color: #ffc107;
           color: #212529;
       }
       
       .status-in-progress {
           background-color: #17a2b8;
           color: white;
       }
       
       .status-completed {
           background-color: #28a745;
           color: white;
       }
       
       .status-referred {
           background-color: #6f42c1;
           color: white;
       }
       
       .status-cancelled {
           background-color: #dc3545;
           color: white;
       }
       
       .bite-category-1 {
           background-color: #28a745;
           color: white;
       }
       
       .bite-category-2 {
           background-color: #fd7e14;
           color: white;
       }
       
       .bite-category-3 {
           background-color: #dc3545;
           color: white;
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
       
       .pagination {
           margin-top: 1.5rem;
           justify-content: center;
       }
       
       .page-link {
           color: var(--bs-primary);
           border-radius: 0.25rem;
           margin: 0 0.25rem;
       }
       
       .page-item.active .page-link {
           background-color: var(--bs-primary);
           border-color: var(--bs-primary);
       }
       
       .footer {
           background-color: white;
           padding: 1rem 0;
           margin-top: 3rem;
           border-top: 1px solid rgba(0, 0, 0, 0.05);
       }
       
       /* Notification Styles */
       .notification-dropdown {
           min-width: 320px;
           max-width: 320px;
           max-height: 400px;
           overflow-y: auto;
           padding: 0;
       }
       
       .notification-item {
           border-left: 4px solid transparent;
           transition: all 0.2s ease;
       }
       
       .notification-item.unread {
           background-color: rgba(var(--bs-primary-rgb), 0.05);
       }
       
       .notification-item:hover {
           background-color: rgba(var(--bs-primary-rgb), 0.03);
       }
       
       .notification-icon {
           width: 40px;
           height: 40px;
           display: flex;
           align-items: center;
           justify-content: center;
           border-radius: 50%;
       }
       
       @media (max-width: 768px) {
           .reports-container {
               padding: 1rem;
           }
           
           .stats-card {
               margin-bottom: 1rem;
           }
           
           .table-responsive {
               border-radius: 10px;
           }
       }
   </style>
</head>
<body>
   <!-- Include the navbar with notifications -->
   <?php include 'includes/navbar.php'; ?>

   <div class="reports-container">
       <!-- Page Header -->
       <div class="d-flex justify-content-between align-items-center mb-4">
           <div>
               <h2 class="mb-1">Animal Bite Reports</h2>
               <p class="text-muted mb-0">Manage and track all reported animal bite cases</p>
           </div>
           <div class="d-flex gap-2">
               <div class="dropdown">
                   <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                       <i class="bi bi-printer me-2"></i>Print Reports
                   </button>
                   <ul class="dropdown-menu">
                       <li><a class="dropdown-item" href="print_reports.php?type=all" target="_blank">
                           <i class="bi bi-file-text me-2"></i>Print All Reports
                       </a></li>
                       <li><a class="dropdown-item" href="print_reports.php?type=filtered<?php echo !empty($_GET) ? '&' . http_build_query($_GET) : ''; ?>" target="_blank">
                           <i class="bi bi-funnel me-2"></i>Print Filtered Reports
                       </a></li>
                   </ul>
               </div>
               <div class="dropdown">
                   <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                       <i class="bi bi-download me-2"></i>Export
                   </button>
                   <ul class="dropdown-menu">
                       <li><a class="dropdown-item" href="export_reports.php?format=csv<?php echo !empty($_GET) ? '&' . http_build_query($_GET) : ''; ?>">
                           <i class="bi bi-file-earmark-spreadsheet me-2"></i>Export as CSV
                       </a></li>
                       <li><a class="dropdown-item" href="export_reports.php?format=excel<?php echo !empty($_GET) ? '&' . http_build_query($_GET) : ''; ?>">
                           <i class="bi bi-file-earmark-excel me-2"></i>Export as Excel
                       </a></li>
                   </ul>
               </div>
               <div class="dropdown">
                   <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                       <i class="bi bi-three-dots me-2"></i>Quick Actions
                   </button>
                   <ul class="dropdown-menu">
                       <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#saveFilterModal">
                           <i class="bi bi-bookmark me-2"></i>Save Current Filter
                       </a></li>
                       <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#loadFilterModal">
                           <i class="bi bi-bookmark-fill me-2"></i>Load Saved Filter
                       </a></li>
                       <li><hr class="dropdown-divider"></li>
                       <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#columnVisibilityModal">
                           <i class="bi bi-columns me-2"></i>Customize Columns
                       </a></li>
                   </ul>
               </div>
           </div>
       </div>
       
       <!-- Stats Cards -->
       <div class="row g-4 mb-4">
           <div class="col-md-3">
               <div class="card h-100">
                   <div class="card-body text-center">
                       <div class="card-icon text-warning">
                           <i class="bi bi-hourglass-split"></i>
                       </div>
                       <div class="stats-number"><?php echo $pendingCount; ?></div>
                       <p class="card-text mb-0">Pending Reports</p>
                   </div>
               </div>
           </div>
           
           <div class="col-md-3">
               <div class="card h-100">
                   <div class="card-body text-center">
                       <div class="card-icon text-info">
                           <i class="bi bi-arrow-repeat"></i>
                       </div>
                       <div class="stats-number"><?php echo $inProgressCount; ?></div>
                       <p class="card-text mb-0">In Progress</p>
                   </div>
               </div>
           </div>
           
           <div class="col-md-3">
               <div class="card h-100">
                   <div class="card-body text-center">
                       <div class="card-icon text-success">
                           <i class="bi bi-check-circle"></i>
                       </div>
                       <div class="stats-number"><?php echo $completedCount; ?></div>
                       <p class="card-text mb-0">Completed</p>
                   </div>
               </div>
           </div>
           
           <div class="col-md-3">
               <div class="card h-100">
                   <div class="card-body text-center">
                       <div class="card-icon text-danger">
                           <i class="bi bi-exclamation-triangle"></i>
                       </div>
                       <div class="stats-number"><?php echo $highRiskCount; ?></div>
                       <p class="card-text mb-0">High Risk (Cat III)</p>
                   </div>
               </div>
           </div>
       </div>
       
       <!-- Filter Section -->
       <div class="filter-card">
           <form method="GET" action="view_reports.php">
               <div class="row g-3">
                   <div class="col-md-3">
                       <label for="status" class="form-label">Status</label>
                       <select class="form-select" id="status" name="status">
                           <option value="">All Statuses</option>
                           <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                           <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                           <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                           <option value="referred" <?php echo $status === 'referred' ? 'selected' : ''; ?>>Referred</option>
                       </select>
                   </div>
                   
                   <div class="col-md-3">
                       <label for="animal_type" class="form-label">Animal Type</label>
                       <select class="form-select" id="animal_type" name="animal_type">
                           <option value="">All Animals</option>
                           <option value="Dog" <?php echo $animalType === 'Dog' ? 'selected' : ''; ?>>Dog</option>
                           <option value="Cat" <?php echo $animalType === 'Cat' ? 'selected' : ''; ?>>Cat</option>
                           <option value="Rat" <?php echo $animalType === 'Rat' ? 'selected' : ''; ?>>Rat</option>
                           <option value="Other" <?php echo $animalType === 'Other' ? 'selected' : ''; ?>>Other</option>
                       </select>
                   </div>
                   
                   <div class="col-md-3">
                       <label for="bite_type" class="form-label">Bite Category</label>
                       <select class="form-select" id="bite_type" name="bite_type">
                           <option value="">All Categories</option>
                           <option value="Category I" <?php echo $biteType === 'Category I' ? 'selected' : ''; ?>>Category I</option>
                           <option value="Category II" <?php echo $biteType === 'Category II' ? 'selected' : ''; ?>>Category II</option>
                           <option value="Category III" <?php echo $biteType === 'Category III' ? 'selected' : ''; ?>>Category III</option>
                       </select>
                   </div>
                   
                   <div class="col-md-3">
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
                   
                   <div class="col-md-3">
                       <label for="search" class="form-label">Search</label>
                       <input type="text" class="form-control" id="search" name="search" placeholder="Name, Contact, Ref #" value="<?php echo htmlspecialchars($search); ?>">
                   </div>
                   
                   <div class="col-md-3">
                       <label for="date_from" class="form-label">Date From</label>
                       <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                   </div>
                   
                   <div class="col-md-3">
                       <label for="date_to" class="form-label">Date To</label>
                       <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                   </div>
                   
                   <div class="col-md-6 d-flex align-items-end">
                       <div class="d-grid gap-2 d-md-flex">
                           <button type="submit" class="btn btn-primary">
                               <i class="bi bi-search me-2"></i>Filter Reports
                           </button>
                           <a href="view_reports.php" class="btn btn-outline-secondary">
                               <i class="bi bi-x-circle me-2"></i>Clear Filters
                           </a>
                       </div>
                   </div>
               </div>
           </form>
       </div>
       
       <!-- Reports Table -->
       <div class="table-card">
           <div class="table-responsive">
               <table class="table table-hover" id="reportsTable">
                   <thead>
                       <tr>
                           <th data-sort="reportId">ID <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="patientName">Patient Name <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="contactNumber">Contact <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="barangay">Barangay <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="animalType">Animal <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="biteDate">Bite Date <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="biteType">Category <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="status">Status <i class="bi bi-arrow-down-up"></i></th>
                           <th data-sort="reportDate">Report Date <i class="bi bi-arrow-down-up"></i></th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php if (empty($reports)): ?>
                       <tr>
                           <td colspan="10" class="text-center py-4">
                               <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                               <p class="mt-2 mb-0 text-muted">No reports found matching your criteria.</p>
                           </td>
                       </tr>
                       <?php else: ?>
                           <?php foreach ($reports as $report): ?>
                           <tr>
                               <td><?php echo htmlspecialchars($report['reportId']); ?></td>
                               <td>
                                   <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#reportModal" 
                                      data-report-id="<?php echo $report['reportId']; ?>">
                                       <?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?>
                                   </a>
                               </td>
                               <td><?php echo htmlspecialchars($report['contactNumber']); ?></td>
                               <td><?php echo htmlspecialchars($report['barangay']); ?></td>
                               <td><?php echo htmlspecialchars($report['animalType']); ?></td>
                               <td><?php echo date('M d, Y', strtotime($report['biteDate'])); ?></td>
                               <td>
                                   <span class="status-badge <?php 
                                       switch($report['biteType']) {
                                           case 'Category I': echo 'bite-category-1'; break;
                                           case 'Category II': echo 'bite-category-2'; break;
                                           case 'Category III': echo 'bite-category-3'; break;
                                           default: echo '';
                                       }
                                   ?>">
                                       <?php echo $report['biteType']; ?>
                                   </span>
                               </td>
                               <td>
                                   <span class="status-badge <?php 
                                       switch($report['status']) {
                                           case 'pending': echo 'status-pending'; break;
                                           case 'in_progress': echo 'status-in-progress'; break;
                                           case 'completed': echo 'status-completed'; break;
                                           case 'referred': echo 'status-referred'; break;
                                           case 'cancelled': echo 'status-cancelled'; break;
                                           default: echo 'status-pending';
                                       }
                                   ?>">
                                       <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                   </span>
                               </td>
                               <td><?php echo date('M d, Y', strtotime($report['reportDate'])); ?></td>
                               <td>
                                   <div class="btn-group">
                                       <a href="view_report.php?id=<?php echo $report['reportId']; ?>" class="btn btn-sm btn-primary">
                                           <i class="bi bi-eye"></i>
                                       </a>
                                       <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                                       </button>
                                       <ul class="dropdown-menu">
                                           <li><a class="dropdown-item" href="print_report.php?id=<?php echo $report['reportId']; ?>" target="_blank">
                                               <i class="bi bi-printer me-2"></i>Print Report
                                           </a></li>
                                           <li><a class="dropdown-item" href="edit_report.php?id=<?php echo $report['reportId']; ?>">
                                               <i class="bi bi-pencil me-2"></i>Edit Report
                                           </a></li>
                                       </ul>
                                   </div>
                               </td>
                           </tr>
                           <?php endforeach; ?>
                       <?php endif; ?>
                   </tbody>
               </table>
           </div>
       </div>
       
       <!-- Pagination -->
       <?php if ($totalPages > 1): ?>
       <nav aria-label="Page navigation">
           <ul class="pagination">
               <?php if ($page > 1): ?>
               <li class="page-item">
                   <a class="page-link" href="?page=1<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" aria-label="First">
                       <span aria-hidden="true">&laquo;&laquo;</span>
                   </a>
               </li>
               <li class="page-item">
                   <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" aria-label="Previous">
                       <span aria-hidden="true">&laquo;</span>
                   </a>
               </li>
               <?php endif; ?>
               
               <?php
               $startPage = max(1, $page - 2);
               $endPage = min($totalPages, $page + 2);
               
               for ($i = $startPage; $i <= $endPage; $i++):
               ?>
               <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                   <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                       <?php echo $i; ?>
                   </a>
               </li>
               <?php endfor; ?>
               
               <?php if ($page < $totalPages): ?>
               <li class="page-item">
                   <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" aria-label="Next">
                       <span aria-hidden="true">&raquo;</span>
                   </a>
               </li>
               <li class="page-item">
                   <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" aria-label="Last">
                       <span aria-hidden="true">&raquo;&raquo;</span>
                   </a>
               </li>
               <?php endif; ?>
           </ul>
       </nav>
       <?php endif; ?>
       
       <!-- Results Summary -->
       <div class="text-center mt-3">
           <p class="text-muted">
               Showing <?php echo min(($page - 1) * $recordsPerPage + 1, $totalRecords); ?> to 
               <?php echo min($page * $recordsPerPage, $totalRecords); ?> of 
               <?php echo $totalRecords; ?> reports
           </p>
       </div>
   </div>

   <!-- Footer -->
   <footer class="footer">
       <div class="container">
           <div class="d-flex justify-content-between align-items-center">
               <div>
                   <small class="text-muted">&copy; <?php echo date('Y'); ?> Barangay Health Workers Management System</small>
               </div>
               <div>
                   <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
               </div>
           </div>
       </div>
   </footer>

   <!-- Report Preview Modal -->
   <div class="modal fade" id="reportModal" tabindex="-1">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Report Details</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                   <div id="reportDetails">Loading...</div>
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                   <a href="#" class="btn btn-primary" id="viewFullReport">View Full Report</a>
               </div>
           </div>
       </div>
   </div>

   <!-- Save Filter Modal -->
   <div class="modal fade" id="saveFilterModal" tabindex="-1">
       <div class="modal-dialog">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Save Filter</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                   <form id="saveFilterForm" onsubmit="return false;">
                       <div class="mb-3">
                           <label for="filterName" class="form-label">Filter Name</label>
                           <input type="text" class="form-control" id="filterName" required>
                       </div>
                       <div class="mb-3">
                           <label for="filterDescription" class="form-label">Description (Optional)</label>
                           <textarea class="form-control" id="filterDescription" rows="2"></textarea>
                       </div>
                   </form>
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                   <button type="button" class="btn btn-primary" id="saveFilterBtn">Save Filter</button>
               </div>
           </div>
       </div>
   </div>

   <!-- Load Filter Modal -->
   <div class="modal fade" id="loadFilterModal" tabindex="-1">
       <div class="modal-dialog">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Load Saved Filter</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                   <div id="savedFiltersList">
                       <!-- Saved filters will be loaded here -->
                   </div>
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
               </div>
           </div>
       </div>
   </div>

   <!-- Column Visibility Modal -->
   <div class="modal fade" id="columnVisibilityModal" tabindex="-1">
       <div class="modal-dialog">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Customize Columns</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="reportId" id="colReportId" checked>
                       <label class="form-check-label" for="colReportId">ID</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="patientName" id="colPatientName" checked>
                       <label class="form-check-label" for="colPatientName">Patient Name</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="contactNumber" id="colContactNumber" checked>
                       <label class="form-check-label" for="colContactNumber">Contact</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="barangay" id="colBarangay" checked>
                       <label class="form-check-label" for="colBarangay">Barangay</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="animalType" id="colAnimalType" checked>
                       <label class="form-check-label" for="colAnimalType">Animal Type</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="biteDate" id="colBiteDate" checked>
                       <label class="form-check-label" for="colBiteDate">Bite Date</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="biteType" id="colBiteType" checked>
                       <label class="form-check-label" for="colBiteType">Category</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="status" id="colStatus" checked>
                       <label class="form-check-label" for="colStatus">Status</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="reportDate" id="colReportDate" checked>
                       <label class="form-check-label" for="colReportDate">Report Date</label>
                   </div>
                   <div class="form-check mb-2">
                       <input class="form-check-input column-toggle" type="checkbox" value="actions" id="colActions" checked>
                       <label class="form-check-label" for="colActions">Actions</label>
                   </div>
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                   <button type="button" class="btn btn-primary" id="applyColumnVisibility">Apply Changes</button>
               </div>
           </div>
       </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
       // Table sorting functionality
       document.querySelectorAll('th[data-sort]').forEach(header => {
           header.addEventListener('click', () => {
               const sortField = header.dataset.sort;
               const currentOrder = new URLSearchParams(window.location.search).get('order') || 'asc';
               const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
               
               const url = new URL(window.location.href);
               url.searchParams.set('sort', sortField);
               url.searchParams.set('order', newOrder);
               window.location.href = url.toString();
           });
       });

       // Save Filter functionality
       document.addEventListener('DOMContentLoaded', function() {
           const saveFilterBtn = document.getElementById('saveFilterBtn');
           if (saveFilterBtn) {
               saveFilterBtn.addEventListener('click', function() {
                   const filterName = document.getElementById('filterName').value.trim();
                   const filterDescription = document.getElementById('filterDescription').value.trim();
                   
                   if (!filterName) {
                       alert('Please enter a filter name');
                       return;
                   }

                   // Get current filter values
                   const currentFilters = {
                       name: filterName,
                       description: filterDescription,
                       filters: {
                           status: document.getElementById('status').value,
                           animal_type: document.getElementById('animal_type').value,
                           bite_type: document.getElementById('bite_type').value,
                           barangay: document.getElementById('barangay').value,
                           search: document.getElementById('search').value,
                           date_from: document.getElementById('date_from').value,
                           date_to: document.getElementById('date_to').value
                       }
                   };

                   try {
                       // Get existing filters from localStorage
                       let savedFilters = JSON.parse(localStorage.getItem('reportFilters') || '[]');
                       
                       // Check if filter name already exists
                       if (savedFilters.some(f => f.name === filterName)) {
                           if (!confirm('A filter with this name already exists. Do you want to overwrite it?')) {
                               return;
                           }
                           savedFilters = savedFilters.filter(f => f.name !== filterName);
                       }

                       // Add new filter
                       savedFilters.push(currentFilters);
                       
                       // Save to localStorage
                       localStorage.setItem('reportFilters', JSON.stringify(savedFilters));
                       
                       // Close modal and show success message
                       const modal = bootstrap.Modal.getInstance(document.getElementById('saveFilterModal'));
                       modal.hide();
                       
                       // Clear the form
                       document.getElementById('filterName').value = '';
                       document.getElementById('filterDescription').value = '';
                       
                       alert('Filter saved successfully!');
                   } catch (error) {
                       console.error('Error saving filter:', error);
                       alert('Error saving filter. Please try again.');
                   }
               });
           }
       });

       // Load Filter functionality
       function loadSavedFilters() {
           try {
               const savedFilters = JSON.parse(localStorage.getItem('reportFilters') || '[]');
               const filtersList = document.getElementById('savedFiltersList');
               
               if (savedFilters.length === 0) {
                   filtersList.innerHTML = '<p class="text-muted text-center">No saved filters found</p>';
                   return;
               }

               filtersList.innerHTML = savedFilters.map(filter => `
                   <div class="card mb-2">
                       <div class="card-body">
                           <h6 class="card-title">${filter.name}</h6>
                           ${filter.description ? `<p class="card-text small text-muted">${filter.description}</p>` : ''}
                           <div class="d-flex gap-2">
                               <button class="btn btn-sm btn-primary load-filter" data-filter='${JSON.stringify(filter)}'>
                                   <i class="bi bi-funnel me-1"></i>Load
                               </button>
                               <button class="btn btn-sm btn-danger delete-filter" data-name="${filter.name}">
                                   <i class="bi bi-trash me-1"></i>Delete
                               </button>
                           </div>
                       </div>
                   </div>
               `).join('');

               // Add event listeners for load buttons
               document.querySelectorAll('.load-filter').forEach(button => {
                   button.addEventListener('click', function() {
                       const filter = JSON.parse(this.dataset.filter);
                       applyFilter(filter.filters);
                   });
               });

               // Add event listeners for delete buttons
               document.querySelectorAll('.delete-filter').forEach(button => {
                   button.addEventListener('click', function() {
                       const filterName = this.dataset.name;
                       if (confirm(`Are you sure you want to delete the filter "${filterName}"?`)) {
                           let savedFilters = JSON.parse(localStorage.getItem('reportFilters') || '[]');
                           savedFilters = savedFilters.filter(f => f.name !== filterName);
                           localStorage.setItem('reportFilters', JSON.stringify(savedFilters));
                           loadSavedFilters(); // Refresh the list
                       }
                   });
               });
           } catch (error) {
               console.error('Error loading filters:', error);
               document.getElementById('savedFiltersList').innerHTML = 
                   '<p class="text-danger text-center">Error loading saved filters</p>';
           }
       }

       // Function to apply filter
       function applyFilter(filters) {
           const form = document.querySelector('form[method="GET"]');
           Object.entries(filters).forEach(([key, value]) => {
               const input = form.querySelector(`[name="${key}"]`);
               if (input) {
                   input.value = value;
               }
           });
           form.submit();
       }

       // Load saved filters when modal opens
       document.getElementById('loadFilterModal').addEventListener('show.bs.modal', loadSavedFilters);

       // Report preview modal
       const reportModal = document.getElementById('reportModal');
       reportModal.addEventListener('show.bs.modal', event => {
           const button = event.relatedTarget;
           const reportId = button.dataset.reportId;
           const modalBody = reportModal.querySelector('#reportDetails');
           const viewFullReportBtn = document.getElementById('viewFullReport');
           
           // Update view full report link
           viewFullReportBtn.href = `view_report.php?id=${reportId}`;
           
           // Load report details
           fetch(`get_report_details.php?id=${reportId}`)
               .then(response => response.json())
               .then(data => {
                   modalBody.innerHTML = `
                       <div class="row">
                           <div class="col-md-6">
                               <p><strong>Patient:</strong> ${data.patientName}</p>
                               <p><strong>Contact:</strong> ${data.contactNumber}</p>
                               <p><strong>Barangay:</strong> ${data.barangay}</p>
                           </div>
                           <div class="col-md-6">
                               <p><strong>Animal Type:</strong> ${data.animalType}</p>
                               <p><strong>Bite Category:</strong> ${data.biteType}</p>
                               <p><strong>Status:</strong> ${data.status}</p>
                           </div>
                       </div>
                   `;
               })
               .catch(error => {
                   modalBody.innerHTML = 'Error loading report details.';
               });
       });

       // Column visibility functionality
       document.addEventListener('DOMContentLoaded', function() {
           // Load saved column preferences
           const savedColumns = JSON.parse(localStorage.getItem('reportColumns') || '{}');
           
           // Initialize column visibility
           function initializeColumnVisibility() {
               const table = document.getElementById('reportsTable');
               if (!table) return; // Guard clause if table doesn't exist
               
               const headers = table.querySelectorAll('th');
               const checkboxes = document.querySelectorAll('.column-toggle');
               
               // Set initial checkbox states
               checkboxes.forEach(checkbox => {
                   const columnName = checkbox.value;
                   const isVisible = savedColumns[columnName] !== false; // Default to true if not saved
                   checkbox.checked = isVisible;
                   
                   // Find corresponding column index
                   const columnIndex = Array.from(headers).findIndex(th => 
                       th.getAttribute('data-sort') === columnName || 
                       (columnName === 'actions' && th.textContent.trim() === 'Actions')
                   );
                   
                   if (columnIndex !== -1) {
                       // Hide/show column in table
                       const cells = table.querySelectorAll(`td:nth-child(${columnIndex + 1}), th:nth-child(${columnIndex + 1})`);
                       cells.forEach(cell => {
                           cell.style.display = isVisible ? '' : 'none';
                       });
                   }
               });
           }
           
           // Apply column visibility changes
           const applyButton = document.getElementById('applyColumnVisibility');
           if (applyButton) {
               applyButton.addEventListener('click', function() {
                   const table = document.getElementById('reportsTable');
                   if (!table) return; // Guard clause if table doesn't exist
                   
                   const headers = table.querySelectorAll('th');
                   const checkboxes = document.querySelectorAll('.column-toggle');
                   const columnPreferences = {};
                   
                   checkboxes.forEach(checkbox => {
                       const columnName = checkbox.value;
                       const isVisible = checkbox.checked;
                       columnPreferences[columnName] = isVisible;
                       
                       // Find corresponding column index
                       const columnIndex = Array.from(headers).findIndex(th => 
                           th.getAttribute('data-sort') === columnName || 
                           (columnName === 'actions' && th.textContent.trim() === 'Actions')
                       );
                       
                       if (columnIndex !== -1) {
                           // Hide/show column in table
                           const cells = table.querySelectorAll(`td:nth-child(${columnIndex + 1}), th:nth-child(${columnIndex + 1})`);
                           cells.forEach(cell => {
                               cell.style.display = isVisible ? '' : 'none';
                           });
                       }
                   });
                   
                   // Save preferences to localStorage
                   localStorage.setItem('reportColumns', JSON.stringify(columnPreferences));
                   
                   // Close the modal
                   const modal = bootstrap.Modal.getInstance(document.getElementById('columnVisibilityModal'));
                   if (modal) {
                       modal.hide();
                   }
               });
           }
           
           // Initialize column visibility when page loads
           initializeColumnVisibility();
       });
   </script>
</body>
</html>
