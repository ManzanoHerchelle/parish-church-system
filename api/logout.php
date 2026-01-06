<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();

// Log the logout activity if user is logged in
if (isLoggedIn()) {
    $userId = getCurrentUserId();
    $conn = getDBConnection();
    
    if ($conn && $userId) {
        // Verify user exists before logging
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, 'logout', 'User logged out', ?, ?)");
            
            if ($logStmt) {
                $logStmt->bind_param("iss", $userId, $ipAddress, $userAgent);
                $logStmt->execute();
                $logStmt->close();
            }
        }
        $checkStmt->close();
        closeDBConnection($conn);
    }
}

// Logout user
logoutUser();

// Redirect to login page
header('Location: /documentSystem/client/login.php');
exit;
?>
