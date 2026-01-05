<?php
/**
 * Client Dashboard - Home
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

// Require login
if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

// Get user info
$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = getCurrentUserEmail();
$userRole = getCurrentUserRole();

// Generate user initials
$nameParts = explode(' ', $userName);
$userInitials = strtoupper(
    substr($nameParts[0], 0, 1) . 
    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
);

// Redirect admin/staff to admin dashboard
if ($userRole === 'admin' || $userRole === 'staff') {
    header('Location: /documentSystem/admin/dashboard.php');
    exit;
}

// Get database connection
$conn = getDBConnection();

// Get user details
$userQuery = "SELECT email_verified, phone, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$userResult = $stmt->get_result();
$userDetails = $userResult->fetch_assoc();
$emailVerified = (bool)$userDetails['email_verified'];
$userPhone = $userDetails['phone'] ?? 'Not provided';
$userJoinDate = $userDetails['created_at'];

// Get upcoming appointments (next 5)
$appointmentsQuery = "
    SELECT b.id, b.reference_number, b.booking_date, b.booking_time, b.status, bt.name 
    FROM bookings b
    JOIN booking_types bt ON b.booking_type_id = bt.id
    WHERE b.user_id = ? AND b.booking_date >= CURDATE() 
    ORDER BY b.booking_date ASC, b.booking_time ASC
    LIMIT 5
";
$stmt = $conn->prepare($appointmentsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$appointmentsResult = $stmt->get_result();
$appointments = $appointmentsResult->fetch_all(MYSQLI_ASSOC);

// Get pending document requests
$documentRequestsQuery = "
    SELECT dr.id, dr.reference_number, dr.status, dr.payment_status, dt.name, dr.created_at
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    WHERE dr.user_id = ? AND dr.status IN ('pending', 'processing')
    ORDER BY dr.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($documentRequestsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$documentRequestsResult = $stmt->get_result();
$documentRequests = $documentRequestsResult->fetch_all(MYSQLI_ASSOC);

// Get documents ready for pickup
$readyDocumentsQuery = "
    SELECT dr.id, dr.reference_number, dt.name, dr.updated_at
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    WHERE dr.user_id = ? AND dr.status = 'ready'
    ORDER BY dr.updated_at DESC
";
$stmt = $conn->prepare($readyDocumentsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$readyDocumentsResult = $stmt->get_result();
$readyDocuments = $readyDocumentsResult->fetch_all(MYSQLI_ASSOC);

// Get payment history
$paymentsQuery = "
    SELECT p.id, p.transaction_number, p.amount, p.payment_method, p.status, p.created_at, 
           p.reference_type, COALESCE(dr.reference_number, b.reference_number) as ref_number
    FROM payments p
    LEFT JOIN document_requests dr ON p.reference_type = 'document_request' AND p.reference_id = dr.id
    LEFT JOIN bookings b ON p.reference_type = 'booking' AND p.reference_id = b.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($paymentsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$paymentsResult = $stmt->get_result();
$payments = $paymentsResult->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$notificationsQuery = "
    SELECT id, title, message, type, created_at, link
    FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($notificationsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notificationsResult = $stmt->get_result();
$notifications = $notificationsResult->fetch_all(MYSQLI_ASSOC);

// Get dashboard statistics
$stats = array();

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

// Count pending appointments
$countQuery = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status IN ('pending', 'approved') AND booking_date >= CURDATE()";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['pending_appointments'] = $stmt->get_result()->fetch_assoc()['count'];

// Count pending documents
$countQuery = "SELECT COUNT(*) as count FROM document_requests WHERE user_id = ? AND status IN ('pending', 'processing')";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['pending_documents'] = $stmt->get_result()->fetch_assoc()['count'];

// Count ready documents
$countQuery = "SELECT COUNT(*) as count FROM document_requests WHERE user_id = ? AND status = 'ready'";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['ready_documents'] = $stmt->get_result()->fetch_assoc()['count'];

// Count unread notifications
$stats['unread_notifications'] = $notificationsResult->num_rows;

// Check if parish office is open (9 AM - 5 PM, Monday-Saturday)
$currentHour = (int)date('G');
$currentDay = date('N'); // 1 (Monday) to 7 (Sunday)
$isOpen = ($currentDay >= 1 && $currentDay <= 6) && ($currentHour >= 9 && $currentHour < 17);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
  <link href="/documentSystem/assets/css/dashboard.css" rel="stylesheet">
</head>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-circles">
        <?php if ($activeLogo): ?>
          <img src="/documentSystem/<?php echo htmlspecialchars($activeLogo['file_path']); ?>" 
               alt="<?php echo htmlspecialchars($activeLogo['alt_text'] ?: $activeLogo['name']); ?>" 
               style="max-width: 45px; max-height: 45px; object-fit: contain;">
        <?php else: ?>
          <div class="logo-circle">PC</div>
        <?php endif; ?>
      </div>
      <div class="system-title">
        Parish Ease: An Interactive<br>
        Document Request and<br>
        Appointment System
      </div>
    </div>

    <!-- User Profile Card -->
    <div class="user-profile-card">
      <div class="user-avatar"><?php echo $userInitials; ?></div>
      <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
      <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
    </div>

    <ul class="nav-menu">
      <li class="nav-item">
        <a href="/documentSystem/client/dashboard.php" class="nav-link active">
          <i class="bi bi-house-door-fill"></i>
          <span>Home</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/view-documents.php" class="nav-link">
          <i class="bi bi-file-earmark-check"></i>
          <span>View Documents</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/view-appointments.php" class="nav-link">
          <i class="bi bi-calendar-check"></i>
          <span>View Appointments</span>
          <?php if ($stats['pending_appointments'] > 0): ?>
            <span class="nav-badge"><?php echo $stats['pending_appointments']; ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/new-appointment.php" class="nav-link">
          <i class="bi bi-calendar-plus"></i>
          <span>New Appointment</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/request-documents.php" class="nav-link">
          <i class="bi bi-file-earmark-text"></i>
          <span>Request Document</span>
        </a>
      </li>
      
      <div class="nav-separator"></div>
      
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
      <h1 class="page-title">DASHBOARD</h1>
      <div class="title-underline"></div>

      <!-- Notifications Section -->
      <?php if (count($notifications) > 0): ?>
        <div style="margin-bottom: 30px;">
          <?php foreach ($notifications as $notif): ?>
            <div class="notification-alert <?php echo htmlspecialchars($notif['type']); ?>">
              <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
              <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($notif['message']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Email Verification Status -->
      <div class="verification-status <?php echo $emailVerified ? 'verified' : ''; ?>">
        <div style="flex: 1;">
          <strong><?php echo $emailVerified ? '✓ Email Verified' : '⚠ Email Not Verified'; ?></strong>
          <?php if (!$emailVerified): ?>
            <p style="margin: 5px 0 0 0; font-size: 12px;">Please verify your email to unlock all features.</p>
          <?php endif; ?>
        </div>
        <?php if (!$emailVerified): ?>
          <a href="/documentSystem/client/verify-email.php" style="color: #856404; font-weight: bold; text-decoration: none;">Verify Now →</a>
        <?php endif; ?>
      </div>

      <!-- Welcome Box -->
      <div class="greeting-box">
        <div class="greeting-text">Welcome, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>!</div>
        <div class="office-status">
          THE PARISH OFFICE IS NOW: 
          <span class="<?php echo $isOpen ? 'status-open' : 'status-closed'; ?>">
            <?php echo $isOpen ? 'OPEN' : 'CLOSED'; ?>
          </span>
        </div>
      </div>

      <!-- Dashboard Statistics -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
          <div class="stat-label">Upcoming Appointments</div>
          <div class="stat-number"><?php echo $stats['pending_appointments']; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
          <div class="stat-label">Pending Documents</div>
          <div class="stat-number"><?php echo $stats['pending_documents']; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-file-check"></i></div>
          <div class="stat-label">Ready for Pickup</div>
          <div class="stat-number"><?php echo $stats['ready_documents']; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon"><i class="bi bi-bell"></i></div>
          <div class="stat-label">Unread Messages</div>
          <div class="stat-number"><?php echo $stats['unread_notifications']; ?></div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div style="margin-bottom: 30px;">
        <h3 style="font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; color: #000;">Quick Actions</h3>
        <div class="quick-actions">
          <a href="/documentSystem/client/new-appointment.php" class="action-btn">
            <i class="bi bi-calendar-plus"></i> New Appointment
          </a>
          <a href="/documentSystem/client/request-documents.php" class="action-btn">
            <i class="bi bi-file-earmark-text"></i> Request Document
          </a>
          <a href="/documentSystem/client/view-appointments.php" class="action-btn">
            <i class="bi bi-calendar2-check"></i> View Bookings
          </a>
          <a href="/documentSystem/client/change-password.php" class="action-btn">
            <i class="bi bi-key-fill"></i> Security
          </a>
        </div>
      </div>

      <!-- Two Column Section -->
      <div class="two-column-grid">
        <!-- Upcoming Appointments -->
        <div class="section-box">
          <div class="section-header">UPCOMING APPOINTMENTS</div>
          <div class="section-content">
            <?php if (count($appointments) > 0): ?>
              <div style="padding: 0;">
                <?php foreach ($appointments as $apt): ?>
                  <div class="list-item">
                    <div class="list-item-content">
                      <div class="list-item-title"><?php echo htmlspecialchars($apt['name']); ?></div>
                      <div class="list-item-meta">
                        <?php echo date('M d, Y', strtotime($apt['booking_date'])); ?> at 
                        <?php echo date('g:i A', strtotime($apt['booking_time'])); ?> 
                        | <?php echo htmlspecialchars($apt['reference_number']); ?>
                      </div>
                    </div>
                    <span class="list-item-status status-<?php echo htmlspecialchars($apt['status']); ?>">
                      <?php echo htmlspecialchars($apt['status']); ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>No upcoming appointments</p>
                <a href="/documentSystem/client/new-appointment.php" style="color: #3d4f5c; font-weight: bold;">Schedule one now →</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Pending Document Requests -->
        <div class="section-box">
          <div class="section-header">PENDING DOCUMENTS</div>
          <div class="section-content">
            <?php if (count($documentRequests) > 0): ?>
              <div style="padding: 0;">
                <?php foreach ($documentRequests as $doc): ?>
                  <div class="list-item">
                    <div class="list-item-content">
                      <div class="list-item-title"><?php echo htmlspecialchars($doc['name']); ?></div>
                      <div class="list-item-meta">
                        <?php echo htmlspecialchars($doc['reference_number']); ?> | 
                        Requested: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                      </div>
                    </div>
                    <span class="list-item-status status-<?php echo htmlspecialchars($doc['status']); ?>">
                      <?php echo htmlspecialchars($doc['status']); ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="bi bi-file-earmark"></i>
                <p>No pending requests</p>
                <a href="/documentSystem/client/request-documents.php" style="color: #3d4f5c; font-weight: bold;">Request a document →</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Ready for Pickup Documents -->
      <?php if (count($readyDocuments) > 0): ?>
        <div class="section-box">
          <div class="section-header"><i class="bi bi-file-check" style="margin-right: 10px;"></i>DOCUMENTS READY FOR PICKUP</div>
          <div class="section-content">
            <div style="padding: 0;">
              <?php foreach ($readyDocuments as $ready): ?>
                <div class="list-item" style="background: #e8f5e9; padding: 15px; margin-bottom: 10px; border-radius: 4px;">
                  <div class="list-item-content">
                    <div class="list-item-title" style="color: #2e7d32;"><?php echo htmlspecialchars($ready['name']); ?></div>
                    <div class="list-item-meta">
                      <?php echo htmlspecialchars($ready['reference_number']); ?> | 
                      Ready since: <?php echo date('M d, Y', strtotime($ready['updated_at'])); ?>
                    </div>
                  </div>
                  <span class="list-item-status status-ready">Ready</span>
                </div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
              <strong>Pickup Instructions:</strong>
              <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                Please visit the Parish Office during office hours (Mon-Sat, 9 AM - 5 PM) with a valid ID to collect your documents.
              </p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Account Information -->
      <div class="section-box">
        <div class="section-header">ACCOUNT INFORMATION</div>
        <div class="section-content">
          <div class="info-grid">
            <div class="info-label">Email:</div>
            <div class="info-value"><?php echo htmlspecialchars($userEmail); ?> <span style="color: #666;"><?php echo $emailVerified ? '✓' : '(Unverified)'; ?></span></div>

            <div class="info-label">Phone:</div>
            <div class="info-value"><?php echo htmlspecialchars($userPhone); ?></div>

            <div class="info-label">Account Status:</div>
            <div class="info-value"><span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Active</span></div>

            <div class="info-label">Member Since:</div>
            <div class="info-value"><?php echo date('F d, Y', strtotime($userJoinDate)); ?></div>
          </div>
          <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="/documentSystem/client/change-password.php" style="color: #3d4f5c; font-weight: bold; text-decoration: none;">
              Change Password →
            </a>
          </div>
        </div>
      </div>

      <!-- Payment History -->
      <?php if (count($payments) > 0): ?>
        <div class="section-box">
          <div class="section-header">RECENT PAYMENTS</div>
          <div class="section-content">
            <div style="padding: 0;">
              <?php foreach ($payments as $payment): ?>
                <div class="list-item">
                  <div class="list-item-content">
                    <div class="list-item-title">
                      ₱<?php echo number_format($payment['amount'], 2); ?> - 
                      <?php echo ucfirst($payment['reference_type']); ?>
                    </div>
                    <div class="list-item-meta">
                      Transaction: <?php echo htmlspecialchars($payment['transaction_number']); ?> | 
                      <?php echo date('M d, Y', strtotime($payment['created_at'])); ?>
                    </div>
                  </div>
                  <span class="list-item-status status-<?php echo htmlspecialchars($payment['status']); ?>">
                    <?php echo htmlspecialchars($payment['status']); ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Help & Support -->
      <div class="section-box">
        <div class="section-header">HELP & SUPPORT</div>
        <div class="section-content">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
              <h4 style="margin-bottom: 10px; font-weight: bold; color: #000;">Quick Links</h4>
              <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="margin-bottom: 8px;"><a href="/documentSystem/client/view-appointments.php" style="color: #3d4f5c; text-decoration: none;">→ View All Appointments</a></li>
                <li style="margin-bottom: 8px;"><a href="/documentSystem/client/request-documents.php" style="color: #3d4f5c; text-decoration: none;">→ Request New Document</a></li>
                <li style="margin-bottom: 8px;"><a href="/documentSystem/client/change-password.php" style="color: #3d4f5c; text-decoration: none;">→ Change Password</a></li>
              </ul>
            </div>
            <div>
              <h4 style="margin-bottom: 10px; font-weight: bold; color: #000;">Contact Us</h4>
              <p style="margin: 0 0 8px 0; color: #666; font-size: 13px;">
                <strong>Office Hours:</strong><br>
                Monday - Saturday, 9:00 AM - 5:00 PM
              </p>
              <p style="margin: 8px 0; color: #666; font-size: 13px;">
                <strong>Hotline:</strong> 0999 MAYNAY
              </p>
              <p style="margin: 8px 0; color: #666; font-size: 13px;">
                <strong>Email:</strong> maequinas@gmail.com
              </p>
            </div>
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
        Parish Name Copyright © <?php echo date('Y'); ?>
      </div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/documentSystem/assets/js/common.js"></script>
  <script src="/documentSystem/assets/js/dashboard.js"></script>
</body>
</html>
<?php
// Close database connection
closeDBConnection($conn);
?>
