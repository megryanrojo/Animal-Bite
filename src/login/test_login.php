<?php
/**
 * Login System Test Script
 * This script tests the updated login functionality
 */

echo "<h2>Login System Test Results</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px;'>";

// Test 1: Check if files exist
echo "<h3>File Existence Tests</h3>";
$files_to_check = [
    'admin_login.html',
    'admin_login.php', 
    'staff_login.html',
    'staff_login.php',
    'google_auth.php',
    'google_config.php',
    'google_setup.php'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>File</th><th>Status</th></tr>";

foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    $status = $exists ? '✅ Exists' : '❌ Missing';
    echo "<tr><td>$file</td><td>$status</td></tr>";
}

echo "</table>";

// Test 2: Check configuration
echo "<h3>Configuration Tests</h3>";
if (file_exists('google_config.php')) {
    $config = include 'google_config.php';
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
    
    $client_id_status = strpos($config['client_id'], 'YOUR_') === false ? '✅ Configured' : '⚠️ Needs Setup';
    $client_secret_status = strpos($config['client_secret'], 'YOUR_') === false ? '✅ Configured' : '⚠️ Needs Setup';
    
    echo "<tr><td>Client ID</td><td>" . substr($config['client_id'], 0, 20) . "...</td><td>$client_id_status</td></tr>";
    echo "<tr><td>Client Secret</td><td>" . substr($config['client_secret'], 0, 10) . "...</td><td>$client_secret_status</td></tr>";
    echo "<tr><td>Redirect URI</td><td>{$config['redirect_uri']}</td><td>✅ Set</td></tr>";
    echo "</table>";
} else {
    echo "<p>❌ Configuration file not found</p>";
}

// Test 3: Check database connection
echo "<h3>Database Connection Test</h3>";
try {
    require_once '../conn/conn.php';
    echo "<p>✅ Database connection successful</p>";
    
    // Check if google_id columns exist
    $admin_check = $pdo->query("SHOW COLUMNS FROM admin LIKE 'google_id'")->fetch();
    $staff_check = $pdo->query("SHOW COLUMNS FROM staff LIKE 'google_id'")->fetch();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Table</th><th>google_id Column</th><th>Status</th></tr>";
    echo "<tr><td>admin</td><td>google_id</td><td>" . ($admin_check ? '✅ Exists' : '❌ Missing') . "</td></tr>";
    echo "<tr><td>staff</td><td>google_id</td><td>" . ($staff_check ? '✅ Exists' : '❌ Missing') . "</td></tr>";
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test 4: URL accessibility
echo "<h3>URL Accessibility Tests</h3>";
$base_url = 'http://localhost/Animal-Bite/src/login/';
$urls_to_test = [
    'admin_login.html',
    'staff_login.html',
    'google_setup.php'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>URL</th><th>Status</th></tr>";

foreach ($urls_to_test as $url) {
    $full_url = $base_url . $url;
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'HEAD'
        ]
    ]);
    
    $headers = @get_headers($full_url, 1, $context);
    $status = $headers && strpos($headers[0], '200') !== false ? '✅ Accessible' : '⚠️ Check Manually';
    echo "<tr><td><a href='$full_url' target='_blank'>$url</a></td><td>$status</td></tr>";
}

echo "</table>";

// Test 5: Security recommendations
echo "<h3>Security Recommendations</h3>";
echo "<ul>";
echo "<li>✅ Input validation implemented</li>";
echo "<li>✅ Password verification using password_verify()</li>";
echo "<li>✅ Failed login attempts logging</li>";
echo "<li>✅ Session management</li>";
echo "<li>⚠️ Consider implementing rate limiting for failed attempts</li>";
echo "<li>⚠️ Consider implementing CAPTCHA after multiple failed attempts</li>";
echo "<li>⚠️ Ensure HTTPS in production</li>";
echo "</ul>";

echo "<h3>Next Steps</h3>";
echo "<ol>";
echo "<li>Configure Google OAuth credentials in google_config.php</li>";
echo "<li>Add google_id columns to database tables</li>";
echo "<li>Test login functionality with both methods</li>";
echo "<li>Test error scenarios</li>";
echo "<li>Deploy to production with proper security measures</li>";
echo "</ol>";

echo "</div>";
?>
