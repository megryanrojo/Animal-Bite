<?php
/**
 * Google OAuth Setup Script
 * This script helps configure Google OAuth for the Animal Bite system
 */

echo "<h2>Google OAuth Setup Instructions</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px;'>";

echo "<h3>Step 1: Create Google Cloud Project</h3>";
echo "<ol>";
echo "<li>Go to <a href='https://console.cloud.google.com/' target='_blank'>Google Cloud Console</a></li>";
echo "<li>Create a new project or select an existing one</li>";
echo "<li>Note down your project ID</li>";
echo "</ol>";

echo "<h3>Step 2: Enable Google+ API</h3>";
echo "<ol>";
echo "<li>In the Google Cloud Console, go to 'APIs & Services' > 'Library'</li>";
echo "<li>Search for 'Google+ API' and enable it</li>";
echo "<li>Also enable 'Google OAuth2 API' if available</li>";
echo "</ol>";

echo "<h3>Step 3: Create OAuth 2.0 Credentials</h3>";
echo "<ol>";
echo "<li>Go to 'APIs & Services' > 'Credentials'</li>";
echo "<li>Click 'Create Credentials' > 'OAuth 2.0 Client ID'</li>";
echo "<li>Choose 'Web application' as the application type</li>";
echo "<li>Add authorized redirect URIs:</li>";
echo "<ul>";
echo "<li><code>http://localhost/Animal-Bite/src/login/google_auth.php</code> (for development)</li>";
echo "<li><code>https://yourdomain.com/src/login/google_auth.php</code> (for production)</li>";
echo "</ul>";
echo "<li>Copy the Client ID and Client Secret</li>";
echo "</ol>";

echo "<h3>Step 4: Update Configuration</h3>";
echo "<p>Edit the file <code>src/login/google_config.php</code> and replace:</p>";
echo "<ul>";
echo "<li><code>YOUR_GOOGLE_CLIENT_ID</code> with your actual Client ID</li>";
echo "<li><code>YOUR_GOOGLE_CLIENT_SECRET</code> with your actual Client Secret</li>";
echo "<li>Update the redirect URI to match your domain</li>";
echo "</ul>";

echo "<h3>Step 5: Database Schema Update</h3>";
echo "<p>Add Google ID columns to your database tables:</p>";
echo "<pre>";
echo "-- For admin table
ALTER TABLE admin ADD COLUMN google_id VARCHAR(255) NULL;

-- For staff table  
ALTER TABLE staff ADD COLUMN google_id VARCHAR(255) NULL;

-- Add indexes for better performance
CREATE INDEX idx_admin_google_id ON admin(google_id);
CREATE INDEX idx_staff_google_id ON staff(google_id);
";
echo "</pre>";

echo "<h3>Step 6: Test the Integration</h3>";
echo "<ol>";
echo "<li>Visit the admin login page: <a href='admin_login.html'>admin_login.html</a></li>";
echo "<li>Click 'Continue with Google'</li>";
echo "<li>Complete the Google OAuth flow</li>";
echo "<li>Verify that you're logged in successfully</li>";
echo "</ol>";

echo "<h3>Security Notes</h3>";
echo "<ul>";
echo "<li>Keep your Client Secret secure and never commit it to version control</li>";
echo "<li>Use environment variables for production deployments</li>";
echo "<li>Regularly rotate your OAuth credentials</li>";
echo "<li>Monitor OAuth usage in Google Cloud Console</li>";
echo "</ul>";

echo "<h3>Troubleshooting</h3>";
echo "<ul>";
echo "<li><strong>Error: redirect_uri_mismatch</strong> - Check that your redirect URI exactly matches what's configured in Google Console</li>";
echo "<li><strong>Error: invalid_client</strong> - Verify your Client ID and Secret are correct</li>";
echo "<li><strong>Error: access_denied</strong> - User cancelled the OAuth flow or denied permissions</li>";
echo "<li><strong>Database errors</strong> - Ensure the google_id columns exist in your tables</li>";
echo "</ul>";

echo "</div>";

echo "<h3>Current Configuration Status</h3>";
$config_file = 'google_config.php';
if (file_exists($config_file)) {
    $config = include $config_file;
    echo "<table border='1' style='border-collapse: collapse; margin: 20px;'>";
    echo "<tr><th>Setting</th><th>Status</th></tr>";
    echo "<tr><td>Client ID</td><td>" . (strpos($config['client_id'], 'YOUR_') === false ? '✅ Configured' : '❌ Needs Setup') . "</td></tr>";
    echo "<tr><td>Client Secret</td><td>" . (strpos($config['client_secret'], 'YOUR_') === false ? '✅ Configured' : '❌ Needs Setup') . "</td></tr>";
    echo "<tr><td>Redirect URI</td><td>" . (strpos($config['redirect_uri'], 'localhost') !== false ? '✅ Development' : '✅ Production') . "</td></tr>";
    echo "</table>";
} else {
    echo "<p>❌ Configuration file not found</p>";
}
?>
