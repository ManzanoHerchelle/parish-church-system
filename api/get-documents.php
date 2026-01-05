<?php
/**
 * API: Get User Document Requests
 * Returns: JSON list of document requests based on filters
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
$status = isset($_GET['status']) ? $_GET['status'] : 'pending'; // pending, ready, completed, all

// Build query based on status filter
$query = "SELECT dr.id, dr.reference_number, dr.status, dr.payment_status, 
                 dr.purpose, dr.created_at, dr.updated_at, dr.ready_date,
                 dt.name as document_type_name, dt.processing_days, dt.fee
          FROM document_requests dr
          JOIN document_types dt ON dr.document_type_id = dt.id
          WHERE dr.user_id = ?";

if ($status !== 'all') {
    $query .= " AND dr.status = ?";
}

$query .= " ORDER BY dr.created_at DESC LIMIT " . $limit;

$stmt = $conn->prepare($query);

if ($status !== 'all') {
    $stmt->bind_param("is", $userId, $status);
} else {
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();
$documents = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close connection
closeDBConnection($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['documents' => $documents]);
?>
