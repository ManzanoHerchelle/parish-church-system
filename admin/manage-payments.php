<?php
/**
 * Admin: Manage Payments
 * Interface for admin to verify, reject, and manage payments
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../src/Services/PaymentService.php';
require_once __DIR__ . '/../src/Services/SearchService.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';
require_once __DIR__ . '/../src/UI/Helpers/UIHelpers.php';
require_once __DIR__ . '/../src/UI/Components/PaymentComponents.php';

startSecureSession();

// Check if user is logged in and is admin/staff
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || !in_array($userRole, ['admin', 'staff'])) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

use Services\PaymentService;
use Services\SearchService;

$paymentService = new PaymentService();
$searchService = new SearchService();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Handle action requests (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $paymentId = intval($_POST['payment_id'] ?? 0);
    
    if ($paymentId > 0) {
        switch ($action) {
            case 'verify':
                $notes = trim($_POST['notes'] ?? '');
                $paymentService->verifyPayment($paymentId, $userId, $notes);
                header('Location: /documentSystem/admin/manage-payments.php?status=success&message=Payment+verified');
                exit;
                
            case 'reject':
                $notes = trim($_POST['notes'] ?? '');
                if (!$notes) {
                    header('Location: /documentSystem/admin/manage-payments.php?status=error&message=Rejection+reason+required');
                    exit;
                }
                $paymentService->rejectPayment($paymentId, $userId, $notes);
                header('Location: /documentSystem/admin/manage-payments.php?status=success&message=Payment+rejected');
                exit;
        }
    }
}

// Get filter parameters
$keyword = $_GET['keyword'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';
$amountMin = $_GET['amount_min'] ?? '';
$amountMax = $_GET['amount_max'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$pageNum = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($pageNum - 1) * $limit;

// Build search filters
$searchFilters = [
    'keyword' => $keyword,
    'status' => $statusFilter,
    'payment_method' => $paymentMethod,
    'amount_min' => $amountMin,
    'amount_max' => $amountMax,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'limit' => $limit,
    'offset' => $offset
];

// Fetch payments using search service
$payments = $searchService->searchPayments($searchFilters);
$totalCount = $searchService->countPayments($searchFilters);
$totalPages = ceil($totalCount / $limit);

// Get status counts
$statusCounts = [];
$statusQuery = "
    SELECT status, COUNT(*) as count 
    FROM payments 
    GROUP BY status
";
$result = $conn->query($statusQuery);
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Get total revenue (verified payments)
$revenueQuery = "SELECT SUM(amount) as total FROM payments WHERE status = 'verified'";
$totalRevenue = $conn->query($revenueQuery)->fetch_assoc()['total'] ?? 0;

$statusMessage = $_GET['message'] ?? '';
$statusType = $_GET['status'] ?? '';

closeDBConnection($conn);

// Build content
$content = '';

// Alert messages
if ($statusMessage) {
    $content .= alert(htmlspecialchars($statusMessage), 
                     ($statusType === 'success') ? 'success' : 'danger');
}

// Revenue card
$content .= '<div class="row mb-3">';
$content .= statCard('Total Revenue', '₱' . number_format($totalRevenue, 2), 'cash-coin', 'success');
$content .= statCard('Pending Payments', $statusCounts['pending'] ?? 0, 'clock', 'warning');
$content .= statCard('Verified Payments', $statusCounts['verified'] ?? 0, 'check-circle', 'success');
$content .= statCard('Rejected Payments', $statusCounts['rejected'] ?? 0, 'x-circle', 'danger');
$content .= '</div>';

// Search and Advanced Filters Section
$content .= '
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bi bi-search"></i> Search & Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="keyword" class="form-label">Search (Transaction #, Client Name, Email)</label>
                <input type="text" class="form-control" id="keyword" name="keyword" placeholder="Search..." value="' . htmlspecialchars($keyword) . '">
            </div>
            
            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">-- All Statuses --</option>
                    <option value="pending" ' . ($statusFilter === 'pending' ? 'selected' : '') . '>Pending</option>
                    <option value="verified" ' . ($statusFilter === 'verified' ? 'selected' : '') . '>Verified</option>
                    <option value="rejected" ' . ($statusFilter === 'rejected' ? 'selected' : '') . '>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="payment_method" class="form-label">Payment Method</label>
                <select class="form-select" id="payment_method" name="payment_method">
                    <option value="">-- All Methods --</option>
                    <option value="cash" ' . ($paymentMethod === 'cash' ? 'selected' : '') . '>Cash</option>
                    <option value="online" ' . ($paymentMethod === 'online' ? 'selected' : '') . '>Online</option>
                    <option value="bank_transfer" ' . ($paymentMethod === 'bank_transfer' ? 'selected' : '') . '>Bank Transfer</option>
                    <option value="gcash" ' . ($paymentMethod === 'gcash' ? 'selected' : '') . '>GCash</option>
                    <option value="paymaya" ' . ($paymentMethod === 'paymaya' ? 'selected' : '') . '>PayMaya</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Amount Range (₱)</label>
                <div class="input-group">
                    <input type="number" class="form-control" placeholder="Min" name="amount_min" value="' . htmlspecialchars($amountMin) . '" step="0.01">
                    <input type="number" class="form-control" placeholder="Max" name="amount_max" value="' . htmlspecialchars($amountMax) . '" step="0.01">
                </div>
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
                <a href="manage-payments.php" class="btn btn-secondary">
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
    'verified' => 'Verified',
    'verified_count' => $statusCounts['verified'] ?? 0,
    'rejected' => 'Rejected',
    'rejected_count' => $statusCounts['rejected'] ?? 0,
];

$filterComponent = new StatusFilterTabs($statusFilter, $filtersArray);
$content .= $filterComponent->render();

// Payments list
$paymentsHtml = '';
if (empty($payments)) {
    $paymentsHtml .= alert('No payments found', 'info', false);
} else {
    foreach ($payments as $payment) {
        $paymentCard = new PaymentCard($payment);
        $paymentsHtml .= $paymentCard->render();
        
        // Add modals for this payment
        $verifyModal = new PaymentVerifyModal($payment['id']);
        $paymentsHtml .= $verifyModal->render();
        
        $rejectModal = new PaymentRejectModal($payment['id']);
        $paymentsHtml .= $rejectModal->render();
        
        $detailsModal = new PaymentDetailsModal($payment);
        $paymentsHtml .= $detailsModal->render();
    }
}

$content .= card('Payments (' . count($payments) . ' of ' . $totalCount . ')', $paymentsHtml);

// Pagination - build URL with all filters
if ($totalPages > 1) {
    $baseUrl = '?keyword=' . urlencode($keyword) . '&status=' . urlencode($statusFilter) . 
               '&payment_method=' . urlencode($paymentMethod) . '&amount_min=' . urlencode($amountMin) .
               '&amount_max=' . urlencode($amountMax) . '&start_date=' . urlencode($startDate) . 
               '&end_date=' . urlencode($endDate);
    $pagination = new Pagination($pageNum, $totalPages, $baseUrl);
    $content .= $pagination->render();
}

// Create layout and render
$layout = new AdminLayout('Manage Payments', 'Manage Payments', 'cash-coin');
$layout->setContent($content);
$layout->setNotificationCount($statusCounts['pending'] ?? 0);

echo $layout->render();
?>
