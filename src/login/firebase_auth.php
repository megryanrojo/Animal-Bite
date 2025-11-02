<?php
session_start();

// Set JSON header first
header('Content-Type: application/json');

// Try to include database connection
try {
    require_once '../conn/conn.php';
    
    // Verify database connection is available
    if (!isset($pdo)) {
        throw new Exception('Database connection variable ($pdo) not found');
    }
    
    // Test the connection with a simple query
    try {
        $testQuery = $pdo->query("SELECT 1 as test");
        if (!$testQuery) {
            throw new Exception('Database connection test query failed');
        }
    } catch (PDOException $testError) {
        throw new PDOException('Database connection test failed: ' . $testError->getMessage());
    }
} catch (PDOException $e) {
    error_log("Database connection error in firebase_auth.php: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Please check your server configuration.',
        'debug_info' => 'Check error logs for details'
    ]);
    exit;
} catch (Exception $e) {
    error_log("General error in firebase_auth.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'System error occurred. Please contact your administrator.',
        'debug_info' => 'Check error logs for details'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['idToken']) || !isset($input['type'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$idToken = $input['idToken'];
$userType = $input['type']; // 'admin' or 'staff'

// Firebase project configuration
$apiKey = "AIzaSyCFBtZ_T_hNpqNyDRQjLy2aA_J6rxvVlhQ";
$verifyTokenUrl = "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=" . $apiKey;

// Verify the Firebase ID token using Firebase REST API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verifyTokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMessage = $errorData['error']['message'] ?? 'Token verification failed';
    error_log("Firebase token verification failed (HTTP $httpCode): " . $response);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired token. Please try signing in again.']);
    exit;
}

if ($curlError) {
    error_log("cURL error during token verification: " . $curlError);
    echo json_encode(['success' => false, 'error' => 'Connection error. Please try again.']);
    exit;
}

$tokenData = json_decode($response, true);

if (!isset($tokenData['users']) || empty($tokenData['users'])) {
    error_log("No user data in Firebase response: " . $response);
    echo json_encode(['success' => false, 'error' => 'Invalid token response']);
    exit;
}

$userData = $tokenData['users'][0];
$email = $userData['email'] ?? null;
$googleId = $userData['localId'] ?? null;
$name = $userData['displayName'] ?? null;
$emailVerified = $userData['emailVerified'] ?? false;

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Email not found in token']);
    exit;
}

try {
    if ($userType === 'admin') {
        // Check if admin exists with this email
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            echo json_encode([
                'success' => false,
                'error' => 'No admin account found with this Google email. Please contact your administrator.'
            ]);
            exit;
        }
        
        // Update Google ID if not set (only if column exists)
        if (!empty($googleId)) {
            try {
                // Check if google_id column exists before updating
                $checkColumn = $pdo->query("SHOW COLUMNS FROM admin LIKE 'google_id'");
                if ($checkColumn->rowCount() > 0 && empty($admin['google_id'])) {
                    $update_stmt = $pdo->prepare("UPDATE admin SET google_id = :google_id WHERE adminId = :admin_id");
                    $update_stmt->execute([
                        'google_id' => $googleId,
                        'admin_id' => $admin['adminId']
                    ]);
                }
            } catch (PDOException $updateError) {
                // Silently ignore if column doesn't exist or update fails
                error_log("Could not update google_id for admin: " . $updateError->getMessage());
            }
        }
        
        // Set session variables
        $_SESSION['admin_id'] = $admin['adminId'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['login_method'] = 'google_firebase';
        
        echo json_encode([
            'success' => true,
            'redirect' => '../admin/admin_dashboard.php'
        ]);
    } else if ($userType === 'staff') {
        // Check if staff exists with this email
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            echo json_encode([
                'success' => false,
                'error' => 'No staff account found with this Google email. Please contact your administrator.'
            ]);
            exit;
        }
        
        // Update Google ID if not set (only if column exists)
        if (!empty($googleId)) {
            try {
                // Check if google_id column exists before updating
                $checkColumn = $pdo->query("SHOW COLUMNS FROM staff LIKE 'google_id'");
                if ($checkColumn->rowCount() > 0 && empty($staff['google_id'])) {
                    $update_stmt = $pdo->prepare("UPDATE staff SET google_id = :google_id WHERE staffId = :staff_id");
                    $update_stmt->execute([
                        'google_id' => $googleId,
                        'staff_id' => $staff['staffId']
                    ]);
                }
            } catch (PDOException $updateError) {
                // Silently ignore if column doesn't exist or update fails
                error_log("Could not update google_id for staff: " . $updateError->getMessage());
            }
        }
        
        // Set session variables
        $_SESSION['staffId'] = $staff['staffId'];
        $_SESSION['staffName'] = $staff['firstName'] . ' ' . $staff['lastName'];
        $_SESSION['staff_email'] = $staff['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['login_method'] = 'google_firebase';
        
        echo json_encode([
            'success' => true,
            'redirect' => '../staff/dashboard.php'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid user type']);
    }
} catch (PDOException $e) {
    error_log("Database error during Firebase authentication: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Check for specific database errors
    $errorMessage = 'A system error occurred. Please try again later.';
    if (strpos($e->getMessage(), 'SQLSTATE') !== false) {
        // It's a database-specific error
        if (strpos($e->getMessage(), 'could not find driver') !== false) {
            $errorMessage = 'Database driver not found. Please contact your administrator.';
        } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
            $errorMessage = 'Database access denied. Please contact your administrator.';
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            $errorMessage = 'Database not found. Please contact your administrator.';
        }
    }
    
    echo json_encode([
        'success' => false,
        'error' => $errorMessage
    ]);
} catch (Exception $e) {
    error_log("General error during Firebase authentication: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'A system error occurred. Please try again later.'
    ]);
}
?>

