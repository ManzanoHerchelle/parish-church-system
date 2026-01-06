<?php
/**
 * Admin Dashboard - System Overview
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

// Require login and admin role
if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$userRole = getCurrentUserRole();
if ($userRole !== 'admin' && $userRole !== 'staff') {
    header('Location: /documentSystem/client/dashboard.php');
    exit;
}

$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'Admin';
$userEmail = getCurrentUserEmail();

// Generate user initials
$nameParts = explode(' ', $userName);
$userInitials = strtoupper(
    substr($nameParts[0], 0, 1) . 
    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
);

$conn = getDBConnection();

// Get active logo
$activeLogo = null;
try {
    $tableCheck = $conn->query("SELECT 1 FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA='parish_church_system' AND TABLE_NAME='system_logos'");
    
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $logoResult = $conn->query("SELECT file_path, alt_text, name FROM system_logos WHERE is_active = 1 AND is_archived = 0 LIMIT 1");
        if ($logoResult && $logoResult->num_rows > 0) {
            $activeLogo = $logoResult->fetch_assoc();
        }
    }
} catch (Exception $e) {
    // Logo fetch failed, continue without it
}

// Get system statistics
$stats = array();

// Total users (excluding admin/staff)
$countQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'client'";
$stats['total_clients'] = $conn->query($countQuery)->fetch_assoc()['count'];

// Pending document requests
$countQuery = "SELECT COUNT(*) as count FROM document_requests WHERE status IN ('pending', 'processing')";
$stats['pending_documents'] = $conn->query($countQuery)->fetch_assoc()['count'];

// Pending appointments
$countQuery = "SELECT COUNT(*) as count FROM bookings WHERE status IN ('pending', 'confirmed') AND appointment_date >= NOW()";
$stats['pending_appointments'] = $conn->query($countQuery)->fetch_assoc()['count'];

// Ready documents for pickup
$countQuery = "SELECT COUNT(*) as count FROM document_requests WHERE status = 'ready'";
$stats['ready_documents'] = $conn->query($countQuery)->fetch_assoc()['count'];

// Unpaid payments
$countQuery = "SELECT COUNT(*) as count FROM payments WHERE status IN ('unpaid', 'pending')";
$stats['unpaid_payments'] = $conn->query($countQuery)->fetch_assoc()['count'];

// Total revenue (paid payments)
$countQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid'";
$stats['total_revenue'] = (float)$conn->query($countQuery)->fetch_assoc()['total'];

// Get recent document requests
$recentDocsQuery = "
    SELECT dr.id, dr.reference_number, dr.status, CONCAT(u.first_name, ' ', u.last_name) as client_name, 
           dt.name as document_type, dr.created_at
    FROM document_requests dr
    JOIN users u ON dr.user_id = u.id
    JOIN document_types dt ON dr.document_type_id = dt.id
    ORDER BY dr.created_at DESC
    LIMIT 5
";
$recentDocs = $conn->query($recentDocsQuery)->fetch_all(MYSQLI_ASSOC);

// Get recent appointments
$recentAptQuery = "
    SELECT b.id, b.reference_number, b.status, CONCAT(u.first_name, ' ', u.last_name) as client_name, 
           bt.name as booking_type, b.appointment_date, b.created_at
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN booking_types bt ON b.booking_type_id = bt.id
    ORDER BY b.created_at DESC
    LIMIT 5
";
$recentApts = $conn->query($recentAptQuery)->fetch_all(MYSQLI_ASSOC);

// Get pending approvals count by type
$pendingDocCount = $conn->query("SELECT COUNT(*) as count FROM document_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pendingAptCount = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")->fetch_assoc()['count'];
$pendingPaymentCount = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")->fetch_assoc()['count'];

// Check if is admin (not just staff)
$isAdmin = ($userRole === 'admin');

// SLA Monitoring - Get overdue items
$docSLAQuery = "
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
    LIMIT 10
";

$overdueDocuments = [];
$result = $conn->query($docSLAQuery);
while ($row = $result->fetch_assoc()) {
    $overdueDocuments[] = $row;
}

$bookingSLAQuery = "
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
    LIMIT 10
";

$overdueBookings = [];
$result = $conn->query($bookingSLAQuery);
while ($row = $result->fetch_assoc()) {
    $overdueBookings[] = $row;
}

$paymentSLAQuery = "
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
    LIMIT 10
";

$overduePayments = [];
$result = $conn->query($paymentSLAQuery);
while ($row = $result->fetch_assoc()) {
    $overduePayments[] = $row;
}

// Count critical items
$criticalDocs = array_filter($overdueDocuments, fn($item) => $item['sla_status'] === 'critical');
$criticalBookings = array_filter($overdueBookings, fn($item) => $item['sla_status'] === 'critical');
$criticalPayments = array_filter($overduePayments, fn($item) => $item['sla_status'] === 'critical');
$totalCritical = count($criticalDocs) + count($criticalBookings) + count($criticalPayments);

closeDBConnection($conn);

// Format hours helper function
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
  <link href="/documentSystem/assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-circles">
        <?php if ($activeLogo): ?>
          <img src="/documentSystem/<?php echo htmlspecialchars($activeLogo['file_path']); ?>" 
               alt="<?php echo htmlspecialchars($activeLogo['alt_text'] ?: $activeLogo['name']); ?>" 
               style="max-width: 120px; max-height: 120px; object-fit: contain; border-radius: 50%;">
        <?php else: ?>
          <div class="logo-circle">PC</div>
        <?php endif; ?>
      </div>
      <div class="system-title">
        Parish Ease: Admin Panel
      </div>
    </div>

    <!-- User Profile Card -->
    <div class="user-profile-card">
      <div class="user-avatar"><?php echo $userInitials; ?></div>
      <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
      <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
      <span style="font-size: 10px; color: #ffc107; text-transform: uppercase; font-weight: bold;">
        <?php echo ucfirst($userRole); ?>
      </span>
    </div>

    <ul class="nav-menu">
      <li class="nav-item">
        <a href="/documentSystem/admin/dashboard.php" class="nav-link active">
          <i class="bi bi-speedometer2"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <?php if ($isAdmin): ?>
      <li class="nav-item">
        <a href="/documentSystem/admin/manage-documents.php" class="nav-link">
          <i class="bi bi-file-earmark-check"></i>
          <span>Manage Documents</span>
          <?php if ($pendingDocCount > 0): ?>
            <span class="nav-badge"><?php echo $pendingDocCount; ?></span>
          <?php endif; ?>
        </a>
      </li>

      <li class="nav-item">
        <a href="/documentSystem/admin/manage-appointments.php" class="nav-link">
          <i class="bi bi-calendar-check"></i>
          <span>Manage Appointments</span>
          <?php if ($pendingAptCount > 0): ?>
            <span class="nav-badge"><?php echo $pendingAptCount; ?></span>
          <?php endif; ?>
        </a>
      </li>

      <li class="nav-item">
        <a href="/documentSystem/admin/manage-payments.php" class="nav-link">
          <i class="bi bi-credit-card"></i>
          <span>Manage Payments</span>
          <?php if ($pendingPaymentCount > 0): ?>
            <span class="nav-badge"><?php echo $pendingPaymentCount; ?></span>
          <?php endif; ?>
        </a>
      </li>

      <li class="nav-item">
        <a href="/documentSystem/admin/manage-users.php" class="nav-link">
          <i class="bi bi-people"></i>
          <span>Manage Users</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/documentSystem/admin/manage-logos.php" class="nav-link">
          <i class="bi bi-image"></i>
          <span>Manage Logos</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/documentSystem/admin/system-settings.php" class="nav-link">
          <i class="bi bi-gear"></i>
          <span>System Settings</span>
        </a>
      </li>

      <div class="nav-separator"></div>
      <?php endif; ?>

      <li class="nav-item">
        <a href="/documentSystem/client/change-password.php" class="nav-link">
          <i class="bi bi-key-fill"></i>
          <span>Change Password</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/api/logout.php" class="nav-link">
          <i class="bi bi-box-arrow-right"></i>
          <span>Log Out</span>
        </a>
      </li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="content-area">
      <h1 class="page-title">ADMIN DASHBOARD</h1>
      <div class="title-underline"></div>

      <!-- System Status Alert -->
      <div class="notification-alert info" style="margin-bottom: 20px;">
        <strong><i class="bi bi-info-circle"></i> System Status:</strong>
        <p style="margin: 5px 0 0 0;">
          <?php 
          $isOpen = (date('N') >= 1 && date('N') <= 6) && (date('G') >= 9 && date('G') < 17);
          echo $isOpen ? 
            '<span style="color: #0c5460; font-weight: bold;">✓ Parish Office is OPEN</span>' : 
            '<span style="color: #721c24; font-weight: bold;">✗ Parish Office is CLOSED</span>';
          ?>
        </p>
      </div>

      <!-- Quick Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-people"></i></div>
          <div class="stat-label">Total Clients</div>
          <div class="stat-number"><?php echo $stats['total_clients']; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
          <div class="stat-label">Pending Documents</div>
          <div class="stat-number"><?php echo $stats['pending_documents']; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
          <div class="stat-label">Pending Appointments</div>
          <div class="stat-number"><?php echo $stats['pending_appointments']; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
          <div class="stat-label">Ready for Pickup</div>
          <div class="stat-number"><?php echo $stats['ready_documents']; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-cash"></i></div>
          <div class="stat-label">Unpaid Payments</div>
          <div class="stat-number">₱<?php echo number_format($stats['unpaid_payments']); ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
          <div class="stat-label">Total Revenue</div>
          <div class="stat-number">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
        </div>
      </div>

      <!-- Quick Action Buttons -->
      <?php if ($isAdmin): ?>
      <div style="margin-bottom: 30px;">
        <h3 style="font-weight: bold; margin-bottom: 15px;">QUICK ACTIONS</h3>
        <div class="quick-actions">
          <a href="/documentSystem/admin/manage-documents.php" class="action-btn">
            <i class="bi bi-file-earmark-check"></i>
            <span>Review Documents</span>
          </a>
          <a href="/documentSystem/admin/manage-appointments.php" class="action-btn">
            <i class="bi bi-calendar-check"></i>
            <span>Review Appointments</span>
          </a>
          <a href="/documentSystem/admin/manage-payments.php" class="action-btn">
            <i class="bi bi-credit-card"></i>
            <span>Verify Payments</span>
          </a>
          <a href="/documentSystem/admin/manage-logos.php" class="action-btn">
            <i class="bi bi-image"></i>
            <span>Manage Logos</span>
          </a>
          <a href="/documentSystem/admin/system-settings.php" class="action-btn">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <!-- SLA Alert & Critical Items -->
      <?php if ($totalCritical > 0): ?>
      <div style="background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; padding: 15px; margin-bottom: 30px; color: #7f1d1d;">
        <h4 style="margin: 0 0 10px 0;">
          <i class="bi bi-exclamation-triangle"></i> 
          <strong><?php echo $totalCritical; ?> Item(s) in Critical Status</strong>
        </h4>
        <p style="margin: 0; font-size: 14px;">
          These items have exceeded their SLA deadline. Please address them immediately.
        </p>
      </div>
      <?php endif; ?>

      <!-- Overdue Items by Type -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <!-- Overdue Documents -->
        <?php if (!empty($overdueDocuments)): ?>
        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
          <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; font-weight: bold;">
            <i class="bi bi-file-earmark"></i> Overdue Documents
          </div>
          <div style="padding: 15px; max-height: 350px; overflow-y: auto;">
            <?php foreach ($overdueDocuments as $doc): ?>
            <div style="border-left: 3px solid <?php echo $doc['sla_status'] === 'critical' ? '#ef4444' : '#f59e0b'; ?>; 
                        background: <?php echo $doc['sla_status'] === 'critical' ? '#fef2f2' : '#fffbeb'; ?>; 
                        padding: 12px; margin-bottom: 10px; border-radius: 4px;">
              <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                  <strong style="font-size: 14px;"><?php echo htmlspecialchars($doc['document_type']); ?></strong><br>
                  <small style="color: #6b7280;"><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></small>
                </div>
                <span style="background: <?php echo $doc['sla_status'] === 'critical' ? '#ef4444' : '#f59e0b'; ?>; 
                             color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                  <?php echo formatHours($doc['hours_pending']); ?>
                </span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Overdue Bookings -->
        <?php if (!empty($overdueBookings)): ?>
        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
          <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; font-weight: bold;">
            <i class="bi bi-calendar"></i> Overdue Appointments
          </div>
          <div style="padding: 15px; max-height: 350px; overflow-y: auto;">
            <?php foreach ($overdueBookings as $booking): ?>
            <div style="border-left: 3px solid <?php echo $booking['sla_status'] === 'critical' ? '#ef4444' : '#f59e0b'; ?>; 
                        background: <?php echo $booking['sla_status'] === 'critical' ? '#fef2f2' : '#fffbeb'; ?>; 
                        padding: 12px; margin-bottom: 10px; border-radius: 4px;">
              <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                  <strong style="font-size: 14px;"><?php echo htmlspecialchars($booking['booking_type']); ?></strong><br>
                  <small style="color: #6b7280;"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></small>
                </div>
                <span style="background: <?php echo $booking['sla_status'] === 'critical' ? '#ef4444' : '#f59e0b'; ?>; 
                             color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                  <?php echo formatHours($booking['hours_pending']); ?>
                </span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Overdue Payments -->
        <?php if (!empty($overduePayments)): ?>
        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
          <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; font-weight: bold;">
            <i class="bi bi-wallet2"></i> Overdue Payments
          </div>
          <div style="padding: 15px; max-height: 350px; overflow-y: auto;">
            <?php foreach ($overduePayments as $payment): ?>
            <div style="border-left: 3px solid <?php echo $payment['sla_status'] === 'critical' ? '#ef4444' : '#f59e0b'; ?>; 
                        background: <?php echo $payment['sla_status'] === 'critical' ? '#fef2f2' : '#fffbeb'; ?>; 
                        padding: 12px; margin-bottom: 10px; border-radius: 4px;">
              <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                  <strong style="font-size: 14px;">₱<?php echo number_format($payment['amount'], 2); ?></strong><br>
                  <small style="color: #6b7280;"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></small>
                </div>
                <span style="background: <?php echo $payment['sla_status'] === 'critical' ? '#ef4444' : '#f59e0b'; ?>; 
                             color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                  <?php echo formatHours($payment['hours_pending']); ?>
                </span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div class="two-column-grid">
        <!-- Recent Documents -->
        <div class="section-box">
          <div class="section-header">
            <i class="bi bi-file-earmark"></i> RECENT DOCUMENT REQUESTS
          </div>
          <div class="section-content">
            <?php if (count($recentDocs) > 0): ?>
              <?php foreach ($recentDocs as $doc): ?>
                <div class="list-item">
                  <div class="list-item-content">
                    <div class="list-item-title"><?php echo htmlspecialchars($doc['client_name']); ?></div>
                    <div class="list-item-meta">
                      <?php echo htmlspecialchars($doc['document_type']); ?> | 
                      <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                    </div>
                  </div>
                  <span class="list-item-status status-<?php echo $doc['status']; ?>">
                    <?php echo ucfirst($doc['status']); ?>
                  </span>
                </div>
              <?php endforeach; ?>
              <div style="margin-top: 15px; text-align: center;">
                <a href="/documentSystem/admin/manage-documents.php" style="color: #3d4f5c; font-weight: bold; text-decoration: none;">
                  View All →
                </a>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No recent requests</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Appointments -->
        <div class="section-box">
          <div class="section-header">
            <i class="bi bi-calendar"></i> RECENT APPOINTMENTS
          </div>
          <div class="section-content">
            <?php if (count($recentApts) > 0): ?>
              <?php foreach ($recentApts as $apt): ?>
                <div class="list-item">
                  <div class="list-item-content">
                    <div class="list-item-title"><?php echo htmlspecialchars($apt['client_name']); ?></div>
                    <div class="list-item-meta">
                      <?php echo htmlspecialchars($apt['booking_type']); ?> | 
                      <?php echo date('M d, Y g:i A', strtotime($apt['appointment_date'])); ?>
                    </div>
                  </div>
                  <span class="list-item-status status-<?php echo $apt['status']; ?>">
                    <?php echo ucfirst($apt['status']); ?>
                  </span>
                </div>
              <?php endforeach; ?>
              <div style="margin-top: 15px; text-align: center;">
                <a href="/documentSystem/admin/manage-appointments.php" style="color: #3d4f5c; font-weight: bold; text-decoration: none;">
                  View All →
                </a>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No recent appointments</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Admin Info Box -->
      <div class="section-box" style="margin-top: 30px;">
        <div class="section-header">ADMIN INFORMATION</div>
        <div class="section-content">
          <div class="info-grid">
            <div class="info-label">Admin Name:</div>
            <div class="info-value"><?php echo htmlspecialchars($userName); ?></div>

            <div class="info-label">Email:</div>
            <div class="info-value"><?php echo htmlspecialchars($userEmail); ?></div>

            <div class="info-label">Role:</div>
            <div class="info-value"><span style="background: #d1ecf1; color: #0c5460; padding: 2px 8px; border-radius: 3px; font-size: 12px; text-transform: uppercase; font-weight: bold;"><?php echo ucfirst($userRole); ?></span></div>

            <div class="info-label">Current Time:</div>
            <div class="info-value" id="currentTime"><?php echo date('F d, Y g:i A'); ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-contact">
          <strong>FOR INQUIRIES AND CONCERN:</strong><br>
          HOTLINE: 0999 MAYNAY<br>
          EMAIL: maequinas@gmail.com
        </div>
        
        <div class="footer-links">
          <a href="#home">HOME</a>
          <a href="#about">ABOUT US</a>
          <a href="#terms">TERMS OF SERVICE</a>
          <a href="#privacy">PRIVACY POLICY</a>
        </div>
        
        <div class="footer-social">
          <div class="footer-social-title">FOLLOW US ON:</div>
          <div class="social-icons">
            <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
            <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
          </div>
        </div>
      </div>
      
      <div class="footer-bottom">
        Parish Name Copyright © <?php echo date('Y'); ?> | Admin Panel v1.0
      </div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/documentSystem/assets/js/common.js"></script>
  <script>
    // Update time every second
    setInterval(() => {
      const now = new Date();
      const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
      document.getElementById('currentTime').textContent = now.toLocaleDateString(undefined, options);
    }, 1000);
  </script>
</body>
</html>
