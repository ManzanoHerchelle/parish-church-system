<?php
/**
 * Client - View Appointments
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = getCurrentUserEmail();

$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $bookingId = $_POST['booking_id'] ?? null;
    
    if ($action === 'cancel' && $bookingId) {
        $cancellationReason = $_POST['cancellation_reason'] ?? '';
        
        // Verify booking belongs to user
        $verifyQuery = "SELECT status FROM bookings WHERE id = ? AND user_id = ?";
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->bind_param("ii", $bookingId, $userId);
        $verifyStmt->execute();
        $booking = $verifyStmt->get_result()->fetch_assoc();
        $verifyStmt->close();
        
        if ($booking) {
            if ($booking['status'] === 'completed') {
                $errorMsg = 'Cannot cancel a completed booking.';
            } elseif ($booking['status'] === 'cancelled') {
                $errorMsg = 'This booking is already cancelled.';
            } else {
                $updateQuery = "UPDATE bookings SET status = 'cancelled', cancellation_reason = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("si", $cancellationReason, $bookingId);
                
                if ($updateStmt->execute()) {
                    $successMsg = 'Booking cancelled successfully.';
                    $updateStmt->close();
                } else {
                    $errorMsg = 'Error cancelling booking. Please try again.';
                }
            }
        } else {
            $errorMsg = 'Booking not found.';
        }
    }
}

// Get all bookings for user
$bookingsQuery = "
    SELECT b.id, b.reference_number, b.booking_date, b.booking_time, b.status, b.payment_status, b.payment_amount, b.purpose, b.special_requests, b.cancellation_reason, bt.name as booking_type, bt.fee
    FROM bookings b
    JOIN booking_types bt ON b.booking_type_id = bt.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC
";

$stmt = $conn->prepare($bookingsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$bookingsResult = $stmt->get_result();
$bookings = $bookingsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Separate upcoming and past bookings
$upcomingBookings = array_filter($bookings, function($b) {
    return strtotime($b['booking_date']) >= strtotime('today') && $b['status'] !== 'cancelled';
});

$pastBookings = array_filter($bookings, function($b) {
    return strtotime($b['booking_date']) < strtotime('today') || $b['status'] === 'cancelled';
});

// Get user initials
$nameParts = explode(' ', $userName);
$userInitials = strtoupper(
    substr($nameParts[0], 0, 1) . 
    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
);

function getStatusBadge($status) {
    $colors = [
        'pending' => ['bg' => '#fff3cd', 'text' => '#856404', 'icon' => 'hourglass-split'],
        'approved' => ['bg' => '#d4edda', 'text' => '#155724', 'icon' => 'check-circle'],
        'rejected' => ['bg' => '#f8d7da', 'text' => '#721c24', 'icon' => 'x-circle'],
        'completed' => ['bg' => '#d4edda', 'text' => '#155724', 'icon' => 'check-circle-fill'],
        'cancelled' => ['bg' => '#e2e3e5', 'text' => '#383d41', 'icon' => 'dash-circle']
    ];
    
    $color = $colors[$status] ?? ['bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => 'info-circle'];
    return [
        'badge' => '<span style="background: ' . $color['bg'] . '; color: ' . $color['text'] . '; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase;">' . 
                   '<i class="bi bi-' . $color['icon'] . '" style="margin-right: 4px;"></i>' . ucfirst($status) . '</span>',
        'color' => $color['text']
    ];
}

function getPaymentStatusBadge($status) {
    $colors = [
        'unpaid' => ['bg' => '#f8d7da', 'text' => '#721c24'],
        'pending' => ['bg' => '#fff3cd', 'text' => '#856404'],
        'paid' => ['bg' => '#d4edda', 'text' => '#155724']
    ];
    
    $color = $colors[$status] ?? ['bg' => '#d1ecf1', 'text' => '#0c5460'];
    return '<span style="background: ' . $color['bg'] . '; color: ' . $color['text'] . '; padding: 3px 10px; border-radius: 15px; font-size: 10px; font-weight: bold;">' . ucfirst($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Appointments - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
  <link href="/documentSystem/assets/css/appointments.css" rel="stylesheet">
  <style>
    /* Fix modal z-index to ensure it appears above backdrop */
    .modal {
      z-index: 1060 !important;
    }
    .modal-backdrop {
      z-index: 1050 !important;
    }
    .modal-dialog {
      z-index: 1061 !important;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-circles">
        <div class="logo-circle">PC</div>
      </div>
      <div class="system-title">
        Parish Ease: An Interactive<br>
        Document Request and<br>
        Appointment System
      </div>
    </div>

    <div class="user-profile-card">
      <div class="user-avatar"><?php echo $userInitials; ?></div>
      <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
      <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
    </div>

    <ul class="nav-menu">
      <li class="nav-item">
        <a href="/documentSystem/client/dashboard.php" class="nav-link">
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
        <a href="/documentSystem/client/view-appointments.php" class="nav-link active">
          <i class="bi bi-calendar-check"></i>
          <span>View Appointments</span>
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
      <h1 class="page-title">MY APPOINTMENTS</h1>
      <div class="title-underline"></div>

      <?php if ($successMsg): ?>
        <div class="alert alert-success">
          <strong>Success:</strong> <?php echo htmlspecialchars($successMsg); ?>
        </div>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
        <div class="alert alert-danger">
          <strong>Error:</strong> <?php echo htmlspecialchars($errorMsg); ?>
        </div>
      <?php endif; ?>

      <!-- Upcoming Appointments -->
      <div class="section-box">
        <div class="section-header"><i class="bi bi-calendar-event"></i> UPCOMING APPOINTMENTS</div>
        <div class="section-content">
          <?php if (count($upcomingBookings) > 0): ?>
            <?php foreach ($upcomingBookings as $booking): ?>
              <div class="booking-card">
                <div class="booking-header">
                  <div>
                    <div class="booking-title"><?php echo htmlspecialchars($booking['booking_type']); ?></div>
                    <div class="booking-ref">Reference: <?php echo htmlspecialchars($booking['reference_number']); ?></div>
                  </div>
                  <div><?php echo getStatusBadge($booking['status'])['badge']; ?></div>
                </div>

                <div class="booking-details">
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-calendar2"></i> Date</div>
                    <div class="detail-value"><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-clock"></i> Time</div>
                    <div class="detail-value"><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-tag"></i> Fee</div>
                    <div class="detail-value">₱<?php echo number_format($booking['payment_amount'] ?? $booking['fee'], 2); ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-credit-card"></i> Payment</div>
                    <div class="detail-value"><?php echo getPaymentStatusBadge($booking['payment_status']); ?></div>
                  </div>
                </div>

                <div>
                  <strong style="color: #666;">Purpose/Details:</strong><br>
                  <p style="margin: 8px 0; color: #555; font-size: 13px;"><?php echo nl2br(htmlspecialchars($booking['purpose'])); ?></p>
                  <?php if (!empty($booking['special_requests'])): ?>
                    <strong style="color: #666;">Special Requests:</strong><br>
                    <p style="margin: 8px 0; color: #555; font-size: 13px;"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                  <?php endif; ?>
                </div>

                <?php if ($booking['status'] !== 'completed' && $booking['status'] !== 'cancelled'): ?>
                  <div class="booking-actions" style="margin-top: 15px;">
                    <?php if ($booking['payment_status'] === 'unpaid'): ?>
                      <button class="btn-small" style="background: #28a745; color: white;" onclick="openAppointmentPaymentModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['reference_number']); ?>', <?php echo $booking['payment_amount'] ?? $booking['fee']; ?>)">
                        <i class="bi bi-credit-card"></i> Make Payment
                      </button>
                    <?php endif; ?>
                    <button class="btn-small btn-cancel" onclick="openCancelModal(<?php echo $booking['id']; ?>)">
                      <i class="bi bi-x-circle"></i> Cancel Booking
                    </button>
                    <?php if ($booking['status'] === 'approved'): ?>
                      <button class="btn-small btn-reschedule" onclick="alert('Reschedule feature coming soon. Please contact the parish office.')">
                        <i class="bi bi-arrow-repeat"></i> Reschedule
                      </button>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state">
              <i class="bi bi-calendar-x"></i>
              <p>No upcoming appointments</p>
              <a href="/documentSystem/client/new-appointment.php" style="color: #3d4f5c; font-weight: bold; text-decoration: none;">Book an appointment →</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Past/Cancelled Appointments -->
      <?php if (count($pastBookings) > 0): ?>
        <div class="section-box">
          <div class="section-header"><i class="bi bi-calendar-check"></i> PAST & CANCELLED APPOINTMENTS</div>
          <div class="section-content">
            <?php foreach ($pastBookings as $booking): ?>
              <div class="booking-card <?php echo $booking['status'] === 'cancelled' ? 'cancelled' : ''; ?>">
                <div class="booking-header">
                  <div>
                    <div class="booking-title"><?php echo htmlspecialchars($booking['booking_type']); ?></div>
                    <div class="booking-ref">Reference: <?php echo htmlspecialchars($booking['reference_number']); ?></div>
                  </div>
                  <div><?php echo getStatusBadge($booking['status'])['badge']; ?></div>
                </div>

                <div class="booking-details">
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-calendar2"></i> Date</div>
                    <div class="detail-value"><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-clock"></i> Time</div>
                    <div class="detail-value"><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-tag"></i> Fee</div>
                    <div class="detail-value">₱<?php echo number_format($booking['payment_amount'] ?? $booking['fee'], 2); ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><i class="bi bi-credit-card"></i> Payment</div>
                    <div class="detail-value"><?php echo getPaymentStatusBadge($booking['payment_status']); ?></div>
                  </div>
                </div>

                <?php if ($booking['status'] === 'cancelled' && !empty($booking['cancellation_reason'])): ?>
                  <div style="margin-top: 15px; padding: 10px; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 3px;">
                    <strong style="color: #721c24;">Cancellation Reason:</strong><br>
                    <p style="margin: 5px 0; color: #721c24; font-size: 13px;"><?php echo nl2br(htmlspecialchars($booking['cancellation_reason'])); ?></p>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <strong>FOR INQUIRIES:</strong><br>
        HOTLINE: 0999 MAYNAY<br>
        EMAIL: maequinas@gmail.com
      </div>
      <div class="footer-bottom">
        Parish Church © <?php echo date('Y'); ?>
      </div>
    </footer>
  </div>

  <!-- Cancel Modal -->
  <div id="cancelModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">Cancel Booking</div>
      <form method="POST">
        <div class="modal-body">
          <p style="color: #666; margin-bottom: 15px;">Are you sure you want to cancel this booking?</p>
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="booking_id" id="cancel_booking_id">
          <div class="form-group">
            <label class="form-label">Reason for Cancellation (Optional)</label>
            <textarea name="cancellation_reason" class="form-control" rows="3" placeholder="Please tell us why you're cancelling..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeCancelModal()">Keep Booking</button>
          <button type="submit" class="btn-primary">Cancel Booking</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Appointment Payment Modal -->
  <div class="modal fade" id="appointmentPaymentModal" tabindex="-1" aria-labelledby="appointmentPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="appointmentPaymentModalLabel">
            <i class="bi bi-credit-card"></i> Submit Appointment Payment
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="/documentSystem/client/upload-appointment-payment.php" method="POST" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="alert alert-info">
              <strong><i class="bi bi-info-circle"></i> Appointment Payment</strong><br>
              <small>Upload your payment proof after paying through your preferred method.</small>
            </div>

            <input type="hidden" name="booking_id" id="appointment_booking_id">
            
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-hash"></i> Reference Number</label>
              <input type="text" class="form-control" id="appointment_reference" readonly>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="bi bi-cash"></i> Amount to Pay</label>
              <input type="text" class="form-control" id="appointment_amount" readonly>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="bi bi-wallet2"></i> Payment Method *</label>
              <select name="payment_method" id="appointment_payment_method" class="form-control" required onchange="showAppointmentPaymentDetails()">
                <option value="">-- Select Payment Method --</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">PayMaya</option>
                <option value="over_counter">Pay at Office (Cash)</option>
              </select>
            </div>

            <!-- Payment Details for Bank Transfer -->
            <div class="alert alert-warning" id="appointmentBankDetails" style="display: none;">
              <strong><i class="bi bi-bank"></i> Bank Transfer Details:</strong><br>
              <strong>Bank:</strong> BDO<br>
              <strong>Account Number:</strong> 1234567890<br>
              <strong>Account Name:</strong> Parish Church<br>
              <small class="text-muted">Please save your transaction receipt.</small>
            </div>

            <!-- Payment Details for GCash -->
            <div class="alert alert-success" id="appointmentGcashDetails" style="display: none;">
              <strong><i class="bi bi-phone"></i> GCash Payment Details:</strong><br>
              <strong>Mobile Number:</strong> 0999-123-4567<br>
              <strong>Account Name:</strong> Parish Church<br>
              <small class="text-muted">Please save your transaction receipt.</small>
            </div>

            <!-- Payment Details for PayMaya -->
            <div class="alert alert-info" id="appointmentPaymayaDetails" style="display: none;">
              <strong><i class="bi bi-phone"></i> PayMaya Payment Details:</strong><br>
              <strong>Mobile Number:</strong> 0999-765-4321<br>
              <strong>Account Name:</strong> Parish Church<br>
              <small class="text-muted">Please save your transaction receipt.</small>
            </div>

            <!-- Payment Details for Over Counter -->
            <div class="alert alert-secondary" id="appointmentCounterDetails" style="display: none;">
              <strong><i class="bi bi-building"></i> Pay at Parish Office:</strong><br>
              Visit our office during business hours (Mon-Fri, 8 AM - 5 PM) to pay in cash.<br>
              <small class="text-muted">Please bring this reference number when paying.</small>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="bi bi-receipt"></i> Transaction Reference Number <span id="refRequired" style="color: red;">*</span></label>
              <input type="text" name="transaction_number" id="appointment_transaction_number" class="form-control" placeholder="Enter the reference number from your payment">
              <small class="text-muted" id="refNote">This is the reference/transaction number from your bank/GCash/PayMaya receipt</small>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="bi bi-file-earmark-image"></i> Upload Payment Proof <span id="proofRequired" style="color: red;">*</span></label>
              <input type="file" name="payment_proof" id="appointment_proof_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" disabled style="background-color: #f0f0f0;">
              <small class="text-muted" id="proofNote" style="display: block; margin-top: 5px;">⚠️ <strong>This field is only required for online payments (Bank/GCash/PayMaya).</strong> It will be enabled once you select an online payment method.</small>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="bi bi-chat-left-text"></i> Additional Notes (Optional)</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Any additional information about your payment..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-circle"></i> Cancel
            </button>
            <button type="submit" class="btn btn-success">
              <i class="bi bi-upload"></i> Submit Payment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/documentSystem/assets/js/common.js"></script>
  <script src="/documentSystem/assets/js/appointments.js"></script>
  
  <script>
    // Open appointment payment modal
    function openAppointmentPaymentModal(bookingId, referenceNumber, amount) {
      document.getElementById('appointment_booking_id').value = bookingId;
      document.getElementById('appointment_reference').value = referenceNumber;
      document.getElementById('appointment_amount').value = '₱' + parseFloat(amount).toFixed(2);
      
      // Reset form
      document.getElementById('appointment_payment_method').value = '';
      document.getElementById('appointment_proof_file').value = '';
      document.getElementById('appointment_proof_file').required = false;
      showAppointmentPaymentDetails();
      
      const modal = new bootstrap.Modal(document.getElementById('appointmentPaymentModal'));
      modal.show();
    }

    // Show payment details based on selected method
    function showAppointmentPaymentDetails() {
      const method = document.getElementById('appointment_payment_method').value;
      const proofFile = document.getElementById('appointment_proof_file');
      const proofRequired = document.getElementById('proofRequired');
      const proofNote = document.getElementById('proofNote');
      const transactionRef = document.getElementById('appointment_transaction_number');
      const refRequired = document.getElementById('refRequired');
      const refNote = document.getElementById('refNote');
      
      // Hide all payment details
      document.getElementById('appointmentBankDetails').style.display = 'none';
      document.getElementById('appointmentGcashDetails').style.display = 'none';
      document.getElementById('appointmentPaymayaDetails').style.display = 'none';
      document.getElementById('appointmentCounterDetails').style.display = 'none';
      
      // Show selected payment method details and set file upload requirement
      if (method === 'bank_transfer') {
        document.getElementById('appointmentBankDetails').style.display = 'block';
        proofFile.required = true;
        proofFile.disabled = false;
        proofFile.style.backgroundColor = '';
        proofRequired.style.display = 'inline';
        proofNote.innerHTML = '📄 Upload a screenshot or photo of your payment receipt (JPG, PNG, or PDF, max 5MB)';
        transactionRef.required = true;
        transactionRef.disabled = false;
        transactionRef.style.backgroundColor = '';
        refRequired.style.display = 'inline';
        refNote.innerHTML = 'Enter the reference/transaction number from your bank receipt';
      } else if (method === 'gcash') {
        document.getElementById('appointmentGcashDetails').style.display = 'block';
        proofFile.required = true;
        proofFile.disabled = false;
        proofFile.style.backgroundColor = '';
        proofRequired.style.display = 'inline';
        proofNote.innerHTML = '📄 Upload a screenshot or photo of your payment receipt (JPG, PNG, or PDF, max 5MB)';
        transactionRef.required = true;
        transactionRef.disabled = false;
        transactionRef.style.backgroundColor = '';
        refRequired.style.display = 'inline';
        refNote.innerHTML = 'Enter the reference number from your GCash transaction';
      } else if (method === 'paymaya') {
        document.getElementById('appointmentPaymayaDetails').style.display = 'block';
        proofFile.required = true;
        proofFile.disabled = false;
        proofFile.style.backgroundColor = '';
        proofRequired.style.display = 'inline';
        proofNote.innerHTML = '📄 Upload a screenshot or photo of your payment receipt (JPG, PNG, or PDF, max 5MB)';
        transactionRef.required = true;
        transactionRef.disabled = false;
        transactionRef.style.backgroundColor = '';
        refRequired.style.display = 'inline';
        refNote.innerHTML = 'Enter the reference number from your PayMaya transaction';
      } else if (method === 'over_counter') {
        document.getElementById('appointmentCounterDetails').style.display = 'block';
        proofFile.required = false;
        proofFile.disabled = true;
        proofFile.style.backgroundColor = '#f0f0f0';
        proofFile.value = '';
        proofRequired.style.display = 'none';
        proofNote.innerHTML = '✓ <strong>No upload needed for cash payment.</strong> Simply provide your payment method and you\'re all set!';
        transactionRef.required = false;
        transactionRef.disabled = true;
        transactionRef.style.backgroundColor = '#f0f0f0';
        transactionRef.value = '';
        refRequired.style.display = 'none';
        refNote.innerHTML = '✓ No reference number needed for cash payment. The office will provide one when you pay.';
      }
    }
  </script>
</body>
</html>
<?php
closeDBConnection($conn);
?>
