<?php
/**
 * Client Navigation Helper
 * Calculates notification counts for sidebar badges on all client pages
 */

// Initialize navigation stats
$navStats = array(
    'pending_appointments' => 0,
    'pending_documents' => 0,
    'ready_documents' => 0,
    'unread_notifications' => 0
);

// Only calculate if user is logged in
if (isLoggedIn()) {
    $userId = getCurrentUserId();
    $conn = getDBConnection();
    
    // Count pending appointments
    $countQuery = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status IN ('pending', 'approved') AND booking_date >= CURDATE()";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $navStats['pending_appointments'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // Count pending documents
    $countQuery = "SELECT COUNT(*) as count FROM document_requests WHERE user_id = ? AND status IN ('pending', 'processing')";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $navStats['pending_documents'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // Count ready documents
    $countQuery = "SELECT COUNT(*) as count FROM document_requests WHERE user_id = ? AND status = 'ready'";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $navStats['ready_documents'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // Count unread notifications
    $countQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $navStats['unread_notifications'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    closeDBConnection($conn);
}

// Also set as $stats for backward compatibility with dashboard
$stats = $navStats;
?>
