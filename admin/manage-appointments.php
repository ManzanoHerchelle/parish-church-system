<?php
/**
 * Admin: Manage Appointments
 * Interface for admin to view, approve, reject, and manage bookings
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../src/Services/BookingService.php';
require_once __DIR__ . '/../src/Services/SearchService.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';
require_once __DIR__ . '/../src/UI/Helpers/UIHelpers.php';
require_once __DIR__ . '/../src/UI/Components/BookingComponents.php';

startSecureSession();

// Check if user is logged in and is admin/staff
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || !in_array($userRole, ['admin', 'staff'])) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

use Services\BookingService;
use Services\SearchService;

$bookingService = new BookingService();
$searchService = new SearchService();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Handle action requests (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = intval($_POST['booking_id'] ?? 0);
    
    if ($bookingId > 0) {
        switch ($action) {
            case 'approve':
                // Fetch booking to check payment status
                $bookingStmt = $conn->prepare("SELECT payment_status FROM bookings WHERE id = ?");
                $bookingStmt->bind_param("i", $bookingId);
                $bookingStmt->execute();
                $bookingResult = $bookingStmt->get_result()->fetch_assoc();
                $bookingStmt->close();
                
                if ($bookingResult && $bookingResult['payment_status'] === 'unpaid') {
                    header('Location: /documentSystem/admin/manage-appointments.php?alert=error&message=Cannot+approve+unpaid+appointments');
                    exit;
                }
                
                $bookingService->approveBooking($bookingId, $userId);
                header('Location: /documentSystem/admin/manage-appointments.php?alert=success&message=Appointment+approved');
                exit;
                
            case 'reject':
                $reason = trim($_POST['reason'] ?? '');
                if (!$reason) {
                    header('Location: /documentSystem/admin/manage-appointments.php?alert=error&message=Rejection+reason+required');
                    exit;
                }
                $bookingService->rejectBooking($bookingId, $userId, $reason);
                header('Location: /documentSystem/admin/manage-appointments.php?alert=success&message=Appointment+rejected');
                exit;
                
            case 'mark_completed':
                $bookingService->markAsCompleted($bookingId, $userId);
                header('Location: /documentSystem/admin/manage-appointments.php?alert=success&message=Appointment+marked+as+completed');
                exit;
        }
    }
}

// Get filter parameters
$keyword = $_GET['keyword'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$bookingType = $_GET['booking_type'] ?? '';
$paymentStatus = $_GET['payment_status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$pageNum = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($pageNum - 1) * $limit;

// Build search filters
$searchFilters = [
    'keyword' => $keyword,
    'status' => $statusFilter,
    'booking_type' => $bookingType,
    'payment_status' => $paymentStatus,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'limit' => $limit,
    'offset' => $offset
];

// Fetch bookings using search service
$bookings = $searchService->searchBookings($searchFilters);
$totalCount = $searchService->countBookings($searchFilters);
$totalPages = ceil($totalCount / $limit);

// Get booking types for filter dropdown
$bookingTypesQuery = "SELECT id, name FROM booking_types WHERE is_active = 1 ORDER BY name";
$bookingTypesResult = $conn->query($bookingTypesQuery);
$bookingTypes = $bookingTypesResult->fetch_all(MYSQLI_ASSOC);

// Get status counts
$statusCounts = [];
$statusQuery = "
    SELECT status, COUNT(*) as count 
    FROM bookings 
    GROUP BY status
";
$result = $conn->query($statusQuery);
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
}

$statusMessage = $_GET['message'] ?? '';
$statusType = $_GET['alert'] ?? '';

closeDBConnection($conn);

// Build content
$content = '';

// Alert messages
if ($statusMessage) {
    $content .= alert(htmlspecialchars($statusMessage), 
                     ($statusType === 'success') ? 'success' : 'danger');
}

// Search and Advanced Filters Section
$content .= '
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bi bi-search"></i> Search & Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="keyword" class="form-label">Search (Reference, Client Name, Email)</label>
                <input type="text" class="form-control" id="keyword" name="keyword" placeholder="Search..." value="' . htmlspecialchars($keyword) . '">
            </div>
            
            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">-- All Statuses --</option>
                    <option value="pending" ' . ($statusFilter === 'pending' ? 'selected' : '') . '>Pending</option>
                    <option value="approved" ' . ($statusFilter === 'approved' ? 'selected' : '') . '>Approved</option>
                    <option value="completed" ' . ($statusFilter === 'completed' ? 'selected' : '') . '>Completed</option>
                    <option value="rejected" ' . ($statusFilter === 'rejected' ? 'selected' : '') . '>Rejected</option>
                    <option value="cancelled" ' . ($statusFilter === 'cancelled' ? 'selected' : '') . '>Cancelled</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="booking_type" class="form-label">Booking Type</label>
                <select class="form-select" id="booking_type" name="booking_type">
                    <option value="">-- All Types --</option>';
                    
foreach ($bookingTypes as $bType) {
    $selected = $bookingType == $bType['id'] ? 'selected' : '';
    $content .= '<option value="' . $bType['id'] . '" ' . $selected . '>' . htmlspecialchars($bType['name']) . '</option>';
}

$content .= '
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="payment_status" class="form-label">Payment Status</label>
                <select class="form-select" id="payment_status" name="payment_status">
                    <option value="">-- All Payment Status --</option>
                    <option value="unpaid" ' . ($paymentStatus === 'unpaid' ? 'selected' : '') . '>Unpaid</option>
                    <option value="pending" ' . ($paymentStatus === 'pending' ? 'selected' : '') . '>Pending</option>
                    <option value="paid" ' . ($paymentStatus === 'paid' ? 'selected' : '') . '>Paid</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="' . htmlspecialchars($startDate) . '">
            </div>
            
            <div class="col-md-6">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="' . htmlspecialchars($endDate) . '">
            </div>
            
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Search
                </button>
                <a href="manage-appointments.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>';

// Status filter tabs
$allCount = array_sum($statusCounts);
$filtersArray = [
    'all' => 'All',
    'all_count' => $allCount,
    'pending' => 'Pending',
    'pending_count' => $statusCounts['pending'] ?? 0,
    'approved' => 'Approved',
    'approved_count' => $statusCounts['approved'] ?? 0,
    'completed' => 'Completed',
    'completed_count' => $statusCounts['completed'] ?? 0,
    'rejected' => 'Rejected',
    'rejected_count' => $statusCounts['rejected'] ?? 0,
    'cancelled' => 'Cancelled',
    'cancelled_count' => $statusCounts['cancelled'] ?? 0,
];

$filterComponent = new StatusFilterTabs($statusFilter, $filtersArray);
$content .= $filterComponent->render();

// Bookings list
$bookingsHtml = '';
if (empty($bookings)) {
    $bookingsHtml .= alert('No appointments found', 'info', false);
} else {
    foreach ($bookings as $booking) {
        $bookingCard = new BookingCard($booking);
        $bookingsHtml .= $bookingCard->render();
        
        // Add modals for this booking
        $rejectModal = new BookingRejectModal($booking['id']);
        $bookingsHtml .= $rejectModal->render();
        
        $detailsModal = new BookingDetailsModal($booking);
        $bookingsHtml .= $detailsModal->render();
    }
}

$content .= card('Appointments (' . count($bookings) . ' of ' . $totalCount . ')', $bookingsHtml);

// Pagination - build URL with all filters
if ($totalPages > 1) {
    $baseUrl = '?keyword=' . urlencode($keyword) . '&status=' . urlencode($statusFilter) . 
               '&booking_type=' . urlencode($bookingType) . '&payment_status=' . urlencode($paymentStatus) .
               '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
    $pagination = new Pagination($pageNum, $totalPages, $baseUrl);
    $content .= $pagination->render();
}

// Create layout and render
$layout = new AdminLayout('Manage Appointments', 'Manage Appointments', 'calendar-check');
$layout->setContent($content);
$layout->setNotificationCount($statusCounts['pending'] ?? 0);

echo $layout->render();
?>
