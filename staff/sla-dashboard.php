<?php
/**
 * Staff: SLA Dashboard & Alerts
 * Monitor SLA compliance, overdue items, and performance metrics
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

// Check if user is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'staff'])) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();
$isAdmin = $_SESSION['role'] === 'admin';

// Get SLA configuration (48 hours for documents, 24 hours for bookings, 24 hours for payments)
$docSLAHours = 48;
$bookingSLAHours = 24;
$paymentSLAHours = 24;

// Get overdue documents
$docQuery = "
    SELECT 
        dr.*,
        dt.name as document_type,
        u.first_name, u.last_name, u.email,
        TIMESTAMPDIFF(HOUR, dr.created_at, NOW()) as hours_pending,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, dr.created_at, NOW()) > 48 THEN 'critical'
            WHEN TIMESTAMPDIFF(HOUR, dr.created_at, NOW()) > 36 THEN 'warning'
            ELSE 'ok'
        END as sla_status
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    JOIN users u ON dr.user_id = u.id
    WHERE dr.status = 'pending'
    ORDER BY dr.created_at ASC
";

$overdueDocuments = [];
$result = $conn->query($docQuery);
while ($row = $result->fetch_assoc()) {
    $overdueDocuments[] = $row;
}

// Get overdue bookings
$bookingQuery = "
    SELECT 
        b.*,
        bt.name as booking_type,
        u.first_name, u.last_name, u.email,
        TIMESTAMPDIFF(HOUR, b.created_at, NOW()) as hours_pending,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, b.created_at, NOW()) > 24 THEN 'critical'
            WHEN TIMESTAMPDIFF(HOUR, b.created_at, NOW()) > 18 THEN 'warning'
            ELSE 'ok'
        END as sla_status
    FROM bookings b
    JOIN booking_types bt ON b.booking_type_id = bt.id
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'pending'
    ORDER BY b.created_at ASC
";

$overdueBookings = [];
$result = $conn->query($bookingQuery);
while ($row = $result->fetch_assoc()) {
    $overdueBookings[] = $row;
}

// Get overdue payments
$paymentQuery = "
    SELECT 
        p.*,
        u.first_name, u.last_name, u.email,
        TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_pending,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, p.created_at, NOW()) > 24 THEN 'critical'
            WHEN TIMESTAMPDIFF(HOUR, p.created_at, NOW()) > 18 THEN 'warning'
            ELSE 'ok'
        END as sla_status
    FROM payments p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at ASC
";

$overduePayments = [];
$result = $conn->query($paymentQuery);
while ($row = $result->fetch_assoc()) {
    $overduePayments[] = $row;
}

// Count critical items
$criticalDocs = array_filter($overdueDocuments, fn($item) => $item['sla_status'] === 'critical');
$criticalBookings = array_filter($overdueBookings, fn($item) => $item['sla_status'] === 'critical');
$criticalPayments = array_filter($overduePayments, fn($item) => $item['sla_status'] === 'critical');

$totalCritical = count($criticalDocs) + count($criticalBookings) + count($criticalPayments);

// Get performance statistics
$performanceQuery = "
    SELECT 
        CASE 
            WHEN role = 'admin' THEN 'Admin'
            WHEN role = 'staff' THEN 'Staff'
            ELSE 'Other'
        END as user_role,
        COUNT(DISTINCT CASE WHEN action = 'document_approved' THEN 1 END) as docs_approved,
        COUNT(DISTINCT CASE WHEN action = 'document_rejected' THEN 1 END) as docs_rejected,
        COUNT(DISTINCT CASE WHEN action = 'booking_confirmed' THEN 1 END) as bookings_confirmed,
        COUNT(DISTINCT CASE WHEN action = 'booking_cancelled' THEN 1 END) as bookings_cancelled,
        COUNT(DISTINCT CASE WHEN action = 'payment_verified' THEN 1 END) as payments_verified,
        COUNT(DISTINCT CASE WHEN action = 'payment_rejected' THEN 1 END) as payments_rejected,
        COUNT(*) as total_actions,
        DATE(created_at) as action_date
    FROM activity_logs
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY user_role, action_date
    ORDER BY action_date DESC
";

$performanceStats = [];
$result = $conn->query($performanceQuery);
while ($row = $result->fetch_assoc()) {
    $performanceStats[] = $row;
}

// Get today's activity
$todayQuery = "
    SELECT 
        CONCAT(u.first_name, ' ', u.last_name) as staff_name,
        COUNT(DISTINCT CASE WHEN action = 'document_approved' THEN 1 END) as docs_approved,
        COUNT(DISTINCT CASE WHEN action = 'document_rejected' THEN 1 END) as docs_rejected,
        COUNT(DISTINCT CASE WHEN action = 'booking_confirmed' THEN 1 END) as bookings_confirmed,
        COUNT(DISTINCT CASE WHEN action = 'payment_verified' THEN 1 END) as payments_verified
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    WHERE DATE(al.created_at) = CURDATE() AND u.role = 'staff'
    GROUP BY al.user_id
    ORDER BY (
        COUNT(DISTINCT CASE WHEN action = 'document_approved' THEN 1 END) +
        COUNT(DISTINCT CASE WHEN action = 'booking_confirmed' THEN 1 END) +
        COUNT(DISTINCT CASE WHEN action = 'payment_verified' THEN 1 END)
    ) DESC
";

$todayActivity = [];
$result = $conn->query($todayQuery);
while ($row = $result->fetch_assoc()) {
    $todayActivity[] = $row;
}

closeDBConnection($conn);

// Helper function to format hours
function formatHours($hours) {
    if ($hours < 24) {
        return $hours . 'h';
    } else {
        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;
        return $days . 'd ' . $remainingHours . 'h';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLA Dashboard - <?php echo $isAdmin ? 'Admin' : 'Staff'; ?> Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <!-- Toast notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
        }
        
        body {
            background: #f8fafc;
        }
        
        .sla-alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .sla-critical {
            background: #fee2e2;
            border-color: #ef4444;
            color: #7f1d1d;
        }
        
        .sla-warning {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #78350f;
        }
        
        .sla-ok {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 13px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .card-header-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .status-critical { background: #ef4444; color: white; }
        .status-warning { background: #f59e0b; color: white; }
        .status-ok { background: #10b981; color: white; }
        
        .item-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 12px;
            background: white;
        }
        
        .item-card.critical {
            border-left: 3px solid #ef4444;
            background: #fef2f2;
        }
        
        .item-card.warning {
            border-left: 3px solid #f59e0b;
            background: #fffbeb;
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-size: 13px;
            color: #6b7280;
            z-index: 1000;
        }
        
        .refresh-indicator.active {
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="bi bi-arrow-clockwise"></i> <span id="refreshText">Auto-refresh in <span id="countdown">120</span>s</span>
    </div>
    <div class="container-fluid" style="padding: 30px 20px;">
        <div style="padding: 30px 0; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #1f2937;">
                <i class="bi bi-clock-history"></i> SLA Dashboard & Alerts
            </h1>
            <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">
                Monitor request processing times and SLA compliance
            </p>
        </div>

        <!-- Critical Alert -->
        <?php if ($totalCritical > 0): ?>
            <div class="sla-alert sla-critical">
                <h4 style="margin: 0 0 10px 0;">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong><?php echo $totalCritical; ?> Item(s) in Critical Status</strong>
                </h4>
                <p style="margin: 0;">
                    These items have exceeded their SLA deadline. Please address them immediately.
                </p>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-file-earmark" style="font-size: 24px; color: #3b82f6; display: block; margin-bottom: 10px;"></i>
                    <div class="stat-number"><?php echo count($overdueDocuments); ?></div>
                    <div class="stat-label">Pending Documents</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-calendar" style="font-size: 24px; color: #f59e0b; display: block; margin-bottom: 10px;"></i>
                    <div class="stat-number"><?php echo count($overdueBookings); ?></div>
                    <div class="stat-label">Pending Bookings</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-wallet2" style="font-size: 24px; color: #10b981; display: block; margin-bottom: 10px;"></i>
                    <div class="stat-number"><?php echo count($overduePayments); ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-exclamation-circle" style="font-size: 24px; color: #ef4444; display: block; margin-bottom: 10px;"></i>
                    <div class="stat-number" style="color: #ef4444;"><?php echo $totalCritical; ?></div>
                    <div class="stat-label">Critical Items</div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Overdue Documents -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <i class="bi bi-file-earmark"></i> Overdue Documents
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($overdueDocuments)): ?>
                            <p class="text-muted text-center py-4">No pending documents</p>
                        <?php else: ?>
                            <?php foreach ($overdueDocuments as $doc): ?>
                                <div class="item-card <?php echo $doc['sla_status']; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($doc['document_type']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                                            </small>
                                        </div>
                                        <span class="badge status-<?php echo $doc['sla_status']; ?>">
                                            <?php echo formatHours($doc['hours_pending']); ?>
                                        </span>
                                    </div>
                                    <small style="display: block; margin-top: 8px; color: #6b7280;">
                                        Ref: <?php echo htmlspecialchars($doc['reference_number']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Overdue Bookings -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <i class="bi bi-calendar"></i> Overdue Bookings
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($overdueBookings)): ?>
                            <p class="text-muted text-center py-4">No pending bookings</p>
                        <?php else: ?>
                            <?php foreach ($overdueBookings as $booking): ?>
                                <div class="item-card <?php echo $booking['sla_status']; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($booking['booking_type']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                            </small>
                                        </div>
                                        <span class="badge status-<?php echo $booking['sla_status']; ?>">
                                            <?php echo formatHours($booking['hours_pending']); ?>
                                        </span>
                                    </div>
                                    <small style="display: block; margin-top: 8px; color: #6b7280;">
                                        Ref: <?php echo htmlspecialchars($booking['reference_number']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Payments -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <i class="bi bi-wallet2"></i> Overdue Payments
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($overduePayments)): ?>
                            <p class="text-muted text-center py-4">No pending payments</p>
                        <?php else: ?>
                            <?php foreach ($overduePayments as $payment): ?>
                                <div class="item-card <?php echo $payment['sla_status']; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <strong>₱<?php echo number_format($payment['amount'], 2); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                            </small>
                                        </div>
                                        <span class="badge status-<?php echo $payment['sla_status']; ?>">
                                            <?php echo formatHours($payment['hours_pending']); ?>
                                        </span>
                                    </div>
                                    <small style="display: block; margin-top: 8px; color: #6b7280;">
                                        ID: <?php echo htmlspecialchars($payment['transaction_number']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Today's Activity -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <i class="bi bi-calendar-check"></i> Today's Processing
                    </div>
                    <div class="card-body">
                        <?php if (empty($todayActivity)): ?>
                            <p class="text-muted text-center py-4">No activity today</p>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($todayActivity as $activity): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($activity['staff_name']); ?></strong>
                                            </td>
                                            <td style="text-align: right;">
                                                <small>
                                                    <?php if ($activity['docs_approved'] > 0): ?>
                                                        <span class="badge bg-info"><?php echo $activity['docs_approved']; ?> docs</span>
                                                    <?php endif; ?>
                                                    <?php if ($activity['bookings_confirmed'] > 0): ?>
                                                        <span class="badge bg-warning"><?php echo $activity['bookings_confirmed']; ?> bookings</span>
                                                    <?php endif; ?>
                                                    <?php if ($activity['payments_verified'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo $activity['payments_verified']; ?> payments</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SLA Information -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <i class="bi bi-info-circle"></i> SLA Standards
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
        // Initialize notification library
        const notyf = new Notyf({
            duration: 5000,
            position: { x: 'right', y: 'top' },
            dismissible: true
        });
        
        // Track previous counts
        let previousCritical = <?php echo $totalCritical; ?>;
        let previousWarning = <?php echo count($overdueDocuments) + count($overdueBookings) + count($overduePayments) - $totalCritical; ?>;
        
        // Auto-refresh configuration
        const REFRESH_INTERVAL = 120; // 2 minutes in seconds
        let countdownTimer = REFRESH_INTERVAL;
        let countdownInterval;
        let refreshTimeout;
        
        // Start countdown
        function startCountdown() {
            countdownTimer = REFRESH_INTERVAL;
            updateCountdownDisplay();
            
            countdownInterval = setInterval(() => {
                countdownTimer--;
                updateCountdownDisplay();
                
                if (countdownTimer <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
        }
        
        function updateCountdownDisplay() {
            document.getElementById('countdown').textContent = countdownTimer;
        }
        
        // Check SLA status via API
        async function checkSLAStatus() {
            try {
                const response = await fetch('/documentSystem/api/sla-status.php');
                const data = await response.json();
                
                if (data.success) {
                    // Check if critical count increased
                    if (data.summary.critical > previousCritical) {
                        const newCritical = data.summary.critical - previousCritical;
                        notyf.error({
                            message: `🔴 ${newCritical} new critical item(s)! Total: ${data.summary.critical}`,
                            duration: 10000
                        });
                        
                        // Flash the critical stat card
                        document.querySelectorAll('.stat-number').forEach(el => {
                            if (el.textContent == data.summary.critical) {
                                el.classList.add('pulse-animation');
                                setTimeout(() => el.classList.remove('pulse-animation'), 3000);
                            }
                        });
                        
                        // Play sound alert if available
                        playAlertSound();
                    }
                    
                    // Check if warning count increased
                    if (data.summary.warning > previousWarning && data.summary.critical === previousCritical) {
                        const newWarning = data.summary.warning - previousWarning;
                        notyf.open({
                            type: 'warning',
                            message: `⚠️ ${newWarning} new warning item(s)! Total: ${data.summary.warning}`,
                            duration: 7000,
                            background: '#f59e0b'
                        });
                    }
                    
                    // Show specific critical items if any
                    if (data.criticalItems && data.criticalItems.length > 0 && data.summary.critical > previousCritical) {
                        data.criticalItems.forEach((item, index) => {
                            setTimeout(() => {
                                notyf.error({
                                    message: `${item.type.toUpperCase()}: ${item.item_name} - ${item.client_name} (${item.hours_pending}h)`,
                                    duration: 8000
                                });
                            }, index * 500);
                        });
                    }
                    
                    // Update previous counts
                    previousCritical = data.summary.critical;
                    previousWarning = data.summary.warning;
                }
            } catch (error) {
                console.error('Failed to check SLA status:', error);
            }
        }
        
        // Play alert sound
        function playAlertSound() {
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIEm3A7+SfUBAKU6fh8LNiGgU4jtbywngqBSl3xu/hjj8JE17A6+yjUxEKRZ/g8r5yIQQtgc7y2Ik3CBdtwO/knlAPClSn4e+/WRoFOIvW8cN2KgYqd8Tv4Y0+CRNfv+vrpVMRCkSf4PK+ciEFLYHO8tmJNggXbcDv5J9QEApUp+HwvVsaBTiK1vHDdioGKnfD7+CKPAkTX7/r66VTEQpCnt/yvm4hBS6Bzu/aizYIFm3A7+WhUBAKVKfh8L1bGgU3i9bxw3YqBCp3w+3cijsJE1+/6+umVRIKQp7f8r5uIQUvgc7v2os2CBZtyO/kn1AQClSn4fC+XBkHN4rW8cN2KgQsdsLt3Io5CBNfvunrpVUTCkGe3/K9biEFL4HO79qLNggWbsDv5KBQEApUp+Hwr10ZBzeK1vHDdioFLXbC7dyKOQgUX77q66ZVEwo/nt/yvW4hBS+Bzu7aizYIFm3B7+ShUBAKVKbh8bpfGgY3idbxw3YqBS12wu3cjDkJFF++6OvnVhMKPp7f8r1uIQUvgM7u2Yo2CBdtwO/kolgQClOn4fG6YRsGN4nW8cN3KgUudsLt24w5CRVfvOnrp1YTCj6f3vK9biEFL4DO7tqLNwcWbcDv5KJaEApTqOHxunEcBjaJ1vHDeCkEL3bB7duKOAkVX7/o66ZWEwg9nt7yvW4hBS+Azm7biz0HFm3A7+ShWhAKU6jh8bpxHAU2iNbxw3kqBC92we3bijgJFV++6OumVxMIPJ7e8r1uIQUvgM5u24s9BxZtwO/kpFsRCk+o4fC7cxwGNYjW8cN6KgQwdsHs3Ik4CRZfvenqplYUCDye3vK9biEFL4DObtuLPQcWbcDv5KRbEQpPp+Dwu3McBzWH1u/DeysFMHa/7duJNwoXX77p6qZWFAg8nt3yvW4fBS+Azm7biz0GFm3A7+SkXBEKTqfg8LxxHAc1h9bvw3wrBTB2v+zbiTYKF16+6OqmVhQIPJ7d8r1uHwQwgM5u24o+BhVtwO/kpF4RCk2o4O+8dBwHNIfW78N8KwYwd7/s24k1ChoX77nqplYUCDye3fK8bh8EMIDN');
                audio.play().catch(e => console.log('Audio play failed:', e));
            } catch (e) {
                console.log('Audio not supported');
            }
        }
        
        // Schedule automatic page refresh
        function scheduleRefresh() {
            // Clear existing timeout
            if (refreshTimeout) {
                clearTimeout(refreshTimeout);
            }
            
            // Clear existing countdown
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            
            // Start countdown
            startCountdown();
            
            // Schedule refresh
            refreshTimeout = setTimeout(() => {
                const indicator = document.getElementById('refreshIndicator');
                const refreshText = document.getElementById('refreshText');
                
                // Show refreshing state
                indicator.classList.add('active');
                refreshText.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refreshing...';
                
                // Reload page
                window.location.reload();
            }, REFRESH_INTERVAL * 1000);
        }
        
        // Check SLA status every 30 seconds (more frequent than refresh)
        function startSLAMonitoring() {
            // Initial check after 10 seconds
            setTimeout(checkSLAStatus, 10000);
            
            // Then check every 30 seconds
            setInterval(checkSLAStatus, 30000);
        }
        
        // Manual refresh button
        function manualRefresh() {
            window.location.reload();
        }
        
        // Add manual refresh button to page
        document.querySelector('h1').insertAdjacentHTML('afterend', `
            <button onclick="manualRefresh()" class="btn btn-sm btn-outline-primary" style="position: absolute; top: 35px; right: 180px;">
                <i class="bi bi-arrow-clockwise"></i> Refresh Now
            </button>
        `);
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            scheduleRefresh();
            startSLAMonitoring();
            
            // Show welcome notification
            if (<?php echo $totalCritical; ?> > 0) {
                notyf.error({
                    message: `⚠️ You have ${<?php echo $totalCritical; ?>} critical items requiring immediate attention!`,
                    duration: 8000
                });
            } else {
                notyf.success('✅ All items within SLA targets!');
            }
            
            // Add click handlers to item cards for quick navigation
            document.querySelectorAll('.item-card').forEach(card => {
                card.style.cursor = 'pointer';
                card.addEventListener('click', function() {
                    const refNumber = this.querySelector('small').textContent;
                    console.log('Clicked:', refNumber);
                    // Could redirect to detail page here
                });
            });
        });
        
        // Pause auto-refresh when user is inactive
        let userActivity = Date.now();
        let isUserActive = true;
        
        document.addEventListener('mousemove', () => { userActivity = Date.now(); });
        document.addEventListener('keypress', () => { userActivity = Date.now(); });
        document.addEventListener('click', () => { userActivity = Date.now(); });
        
        // Check user activity every minute
        setInterval(() => {
            const inactiveTime = Date.now() - userActivity;
            const wasActive = isUserActive;
            isUserActive = inactiveTime < 5 * 60 * 1000; // 5 minutes
            
            if (!isUserActive && wasActive) {
                console.log('User inactive - pausing auto-refresh');
                if (countdownInterval) clearInterval(countdownInterval);
                document.getElementById('refreshText').textContent = 'Auto-refresh paused (inactive)';
            } else if (isUserActive && !wasActive) {
                console.log('User active - resuming auto-refresh');
                startCountdown();
            }
        }, 60000);
    </script>
                                <h6><i class="bi bi-file-earmark"></i> Documents</h6>
                                <p style="margin: 0; font-size: 13px;">
                                    <strong>SLA Target:</strong> 48 hours<br>
                                    <strong>Warning:</strong> After 36 hours<br>
                                    <strong>Critical:</strong> After 48 hours
                                </p>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="bi bi-calendar"></i> Bookings</h6>
                                <p style="margin: 0; font-size: 13px;">
                                    <strong>SLA Target:</strong> 24 hours<br>
                                    <strong>Warning:</strong> After 18 hours<br>
                                    <strong>Critical:</strong> After 24 hours
                                </p>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="bi bi-wallet2"></i> Payments</h6>
                                <p style="margin: 0; font-size: 13px;">
                                    <strong>SLA Target:</strong> 24 hours<br>
                                    <strong>Warning:</strong> After 18 hours<br>
                                    <strong>Critical:</strong> After 24 hours
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
