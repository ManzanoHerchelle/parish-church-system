<?php
/**
 * Staff: Verify Payments
 * Queue of payments awaiting staff verification
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

// Handle payment action
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = $_POST['payment_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($payment_id && in_array($action, ['verified', 'rejected'])) {
        $status = $action;
        
        // Get payment and client info before updating
        $infoQuery = "
            SELECT p.*, u.email, u.first_name
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ";
        $infoStmt = $conn->prepare($infoQuery);
        $infoStmt->bind_param('i', $payment_id);
        $infoStmt->execute();
        $paymentInfo = $infoStmt->get_result()->fetch_assoc();
        $infoStmt->close();
        
        // Update payment status
        $query = "UPDATE payments SET status = ?, verification_notes = ?, verified_at = NOW(), client_notified = 1, client_notification_sent_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $status, $notes, $payment_id);
        
        if ($stmt->execute()) {
            // Send email notification to client
            if ($status === 'verified') {
                $emailHandler->sendPaymentVerifiedNotification(
                    $paymentInfo['user_id'],
                    $paymentInfo['email'],
                    $paymentInfo['first_name'],
                    $paymentInfo['transaction_number'],
                    $paymentInfo['amount'],
                    $notes
                );
            } else {
                $emailHandler->sendPaymentRejectedNotification(
                    $paymentInfo['user_id'],
                    $paymentInfo['email'],
                    $paymentInfo['first_name'],
                    $paymentInfo['transaction_number'],
                    $paymentInfo['amount'],
                    $notes
                );
            }
            
            $message = "Payment has been " . $status . " successfully and client has been notified.";
            $messageType = 'success';
        } else {
            $message = "Error processing payment.";
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get payments with filtering
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "
    SELECT p.*, u.full_name as client_name, u.email
    FROM payments p
    JOIN users u ON p.user_id = u.id
    WHERE (p.status = 'pending' OR p.status = 'verified' OR p.status = 'rejected')
";

if (!empty($search)) {
    $search = '%' . $conn->real_escape_string($search) . '%';
    $query .= " AND (u.full_name LIKE '$search' OR u.email LIKE '$search' OR p.transaction_number LIKE '$search')";
}

if ($filter === 'verified') {
    $query .= " AND p.status = 'verified'";
} elseif ($filter === 'rejected') {
    $query .= " AND p.status = 'rejected'";
} else {
    $query .= " AND p.status = 'pending'";
}

$query .= " ORDER BY p.created_at DESC";

$payments = [];
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payments - Staff Portal</title>
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
        .badge-verified { background: #10b981; color: white; }
        .badge-rejected { background: #ef4444; color: white; }
        
        .payment-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            transition: all 0.2s;
        }
        
        .payment-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .payment-amount {
            font-size: 24px;
            font-weight: 700;
            color: #10b981;
        }
        
        .payment-meta {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .payment-details {
            background: #f8fafc;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 3px solid #3b82f6;
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
            
            <a href="/documentSystem/staff/process-bookings.php">
                <i class="bi bi-calendar-check"></i>
                <span>Process Bookings</span>
            </a>
            
            <a href="/documentSystem/staff/verify-payments.php" class="active">
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
                    <i class="bi bi-wallet-check"></i> Verify Payments
                </h3>
                <small style="color: #64748b; display: block; margin-top: 5px;">
                    <a href="/documentSystem/staff/dashboard.php" style="color: var(--primary-color); text-decoration: none;">Dashboard</a> / Verify Payments
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
                            <input type="text" name="search" class="form-control" placeholder="Search by client name, email, or transaction ID..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="filter" class="form-select">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Pending</option>
                                <option value="verified" <?php echo $filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
            
            <!-- Payments List -->
            <div class="row">
                <div class="col-lg-8">
                    <?php if (empty($payments)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle"></i> No payments found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-card">
                                <div class="payment-header">
                                    <div>
                                        <div class="payment-meta">
                                            <strong><?php echo htmlspecialchars($payment['client_name']); ?></strong>
                                            <br>
                                            <?php echo htmlspecialchars($payment['email']); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="payment-amount">
                                            ₱<?php echo number_format($payment['amount'], 2); ?>
                                        </div>
                                        <?php 
                                        $badgeClass = 'badge-pending';
                                        if ($payment['status'] === 'verified') $badgeClass = 'badge-verified';
                                        elseif ($payment['status'] === 'rejected') $badgeClass = 'badge-rejected';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>" style="font-size: 12px; padding: 6px 12px;">
                                            <?php echo strtoupper($payment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="payment-details">
                                    <div style="font-size: 12px;">
                                        <div style="margin-bottom: 6px;">
                                            <strong>Transaction ID:</strong> <code><?php echo htmlspecialchars($payment['transaction_number']); ?></code>
                                        </div>
                                        <div style="margin-bottom: 6px;">
                                            <strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($payment['payment_method'])); ?>
                                        </div>
                                        <div style="margin-bottom: 6px;">
                                            <strong>Submitted:</strong> <?php echo date('M d, Y @ h:i A', strtotime($payment['created_at'])); ?>
                                        </div>
                                        <?php if (!empty($payment['proof_file'])): ?>
                                            <div style="margin-top: 10px; padding: 10px; background: #f0f9ff; border-radius: 4px; border-left: 3px solid #3b82f6;">
                                                <strong style="color: #1e40af;"><i class="bi bi-file-earmark-image"></i> Payment Proof:</strong><br>
                                                <a href="/documentSystem/<?php echo htmlspecialchars($payment['proof_file']); ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-outline-primary mt-2" 
                                                   style="font-size: 11px;">
                                                    <i class="bi bi-eye"></i> View Receipt
                                                </a>
                                                <a href="/documentSystem/<?php echo htmlspecialchars($payment['proof_file']); ?>" 
                                                   download 
                                                   class="btn btn-sm btn-outline-secondary mt-2" 
                                                   style="font-size: 11px;">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div style="margin-top: 10px; padding: 8px; background: #fef3c7; border-radius: 4px; font-size: 11px; color: #92400e;">
                                                <i class="bi bi-exclamation-triangle"></i> No payment proof uploaded yet
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($payment['verification_notes'])): ?>
                                    <div style="background: #f8fafc; padding: 10px; border-radius: 4px; margin-bottom: 12px; border-left: 3px solid var(--primary-color);">
                                        <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Verification Notes:</div>
                                        <div style="font-size: 13px; color: #334155;">
                                            <?php echo htmlspecialchars($payment['verification_notes']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                                data-bs-target="#verifyModal" 
                                                onclick="setPaymentId('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['client_name']); ?>', '<?php echo number_format($payment['amount'], 2); ?>')">
                                            <i class="bi bi-check-circle"></i> Verify
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                data-bs-target="#rejectPaymentModal"
                                                onclick="setPaymentId('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['client_name']); ?>', '<?php echo number_format($payment['amount'], 2); ?>')">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            Already <?php echo strtoupper($payment['status']); ?>
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
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 5px;">Pending Verification</div>
                                <div style="font-size: 24px; font-weight: 700; color: #fbbf24;">
                                    <?php 
                                    $conn = getDBConnection();
                                    $result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
                                    echo $result->fetch_assoc()['count'] ?? 0;
                                    closeDBConnection($conn);
                                    ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 5px;">Verified Today</div>
                                <div style="font-size: 24px; font-weight: 700; color: #10b981;">
                                    <?php 
                                    $conn = getDBConnection();
                                    $result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'verified' AND DATE(verified_at) = CURDATE()");
                                    echo $result->fetch_assoc()['count'] ?? 0;
                                    closeDBConnection($conn);
                                    ?>
                                </div>
                            </div>
                            
                            <div>
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 5px;">Total Amount Pending</div>
                                <div style="font-size: 18px; font-weight: 700; color: #3b82f6;">
                                    ₱<?php 
                                    $conn = getDBConnection();
                                    $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'pending'");
                                    $row = $result->fetch_assoc();
                                    echo number_format($row['total'] ?? 0, 2);
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
    
    <!-- Verify Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle"></i> Verify Payment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="payment_id" id="verifyPaymentId" value="">
                        <input type="hidden" name="action" value="verified">
                        
                        <div class="mb-3">
                            <label class="form-label">Client Name</label>
                            <input type="text" class="form-control" id="verifyClientName" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="text" class="form-control" id="verifyAmount" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Verification Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add verification details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Verify Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Payment Modal -->
    <div class="modal fade" id="rejectPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle"></i> Reject Payment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="payment_id" id="rejectPaymentId" value="">
                        <input type="hidden" name="action" value="rejected">
                        
                        <div class="mb-3">
                            <label class="form-label">Client Name</label>
                            <input type="text" class="form-control" id="rejectClientName" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="text" class="form-control" id="rejectAmount" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection *</label>
                            <textarea class="form-control" name="notes" rows="4" placeholder="Please provide the reason for rejection..." required></textarea>
                            <small class="text-muted">This will be sent to the client.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Reject Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function setPaymentId(paymentId, clientName, amount) {
            document.getElementById('verifyPaymentId').value = paymentId;
            document.getElementById('verifyClientName').value = clientName;
            document.getElementById('verifyAmount').value = '₱' + amount;
            
            document.getElementById('rejectPaymentId').value = paymentId;
            document.getElementById('rejectClientName').value = clientName;
            document.getElementById('rejectAmount').value = '₱' + amount;
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
