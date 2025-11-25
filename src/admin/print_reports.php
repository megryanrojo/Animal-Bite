<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

$type = $_GET['type'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$animalType = $_GET['animal_type'] ?? '';
$biteType = $_GET['bite_type'] ?? '';
$barangay = $_GET['barangay'] ?? '';
$status = $_GET['status'] ?? '';

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
    $stmt->execute($params);
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
        /* PRINT STYLING */
        @media print {
            @page {
                size: A4;
                margin: 1.5cm;
            }

            body {
                font-family: "Times New Roman", serif;
                font-size: 11pt;
                color: #000;
            }

            .no-print {
                display: none !important;
            }

            .header-title {
                text-align: center;
                font-size: 20pt;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .sub-header {
                text-align: center;
                font-size: 12pt;
                margin-bottom: 20px;
            }

            .filters {
                border: 1px solid #000;
                padding: 10px;
                margin-bottom: 20px;
                background: #f8f8f8;
            }

            .filters h3 {
                margin-top: 0;
                font-size: 13pt;
                text-decoration: underline;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            th, td {
                border: 1px solid #000;
                padding: 6px 8px;
            }

            th {
                background: #e0e0e0 !important;
                font-size: 11pt;
                font-weight: bold;
                text-align: center;
                -webkit-print-color-adjust: exact;
            }

            .footer {
                margin-top: 30px;
                font-size: 12pt;
                text-align: right;
            }

            /* badges */
            .status-badge {
                padding: 3px 7px;
                border-radius: 3px;
                font-size: 10pt;
                font-weight: bold;
            }

            .status-pending { background-color: #ffc107; }
            .status-in-progress { background-color: #17a2b8; color: #fff; }
            .status-completed { background-color: #28a745; color: #fff; }
            .status-referred { background-color: #6f42c1; color: #fff; }
            .status-cancelled { background-color: #dc3545; color: #fff; }

            .bite-category-1 { background-color: #28a745; color: white; }
            .bite-category-2 { background-color: #fd7e14; color: white; }
            .bite-category-3 { background-color: #dc3545; color: white; }
        }

        /* SCREEN PREVIEW */
        body {
            background: #f2f2f2;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            background: #fff;
            padding: 20px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .print-button {
            position: fixed;
            top: 15px;
            right: 15px;
            background: #007bff;
            color: #fff;
            padding: 10px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .print-button:hover {
            background: #0056b3;
        }

    </style>
</head>
<body>

<button onclick="window.print()" class="print-button no-print">Print Reports</button>

<div class="container">

    <div class="header-title">Animal Bite Report Summary</div>
    <div class="sub-header">Generated on: <?php echo date('F d, Y h:i A'); ?></div>

    <?php if ($type === 'filtered'): ?>
    <div class="filters">
        <h3>Applied Filters</h3>
        <p><strong>Date Range:</strong>
            <?php 
                if ($dateFrom && $dateTo) {
                    echo date('F d, Y', strtotime($dateFrom)) . " - " . date('F d, Y', strtotime($dateTo));
                } elseif ($dateFrom) {
                    echo "From " . date('F d, Y', strtotime($dateFrom));
                } elseif ($dateTo) {
                    echo "Until " . date('F d, Y', strtotime($dateTo));
                } else {
                    echo "All Dates";
                }
            ?>
        </p>
        <p><strong>Animal Type:</strong> <?= $animalType ?: 'All' ?></p>
        <p><strong>Bite Category:</strong> <?= $biteType ?: 'All' ?></p>
        <p><strong>Barangay:</strong> <?= $barangay ?: 'All' ?></p>
        <p><strong>Status:</strong> <?= $status ? ucfirst(str_replace('_',' ',$status)) : 'All' ?></p>
    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
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
                <td><?= htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></td>
                <td><?= htmlspecialchars($report['contactNumber']); ?></td>
                <td><?= htmlspecialchars($report['barangay']); ?></td>
                <td><?= htmlspecialchars($report['animalType']); ?></td>
                <td><?= date('M d, Y', strtotime($report['biteDate'])); ?></td>
                <td>
                    <span class="status-badge
                        <?php 
                            switch ($report['biteType']) {
                                case 'Category I': echo 'bite-category-1'; break;
                                case 'Category II': echo 'bite-category-2'; break;
                                case 'Category III': echo 'bite-category-3'; break;
                            }
                        ?>">
                        <?= $report['biteType'] ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge
                        <?php 
                            switch ($report['status']) {
                                case 'pending': echo 'status-pending'; break;
                                case 'in_progress': echo 'status-in-progress'; break;
                                case 'completed': echo 'status-completed'; break;
                                case 'referred': echo 'status-referred'; break;
                                case 'cancelled': echo 'status-cancelled'; break;
                            }
                        ?>">
                        <?= ucfirst(str_replace('_',' ', $report['status'])) ?>
                    </span>
                </td>
                <td><?= date('M d, Y', strtotime($report['reportDate'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <strong>Total Reports: <?= count($reports); ?></strong>
    </div>
</div>

</body>
</html>
