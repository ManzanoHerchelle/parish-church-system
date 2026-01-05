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
