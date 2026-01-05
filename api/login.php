<?php
/**
 * Login API Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

// Initialize response
$response = ['success' => false, 'message' => '', 'redirect' => ''];

try {
    // Get and sanitize input
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        throw new Exception('Email and password are required.');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format.');
    }
    
    // Get database connection
    $conn = getDBConnection();
    
    // Prepare and execute query
    $stmt = $conn->prepare("SELECT id, email, password, first_name, last_name, role, status, email_verified FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Invalid email or password.');
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password - support both SHA2-256 and bcrypt
    $password_verified = false;
    
    // Try SHA2-256 first
    if ($user['password'] === hash('sha256', $password)) {
        $password_verified = true;
    }
    // Fall back to bcrypt
    elseif (password_verify($password, $user['password'])) {
        $password_verified = true;
    }
    
    if (!$password_verified) {
        throw new Exception('Invalid email or password.');
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        throw new Exception('Your account is not active. Please contact the administrator.');
    }
    
    // Check if email is verified
    if (!$user['email_verified']) {
        throw new Exception('Please verify your email address before logging in. Check your inbox for the verification link.');
    }
    
    // Login successful - set session
    loginUser(
        $user['id'],
        $user['email'],
        $user['role'],
        $user['first_name'],
        $user['last_name']
    );
    
    // Log activity
    $userId = $user['id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, 'login', 'User logged in', ?, ?)");
    $logStmt->bind_param("iss", $userId, $ipAddress, $userAgent);
    $logStmt->execute();
    $logStmt->close();
    
    $stmt->close();
    closeDBConnection($conn);
    
    // Redirect based on role
    $redirectUrl = match($user['role']) {
        'admin' => '/documentSystem/admin/dashboard.php',
        'staff' => '/documentSystem/admin/dashboard.php',
        'client' => '/documentSystem/client/dashboard.php',
        default => '/documentSystem/client/dashboard.php'
    };
    
    $response['success'] = true;
    $response['message'] = 'Login successful!';
    $response['redirect'] = $redirectUrl;
    
    // Redirect
    header('Location: ' . $redirectUrl);
    exit;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Store error in session and redirect back
    $_SESSION['login_error'] = $response['message'];
    header('Location: /documentSystem/client/login.php');
    exit;
}
?>
