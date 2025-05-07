<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
   header("Location: admin_login.html");
   exit;
}

require_once '../conn/conn.php';

// Get report ID from URL
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reportId <= 0) {
   header("Location: view_reports.php");
   exit;
}

// Get report details
try {
   $reportStmt = $pdo->prepare("
       SELECT r.*, 
              p.firstName, p.lastName, p.dateOfBirth, p.gender,
              p.contactNumber, p.email, p.address, p.barangay, p.city
       FROM reports r
       JOIN patients p ON r.patientId = p.patientId
       WHERE r.reportId = ?
   ");
   $reportStmt->execute([$reportId]);
   $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
   
   if (!$report) {
       header("Location: view_reports.php");
       exit;
   }
   
   // Get report images from the new report_images table
   $imagesStmt = $pdo->prepare("
       SELECT * FROM report_images 
       WHERE report_id = ?
       ORDER BY uploaded_at
   ");
   $imagesStmt->execute([$reportId]);
   $images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);
   
   // Get staff details if assigned
   $staffName = "Not assigned";
   if (!empty($report['staffId'])) {
       $staffStmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
       $staffStmt->execute([$report['staffId']]);
       $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
       if ($staff) {
           $staffName = $staff['firstName'] . ' ' . $staff['lastName'];
       }
   }
   
   // Get all staff for assignment dropdown
   $allStaffStmt = $pdo->prepare("SELECT staffId, firstName, lastName FROM staff ORDER BY lastName, firstName");
   $allStaffStmt->execute();
   $allStaff = $allStaffStmt->fetchAll(PDO::FETCH_ASSOC);
   
} catch (PDOException $e) {
   $error = "Database error: " . $e->getMessage();
}

// Process form submission for updating report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (isset($_POST['update_report'])) {
       $status = $_POST['status'];
       $notes = $_POST['notes'];
       $followUpDate = !empty($_POST['followUpDate']) ? $_POST['followUpDate'] : null;
       $staffId = !empty($_POST['staffId']) ? $_POST['staffId'] : null;
       
       try {
           $updateStmt = $pdo->prepare("
               UPDATE reports 
               SET status = ?, notes = ?, followUpDate = ?, staffId = ?
               WHERE reportId = ?
           ");
           $updateStmt->execute([$status, $notes, $followUpDate, $staffId, $reportId]);
           
           // Redirect to prevent form resubmission
           header("Location: view_report.php?id=$reportId&updated=1");
           exit;
       } catch (PDOException $e) {
           $error = "Error updating report: " . $e->getMessage();
       }
   }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>View Report | Admin Portal</title>
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
       
       .report-container {
           max-width: 1200px;
           margin: 0 auto;
           padding: 2rem 1rem;
       }
       
       .report-card {
           background-color: white;
           border-radius: 10px;
           box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
           margin-bottom: 2rem;
           overflow: hidden;
       }
       
       .report-header {
           padding: 1.5rem;
           border-bottom: 1px solid rgba(0, 0, 0, 0.05);
           background-color: rgba(var(--bs-primary-rgb), 0.03);
       }
       
       .report-body {
           padding: 1.5rem;
       }
       
       .image-gallery {
           display: flex;
           flex-wrap: wrap;
           gap: 15px;
           margin-top: 1rem;
       }
       
       .image-item {
           position: relative;
           width: 150px;
           height: 150px;
           border-radius: 8px;
           overflow: hidden;
           box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
       }
       
       .image-item img {
           width: 100%;
           height: 100%;
           object-fit: cover;
           cursor: pointer;
       }
       
       .image-overlay {
           position: absolute;
           bottom: 0;
           left: 0;
           right: 0;
           background-color: rgba(0, 0, 0, 0.5);
           color: white;
           padding: 5px;
           font-size: 0.75rem;
           text-align: center;
       }
       
       .modal-img {
           max-width: 100%;
           max-height: 80vh;
       }
       
       .status-badge {
           font-size: 0.875rem;
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
       
       .category-i {
           background-color: #28a745;
           color: white;
       }
       
       .category-ii {
           background-color: #fd7e14;
           color: white;
       }
       
       .category-iii {
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
       
       .footer {
           background-color: white;
           padding: 1rem 0;
           margin-top: 3rem;
           border-top: 1px solid rgba(0, 0, 0, 0.05);
       }
       
       @media print {
           .navbar, .footer, .no-print {
               display: none !important;
           }
           
           .report-card {
               box-shadow: none;
               margin-bottom: 1rem;
               break-inside: avoid;
           }
           
           body {
               background-color: white;
           }
       }
       
       @media (max-width: 768px) {
           .report-container {
               padding: 1rem;
           }
           
           .report-header, .report-body {
               padding: 1rem;
           }
           
           .image-item {
               width: 120px;
               height: 120px;
           }
       }
   </style>
</head>
<body>
   <!-- Navbar -->
   <nav class="navbar navbar-expand-lg navbar-light sticky-top mb-4">
       <div class="container">
           <a class="navbar-brand fw-bold" href="index.php">BHW Admin Portal</a>
           <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
               <span class="navbar-toggler-icon"></span>
           </button>
           <div class="collapse navbar-collapse" id="navbarNav">
               <ul class="navbar-nav ms-auto">
                   <li class="nav-item">
                       <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
                   </li>
                   <li class="nav-item">
                       <a class="nav-link active" href="view_reports.php"><i class="bi bi-file-earmark-text"></i> Reports</a>
                   </li>
                   <li class="nav-item">
                       <a class="nav-link" href="view_staff.php"><i class="bi bi-people"></i> Staff</a>
                   </li>
                   <li class="nav-item">
                       <a class="nav-link" href="admin_settings.php"><i class="bi bi-gear"></i> Settings</a>
                   </li>
                   <li class="nav-item">
                       <a class="nav-link btn-logout ms-2" href="../logout/admin_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                   </li>
               </ul>
           </div>
       </div>
   </nav>

   <div class="report-container">
       <!-- Page Header -->
       <div class="d-flex justify-content-between align-items-center mb-4">
           <div>
               <h2 class="mb-1">Report Details</h2>
               <p class="text-muted mb-0">Report ID: <?php echo htmlspecialchars($reportId); ?></p>
           </div>
           <div class="no-print">
               <a href="view_reports.php" class="btn btn-outline-primary">
                   <i class="bi bi-arrow-left me-2"></i>Back to Reports
               </a>
           </div>
       </div>
       
       <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
       <div class="alert alert-success alert-dismissible fade show mb-4 no-print" role="alert">
           <i class="bi bi-check-circle me-2"></i> Report has been successfully updated.
           <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
       </div>
       <?php endif; ?>
       
       <?php if (isset($error)): ?>
       <div class="alert alert-danger alert-dismissible fade show mb-4 no-print" role="alert">
           <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
           <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
       </div>
       <?php endif; ?>
       
       <!-- Report Status Card -->
       <div class="report-card">
           <div class="report-header">
               <div class="row align-items-center">
                   <div class="col-md-6">
                       <h4 class="mb-0">Report Status</h4>
                   </div>
                   <div class="col-md-6 text-md-end mt-3 mt-md-0">
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
                       
                       <?php if ($report['biteType']): ?>
                       <span class="status-badge ms-2 <?php 
                           switch($report['biteType']) {
                               case 'Category III': echo 'category-iii'; break;
                               case 'Category II': echo 'category-ii'; break;
                               case 'Category I': echo 'category-i'; break;
                               default: echo '';
                           }
                       ?>">
                           <?php echo $report['biteType']; ?>
                       </span>
                       <?php endif; ?>
                   </div>
               </div>
           </div>
           
           <div class="report-body">
               <div class="row">
                   <div class="col-md-6">
                       <p><strong>Report Date:</strong> <?php echo date('M d, Y g:i A', strtotime($report['reportDate'])); ?></p>
                       <p><strong>Bite Date:</strong> <?php echo date('M d, Y', strtotime($report['biteDate'])); ?></p>
                       <p><strong>Multiple Bites:</strong> <?php echo $report['multipleBites'] ? 'Yes' : 'No'; ?></p>
                   </div>
                   <div class="col-md-6">
                       <p><strong>Last Updated:</strong> <?php echo !empty($report['updated_at']) ? date('M d, Y g:i A', strtotime($report['updated_at'])) : 'Not updated yet'; ?></p>
                       <p><strong>Assigned Staff:</strong> <?php echo htmlspecialchars($staffName); ?></p>
                       <p><strong>Follow-up Date:</strong> <?php echo !empty($report['followUpDate']) ? date('M d, Y', strtotime($report['followUpDate'])) : 'Not scheduled'; ?></p>
                   </div>
               </div>
           </div>
       </div>
       
       <!-- Patient Information -->
       <div class="report-card">
           <div class="report-header">
               <h4 class="mb-0">Patient Information</h4>
           </div>
           
           <div class="report-body">
               <div class="row">
                   <div class="col-md-6">
                       <p><strong>Name:</strong> <?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></p>
                       <p><strong>Date of Birth:</strong> <?php echo date('M d, Y', strtotime($report['dateOfBirth'])); ?></p>
                       <p><strong>Gender:</strong> <?php echo htmlspecialchars($report['gender']); ?></p>
                       <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($report['contactNumber']); ?></p>
                       <p><strong>Email:</strong> <?php echo htmlspecialchars($report['email']); ?></p>
                   </div>
                   <div class="col-md-6">
                       <p><strong>Address:</strong> <?php echo htmlspecialchars($report['address']); ?></p>
                       <p><strong>Barangay:</strong> <?php echo htmlspecialchars($report['barangay']); ?></p>
                       <p><strong>City/Municipality:</strong> <?php echo htmlspecialchars($report['city']); ?></p>
                   </div>
               </div>
           </div>
       </div>
       
       <!-- Bite Information -->
       <div class="report-card">
           <div class="report-header">
               <h4 class="mb-0">Bite Information</h4>
           </div>
           
           <div class="report-body">
               <div class="row">
                   <div class="col-md-6">
                       <p><strong>Animal Type:</strong> <?php echo htmlspecialchars($report['animalType']); ?></p>
                       <?php if ($report['animalType'] === 'Other' && !empty($report['animalOtherType'])): ?>
                       <p><strong>Other Animal Type:</strong> <?php echo htmlspecialchars($report['animalOtherType']); ?></p>
                       <?php endif; ?>
                       <p><strong>Animal Ownership:</strong> <?php echo htmlspecialchars($report['animalOwnership']); ?></p>
                       <?php if (!empty($report['ownerName'])): ?>
                       <p><strong>Owner Name:</strong> <?php echo htmlspecialchars($report['ownerName']); ?></p>
                       <?php endif; ?>
                       <?php if (!empty($report['ownerContact'])): ?>
                       <p><strong>Owner Contact:</strong> <?php echo htmlspecialchars($report['ownerContact']); ?></p>
                       <?php endif; ?>
                       <p><strong>Animal Status:</strong> <?php echo htmlspecialchars($report['animalStatus']); ?></p>
                       <p><strong>Animal Vaccinated:</strong> <?php echo htmlspecialchars($report['animalVaccinated']); ?></p>
                   </div>
                   <div class="col-md-6">
                       <p><strong>Bite Location on Body:</strong> <?php echo htmlspecialchars($report['biteLocation']); ?></p>
                       <p><strong>Bite Type:</strong> <?php echo htmlspecialchars($report['biteType']); ?></p>
                       <p><strong>Provoked:</strong> <?php echo htmlspecialchars($report['provoked']); ?></p>
                       <p><strong>Washed with Soap:</strong> <?php echo $report['washWithSoap'] ? 'Yes' : 'No'; ?></p>
                       <p><strong>Rabies Vaccine:</strong> <?php echo $report['rabiesVaccine'] ? 'Yes' : 'No'; ?></p>
                       <?php if ($report['rabiesVaccine'] && !empty($report['rabiesVaccineDate'])): ?>
                       <p><strong>Rabies Vaccine Date:</strong> <?php echo date('M d, Y', strtotime($report['rabiesVaccineDate'])); ?></p>
                       <?php endif; ?>
                       <p><strong>Anti-Tetanus:</strong> <?php echo $report['antiTetanus'] ? 'Yes' : 'No'; ?></p>
                       <?php if ($report['antiTetanus'] && !empty($report['antiTetanusDate'])): ?>
                       <p><strong>Anti-Tetanus Date:</strong> <?php echo date('M d, Y', strtotime($report['antiTetanusDate'])); ?></p>
                       <?php endif; ?>
                   </div>
               </div>
               
               <div class="mt-3">
                   <?php if ($report['antibiotics']): ?>
                   <p><strong>Antibiotics:</strong> Yes</p>
                   <?php if (!empty($report['antibioticsDetails'])): ?>
                   <p><strong>Antibiotics Details:</strong> <?php echo htmlspecialchars($report['antibioticsDetails']); ?></p>
                   <?php endif; ?>
                   <?php else: ?>
                   <p><strong>Antibiotics:</strong> No</p>
                   <?php endif; ?>
                   
                   <?php if ($report['referredToHospital']): ?>
                   <p><strong>Referred to Hospital:</strong> Yes</p>
                   <?php if (!empty($report['hospitalName'])): ?>
                   <p><strong>Hospital Name:</strong> <?php echo htmlspecialchars($report['hospitalName']); ?></p>
                   <?php endif; ?>
                   <?php else: ?>
                   <p><strong>Referred to Hospital:</strong> No</p>
                   <?php endif; ?>
               </div>
           </div>
       </div>
       
       <!-- Bite Images -->
       <div class="report-card">
           <div class="report-header">
               <h4 class="mb-0">Bite Images</h4>
           </div>
           
           <div class="report-body">
               <?php if (!empty($images)): ?>
               <div class="image-gallery">
                   <?php foreach ($images as $index => $image): ?>
                   <div class="image-item">
                       <img src="../uploads/bites/<?php echo htmlspecialchars($image['image_path']); ?>" 
                            alt="<?php echo htmlspecialchars($image['description'] ?: 'Bite Image ' . ($index + 1)); ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#imageModal"
                            data-bs-img="../uploads/bites/<?php echo htmlspecialchars($image['image_path']); ?>"
                            data-bs-index="<?php echo $index + 1; ?>"
                            data-bs-desc="<?php echo htmlspecialchars($image['description'] ?: ''); ?>">
                       <div class="image-overlay">
                           <?php echo htmlspecialchars($image['description'] ?: 'Image ' . ($index + 1)); ?>
                       </div>
                   </div>
                   <?php endforeach; ?>
               </div>
               <?php else: ?>
               <div class="text-center py-4">
                   <i class="bi bi-card-image text-muted" style="font-size: 3rem;"></i>
                   <p class="mt-3 text-muted">No images were uploaded with this report.</p>
               </div>
               <?php endif; ?>
           </div>
       </div>
       
       <!-- Admin Notes and Actions -->
       <div class="report-card no-print">
           <div class="report-header">
               <h4 class="mb-0">Admin Notes and Actions</h4>
           </div>
           
           <div class="report-body">
               <form method="POST">
                   <div class="mb-3">
                       <label for="notes" class="form-label">Notes</label>
                       <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                       <div class="form-text">Add treatment notes, follow-up instructions, or other important information.</div>
                   </div>
                   
                   <div class="row mb-3">
                       <div class="col-md-4">
                           <label for="status" class="form-label">Status</label>
                           <select class="form-select" id="status" name="status">
                               <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                               <option value="in_progress" <?php echo $report['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                               <option value="completed" <?php echo $report['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                               <option value="referred" <?php echo $report['status'] === 'referred' ? 'selected' : ''; ?>>Referred</option>
                               <option value="cancelled" <?php echo $report['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                           </select>
                       </div>
                       
                       <div class="col-md-4">
                           <label for="staffId" class="form-label">Assign Staff</label>
                           <select class="form-select" id="staffId" name="staffId">
                               <option value="">-- Not Assigned --</option>
                               <?php foreach ($allStaff as $staff): ?>
                               <option value="<?php echo $staff['staffId']; ?>" <?php echo $report['staffId'] == $staff['staffId'] ? 'selected' : ''; ?>>
                                   <?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?>
                               </option>
                               <?php endforeach; ?>
                           </select>
                       </div>
                       
                       <div class="col-md-4">
                           <label for="followUpDate" class="form-label">Follow-up Date</label>
                           <input type="date" class="form-control" id="followUpDate" name="followUpDate" value="<?php echo !empty($report['followUpDate']) ? date('Y-m-d', strtotime($report['followUpDate'])) : ''; ?>">
                           <div class="form-text">Schedule the next follow-up appointment.</div>
                       </div>
                   </div>
                   
                   <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                       <button type="button" class="btn btn-outline-secondary me-md-2" onclick="window.print();">
                           <i class="bi bi-printer me-2"></i>Print Report
                       </button>
                       <button type="submit" name="update_report" class="btn btn-primary">
                           <i class="bi bi-save me-2"></i>Update Report
                       </button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   
   <!-- Image Modal -->
   <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
       <div class="modal-dialog modal-lg modal-dialog-centered">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title" id="imageModalLabel">Bite Image</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
               </div>
               <div class="modal-body text-center">
                   <img src="/placeholder.svg" class="modal-img" id="modalImage" alt="Bite Image">
                   <p class="mt-2" id="modalDescription"></p>
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
               </div>
           </div>
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

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
       // Image modal functionality
       const imageModal = document.getElementById('imageModal');
       if (imageModal) {
           imageModal.addEventListener('show.bs.modal', function (event) {
               const button = event.relatedTarget;
               const imgSrc = button.getAttribute('data-bs-img');
               const imgIndex = button.getAttribute('data-bs-index');
               const imgDesc = button.getAttribute('data-bs-desc');
               
               const modalTitle = this.querySelector('.modal-title');
               const modalImg = this.querySelector('#modalImage');
               const modalDesc = this.querySelector('#modalDescription');
               
               modalTitle.textContent = 'Bite Image ' + imgIndex;
               modalImg.src = imgSrc;
               modalDesc.textContent = imgDesc || '';
           });
       }
       
       // Print functionality - hide navbar and buttons when printing
       window.addEventListener('beforeprint', function() {
           document.querySelector('.navbar').style.display = 'none';
           document.querySelector('.footer').style.display = 'none';
           const buttons = document.querySelectorAll('button');
           buttons.forEach(button => {
               button.style.display = 'none';
           });
       });
       
       window.addEventListener('afterprint', function() {
           document.querySelector('.navbar').style.display = 'flex';
           document.querySelector('.footer').style.display = 'block';
           const buttons = document.querySelectorAll('button');
           buttons.forEach(button => {
               button.style.display = 'inline-block';
           });
       });
   </script>
</body>
</html>
