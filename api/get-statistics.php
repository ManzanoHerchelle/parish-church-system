<?php
/**
 * API: Get User Dashboard Statistics
 * Returns: JSON with pending counts, ready counts, unread notifications
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

// Start secure session
startSecureSession();

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get user ID
$userId = getCurrentUserId();
$conn = getDBConnection();

// Response array
$stats = array();

// Count pending appointments (upcoming)
$countQuery = "SELECT COUNT(*) as count FROM bookings 
              WHERE user_id = ? AND status IN ('pending', 'approved') AND booking_date >= CURDATE()";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['pending_appointments'] = (int)$stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count pending documents (not ready)
$countQuery = "SELECT COUNT(*) as count FROM document_requests 
              WHERE user_id = ? AND status IN ('pending', 'processing')";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['pending_documents'] = (int)$stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count ready documents
$countQuery = "SELECT COUNT(*) as count FROM document_requests 
              WHERE user_id = ? AND status = 'ready'";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['ready_documents'] = (int)$stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count unread notifications
$countQuery = "SELECT COUNT(*) as count FROM notifications 
              WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['unread_notifications'] = (int)$stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count unpaid payments
$countQuery = "SELECT COUNT(*) as count FROM payments 
              WHERE user_id = ? AND status = 'unpaid'";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['unpaid_payments'] = (int)$stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Close connection
closeDBConnection($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($stats);
?>
