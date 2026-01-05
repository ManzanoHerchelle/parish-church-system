<?php
/**
 * Staff: Activity Log
 * View staff actions and system activity
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

// Check if user is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();

// Get filters
$filter = $_GET['filter'] ?? 'all';
$action_type = $_GET['action_type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build activity log query
$query = "
    SELECT 
        id, 
        user_id,
        action,
        entity_type,
        entity_id,
        changes,
        ip_address,
        created_at,
        (SELECT full_name FROM users WHERE id = activity_logs.user_id) as staff_name
    FROM activity_logs
    WHERE user_id IN (SELECT id FROM users WHERE role = 'staff')
";

if ($filter === 'my') {
    $userId = $_SESSION['user_id'];
    $query .= " AND user_id = $userId";
}

if ($action_type !== 'all') {
    $actionType = $conn->real_escape_string($action_type);
    $query .= " AND action = '$actionType'";
}

if (!empty($search)) {
    $search = '%' . $conn->real_escape_string($search) . '%';
    $query .= " AND (entity_type LIKE '$search' OR changes LIKE '$search')";
}

$query .= " ORDER BY created_at DESC LIMIT 100";

$activities = [];
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}

// Get unique actions for filter
$actionQuery = "SELECT DISTINCT action FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE role = 'staff') ORDER BY action";
$actionResult = $conn->query($actionQuery);
$actions = [];
while ($row = $actionResult->fetch_assoc()) {
    $actions[] = $row['action'];
}

closeDBConnection($conn);

// Helper function to format action
function formatAction($action) {
    $actions = [
        'document_approved' => ['Document Approved', 'success'],
        'document_rejected' => ['Document Rejected', 'danger'],
        'booking_confirmed' => ['Booking Confirmed', 'success'],
        'booking_cancelled' => ['Booking Cancelled', 'danger'],
        'payment_verified' => ['Payment Verified', 'success'],
        'payment_rejected' => ['Payment Rejected', 'danger'],
        'login' => ['Login', 'info'],
        'logout' => ['Logout', 'secondary'],
    ];
    
    return $actions[$action] ?? [$action, 'secondary'];
}

// Helper function to format entity type
function formatEntityType($type) {
    $types = [
        'document_request' => 'Document Request',
        'booking' => 'Booking',
        'payment' => 'Payment',
        'user' => 'User',
        'system' => 'System',
    ];
    
    return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Staff Portal</title>
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
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 15px;
            transition: background 0.2s;
        }
        
        .activity-item:hover {
            background: #f8fafc;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
            font-size: 18px;
        }
        
        .activity-icon.success { background: #d1fae5; color: #10b981; }
        .activity-icon.danger { background: #fee2e2; color: #ef4444; }
        .activity-icon.info { background: #dbeafe; color: #3b82f6; }
        .activity-icon.warning { background: #fef3c7; color: #f59e0b; }
        .activity-icon.secondary { background: #e2e8f0; color: #64748b; }
        
        .activity-content {
            flex: 1;
            min-width: 0;
        }
        
        .activity-title {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }
        
        .activity-meta {
            font-size: 12px;
            color: #64748b;
        }
        
        .activity-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 8px;
        }
        
        .table-hover tbody tr:hover {
            background: #f8fafc;
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
            
            <a href="/documentSystem/staff/verify-payments.php">
                <i class="bi bi-wallet-check"></i>
                <span>Verify Payments</span>
            </a>
            
            <div class="nav-label">Management</div>
            <a href="/documentSystem/staff/activity-log.php" class="active">
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
                    <i class="bi bi-clock-history"></i> Activity Log
                </h3>
                <small style="color: #64748b; display: block; margin-top: 5px;">
                    <a href="/documentSystem/staff/dashboard.php" style="color: var(--primary-color); text-decoration: none;">Dashboard</a> / Activity Log
                </small>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="page-content">
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <select name="filter" class="form-select">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Staff Activities</option>
                                <option value="my" <?php echo $filter === 'my' ? 'selected' : ''; ?>>My Activities</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="action_type" class="form-select">
                                <option value="all">All Actions</option>
                                <?php foreach ($actions as $act): ?>
                                    <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action_type === $act ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $act))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Activity Timeline -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-list-ul"></i> Recent Activities
                        </div>
                        
                        <?php if (empty($activities)): ?>
                            <div style="padding: 40px; text-align: center; color: #64748b;">
                                <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>No activities found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): 
                                $actionInfo = formatAction($activity['action']);
                                $actionLabel = $actionInfo[0];
                                $actionClass = $actionInfo[1];
                            ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $actionClass; ?>">
                                        <?php 
                                        $icons = [
                                            'success' => 'bi bi-check-circle',
                                            'danger' => 'bi bi-x-circle',
                                            'info' => 'bi bi-info-circle',
                                            'warning' => 'bi bi-exclamation-circle',
                                            'secondary' => 'bi bi-circle',
                                        ];
                                        ?>
                                        <i class="<?php echo $icons[$actionClass] ?? 'bi bi-circle'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <span class="activity-badge badge bg-<?php echo $actionClass; ?>" style="background: rgba(0,0,0,0.05) !important; color: #334155 !important;">
                                                <?php echo htmlspecialchars($actionLabel); ?>
                                            </span>
                                            <span class="activity-badge badge bg-secondary" style="background: rgba(0,0,0,0.05) !important; color: #334155 !important;">
                                                <?php echo htmlspecialchars(formatEntityType($activity['entity_type'])); ?>
                                            </span>
                                        </div>
                                        <div class="activity-meta">
                                            <strong><?php echo htmlspecialchars($activity['staff_name'] ?? 'Unknown'); ?></strong> 
                                            • <?php echo date('M d, Y @ h:i A', strtotime($activity['created_at'])); ?>
                                        </div>
                                        <?php if (!empty($activity['changes'])): ?>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 6px; padding: 8px; background: #f8fafc; border-left: 2px solid #e2e8f0; border-radius: 2px;">
                                                <strong>Changes:</strong> <?php echo htmlspecialchars($activity['changes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stats Sidebar -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-bar-chart"></i> Today's Summary
                        </div>
                        <div class="card-body">
                            <?php
                            $conn = getDBConnection();
                            
                            // Documents approved today
                            $result = $conn->query("SELECT COUNT(*) as count FROM document_requests WHERE status = 'approved' AND DATE(updated_at) = CURDATE() AND updated_by IN (SELECT id FROM users WHERE role = 'staff')");
                            $docsApproved = $result->fetch_assoc()['count'] ?? 0;
                            
                            // Bookings confirmed today
                            $result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed' AND DATE(updated_at) = CURDATE() AND updated_by IN (SELECT id FROM users WHERE role = 'staff')");
                            $bookingsConfirmed = $result->fetch_assoc()['count'] ?? 0;
                            
                            // Payments verified today
                            $result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'verified' AND DATE(verified_at) = CURDATE()");
                            $paymentsVerified = $result->fetch_assoc()['count'] ?? 0;
                            
                            closeDBConnection($conn);
                            ?>
                            
                            <div style="margin-bottom: 20px;">
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 8px;">
                                    <i class="bi bi-file-earmark"></i> Documents Approved
                                </div>
                                <div style="font-size: 24px; font-weight: 700; color: #3b82f6;">
                                    <?php echo $docsApproved; ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 8px;">
                                    <i class="bi bi-calendar"></i> Bookings Confirmed
                                </div>
                                <div style="font-size: 24px; font-weight: 700; color: #10b981;">
                                    <?php echo $bookingsConfirmed; ?>
                                </div>
                            </div>
                            
                            <div>
                                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 8px;">
                                    <i class="bi bi-wallet2"></i> Payments Verified
                                </div>
                                <div style="font-size: 24px; font-weight: 700; color: #8b5cf6;">
                                    <?php echo $paymentsVerified; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <i class="bi bi-info-circle"></i> Help
                        </div>
                        <div class="card-body">
                            <p style="font-size: 13px; margin-bottom: 10px;">
                                This activity log tracks all actions performed by staff members in the system.
                            </p>
                            <p style="font-size: 13px; margin-bottom: 10px;">
                                Use the filters to view:
                            </p>
                            <ul style="font-size: 12px; margin-bottom: 10px;">
                                <li><strong>All Staff Activities</strong> - View all staff actions</li>
                                <li><strong>My Activities</strong> - View only your actions</li>
                            </ul>
                            <p style="font-size: 13px; color: #64748b;">
                                Activities are logged automatically for audit and security purposes.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
