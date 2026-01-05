<?php
/**
 * API: Get User Payment History
 * Returns: JSON list of payments
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = getCurrentUserId();
$conn = getDBConnection();

// Get query parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
$status = isset($_GET['status']) ? $_GET['status'] : null; // null = all, 'paid', 'unpaid', 'pending'

// Build query
$query = "SELECT p.id, p.transaction_number, p.amount, p.payment_method, p.status, 
                 p.created_at, p.reference_type, 
                 COALESCE(dr.reference_number, b.reference_number) as ref_number,
                 COALESCE(dt.name, bt.name) as item_name
          FROM payments p
          LEFT JOIN document_requests dr ON p.reference_type = 'document_request' AND p.reference_id = dr.id
          LEFT JOIN document_types dt ON dr.document_type_id = dt.id
          LEFT JOIN bookings b ON p.reference_type = 'booking' AND p.reference_id = b.id
          LEFT JOIN booking_types bt ON b.booking_type_id = bt.id
          WHERE p.user_id = ?";

if ($status) {
    $query .= " AND p.status = ?";
}

$query .= " ORDER BY p.created_at DESC LIMIT " . $limit;

$stmt = $conn->prepare($query);

if ($status) {
    $stmt->bind_param("is", $userId, $status);
} else {
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close connection
closeDBConnection($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['payments' => $payments]);
?>
