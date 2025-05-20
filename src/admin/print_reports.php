<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$biteType = isset($_GET['bite_type']) ? $_GET['bite_type'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query
$whereConditions = [];
$params = [];

if ($type === 'filtered') {
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

    if (!empty($status)) {
        $whereConditions[] = "r.status = ?";
        $params[] = $status;
    }
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

try {
    $query = "
        SELECT r.*, p.firstName, p.lastName, p.contactNumber, p.barangay
        FROM reports r
        LEFT JOIN patients p ON r.patientId = p.patientId
        $whereClause
        ORDER BY r.reportDate DESC
    ";
    
    $stmt = $pdo->prepare($query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Bite Reports - Print View</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 12pt;
                line-height: 1.4;
                color: #000;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 1rem;
            }
            
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
            }
            
            th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .header {
                text-align: center;
                margin-bottom: 2rem;
            }
            
            .filters {
                margin-bottom: 1rem;
                padding: 1rem;
                border: 1px solid #000;
            }
            
            .filters p {
                margin: 0.5rem 0;
            }
            
            .status-badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 10pt;
            }
            
            .status-pending { background-color: #ffc107; }
            .status-in-progress { background-color: #17a2b8; color: white; }
            .status-completed { background-color: #28a745; color: white; }
            .status-referred { background-color: #6f42c1; color: white; }
            .status-cancelled { background-color: #dc3545; color: white; }
            
            .bite-category-1 { background-color: #28a745; color: white; }
            .bite-category-2 { background-color: #fd7e14; color: white; }
            .bite-category-3 { background-color: #dc3545; color: white; }
        }
        
        /* Screen styles */
        body {
            font-family: Arial, sans-serif;
            margin: 2rem;
            background-color: #f8f9fa;
        }
        
        .print-button {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .print-button:hover {
            background-color: #0056b3;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 2rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-button no-print">Print Reports</button>
    
    <div class="container">
        <div class="header">
            <h1>Animal Bite Reports</h1>
            <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        
        <?php if ($type === 'filtered'): ?>
        <div class="filters">
            <h3>Applied Filters:</h3>
            <p><strong>Date Range:</strong> 
                <?php 
                if (!empty($dateFrom) && !empty($dateTo)) {
                    echo date('F d, Y', strtotime($dateFrom)) . ' to ' . date('F d, Y', strtotime($dateTo));
                } elseif (!empty($dateFrom)) {
                    echo 'From ' . date('F d, Y', strtotime($dateFrom));
                } elseif (!empty($dateTo)) {
                    echo 'Until ' . date('F d, Y', strtotime($dateTo));
                } else {
                    echo 'All dates';
                }
                ?>
            </p>
            <p><strong>Animal Type:</strong> <?php echo !empty($animalType) ? $animalType : 'All'; ?></p>
            <p><strong>Bite Category:</strong> <?php echo !empty($biteType) ? $biteType : 'All'; ?></p>
            <p><strong>Barangay:</strong> <?php echo !empty($barangay) ? $barangay : 'All'; ?></p>
            <p><strong>Status:</strong> <?php echo !empty($status) ? ucfirst(str_replace('_', ' ', $status)) : 'All'; ?></p>
        </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient Name</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Animal Type</th>
                    <th>Bite Date</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Report Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo htmlspecialchars($report['reportId']); ?></td>
                    <td><?php echo htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></td>
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Total Reports: <?php echo count($reports); ?></p>
        </div>
    </div>
</body>
</html> 