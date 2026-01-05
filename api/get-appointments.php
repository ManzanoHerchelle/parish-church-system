<?php
/**
 * API: Get User Appointments
 * Returns: JSON list of appointments based on filters
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
$status = isset($_GET['status']) ? $_GET['status'] : 'upcoming'; // upcoming, all, pending, approved, cancelled

// Build query based on status filter
$query = "SELECT b.id, b.reference_number, b.booking_date, b.booking_time, 
                 b.status, b.payment_status, b.purpose, bt.name as booking_type_name, 
                 bt.fee, b.created_at
          FROM bookings b
          JOIN booking_types bt ON b.booking_type_id = bt.id
          WHERE b.user_id = ?";

if ($status === 'upcoming') {
    $query .= " AND b.booking_date >= CURDATE() AND b.status IN ('pending', 'approved')";
} elseif ($status === 'past') {
    $query .= " AND b.booking_date < CURDATE()";
} elseif ($status === 'pending') {
    $query .= " AND b.status = 'pending'";
} elseif ($status === 'approved') {
    $query .= " AND b.status = 'approved'";
} elseif ($status === 'cancelled') {
    $query .= " AND b.status = 'cancelled'";
}

$query .= " ORDER BY b.booking_date DESC, b.booking_time DESC LIMIT " . $limit;

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close connection
closeDBConnection($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['appointments' => $appointments]);
?>
