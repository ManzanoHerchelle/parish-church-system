<?php
/**
 * Staff: Process Bookings
 * Queue of bookings awaiting staff confirmation
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../handlers/email_handler.php';

startSecureSession();

// Check if user is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();
$emailHandler = new EmailHandler();

// Handle booking action
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($booking_id && in_array($action, ['confirmed', 'cancelled'])) {
        $status = $action;
        
        // Get booking and client info before updating
        $infoQuery = "
            SELECT b.*, bt.name as booking_type, u.email, u.first_name
            FROM bookings b
            JOIN booking_types bt ON b.booking_type_id = bt.id
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
        ";
        $infoStmt = $conn->prepare($infoQuery);
        $infoStmt->bind_param('i', $booking_id);
        $infoStmt->execute();
        $bookingInfo = $infoStmt->get_result()->fetch_assoc();
        $infoStmt->close();
        
        // Update booking status
        $query = "UPDATE bookings SET status = ?, staff_notes = ?, updated_at = NOW(), client_notified = 1, client_notification_sent_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $status, $notes, $booking_id);
        
        if ($stmt->execute()) {
            // Send email notification to client
            if ($status === 'confirmed') {
                $emailHandler->sendBookingConfirmedNotification(
                    $bookingInfo['user_id'],
                    $bookingInfo['email'],
                    $bookingInfo['first_name'],
                    $bookingInfo['reference_number'],
                    $bookingInfo['booking_type'],
                    $bookingInfo['appointment_date'],
                    $bookingInfo['appointment_date'],
                    $notes
                );
            } else {
                $emailHandler->sendBookingCancelledNotification(
                    $bookingInfo['user_id'],
                    $bookingInfo['email'],
                    $bookingInfo['first_name'],
                    $bookingInfo['reference_number'],
                    $bookingInfo['booking_type'],
                    $notes
                );
            }
            
            $message = "Booking has been " . $status . " successfully and client has been notified.";
            $messageType = 'success';
        } else {
            $message = "Error processing booking.";
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get bookings with filtering
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "
    SELECT b.*, bt.name as booking_type, u.full_name as client_name, u.email, u.phone
    FROM bookings b
    JOIN booking_types bt ON b.booking_type_id = bt.id
    JOIN users u ON b.user_id = u.id
    WHERE (b.status = 'pending' OR b.status = 'confirmed' OR b.status = 'cancelled')
";

if (!empty($search)) {
    $search = '%' . $conn->real_escape_string($search) . '%';
    $query .= " AND (u.full_name LIKE '$search' OR bt.name LIKE '$search' OR u.email LIKE '$search')";
}

if ($filter === 'confirmed') {
    $query .= " AND b.status = 'confirmed'";
} elseif ($filter === 'cancelled') {
    $query .= " AND b.status = 'cancelled'";
} else {
    $query .= " AND b.status = 'pending'";
}

$query .= " ORDER BY b.appointment_date ASC, b.created_at DESC";

$bookings = [];
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Bookings - Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --sidebar-bg: #1e293b;
        }
        
        body {
            background: #f8fafc;
        }
        
        .sidebar {
            background: var(--sidebar-bg);
            color: #cbd5e1;
            min-height: 100vh;
            padding: 0;
            position: fixed;
            width: 260px;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .sidebar-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 16px;
        }
        
        .sidebar-header small {
            color: rgba(255,255,255,0.7);
            display: block;
            margin-top: 5px;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: var(--primary-light);
        }
        
        .sidebar-nav a.active {
            background: rgba(99, 102, 241, 0.1);
            color: white;
            border-left-color: var(--primary-light);
        }
        
        .sidebar-nav .nav-label {
            padding: 10px 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin-top: 10px;
        }
        
        .main-content {
            margin-left: 260px;
        }
        
        .topbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .page-content {
            padding: 30px;
        }
        
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
        }
        
        .badge-pending { background: #fbbf24; color: #333; }
        .badge-confirmed { background: #10b981; color: white; }
        .badge-cancelled { background: #ef4444; color: white; }
        
        .booking-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            transition: all 0.2s;
        }
        
        .booking-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        
        .booking-title {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }
        
        .booking-meta {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .booking-date {
            background: #f0f9ff;
            border-left: 3px solid #3b82f6;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h5><i class="bi bi-briefcase"></i> Staff Portal</h5>
            <small><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?></small>
        </div>
        
        <div class="sidebar-nav">
            <a href="/documentSystem/staff/dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-label">Workflow</div>
            <a href="/documentSystem/staff/process-documents.php">
                <i class="bi bi-file-earmark-check"></i>
                <span>Process Documents</span>
            </a>
            
            <a href="/documentSystem/staff/process-bookings.php" class="active">
                <i class="bi bi-calendar-check"></i>
                <span>Process Bookings</span>
            </a>
            
            <a href="/documentSystem/staff/verify-payments.php">
                <i class="bi bi-wallet-check"></i>
                <span>Verify Payments</span>
            </a>
            
            <div class="nav-label">Management</div>
            <a href="/documentSystem/staff/activity-log.php">
                <i class="bi bi-clock-history"></i>
                <span>Activity Log</span>
            </a>
            
            <div style="margin: 15px 0; padding: 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>
            
            <a href="/documentSystem/client/profile.php">
                <i class="bi bi-person-circle"></i>
                <span>My Profile</span>
            </a>
            
            <a href="/documentSystem/api/logout.php" style="color: #ef4444;">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h3 style="margin: 0; font-size: 18px; font-weight: 600;">
                    <i class="bi bi-calendar-check"></i> Process Bookings
                </h3>
                <small style="color: #64748b; display: block; margin-top: 5px;">
                    <a href="/documentSystem/staff/dashboard.php" style="color: var(--primary-color); text-decoration: none;">Dashboard</a> / Process Bookings
                </small>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="page-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <input type="text" name="search" class="form-control" placeholder="Search by client name or booking type..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="filter" class="form-select">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Bookings List -->
            <div class="row">
                <div class="col-lg-8">
                    <?php if (empty($bookings)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle"></i> No bookings found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div>
                                        <div class="booking-title">
                                            <?php echo htmlspecialchars($booking['booking_type']); ?>
                                        </div>
                                        <div class="booking-meta">
                                            Client: <strong><?php echo htmlspecialchars($booking['client_name']); ?></strong> 
                                            (<?php echo htmlspecialchars($booking['phone']); ?>)
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php 
                                        $badgeClass = 'badge-pending';
                                        if ($booking['status'] === 'confirmed') $badgeClass = 'badge-confirmed';
                                        elseif ($booking['status'] === 'cancelled') $badgeClass = 'badge-cancelled';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>" style="font-size: 12px; padding: 6px 12px;">
                                            <?php echo strtoupper($booking['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="booking-date">
                                    <i class="bi bi-calendar-event"></i>
                                    <strong><?php echo date('F d, Y', strtotime($booking['appointment_date'])); ?></strong>
                                    at
                                    <strong><?php echo date('h:i A', strtotime($booking['appointment_date'])); ?></strong>
                                </div>
                                
                                <div class="booking-meta">
                                    Requested: <?php echo date('M d, Y @ h:i A', strtotime($booking['created_at'])); ?>
                                </div>
                                
                                <?php if (!empty($booking['staff_notes'])): ?>
                                    <div style="background: #f8fafc; padding: 10px; border-radius: 4px; margin-bottom: 12px; border-left: 3px solid var(--primary-color);">
                                        <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Staff Notes:</div>
                                        <div style="font-size: 13px; color: #334155;">
                                            <?php echo htmlspecialchars($booking['staff_notes']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                                data-bs-target="#confirmModal" 
                                                onclick="setBookingId('<?php echo $booking['id']; ?>', '<?php echo htmlspecialchars($booking['booking_type']); ?>')">
                                            <i class="bi bi-check-circle"></i> Confirm
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                data-bs-target="#cancelModal"
                                                onclick="setBookingId('<?php echo $booking['id']; ?>', '<?php echo htmlspecialchars($booking['booking_type']); ?>')">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            Already <?php echo strtoupper($booking['status']); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Stats Sidebar -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-bar-chart"></i> Statistics
                        </div>
                        <div class="card-body">
                            <div style="margin-bottom: 15px;">
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 5px;">Pending</div>
                                <div style="font-size: 24px; font-weight: 700; color: #fbbf24;">
                                    <?php 
                                    $conn = getDBConnection();
                                    $result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
                                    echo $result->fetch_assoc()['count'] ?? 0;
                                    closeDBConnection($conn);
                                    ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 5px;">Confirmed Today</div>
                                <div style="font-size: 24px; font-weight: 700; color: #10b981;">
                                    <?php 
                                    $conn = getDBConnection();
                                    $result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed' AND DATE(updated_at) = CURDATE()");
                                    echo $result->fetch_assoc()['count'] ?? 0;
                                    closeDBConnection($conn);
                                    ?>
                                </div>
                            </div>
                            
                            <div>
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 5px;">Cancelled Today</div>
                                <div style="font-size: 24px; font-weight: 700; color: #ef4444;">
                                    <?php 
                                    $conn = getDBConnection();
                                    $result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'cancelled' AND DATE(updated_at) = CURDATE()");
                                    echo $result->fetch_assoc()['count'] ?? 0;
                                    closeDBConnection($conn);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle"></i> Confirm Booking
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="booking_id" id="confirmBookingId" value="">
                        <input type="hidden" name="action" value="confirmed">
                        
                        <div class="mb-3">
                            <label class="form-label">Booking Type</label>
                            <input type="text" class="form-control" id="confirmBookingType" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add confirmation details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle"></i> Cancel Booking
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="booking_id" id="cancelBookingId" value="">
                        <input type="hidden" name="action" value="cancelled">
                        
                        <div class="mb-3">
                            <label class="form-label">Booking Type</label>
                            <input type="text" class="form-control" id="cancelBookingType" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Cancellation *</label>
                            <textarea class="form-control" name="notes" rows="4" placeholder="Please provide the reason for cancellation..." required></textarea>
                            <small class="text-muted">This will be sent to the client.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Cancel Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function setBookingId(bookingId, bookingType) {
            document.getElementById('confirmBookingId').value = bookingId;
            document.getElementById('confirmBookingType').value = bookingType;
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('cancelBookingType').value = bookingType;
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
