<?php
/**
 * Staff Dashboard
 * Overview of pending documents, bookings, and payments
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/UI/Layouts/StaffLayout.php';

startSecureSession();

// Check if user is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();

// Get statistics
$stats = [];

// Pending documents
$query = "SELECT COUNT(*) as count FROM document_requests WHERE status = 'pending'";
$stats['pending_documents'] = $conn->query($query)->fetch_assoc()['count'] ?? 0;

// Pending bookings
$query = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
$stats['pending_bookings'] = $conn->query($query)->fetch_assoc()['count'] ?? 0;

// Pending payments
$query = "SELECT COUNT(*) as count FROM payments WHERE status = 'pending'";
$stats['pending_payments'] = $conn->query($query)->fetch_assoc()['count'] ?? 0;

// Approved today
$query = "SELECT COUNT(*) as count FROM document_requests WHERE status = 'approved' AND DATE(updated_at) = CURDATE()";
$stats['approved_today'] = $conn->query($query)->fetch_assoc()['count'] ?? 0;

// Recent documents
$query = "
    SELECT dr.*, dt.name as document_type, u.full_name as client_name, u.email
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    JOIN users u ON dr.user_id = u.id
    WHERE dr.status = 'pending'
    ORDER BY dr.created_at DESC
    LIMIT 5
";
$recent_documents = [];
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $recent_documents[] = $row;
}

// Recent bookings
$query = "
    SELECT b.*, bt.name as booking_type, u.full_name as client_name
    FROM bookings b
    JOIN booking_types bt ON b.booking_type_id = bt.id
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'pending'
    ORDER BY b.created_at DESC
    LIMIT 5
";
$recent_bookings = [];
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $recent_bookings[] = $row;
}

// Recent payments
$query = "
    SELECT p.*, u.full_name as client_name, u.email
    FROM payments p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
    LIMIT 5
";
$recent_payments = [];
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $recent_payments[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Document Management System</title>
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
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card i {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin: 10px 0;
        }
        
        .stat-card .label {
            font-size: 13px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card.blue i { color: #3b82f6; }
        .stat-card.amber i { color: #f59e0b; }
        .stat-card.green i { color: #10b981; }
        .stat-card.purple i { color: #a855f7; }
        
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
        }
        
        .table-hover tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge-pending { background: #fbbf24; color: #333; }
        .badge-approved { background: #10b981; color: white; }
        .badge-rejected { background: #ef4444; color: white; }
        
        .action-btn {
            padding: 4px 12px;
            font-size: 12px;
            border-radius: 4px;
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
            <a href="/documentSystem/staff/dashboard.php" class="active">
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
                    <i class="bi bi-speedometer2"></i> Staff Dashboard
                </h3>
                <small style="color: #64748b; display: block; margin-top: 5px;">
                    <a href="/documentSystem/staff/dashboard.php" style="color: var(--primary-color); text-decoration: none;">Staff Portal</a> / Dashboard
                </small>
            </div>
            <div>
                <div style="text-align: right;">
                    <div style="font-size: 13px; font-weight: 600; color: #334155;">
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>
                    </div>
                    <div style="font-size: 11px; color: #64748b;">Staff Account</div>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="page-content">
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card blue">
                        <i class="bi bi-file-earmark"></i>
                        <div class="number"><?php echo $stats['pending_documents']; ?></div>
                        <div class="label">Pending Documents</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card amber">
                        <i class="bi bi-calendar"></i>
                        <div class="number"><?php echo $stats['pending_bookings']; ?></div>
                        <div class="label">Pending Bookings</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card green">
                        <i class="bi bi-wallet2"></i>
                        <div class="number"><?php echo $stats['pending_payments']; ?></div>
                        <div class="label">Pending Payments</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card purple">
                        <i class="bi bi-check-circle"></i>
                        <div class="number"><?php echo $stats['approved_today']; ?></div>
                        <div class="label">Approved Today</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Documents -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-file-earmark"></i> Recent Documents
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_documents)): ?>
                                <div class="p-3 text-center text-muted">
                                    <p>No pending documents</p>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        <?php foreach ($recent_documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($doc['document_type']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($doc['client_name']); ?></small>
                                                </td>
                                                <td style="text-align: right;">
                                                    <span class="badge badge-pending">PENDING</span><br>
                                                    <small class="text-muted"><?php echo date('M d', strtotime($doc['created_at'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                            <a href="/documentSystem/staff/process-documents.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-right"></i> View All
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-calendar"></i> Recent Bookings
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_bookings)): ?>
                                <div class="p-3 text-center text-muted">
                                    <p>No pending bookings</p>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['booking_type']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['client_name']); ?></small>
                                                </td>
                                                <td style="text-align: right;">
                                                    <span class="badge badge-pending">PENDING</span><br>
                                                    <small class="text-muted"><?php echo date('M d', strtotime($booking['created_at'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                            <a href="/documentSystem/staff/process-bookings.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-right"></i> View All
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-wallet2"></i> Recent Payments
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_payments)): ?>
                                <div class="p-3 text-center text-muted">
                                    <p>No pending payments</p>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        <?php foreach ($recent_payments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['client_name']); ?></strong><br>
                                                    <small class="text-muted">₱<?php echo number_format($payment['amount'], 2); ?></small>
                                                </td>
                                                <td style="text-align: right;">
                                                    <span class="badge badge-pending">PENDING</span><br>
                                                    <small class="text-muted"><?php echo date('M d', strtotime($payment['created_at'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                            <a href="/documentSystem/staff/verify-payments.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-right"></i> View All
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-lightning"></i> Quick Actions
                        </div>
                        <div class="card-body">
                            <a href="/documentSystem/staff/process-documents.php" class="btn btn-outline-primary btn-sm mb-2 w-100">
                                <i class="bi bi-file-earmark-check"></i> Process Documents
                            </a>
                            <a href="/documentSystem/staff/process-bookings.php" class="btn btn-outline-primary btn-sm mb-2 w-100">
                                <i class="bi bi-calendar-check"></i> Process Bookings
                            </a>
                            <a href="/documentSystem/staff/verify-payments.php" class="btn btn-outline-primary btn-sm mb-2 w-100">
                                <i class="bi bi-wallet-check"></i> Verify Payments
                            </a>
                            <a href="/documentSystem/staff/activity-log.php" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-clock-history"></i> View Activity
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
