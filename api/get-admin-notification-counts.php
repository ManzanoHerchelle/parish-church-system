<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$conn = getDBConnection();

try {
    // Get pending document count
    $docStmt = $conn->prepare("SELECT COUNT(*) as count FROM document_requests WHERE status = 'pending'");
    $docStmt->execute();
    $pendingDocuments = $docStmt->get_result()->fetch_assoc()['count'];
    
    // Get pending booking count
    $bookStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
    $bookStmt->execute();
    $pendingBookings = $bookStmt->get_result()->fetch_assoc()['count'];
    
    // Get pending payment count
    $payStmt = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
    $payStmt->execute();
    $pendingPayments = $payStmt->get_result()->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'pending_documents' => (int)$pendingDocuments,
        'pending_bookings' => (int)$pendingBookings,
        'pending_payments' => (int)$pendingPayments
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notification counts',
        'pending_documents' => 0,
        'pending_bookings' => 0,
        'pending_payments' => 0
    ]);
}

closeDBConnection($conn);
