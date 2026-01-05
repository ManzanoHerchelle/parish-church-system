<?php
/**
 * Admin: Manage Staff
 * Staff accounts, absences, and workload management
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/UI/Layouts/AdminLayout.php';

startSecureSession();

// Check if user is logged in and is admin
$userRole = $_SESSION['user_role'] ?? 'guest';
if (!isset($_SESSION['user_id']) || $userRole !== 'admin') {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$conn = getDBConnection();

// Handle staff absence management
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_absence') {
        $staffId = intval($_POST['staff_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $reassignTo = intval($_POST['reassign_to'] ?? 0);
        
        if ($staffId && $startDate && $endDate) {
            $query = "INSERT INTO staff_absences (staff_id, start_date, end_date, reason, reassign_to, approved_by, approved_date) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('isssii', $staffId, $startDate, $endDate, $reason, $reassignTo, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $message = 'Staff absence added successfully. Tasks will be reassigned automatically.';
                $messageType = 'success';
            } else {
                $message = 'Error adding staff absence.';
                $messageType = 'danger';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_absence') {
        $absenceId = intval($_POST['absence_id'] ?? 0);
        $query = "DELETE FROM staff_absences WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $absenceId);
        
        if ($stmt->execute()) {
            $message = 'Staff absence removed successfully.';
            $messageType = 'success';
        } else {
            $message = 'Error removing absence.';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get all staff members
$staffQuery = "SELECT id, first_name, last_name, email FROM users WHERE role = 'staff' ORDER BY first_name ASC";
$staffList = [];
$result = $conn->query($staffQuery);
while ($row = $result->fetch_assoc()) {
    $staffList[] = $row;
}

// Get current absences with workload
$absenceQuery = "
    SELECT 
        sa.*,
        CONCAT(s.first_name, ' ', s.last_name) as staff_name,
        CONCAT(r.first_name, ' ', r.last_name) as reassign_name,
        (SELECT COUNT(*) FROM document_requests WHERE processed_by = sa.staff_id AND status = 'pending') as pending_docs,
        (SELECT COUNT(*) FROM bookings WHERE approved_by = sa.staff_id AND status = 'pending') as pending_bookings,
        (SELECT COUNT(*) FROM payments WHERE verified_by = sa.staff_id AND status = 'pending') as pending_payments
    FROM staff_absences sa
    JOIN users s ON sa.staff_id = s.id
    LEFT JOIN users r ON sa.reassign_to = r.id
    WHERE sa.start_date <= CURDATE() AND sa.end_date >= CURDATE()
    ORDER BY sa.start_date DESC
";

$activeAbsences = [];
$result = $conn->query($absenceQuery);
while ($row = $result->fetch_assoc()) {
    $activeAbsences[] = $row;
}

// Get all scheduled absences
$upcomingQuery = "
    SELECT 
        sa.*,
        CONCAT(s.first_name, ' ', s.last_name) as staff_name,
        CONCAT(r.first_name, ' ', r.last_name) as reassign_name
    FROM staff_absences sa
    JOIN users s ON sa.staff_id = s.id
    LEFT JOIN users r ON sa.reassign_to = r.id
    ORDER BY sa.start_date DESC
    LIMIT 50
";

$allAbsences = [];
$result = $conn->query($upcomingQuery);
while ($row = $result->fetch_assoc()) {
    $allAbsences[] = $row;
}

// Get staff workload statistics
$workloadQuery = "
    SELECT 
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as staff_name,
        (SELECT COUNT(*) FROM document_requests WHERE status = 'pending') as total_pending_docs,
        (SELECT COUNT(*) FROM document_requests WHERE processed_by = u.id AND status = 'pending') as pending_docs,
        (SELECT COUNT(*) FROM bookings WHERE status = 'pending') as total_pending_bookings,
        (SELECT COUNT(*) FROM bookings WHERE approved_by = u.id AND status = 'pending') as pending_bookings,
        (SELECT COUNT(*) FROM payments WHERE status = 'pending') as total_pending_payments,
        (SELECT COUNT(*) FROM payments WHERE verified_by = u.id AND status = 'pending') as pending_payments,
        CASE WHEN EXISTS(SELECT 1 FROM staff_absences WHERE staff_id = u.id AND start_date <= CURDATE() AND end_date >= CURDATE()) THEN 'absent' ELSE 'available' END as status
    FROM users u
    WHERE u.role = 'staff'
    ORDER BY u.first_name ASC
";

$staffWorkload = [];
$result = $conn->query($workloadQuery);
while ($row = $result->fetch_assoc()) {
    $staffWorkload[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-available { background: #d1fae5; color: #065f46; }
        .status-absent { background: #fee2e2; color: #7f1d1d; }
        
        .workload-bar {
            height: 24px;
            background: #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        .workload-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: 600;
        }
        
        .card-header-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header with breadcrumb -->
        <div style="padding: 30px 0; border-bottom: 1px solid #e5e7eb; margin-bottom: 30px;">
            <div class="container">
                <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #1f2937;">
                    <i class="bi bi-people"></i> Manage Staff
                </h1>
                <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">
                    <a href="/documentSystem/admin/dashboard.php" style="color: #667eea; text-decoration: none;">Admin Dashboard</a> / 
                    Manage Staff & Absences
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Staff Workload -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <i class="bi bi-bar-chart"></i> Staff Workload & Availability
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Staff Member</th>
                                        <th style="text-align: center;">Status</th>
                                        <th>Documents</th>
                                        <th>Bookings</th>
                                        <th>Payments</th>
                                        <th style="text-align: center;">Total Load</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($staffWorkload)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No staff members found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($staffWorkload as $staff): 
                                            $totalLoad = $staff['pending_docs'] + $staff['pending_bookings'] + $staff['pending_payments'];
                                            $statusBadge = $staff['status'] === 'absent' ? 'status-absent' : 'status-available';
                                            $statusText = $staff['status'] === 'absent' ? 'Absent' : 'Available';
                                            $statusIcon = $staff['status'] === 'absent' ? 'clock-history' : 'check-circle';
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($staff['staff_name']); ?></strong>
                                                </td>
                                                <td style="text-align: center;">
                                                    <span class="status-badge <?php echo $statusBadge; ?>">
                                                        <i class="bi bi-<?php echo $statusIcon; ?>"></i>
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $staff['pending_docs']; ?></span>
                                                    / <?php echo $staff['total_pending_docs']; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning"><?php echo $staff['pending_bookings']; ?></span>
                                                    / <?php echo $staff['total_pending_bookings']; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $staff['pending_payments']; ?></span>
                                                    / <?php echo $staff['total_pending_payments']; ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <span class="badge bg-secondary"><?php echo $totalLoad; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Absences -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <i class="bi bi-calendar-x"></i> Currently Absent Staff
                        </div>
                        <?php if (empty($activeAbsences)): ?>
                            <div style="padding: 40px; text-align: center; color: #6b7280;">
                                <p style="margin: 0;">No staff members are currently absent</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Staff Member</th>
                                            <th>Absence Period</th>
                                            <th>Reason</th>
                                            <th>Reassigned To</th>
                                            <th>Pending Work</th>
                                            <th style="text-align: center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeAbsences as $absence): 
                                            $totalPending = $absence['pending_docs'] + $absence['pending_bookings'] + $absence['pending_payments'];
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($absence['staff_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d', strtotime($absence['start_date'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($absence['end_date'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($absence['reason'] ?: 'N/A'); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo $absence['reassign_name'] ? htmlspecialchars($absence['reassign_name']) : '<span class="text-muted">Unassigned</span>'; ?>
                                                </td>
                                                <td>
                                                    <div style="font-size: 12px;">
                                                        <span class="badge bg-info"><?php echo $absence['pending_docs']; ?></span> docs
                                                        <span class="badge bg-warning"><?php echo $absence['pending_bookings']; ?></span> bookings
                                                        <span class="badge bg-success"><?php echo $absence['pending_payments']; ?></span> payments
                                                    </div>
                                                </td>
                                                <td style="text-align: center;">
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_absence">
                                                        <input type="hidden" name="absence_id" value="<?php echo $absence['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this absence record?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Add Staff Absence Form -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <i class="bi bi-calendar-plus"></i> Add Staff Absence
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="add_absence">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Staff Member *</label>
                                        <select name="staff_id" class="form-select" required>
                                            <option value="">Select staff member...</option>
                                            <?php foreach ($staffList as $staff): ?>
                                                <option value="<?php echo $staff['id']; ?>">
                                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Reason *</label>
                                        <input type="text" name="reason" class="form-control" placeholder="e.g., Sick leave, Vacation, Training" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Start Date *</label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">End Date *</label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Reassign Work To (Optional)</label>
                                    <select name="reassign_to" class="form-select">
                                        <option value="0">Select backup staff member...</option>
                                        <?php foreach ($staffList as $staff): ?>
                                            <option value="<?php echo $staff['id']; ?>">
                                                <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        If selected, pending documents, bookings, and payments will be automatically reassigned to this staff member.
                                    </small>
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Add Absence Record
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <i class="bi bi-info-circle"></i> About Absence Management
                        </div>
                        <div class="card-body" style="font-size: 13px;">
                            <p><strong>Why track absences?</strong></p>
                            <ul style="margin-bottom: 15px; padding-left: 20px;">
                                <li>Monitor staff availability</li>
                                <li>Identify workload gaps</li>
                                <li>Plan task reassignment</li>
                                <li>Track SLA compliance</li>
                            </ul>
                            
                            <p><strong>What happens when staff is absent?</strong></p>
                            <ul style="margin-bottom: 15px; padding-left: 20px;">
                                <li>System marks them as unavailable</li>
                                <li>Work is reassigned if specified</li>
                                <li>Other staff can see the workload</li>
                                <li>Absence appears on dashboard</li>
                            </ul>
                            
                            <p><strong>How to use this feature:</strong></p>
                            <ol style="margin: 0; padding-left: 20px;">
                                <li>Select the staff member</li>
                                <li>Choose absence dates</li>
                                <li>Optionally reassign their work</li>
                                <li>Click "Add Absence Record"</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Absences History -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <i class="bi bi-clock-history"></i> Absence History
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Staff</th>
                                        <th>Period</th>
                                        <th>Reason</th>
                                        <th>Reassigned</th>
                                        <th>Status</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allAbsences as $absence): 
                                        $isActive = (strtotime($absence['start_date']) <= time() && strtotime($absence['end_date']) >= time());
                                        $statusBadge = $isActive ? 'danger' : 'secondary';
                                        $statusText = $isActive ? 'Active' : 'Past';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($absence['staff_name']); ?></strong></td>
                                            <td>
                                                <small>
                                                    <?php echo date('M d', strtotime($absence['start_date'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($absence['end_date'])); ?>
                                                </small>
                                            </td>
                                            <td><small><?php echo htmlspecialchars($absence['reason'] ?: '—'); ?></small></td>
                                            <td><small><?php echo $absence['reassign_name'] ? htmlspecialchars($absence['reassign_name']) : '—'; ?></small></td>
                                            <td>
                                                <span class="badge bg-<?php echo $statusBadge; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_absence">
                                                    <input type="hidden" name="absence_id" value="<?php echo $absence['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this record?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
