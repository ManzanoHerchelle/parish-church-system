<?php
session_start();

use Services\BookingService;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user role is client
if ($_SESSION['role'] !== 'client') {
    header('Location: ../admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/BookingService.php';
require_once __DIR__ . '/../src/UI/Layouts/ClientLayout.php';
require_once __DIR__ . '/../src/UI/Components/ClientComponents.php';

$userId = $_SESSION['user_id'];
$conn = getDBConnection();
$bookingService = new BookingService($conn);

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bookingId = $_POST['booking_id'] ?? null;
    if ($bookingId) {
        // Verify ownership
        $booking = $bookingService->getBookingById($bookingId);
        if ($booking && $booking['user_id'] == $userId && $booking['status'] === 'pending') {
            $reason = 'Cancelled by client';
            $result = $bookingService->cancelBooking($bookingId, $reason, $userId);
            if ($result) {
                $_SESSION['success_message'] = 'Appointment cancelled successfully.';
            }
        }
    }
    header('Location: my-appointments.php');
    exit;
}

// Get filter
$statusFilter = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build filter options
$filterOptions = ['user_id' => $userId, 'limit' => $perPage, 'offset' => $offset];
if ($statusFilter !== 'all') {
    $filterOptions['status'] = $statusFilter;
}

// Get bookings
$bookings = $bookingService->getBookings($filterOptions);

// Get total count for pagination
if ($statusFilter !== 'all') {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status = ?");
    $stmt->bind_param("is", $userId, $statusFilter);
    $stmt->execute();
    $totalRecords = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $totalRecords = $stmt->get_result()->fetch_assoc()['total'];
}
$totalPages = ceil($totalRecords / $perPage);

// Build content
ob_start();
?>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?= $_SESSION['success_message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<!-- Status Filter Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all">
            All Appointments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">
            <i class="bi bi-clock"></i> Pending
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'approved' ? 'active' : '' ?>" href="?status=approved">
            <i class="bi bi-check-circle"></i> Approved
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'completed' ? 'active' : '' ?>" href="?status=completed">
            <i class="bi bi-check-all"></i> Completed
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="?status=rejected">
            <i class="bi bi-x-circle"></i> Rejected
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === 'cancelled' ? 'active' : '' ?>" href="?status=cancelled">
            <i class="bi bi-x-circle"></i> Cancelled
        </a>
    </li>
</ul>

<!-- Booking List -->
<?php if (count($bookings) > 0): ?>
    <?php foreach ($bookings as $booking): ?>
        <?php 
        $card = new ClientBookingCard($booking);
        echo $card->render();
        ?>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $page - 1 ?>">Previous</a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $page + 1 ?>">Next</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar-x display-1 text-muted"></i>
            <h5 class="mt-3">No Appointments Found</h5>
            <p class="text-muted">You haven't booked any appointments yet.</p>
            <a href="book-appointment.php" class="btn btn-success">
                <i class="bi bi-calendar-plus"></i> Book Appointment
            </a>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();

// Render layout
$layout = new ClientLayout('My Appointments', 'My Appointments', 'appointments');
$layout->setContent($content);
echo $layout->render();

closeDBConnection($conn);
