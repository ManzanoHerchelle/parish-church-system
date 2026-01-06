<?php
// Email Configuration Template
// Copy this file to email_config.php and update with your credentials

// SMTP Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);

// Gmail Credentials - UPDATE THESE!
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password-here');

// Email Settings
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Parish Church System');
define('SMTP_REPLY_TO', 'your-email@gmail.com');

// Email Options
define('SMTP_DEBUG', 0);
define('SMTP_TIMEOUT', 30);

// Application URLs - UPDATE FOR PRODUCTION!
define('BASE_URL', 'http://localhost/documentSystem');
define('VERIFY_URL', BASE_URL . '/verify-email.php');
define('RESET_URL', BASE_URL . '/reset-password.php');
?>

/*
 * SETUP INSTRUCTIONS:
 * 
 * 1. Go to your Google Account (https://myaccount.google.com/)
 * 2. Click "Security" in the left menu
 * 3. Enable "2-Step Verification" if not already enabled
 * 4. After enabling 2FA, go to "Security" again
 * 5. Click "App passwords" (appears after 2FA is enabled)
 * 6. Select "Mail" and "Windows Computer" (or Other)
 * 7. Click "Generate"
 * 8. Copy the 16-character password (no spaces)
 * 9. Paste it in SMTP_PASSWORD above
 * 10. Replace SMTP_USERNAME and SMTP_FROM_EMAIL with your Gmail address
 * 
 * NOTE: Use App Password, NOT your regular Gmail password!
 */