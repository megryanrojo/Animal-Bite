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
        :root {
            --primary: #0f766e;
            --primary-soft: #e0f2f1;
            --text-main: #0f172a;
            --text-muted: #6b7280;
            --border-soft: #e5e7eb;
        }

        /* SCREEN PREVIEW */
        body {
            background: #f3f4f6;
            padding: 24px;
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text-main);
        }

        .shell {
            max-width: 960px;
            margin: 0 auto;
        }

        .card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 24px 28px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }

        .facility-name {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--primary);
        }

        .facility-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .generated-on {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-align: right;
        }

        .divider {
            margin: 16px 0 20px;
            border: none;
            border-top: 1px solid var(--border-soft);
        }

        .filters {
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            background: #f9fafb;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-size: 0.9rem;
        }

        .filters-title {
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 24px;
        }

        .filter-pill-label {
            font-weight: 600;
            color: var(--text-muted);
        }

        .filter-pill-value {
            font-weight: 500;
            color: var(--text-main);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 0.86rem;
        }

        thead th {
            background: #f9fafb;
            color: #4b5563;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-soft);
            font-weight: 600;
            white-space: nowrap;
        }

        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) td {
            background: #fcfcfc;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-in-progress { background-color: #dbeafe; color: #1d4ed8; }
        .status-completed { background-color: #dcfce7; color: #166534; }
        .status-referred { background-color: #ede9fe; color: #5b21b6; }
        .status-cancelled { background-color: #fee2e2; color: #b91c1c; }

        .bite-category-1 { background-color: #dcfce7; color: #166534; }
        .bite-category-2 { background-color: #ffedd5; color: #c2410c; }
        .bite-category-3 { background-color: #fee2e2; color: #b91c1c; }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .footer-strong {
            font-weight: 600;
            color: var(--text-main);
        }

        .print-button {
            position: fixed;
            top: 16px;
            right: 16px;
            background: var(--primary);
            color: #fff;
            padding: 8px 14px;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 0.85rem;
            box-shadow: 0 8px 20px rgba(15, 118, 110, 0.4);
        }

        .print-button:hover {
            background: #115e59;
        }

        @media (max-width: 640px) {
            .card {
                padding: 16px 14px;
            }

            .header-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .generated-on {
                text-align: left;
            }
        }

        /* PRINT STYLING */
        @media print {
            @page {
                size: A4;
                margin: 1.5cm;
            }

            body {
                background: #ffffff;
                padding: 0;
                font-family: "Times New Roman", serif;
                font-size: 11pt;
                color: #000;
            }

            .print-button,
            .no-print {
                display: none !important;
            }

            .shell {
                max-width: none;
                margin: 0;
            }

            .card {
                box-shadow: none;
                padding: 0;
            }

            .facility-name {
                text-align: center;
                font-size: 18pt;
                margin-bottom: 2px;
            }

            .facility-sub {
                text-align: center;
                font-size: 11pt;
                margin-bottom: 8px;
            }

            .generated-on {
                text-align: center;
                margin-bottom: 10px;
                font-size: 10pt;
            }

            .divider {
                margin: 10px 0 12px;
                border-top-color: #000;
            }

            .filters {
                border-color: #000;
                background: #f8f8f8;
                margin-bottom: 12px;
                font-size: 10pt;
            }

            table {
                margin-top: 4px;
                font-size: 9.5pt;
                page-break-inside: auto;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            thead th {
                background: #e5e7eb !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                text-align: center;
                border-bottom: 1px solid #000;
            }

            tbody td {
                border-bottom: 1px solid #d4d4d4;
            }

            .status-badge {
                border-radius: 3px;
                padding: 2px 5px;
            }

            .footer {
                margin-top: 16px;
                font-size: 10pt;
            }
        }
    </style>
</head>
<body>

<button onclick="window.print()" class="print-button no-print">Print reports</button>

<div class="shell">
    <div class="card">

        <div class="header-row">
            <div>
                <div class="facility-name">Animal Bite Treatment Center</div>
                <div class="facility-sub">Consolidated Patient Bite Reports</div>
            </div>
            <div class="generated-on">
                Generated on<br>
                <strong><?php echo date('F d, Y h:i A'); ?></strong>
            </div>
        </div>

        <hr class="divider">

        <?php if ($type === 'filtered'): ?>
        <div class="filters">
            <div class="filters-title">Applied filters</div>
            <div class="filters-row">
                <div>
                    <span class="filter-pill-label">Date range:</span>
                    <span class="filter-pill-value">
                        <?php 
                            if ($dateFrom && $dateTo) {
                                echo date('F d, Y', strtotime($dateFrom)) . " - " . date('F d, Y', strtotime($dateTo));
                            } elseif ($dateFrom) {
                                echo "From " . date('F d, Y', strtotime($dateFrom));
                            } elseif ($dateTo) {
                                echo "Until " . date('F d, Y', strtotime($dateTo));
                            } else {
                                echo "All dates";
                            }
                        ?>
                    </span>
                </div>
                <div>
                    <span class="filter-pill-label">Animal type:</span>
                    <span class="filter-pill-value"><?= $animalType ?: 'All'; ?></span>
                </div>
                <div>
                    <span class="filter-pill-label">Bite category:</span>
                    <span class="filter-pill-value"><?= $biteType ?: 'All'; ?></span>
                </div>
                <div>
                    <span class="filter-pill-label">Barangay:</span>
                    <span class="filter-pill-value"><?= $barangay ?: 'All'; ?></span>
                </div>
                <div>
                    <span class="filter-pill-label">Status:</span>
                    <span class="filter-pill-value">
                        <?= $status ? ucfirst(str_replace('_',' ',$status)) : 'All'; ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Patient name</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Animal</th>
                    <th>Bite date</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Report date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reports) === 0): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:16px; color:#9ca3af;">
                            No reports found for the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= htmlspecialchars($report['firstName'] . ' ' . $report['lastName']); ?></td>
                        <td><?= htmlspecialchars($report['contactNumber']); ?></td>
                        <td><?= htmlspecialchars($report['barangay']); ?></td>
                        <td><?= htmlspecialchars($report['animalType']); ?></td>
                        <td><?= $report['biteDate'] ? date('M d, Y', strtotime($report['biteDate'])) : '—'; ?></td>
                        <td>
                            <span class="status-badge
                                <?php 
                                    switch ($report['biteType']) {
                                        case 'Category I': echo 'bite-category-1'; break;
                                        case 'Category II': echo 'bite-category-2'; break;
                                        case 'Category III': echo 'bite-category-3'; break;
                                    }
                                ?>">
                                <?= htmlspecialchars($report['biteType']); ?>
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
                                <?= ucfirst(str_replace('_',' ', $report['status'])); ?>
                            </span>
                        </td>
                        <td><?= $report['reportDate'] ? date('M d, Y', strtotime($report['reportDate'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <div>Prepared by: <span class="footer-strong">Animal Bite Center System</span></div>
            <div class="footer-strong">Total reports: <?= count($reports); ?></div>
        </div>
    </div>
</div>

</body>
</html>
