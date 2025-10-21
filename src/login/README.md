# Login System Updates

This update includes improved error handling and Google OAuth authentication for both admin and staff login systems.

## Features Added

### 1. Enhanced Error Handling
- Better error messages for failed login attempts
- Input validation for email format
- Shake animation for error messages
- Success message display
- Security logging for failed attempts

### 2. Google OAuth Integration
- Google Sign-In button on both admin and staff login pages
- Secure OAuth 2.0 flow implementation
- Automatic account linking for existing users
- Fallback to traditional email/password login

## Setup Instructions

### Quick Setup
1. Run the setup script: `src/login/google_setup.php`
2. Follow the on-screen instructions to configure Google OAuth

### Manual Setup

#### 1. Google Cloud Console Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable Google+ API and Google OAuth2 API
4. Create OAuth 2.0 Client ID credentials
5. Add redirect URI: `http://localhost/Animal-Bite/src/login/google_auth.php`

#### 2. Update Configuration
Edit `src/login/google_config.php`:
```php
return [
    'client_id' => 'your-actual-client-id.apps.googleusercontent.com',
    'client_secret' => 'your-actual-client-secret',
    'redirect_uri' => 'http://localhost/Animal-Bite/src/login/google_auth.php',
];
```

#### 3. Database Schema Update
Run these SQL commands to add Google ID support:
```sql
-- Add Google ID columns
ALTER TABLE admin ADD COLUMN google_id VARCHAR(255) NULL;
ALTER TABLE staff ADD COLUMN google_id VARCHAR(255) NULL;

-- Add indexes for better performance
CREATE INDEX idx_admin_google_id ON admin(google_id);
CREATE INDEX idx_staff_google_id ON staff(google_id);
```

## Files Modified/Created

### Modified Files
- `src/login/admin_login.html` - Enhanced UI with better error handling and Google Sign-In
- `src/login/admin_login.php` - Improved error handling and validation
- `src/login/staff_login.html` - Enhanced UI with better error handling and Google Sign-In
- `src/login/staff_login.php` - Improved error handling and validation

### New Files
- `src/login/google_auth.php` - Google OAuth authentication handler
- `src/login/google_config.php` - Google OAuth configuration
- `src/login/google_setup.php` - Setup helper script

## Error Messages

The system now provides specific error messages for different scenarios:

- **Invalid credentials**: "Invalid email or password. Please try again."
- **Empty fields**: "Please enter both email and password."
- **Invalid email**: "Please enter a valid email address."
- **Database error**: "A system error occurred. Please try again later."
- **Google user not found**: "No admin/staff account found with this Google email."
- **Google auth failed**: "Google authentication failed. Please try again or use email/password login."

## Security Features

- Failed login attempts are logged with IP addresses
- Session timeout management
- Secure password verification
- Input validation and sanitization
- CSRF protection through proper form handling

## Testing

1. Test traditional email/password login
2. Test Google OAuth login (requires setup)
3. Test error scenarios (invalid credentials, empty fields, etc.)
4. Verify error messages display correctly
5. Check that failed attempts are logged

## Production Deployment

For production deployment:

1. Update `google_config.php` with production redirect URI
2. Use environment variables for sensitive credentials
3. Enable HTTPS for secure OAuth flow
4. Update Google Console with production domain
5. Test thoroughly in production environment

## Troubleshooting

### Common Issues
- **redirect_uri_mismatch**: Check Google Console configuration
- **invalid_client**: Verify Client ID and Secret
- **Database errors**: Ensure google_id columns exist
- **OAuth flow fails**: Check network connectivity and API enablement

### Debug Mode
Enable PHP error reporting to see detailed error messages:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```
