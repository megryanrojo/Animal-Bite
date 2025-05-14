<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get admin information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM admin WHERE adminId = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin = ['firstName' => 'Admin', 'lastName' => ''];
}

// Check if the barangay_coordinates table exists
try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'barangay_coordinates'");
    $tableExists = $tableExistsStmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create the table
        $createTableSQL = "
            CREATE TABLE barangay_coordinates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                barangay VARCHAR(100) NOT NULL,
                latitude DECIMAL(10, 7) NOT NULL,
                longitude DECIMAL(10, 7) NOT NULL,
                UNIQUE KEY (barangay)
            )
        ";
        $pdo->exec($createTableSQL);
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission for adding/updating coordinates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                // Add new coordinates
                $stmt = $pdo->prepare("INSERT INTO barangay_coordinates (barangay, latitude, longitude) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE latitude = ?, longitude = ?");
                $stmt->execute([
                    $_POST['barangay'],
                    $_POST['latitude'],
                    $_POST['longitude'],
                    $_POST['latitude'],
                    $_POST['longitude']
                ]);
                $success = "Coordinates for " . htmlspecialchars($_POST['barangay']) . " added successfully.";
            } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                // Delete coordinates
                $stmt = $pdo->prepare("DELETE FROM barangay_coordinates WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Coordinates deleted successfully.";
            } elseif ($_POST['action'] === 'import' && isset($_FILES['csv_file'])) {
                // Import coordinates from CSV
                $file = $_FILES['csv_file']['tmp_name'];
                if (($handle = fopen($file, "r")) !== FALSE) {
                    // Skip header row
                    fgetcsv($handle, 1000, ",");
                    
                    $importCount = 0;
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 3) {
                            $barangay = trim($data[0]);
                            $latitude = trim($data[1]);
                            $longitude = trim($data[2]);
                            
                            if (!empty($barangay) && is_numeric($latitude) && is_numeric($longitude)) {
                                $stmt = $pdo->prepare("INSERT INTO barangay_coordinates (barangay, latitude, longitude) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE latitude = ?, longitude = ?");
                                $stmt->execute([$barangay, $latitude, $longitude, $latitude, $longitude]);
                                $importCount++;
                            }
                        }
                    }
                    fclose($handle);
                    $success = "$importCount barangay coordinates imported successfully.";
                } else {
                    $error = "Unable to open the CSV file.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get all barangays without coordinates
try {
    $missingCoordinatesStmt = $pdo->query("
        SELECT DISTINCT p.barangay 
        FROM patients p 
        LEFT JOIN barangay_coordinates bc ON p.barangay = bc.barangay 
        WHERE p.barangay IS NOT NULL AND p.barangay != '' AND bc.id IS NULL
        ORDER BY p.barangay
    ");
    $missingCoordinates = $missingCoordinatesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $missingCoordinates = [];
}

// Get all coordinates
try {
    $coordinatesStmt = $pdo->query("SELECT * FROM barangay_coordinates ORDER BY barangay");
    $coordinates = $coordinatesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $coordinates = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Barangay Coordinates | Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
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
        
        .container {
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
        
        #map {
            height: 400px;
            width: 100%;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-plus"></i>
                <span class="fw-bold">Admin Portal</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house-door me-1"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_reports.php"><i class="bi bi-file-earmark-text me-1"></i> Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="geomapping.php"><i class="bi bi-geo-alt me-1"></i> Geomapping</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_coordinates.php"><i class="bi bi-pin-map me-1"></i> Coordinates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_settings.php"><i class="bi bi-gear me-1"></i> Settings</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($admin['firstName'] . ' ' . $admin['lastName']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="admin_profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="admin_settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout/admin_logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Manage Barangay Coordinates</h2>
                <p class="text-muted mb-0">Add and update geographic coordinates for barangays</p>
            </div>
            <div>
                <a href="geomapping.php" class="btn btn-primary">
                    <i class="bi bi-geo-alt me-2"></i>View Geomapping
                </a>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Map Preview -->
            <div class="col-md-8">
                <div class="content-card">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-map me-2"></i>Map Preview</h5>
                    </div>
                    <div class="content-card-body">
                        <div id="map"></div>
                        <p class="text-muted small">Click on the map to set coordinates for a new barangay.</p>
                    </div>
                </div>
            </div>
            
            <!-- Add Coordinates Form -->
            <div class="col-md-4">
                <div class="content-card">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-pin-map me-2"></i>Add Coordinates</h5>
                    </div>
                    <div class="content-card-body">
                        <form method="POST" action="manage_coordinates.php">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="barangay" class="form-label">Barangay</label>
                                <select class="form-select" id="barangay" name="barangay" required>
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($missingCoordinates as $barangay): ?>
                                    <option value="<?php echo htmlspecialchars($barangay); ?>">
                                        <?php echo htmlspecialchars($barangay); ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <option value="custom">Add Custom Barangay</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="customBarangayField" style="display: none;">
                                <label for="customBarangay" class="form-label">Custom Barangay Name</label>
                                <input type="text" class="form-control" id="customBarangay" name="customBarangay">
                            </div>
                            
                            <div class="mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="number" class="form-control" id="latitude" name="latitude" step="0.0000001" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="number" class="form-control" id="longitude" name="longitude" step="0.0000001" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle me-2"></i>Add Coordinates
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Import Coordinates -->
                <div class="content-card mt-4">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-upload me-2"></i>Import Coordinates</h5>
                    </div>
                    <div class="content-card-body">
                        <form method="POST" action="manage_coordinates.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="import">
                            
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">CSV File</label>
                                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                <div class="form-text">
                                    CSV format: Barangay, Latitude, Longitude
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-upload me-2"></i>Import CSV
                            </button>
                        </form>
                        
                        <div class="mt-3">
                            <a href="sample_coordinates.csv" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="bi bi-download me-2"></i>Download Sample CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Coordinates -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Existing Coordinates</h5>
            </div>
            <div class="content-card-body">
                <?php if (count($coordinates) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Barangay</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coordinates as $coord): ?>
                            <tr>
                                <td><?php echo $coord['id']; ?></td>
                                <td><?php echo htmlspecialchars($coord['barangay']); ?></td>
                                <td><?php echo $coord['latitude']; ?></td>
                                <td><?php echo $coord['longitude']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary view-on-map" 
                                            data-lat="<?php echo $coord['latitude']; ?>" 
                                            data-lng="<?php echo $coord['longitude']; ?>"
                                            data-name="<?php echo htmlspecialchars($coord['barangay']); ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-coord" 
                                            data-id="<?php echo $coord['id']; ?>"
                                            data-barangay="<?php echo htmlspecialchars($coord['barangay']); ?>"
                                            data-lat="<?php echo $coord['latitude']; ?>" 
                                            data-lng="<?php echo $coord['longitude']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="manage_coordinates.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $coord['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete these coordinates?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>No coordinates have been added yet.
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
                    <small class="text-muted">&copy; <?php echo date('Y'); ?> Barangay Health Workers Management System</small>
                </div>
                <div>
                    <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <script>
        // Initialize the map
        var map = L.map('map').setView([10.7445, 122.9760], 13);
        
        // Add OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Add existing coordinates to the map
        var markers = [];
        <?php foreach ($coordinates as $coord): ?>
        var marker = L.marker([<?php echo $coord['latitude']; ?>, <?php echo $coord['longitude']; ?>])
            .addTo(map)
            .bindPopup("<strong><?php echo htmlspecialchars($coord['barangay']); ?></strong>");
        markers.push(marker);
        <?php endforeach; ?>
        
        // Click on map to set coordinates
        map.on('click', function(e) {
            document.getElementById('latitude').value = e.latlng.lat.toFixed(7);
            document.getElementById('longitude').value = e.latlng.lng.toFixed(7);
        });
        
        // View on map button
        document.querySelectorAll('.view-on-map').forEach(function(button) {
            button.addEventListener('click', function() {
                var lat = parseFloat(
