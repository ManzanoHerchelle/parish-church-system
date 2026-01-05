<?php
/**
 * API: Get Booked Slots
 * Returns all booked dates and times to disable them in reschedule modal
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

startSecureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$appointmentId = $_GET['appointment_id'] ?? null;
$conn = getDBConnection();

// Get the booking type of the current appointment to match with available slots
$bookingTypeQuery = "SELECT booking_type_id FROM bookings WHERE id = ? AND user_id = ?";
$bookingTypeStmt = $conn->prepare($bookingTypeQuery);
$bookingTypeStmt->bind_param("ii", $appointmentId, $_SESSION['user_id']);
$bookingTypeStmt->execute();
$bookingTypeResult = $bookingTypeStmt->get_result()->fetch_assoc();
$bookingTypeStmt->close();

if (!$bookingTypeResult) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    closeDBConnection($conn);
    exit;
}

$bookingTypeId = $bookingTypeResult['booking_type_id'];

// Get all approved/pending bookings for this booking type (excluding current appointment)
$bookedQuery = "
    SELECT booking_date, booking_time 
    FROM bookings 
    WHERE booking_type_id = ? 
    AND status IN ('approved', 'pending') 
    AND id != ?
    AND booking_date >= CURDATE()
    ORDER BY booking_date, booking_time
";

$bookedStmt = $conn->prepare($bookedQuery);
$bookedStmt->bind_param("ii", $bookingTypeId, $appointmentId);
$bookedStmt->execute();
$bookedResult = $bookedStmt->get_result();

// Organize booked slots by date
$bookedSlots = [];
while ($row = $bookedResult->fetch_assoc()) {
    $date = $row['booking_date'];
    $time = $row['booking_time'];
    
    if (!isset($bookedSlots[$date])) {
        $bookedSlots[$date] = [];
    }
    
    // Format time as HH:MM for comparison
    $formattedTime = date('H:i', strtotime($time));
    $bookedSlots[$date][] = $formattedTime;
}

$bookedStmt->close();
closeDBConnection($conn);

echo json_encode([
    'success' => true,
    'booked_slots' => $bookedSlots
]);
?>
