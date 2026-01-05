<?php
/**
 * Email Verification Page
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();

// Check if already logged in
if (isLoggedIn()) {
    header('Location: /documentSystem/client/dashboard.php');
    exit;
}

$message = '';
$messageType = 'info'; // 'success', 'error', 'info'
$verified = false;

// Get token from URL
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    try {
        // Get database connection
        $conn = getDBConnection();
        
        // Find user with this verification token
        $stmt = $conn->prepare("SELECT id, email, first_name, verification_token FROM users WHERE verification_token = ? LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Invalid or expired verification token.');
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Update user to mark email as verified
        $updateStmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, status = 'active' WHERE id = ?");
        $updateStmt->bind_param("i", $user['id']);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to verify email. Please try again.');
        }
        
        $updateStmt->close();
        
        // Log the verification activity
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, 'verify_email', 'Email verified', ?, ?)");
        $logStmt->bind_param("iss", $user['id'], $ipAddress, $userAgent);
        $logStmt->execute();
        $logStmt->close();
        
        closeDBConnection($conn);
        
        $messageType = 'success';
        $message = "Email verified successfully! You can now log in to your account.";
        $verified = true;
        
    } catch (Exception $e) {
        $messageType = 'error';
        $message = $e->getMessage();
    }
} else {
    $messageType = 'error';
    $message = 'No verification token provided. Please check your email for the verification link.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ParishEase - Email Verification</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .verification-container {
      background: white;
      border-radius: 10px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      padding: 40px;
      max-width: 500px;
      width: 100%;
      text-align: center;
    }

    .verification-icon {
      font-size: 60px;
      margin-bottom: 20px;
    }

    .success-icon {
      color: #28a745;
    }

    .error-icon {
      color: #dc3545;
    }

    .info-icon {
      color: #17a2b8;
    }

    .verification-title {
      font-size: 28px;
      font-weight: bold;
      margin-bottom: 20px;
      color: #333;
    }

    .verification-message {
      font-size: 16px;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .alert {
      margin-bottom: 30px;
      font-size: 15px;
    }

    .btn-login {
      background: #667eea;
      color: white;
      padding: 12px 30px;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      text-decoration: none;
      display: inline-block;
      transition: background 0.3s ease;
    }

    .btn-login:hover {
      background: #764ba2;
      color: white;
    }

    .btn-back {
      display: block;
      margin-top: 20px;
      color: #667eea;
      text-decoration: none;
      font-size: 14px;
    }

    .btn-back:hover {
      text-decoration: underline;
    }

    .spinner {
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="verification-container">
    <?php if ($verified): ?>
      <div class="verification-icon success-icon">
        <i class="bi bi-check-circle"></i>
      </div>
      <div class="verification-title">Email Verified!</div>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($message); ?>
      </div>
      <div class="verification-message">
        Your email address has been successfully verified. You can now log in to your account and start using all features of the Parish Church System.
      </div>
      <a href="/documentSystem/client/login.php" class="btn-login">
        <i class="bi bi-box-arrow-in-right"></i> Go to Login
      </a>
    <?php elseif ($messageType === 'error'): ?>
      <div class="verification-icon error-icon">
        <i class="bi bi-exclamation-circle"></i>
      </div>
      <div class="verification-title">Verification Failed</div>
      <div class="alert alert-danger">
        <?php echo htmlspecialchars($message); ?>
      </div>
      <div class="verification-message">
        The verification link may have expired or is invalid. Verification links are valid for 24 hours.
      </div>
      <a href="/documentSystem/client/login.php" class="btn-login">
        <i class="bi bi-box-arrow-in-right"></i> Back to Login
      </a>
      <a href="/documentSystem/client/register.php" class="btn-back">
        Create a new account
      </a>
    <?php else: ?>
      <div class="verification-icon info-icon spinner">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div class="verification-title">Verifying Email...</div>
      <div class="verification-message">
        <?php echo htmlspecialchars($message); ?>
      </div>
      <a href="/documentSystem/client/login.php" class="btn-back">
        Back to Login
      </a>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
