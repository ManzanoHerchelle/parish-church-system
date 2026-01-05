<?php
/**
 * Session Management Helper
 */

// Start session with secure settings
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set ini settings BEFORE starting the session
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', '0'); // Set to 1 in production with HTTPS
        ini_set('session.cookie_samesite', 'Lax');
        
        // Start the session
        session_start();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user email
function getCurrentUserEmail() {
    return $_SESSION['user_email'] ?? null;
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// Login user
function loginUser($userId, $email, $role, $firstName, $lastName) {
    // First set the session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
    $_SESSION['login_time'] = time();
    
    // Then regenerate session ID for security (after setting data)
    session_regenerate_id(true);
}

// Logout user
function logoutUser() {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

// Require login (redirect if not logged in)
function requireLogin($redirectUrl = '/client/login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Require specific role
function requireRole($allowedRoles, $redirectUrl = '/client/login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    $userRole = getCurrentUserRole();
    if (!in_array($userRole, $allowedRoles)) {
        header('Location: /403.php');
        exit;
    }
}
?>
