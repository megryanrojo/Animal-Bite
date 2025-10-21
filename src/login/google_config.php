<?php
// Google OAuth Configuration
// Replace these with your actual Google OAuth credentials

// To get these credentials:
// 1. Go to Google Cloud Console (https://console.cloud.google.com/)
// 2. Create a new project or select existing one
// 3. Enable Google+ API
// 4. Go to Credentials and create OAuth 2.0 Client ID
// 5. Add your domain to authorized redirect URIs

return [
    'client_id' => 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
    'redirect_uri' => 'http://localhost/Animal-Bite/src/login/google_auth.php',
    
    // For production, update the redirect URI to your actual domain:
    // 'redirect_uri' => 'https://yourdomain.com/src/login/google_auth.php',
];
?>
