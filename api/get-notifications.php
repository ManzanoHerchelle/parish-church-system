<?php
/**
 * API: Get User Notifications
 * Returns: JSON list of notifications
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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true' ? true : false;

// Build query
$query = "SELECT id, title, message, type, is_read, created_at, link
          FROM notifications
          WHERE user_id = ?";

if ($unreadOnly) {
    $query .= " AND is_read = 0";
}

$query .= " ORDER BY created_at DESC LIMIT " . $limit;

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close connection
closeDBConnection($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['notifications' => $notifications]);
?>
