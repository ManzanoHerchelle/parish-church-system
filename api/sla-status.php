<?php
/**
 * API: Get SLA Status
 * Returns real-time SLA status for dashboard refresh
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

// Check if user is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();

// Count critical documents
$docCriticalQuery = "
    SELECT COUNT(*) as count
    FROM document_requests
    WHERE status = 'pending'
    AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > 48
";
$docCritical = $conn->query($docCriticalQuery)->fetch_assoc()['count'];

// Count warning documents
$docWarningQuery = "
    SELECT COUNT(*) as count
    FROM document_requests
    WHERE status = 'pending'
    AND TIMESTAMPDIFF(HOUR, created_at, NOW()) BETWEEN 36 AND 48
";
$docWarning = $conn->query($docWarningQuery)->fetch_assoc()['count'];

// Count critical bookings
$bookingCriticalQuery = "
    SELECT COUNT(*) as count
    FROM bookings
    WHERE status = 'pending'
    AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > 24
";
$bookingCritical = $conn->query($bookingCriticalQuery)->fetch_assoc()['count'];

// Count warning bookings
$bookingWarningQuery = "
    SELECT COUNT(*) as count
    FROM bookings
    WHERE status = 'pending'
    AND TIMESTAMPDIFF(HOUR, created_at, NOW()) BETWEEN 18 AND 24
";
$bookingWarning = $conn->query($bookingWarningQuery)->fetch_assoc()['count'];

// Count critical payments
$paymentCriticalQuery = "
    SELECT COUNT(*) as count
    FROM payments
    WHERE status = 'pending'
    AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > 24
";
$paymentCritical = $conn->query($paymentCriticalQuery)->fetch_assoc()['count'];

// Count warning payments
$paymentWarningQuery = "
    SELECT COUNT(*) as count
    FROM payments
    WHERE status = 'pending'
    AND TIMESTAMPDIFF(HOUR, created_at, NOW()) BETWEEN 18 AND 24
";
$paymentWarning = $conn->query($paymentWarningQuery)->fetch_assoc()['count'];

// Get most critical items
$criticalItemsQuery = "
    (SELECT 'document' as type, 
            dr.reference_number, 
            dt.name as item_name,
            CONCAT(u.first_name, ' ', u.last_name) as client_name,
            TIMESTAMPDIFF(HOUR, dr.created_at, NOW()) as hours_pending
     FROM document_requests dr
     JOIN document_types dt ON dr.document_type_id = dt.id
     JOIN users u ON dr.user_id = u.id
     WHERE dr.status = 'pending'
     AND TIMESTAMPDIFF(HOUR, dr.created_at, NOW()) > 48
     ORDER BY dr.created_at ASC
     LIMIT 5)
    
    UNION ALL
    
    (SELECT 'booking' as type,
            b.reference_number,
            bt.name as item_name,
            CONCAT(u.first_name, ' ', u.last_name) as client_name,
            TIMESTAMPDIFF(HOUR, b.created_at, NOW()) as hours_pending
     FROM bookings b
     JOIN booking_types bt ON b.booking_type_id = bt.id
     JOIN users u ON b.user_id = u.id
     WHERE b.status = 'pending'
     AND TIMESTAMPDIFF(HOUR, b.created_at, NOW()) > 24
     ORDER BY b.created_at ASC
     LIMIT 5)
    
    UNION ALL
    
    (SELECT 'payment' as type,
            p.transaction_number as reference_number,
            CONCAT('₱', FORMAT(p.amount, 2)) as item_name,
            CONCAT(u.first_name, ' ', u.last_name) as client_name,
            TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_pending
     FROM payments p
     JOIN users u ON p.user_id = u.id
     WHERE p.status = 'pending'
     AND TIMESTAMPDIFF(HOUR, p.created_at, NOW()) > 24
     ORDER BY p.created_at ASC
     LIMIT 5)
    
    ORDER BY hours_pending DESC
    LIMIT 10
";

$criticalItems = [];
$result = $conn->query($criticalItemsQuery);
while ($row = $result->fetch_assoc()) {
    $criticalItems[] = $row;
}

closeDBConnection($conn);

$totalCritical = $docCritical + $bookingCritical + $paymentCritical;
$totalWarning = $docWarning + $bookingWarning + $paymentWarning;

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'critical' => $totalCritical,
        'warning' => $totalWarning,
        'total' => $totalCritical + $totalWarning
    ],
    'documents' => [
        'critical' => $docCritical,
        'warning' => $docWarning
    ],
    'bookings' => [
        'critical' => $bookingCritical,
        'warning' => $bookingWarning
    ],
    'payments' => [
        'critical' => $paymentCritical,
        'warning' => $paymentWarning
    ],
    'criticalItems' => $criticalItems
]);
