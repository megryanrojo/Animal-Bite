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

// Get unread notifications
try {
    $notificationsStmt = $pdo->prepare("
        SELECT r.reportId, r.referenceNumber, r.reportDate, r.urgency, r.status,
               CONCAT(p.firstName, ' ', p.lastName) as patientName,
               p.contactNumber, p.barangay, p.address,
               r.animalType, r.biteType, r.biteDate
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.status = 'pending' 
        AND r.source = 'online'
        AND r.reportDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY 
            CASE WHEN r.urgency = 'High' THEN 0 ELSE 1 END,
            r.reportDate DESC
    ");
    $notificationsStmt->execute();
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get count of high urgency reports
    $highUrgencyStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reports 
        WHERE status = 'pending' 
        AND source = 'online' 
        AND urgency = 'High'
        AND reportDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $highUrgencyStmt->execute();
    $highUrgencyCount = $highUrgencyStmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $notifications = [];
    $highUrgencyCount = 0;
}

// Mark notification as read/in progress
if (isset($_POST['mark_in_progress']) && isset($_POST['report_id'])) {
    $reportId = (int)$_POST['report_id'];
    
    try {
        $updateStmt = $pdo->prepare("
            UPDATE reports 
            SET status = 'in_progress', 
                staffId = ?,
                updatedAt = NOW()
            WHERE reportId = ?
        ");
        $updateStmt->execute([$_SESSION['staffId'], $reportId]);
        
        // Create a notification for the admin
        $notificationStmt = $pdo->prepare("
            INSERT INTO notifications (
                title, message, type, link, created_at
            ) VALUES (
                'Report Status Updated',
                'Report #' . ? . ' has been marked as in progress by ' . ?,
                'info',
                'view_report.php?id=' . ?,
                NOW()
            )
        ");
        $notificationStmt->execute([
            $reportId,
            $_SESSION['staffId'],
            $reportId
        ]);
        
        $_SESSION['message'] = "Report has been marked as in progress.";
        header("Location: notification.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating report: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | Animal Bite Center</title>
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
        
        .notification-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            flex-grow: 1;
        }
        
        .notification-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .notification-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-body {
            padding: 0;
        }
        
        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        
        .notification-item.high-urgency {
            background-color: rgba(220, 53, 69, 0.05);
            border-left: 4px solid #dc3545;
        }
        
        .notification-item.high-urgency:hover {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .notification-time {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .badge-high {
            background-color: #dc3545;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        
        .badge-normal {
            background-color: #17a2b8;
            color: white;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
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
        
        @media (max-width: 768px) {
            .notification-container {
                padding: 1rem;
            }
            
            .notification-header, .notification-item {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-pulse"></i>
                <span class="fw-bold">Animal Bite Center</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door me-1"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_reports.php"><i class="bi bi-file-earmark-text me-1"></i> Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="patients.php"><i class="bi bi-people me-1"></i> Patients</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="notifications.php">
                            <i class="bi bi-bell me-1"></i> Notifications
                            <?php if (count($notifications) > 0): ?>
                            <span class="badge bg-danger"><?php echo count($notifications); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout/staff_logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="notification-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Online Report Notifications</h2>
                <p class="text-muted mb-0">Manage and respond to online animal bite reports</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
            </div>
        </div>
        
        <?php if ($highUrgencyCount > 0): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                <div>
                    <h4 class="alert-heading mb-1">Urgent Reports Require Attention!</h4>
                    <p class="mb-0">There are <strong><?php echo $highUrgencyCount; ?></strong> high urgency reports that need immediate attention.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notifications List -->
        <div class="notification-card">
            <div class="notification-header">
                <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Online Reports</h5>
                <span class="badge bg-secondary"><?php echo count($notifications); ?> Pending</span>
            </div>
            <div class="notification-body">
                <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['urgency'] === 'High' ? 'high-urgency' : ''; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($notification['patientName']); ?>
                                <span class="badge <?php echo $notification['urgency'] === 'High' ? 'badge-high' : 'badge-normal'; ?>">
                                    <?php echo $notification['urgency']; ?> Urgency
                                </span>
                            </h5>
                            <p class="mb-1">
                                <i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($notification['contactNumber']); ?> | 
                                <i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($notification['barangay']); ?>
                            </p>
                            <p class="notification-time mb-0">
                                <i class="bi bi-clock me-1"></i> Reported <?php echo date('M d, Y g:i A', strtotime($notification['reportDate'])); ?>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-0"><strong>Reference:</strong> <?php echo htmlspecialchars($notification['referenceNumber']); ?></p>
                        </div>
                        <div class="col-md-3 text-md-end mt-3 mt-md-0">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="report_id" value="<?php echo $notification['reportId']; ?>">
                                <button type="submit" name="mark_in_progress" class="btn btn-outline-primary me-2">
                                    <i class="bi bi-check-circle me-1"></i> Mark as In Progress
                                </button>
                            </form>
                            <a href="view_report.php?id=<?php echo $notification['reportId']; ?>" class="btn btn-primary">
                                <i class="bi bi-eye me-1"></i> View
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h4>No Pending Reports</h4>
                    <p class="text-muted">There are no pending online reports at this time.</p>
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
    <script>
        // Auto-refresh the page every 60 seconds to check for new notifications
        setTimeout(function() {
            location.reload();
        }, 60000);
        
        // Play notification sound if there are high urgency reports
        document.addEventListener('DOMContentLoaded', function() {
            const highUrgencyCount = <?php echo $highUrgencyCount; ?>;
            if (highUrgencyCount > 0) {
                // You would need to add an audio file to your project
                // const audio = new Audio('notification-sound.mp3');
                // audio.play();
            }
        });
    </script>
</body>
</html>
