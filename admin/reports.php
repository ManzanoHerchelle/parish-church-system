<?php
/**
 * Admin Reports & Analytics Dashboard
 */

session_start();

// Check if user is logged in and is admin
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || $userRole !== 'admin') {
    header('Location: ../client/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/ReportService.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';

use Services\ReportService;

$conn = getDBConnection();
$reportService = new ReportService();

// Get date range from request
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Validate dates
$startDate = date('Y-m-d', strtotime($startDate));
$endDate = date('Y-m-d', strtotime($endDate));

// Handle export to PDF
if ($_GET['action'] ?? null === 'export_pdf') {
    $pdf = $reportService->generatePDFReport($startDate, $endDate, 'Parish Church System - Analytics Report');
    $filename = 'Report_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->output('D', $filename);
    exit;
}

// Get all data
$revenueStats = $reportService->getRevenueStats($startDate, $endDate);
$docStats = $reportService->getDocumentStats($startDate, $endDate);
$bookingStats = $reportService->getBookingStats($startDate, $endDate);
$userStats = $reportService->getUserStats($startDate, $endDate);
$paymentMethods = $reportService->getRevenueByMethod($startDate, $endDate);
$dailyRevenue = $reportService->getDailyRevenueData($startDate, $endDate);
$popularDocs = $reportService->getPopularDocuments($startDate, $endDate, 8);
$popularBookings = $reportService->getPopularBookings($startDate, $endDate, 8);
$topClients = $reportService->getTopClients($startDate, $endDate, 10);
$paymentStatus = $reportService->getPaymentStatusBreakdown($startDate, $endDate);
$monthlyData = $reportService->getMonthlyComparison(12);

// Build content
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="bi bi-bar-chart"></i> Reports & Analytics</h2>
            <p class="text-muted">Comprehensive system statistics and performance metrics</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="?action=export_pdf&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-outline-danger">
                <i class="bi bi-file-pdf"></i> Export PDF
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="startDate" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDate" name="start_date" value="<?= $startDate ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="endDate" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDate" name="end_date" value="<?= $endDate ?>" required>
                </div>
                <div class="col-md-4">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-left-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Total Revenue</h6>
                            <h3 class="mb-0">₱<?= number_format($revenueStats['total_revenue'] ?? 0, 2) ?></h3>
                        </div>
                        <i class="bi bi-currency-peso text-primary display-6 opacity-50"></i>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-arrow-up"></i> <?= $revenueStats['total_transactions'] ?? 0 ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card border-left-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Document Requests</h6>
                            <h3 class="mb-0"><?= $docStats['total_requests'] ?? 0 ?></h3>
                        </div>
                        <i class="bi bi-file-earmark-text text-success display-6 opacity-50"></i>
                    </div>
                    <small class="text-muted">
                        <span class="badge bg-success"><?= $docStats['completion_rate'] ?? 0 ?>%</span> Completion Rate
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card border-left-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Total Bookings</h6>
                            <h3 class="mb-0"><?= $bookingStats['total_bookings'] ?? 0 ?></h3>
                        </div>
                        <i class="bi bi-calendar-check text-info display-6 opacity-50"></i>
                    </div>
                    <small class="text-muted">
                        <span class="badge bg-info"><?= $bookingStats['completion_rate'] ?? 0 ?>%</span> Completion Rate
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card border-left-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Active Users</h6>
                            <h3 class="mb-0"><?= $userStats['active_users'] ?? 0 ?></h3>
                        </div>
                        <i class="bi bi-people text-warning display-6 opacity-50"></i>
                    </div>
                    <small class="text-muted">
                        Total: <?= $userStats['total_users'] ?? 0 ?> users
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Revenue Chart -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Daily Revenue Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Methods Pie Chart -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Revenue by Method</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentMethodChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Popular Items Row -->
    <div class="row mb-4">
        <!-- Popular Documents -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-star"></i> Top Document Types</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th class="text-end">Requests</th>
                                    <th class="text-end">Completed</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($popularDocs) > 0): ?>
                                    <?php foreach ($popularDocs as $doc): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($doc['document_type']) ?></td>
                                        <td class="text-end"><span class="badge bg-light text-dark"><?= $doc['total_requests'] ?></span></td>
                                        <td class="text-end"><span class="badge bg-success"><?= $doc['completed'] ?></span></td>
                                        <td class="text-end">₱<?= number_format($doc['revenue'] ?? 0, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Bookings -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-star"></i> Top Booking Types</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Booking Type</th>
                                    <th class="text-end">Bookings</th>
                                    <th class="text-end">Completed</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($popularBookings) > 0): ?>
                                    <?php foreach ($popularBookings as $booking): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($booking['booking_type']) ?></td>
                                        <td class="text-end"><span class="badge bg-light text-dark"><?= $booking['total_bookings'] ?></span></td>
                                        <td class="text-end"><span class="badge bg-success"><?= $booking['completed'] ?></span></td>
                                        <td class="text-end">₱<?= number_format($booking['revenue'] ?? 0, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-card-checklist"></i> Document Status Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6 class="text-muted">Pending</h6>
                            <h3 class="text-warning"><?= $docStats['pending'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Processing</h6>
                            <h3 class="text-info"><?= $docStats['processing'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Ready</h6>
                            <h3 class="text-success"><?= $docStats['ready'] ?? 0 ?></h3>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6 class="text-muted">Completed</h6>
                            <h3 class="text-primary"><?= $docStats['completed'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Rejected</h6>
                            <h3 class="text-danger"><?= $docStats['rejected'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Completion %</h6>
                            <h3 class="text-success"><?= number_format($docStats['completion_rate'] ?? 0, 1) ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-card-checklist"></i> Booking Status Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6 class="text-muted">Pending</h6>
                            <h3 class="text-warning"><?= $bookingStats['pending'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Approved</h6>
                            <h3 class="text-success"><?= $bookingStats['approved'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Completed</h6>
                            <h3 class="text-primary"><?= $bookingStats['completed'] ?? 0 ?></h3>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6 class="text-muted">Rejected</h6>
                            <h3 class="text-danger"><?= $bookingStats['rejected'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Cancelled</h6>
                            <h3 class="text-secondary"><?= $bookingStats['cancelled'] ?? 0 ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Completion %</h6>
                            <h3 class="text-success"><?= number_format($bookingStats['completion_rate'] ?? 0, 1) ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Clients -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Clients by Spending</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Email</th>
                                    <th class="text-end">Total Spent</th>
                                    <th class="text-end">Transactions</th>
                                    <th class="text-end">Documents</th>
                                    <th class="text-end">Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($topClients) > 0): ?>
                                    <?php foreach ($topClients as $index => $client): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index < 3): ?>
                                                <i class="bi bi-star-fill text-warning"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($client['client_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($client['email']) ?></td>
                                        <td class="text-end fw-bold">₱<?= number_format($client['total_spent'] ?? 0, 2) ?></td>
                                        <td class="text-end"><?= $client['transaction_count'] ?></td>
                                        <td class="text-end"><?= $client['documents_requested'] ?></td>
                                        <td class="text-end"><?= $client['bookings_made'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">No client data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Status -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-credit-card"></i> Payment Status Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentStatus as $status): ?>
                                <tr>
                                    <td>
                                        <?php if ($status['status'] === 'verified'): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php elseif ($status['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= $status['count'] ?></td>
                                    <td class="text-end">₱<?= number_format($status['total_amount'] ?? 0, 2) ?></td>
                                    <td class="text-end"><?= number_format($status['percentage'] ?? 0, 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Total Users:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?= $userStats['total_users'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Active Users:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?= $userStats['active_users'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Email Verified:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?= $userStats['verified_users'] ?? 0 ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Average Payment:</strong>
                        </div>
                        <div class="col-6 text-end">
                            ₱<?= number_format($revenueStats['average_payment'] ?? 0, 2) ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Pending Payments:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?= $revenueStats['pending_payments'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Rejected Payments:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?= $revenueStats['rejected_payments'] ?? 0 ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script for visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
// Daily Revenue Chart
const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
if (revenueCtx) {
    const revenueData = <?= json_encode($dailyRevenue) ?>;
    const labels = revenueData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    const revenues = revenueData.map(d => parseFloat(d.revenue) || 0);
    
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Daily Revenue (₱)',
                data: revenues,
                borderColor: 'rgb(41, 128, 185)',
                backgroundColor: 'rgba(41, 128, 185, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Payment Method Pie Chart
const paymentCtx = document.getElementById('paymentMethodChart')?.getContext('2d');
if (paymentCtx) {
    const paymentData = <?= json_encode($paymentMethods) ?>;
    const methods = paymentData.map(p => {
        const method = p.payment_method;
        return method.charAt(0).toUpperCase() + method.slice(1).replace('_', ' ');
    });
    const amounts = paymentData.map(p => parseFloat(p.total_amount) || 0);
    
    const colors = ['#2980B9', '#27AE60', '#E74C3C', '#F39C12', '#9B59B6'];
    
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: methods,
            datasets: [{
                data: amounts,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 10
                    }
                }
            }
        }
    });
}
</script>

<style>
.stat-card {
    border-left: 4px solid;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.border-left-primary {
    border-left-color: #2980B9 !important;
}

.border-left-success {
    border-left-color: #27AE60 !important;
}

.border-left-info {
    border-left-color: #3498DB !important;
}

.border-left-warning {
    border-left-color: #F39C12 !important;
}

.card-header.bg-light {
    border-bottom: 1px solid #e3e6f0;
    background-color: #f8f9fa !important;
}
</style>

<?php
$content = ob_get_clean();

// Render with admin layout
$layout = new AdminLayout('Reports & Analytics', 'System Analytics Dashboard', 'reports');
$layout->setContent($content);
echo $layout->render();

closeDBConnection($conn);
?>
