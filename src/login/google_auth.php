<?php
session_start();
require_once '../conn/conn.php';
require_once 'google_config.php';

// Load Google OAuth configuration
$config = include 'google_config.php';
$client_id = $config['client_id'];
$client_secret = $config['client_secret'];
$redirect_uri = $config['redirect_uri'];

// Get the user type (admin or staff)
$user_type = isset($_GET['type']) ? $_GET['type'] : 'admin';

if (isset($_GET['code'])) {
    // Handle the OAuth callback
    $code = $_GET['code'];
    
    // Exchange code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirect_uri
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_info = json_decode($response, true);
    
    if (isset($token_info['access_token'])) {
        // Get user info from Google
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_info = json_decode($user_response, true);
        
        if ($user_info && isset($user_info['email'])) {
            $email = $user_info['email'];
            $name = $user_info['name'] ?? '';
            $google_id = $user_info['id'] ?? '';
            
            try {
                if ($user_type === 'admin') {
                    // Check if admin exists with this email
                    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
                    $stmt->execute(['email' => $email]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($admin) {
                        // Update Google ID if not set
                        if (empty($admin['google_id'])) {
                            $update_stmt = $pdo->prepare("UPDATE admin SET google_id = :google_id WHERE adminId = :admin_id");
                            $update_stmt->execute(['google_id' => $google_id, 'admin_id' => $admin['adminId']]);
                        }
                        
                        // Set session variables
                        $_SESSION['admin_id'] = $admin['adminId'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['login_method'] = 'google';
                        
                        header("Location: ../admin/admin_dashboard.php");
                        exit;
                    } else {
                        // Admin not found
                        header("Location: admin_login.html?error=google_user_not_found");
                        exit;
                    }
                } else {
                    // Check if staff exists with this email
                    $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = :email LIMIT 1");
                    $stmt->execute(['email' => $email]);
                    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($staff) {
                        // Update Google ID if not set
                        if (empty($staff['google_id'])) {
                            $update_stmt = $pdo->prepare("UPDATE staff SET google_id = :google_id WHERE staffId = :staff_id");
                            $update_stmt->execute(['google_id' => $google_id, 'staff_id' => $staff['staffId']]);
                        }
                        
                        // Set session variables
                        $_SESSION['staffId'] = $staff['staffId'];
                        $_SESSION['staffName'] = $staff['firstName'] . ' ' . $staff['lastName'];
                        $_SESSION['staff_email'] = $staff['email'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['login_method'] = 'google';
                        
                        header("Location: ../staff/dashboard.php");
                        exit;
                    } else {
                        // Staff not found
                        header("Location: staff_login.html?error=google_user_not_found");
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error during Google authentication: " . $e->getMessage());
                $error_page = $user_type === 'admin' ? 'admin_login.html' : 'staff_login.html';
                header("Location: $error_page?error=database_error");
                exit;
            }
        } else {
            // Failed to get user info
            $error_page = $user_type === 'admin' ? 'admin_login.html' : 'staff_login.html';
            header("Location: $error_page?error=google_auth_failed");
            exit;
        }
    } else {
        // Failed to get access token
        $error_page = $user_type === 'admin' ? 'admin_login.html' : 'staff_login.html';
        header("Location: $error_page?error=google_auth_failed");
        exit;
    }
} else {
    // Redirect to Google OAuth
    $auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => 'email profile',
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $user_type
    ]);
    
    header("Location: $auth_url");
    exit;
}
?>
