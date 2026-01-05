<?php
/**
 * Client - New Appointment Booking
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../handlers/email_handler.php';

startSecureSession();

if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

$userId = getCurrentUserId();
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = getCurrentUserEmail();

$conn = getDBConnection();
$errorMsg = '';
$successMsg = '';
$formSubmitted = false;

// Return booked time slots for a given date (used by client-side to disable taken slots)
if (isset($_GET['action']) && $_GET['action'] === 'booked_slots') {
  $bookingDate = $_GET['date'] ?? '';
  header('Content-Type: application/json');
  if (!$bookingDate) {
    echo json_encode([]);
    exit;
  }
  $slotQuery = "SELECT booking_time FROM bookings WHERE booking_date = ? AND status IN ('pending', 'approved')";
  $slotStmt = $conn->prepare($slotQuery);
  $slotStmt->bind_param("s", $bookingDate);
  $slotStmt->execute();
  $result = $slotStmt->get_result();
  $slots = [];
  while ($row = $result->fetch_assoc()) {
    $slots[] = $row['booking_time'];
  }
  $slotStmt->close();
  echo json_encode($slots);
  exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingTypeId = $_POST['booking_type'] ?? null;
    $bookingDate = $_POST['booking_date'] ?? null;
    $bookingTime = $_POST['booking_time'] ?? null;
    $purpose = $_POST['purpose'] ?? '';
    $specialRequests = $_POST['special_requests'] ?? '';
    
    // Validate
    if (!$bookingTypeId) {
        $errorMsg = 'Please select a booking type.';
    } elseif (!$bookingDate) {
        $errorMsg = 'Please select a date.';
    } elseif (!$bookingTime) {
        $errorMsg = 'Please select a time.';
    } elseif (empty($purpose)) {
        $errorMsg = 'Please provide details about your request.';
    } else {
        // Validate date is in future
        $selectedDate = strtotime($bookingDate);
        if ($selectedDate < strtotime('today')) {
            $errorMsg = 'Please select a future date.';
        } else {
            // Check if date is blocked
            $blockedQuery = "SELECT COUNT(*) as count FROM blocked_dates WHERE DATE(date) = ? AND is_full_day = 1";
            $blockStmt = $conn->prepare($blockedQuery);
            $blockStmt->bind_param("s", $bookingDate);
            $blockStmt->execute();
            $isBlocked = $blockStmt->get_result()->fetch_assoc()['count'] > 0;
            $blockStmt->close();
            
            if ($isBlocked) {
                $errorMsg = 'This date is not available for bookings.';
            } else {
                // Check if time slot is already booked
                $slotQuery = "SELECT COUNT(*) as count FROM bookings 
                             WHERE booking_date = ? 
                             AND booking_time = ? 
                             AND status IN ('pending', 'approved')";
                $slotStmt = $conn->prepare($slotQuery);
                $slotStmt->bind_param("ss", $bookingDate, $bookingTime);
                $slotStmt->execute();
                $isSlotBooked = $slotStmt->get_result()->fetch_assoc()['count'] > 0;
                $slotStmt->close();
                
                if ($isSlotBooked) {
                    $errorMsg = 'This date and time slot is already booked. Please select a different time.';
                } else {
                    // Generate reference number
                $refNumber = 'APT-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . time();
                
                // Get fee from booking type
                $feeQuery = "SELECT fee FROM booking_types WHERE id = ?";
                $feeStmt = $conn->prepare($feeQuery);
                $feeStmt->bind_param("i", $bookingTypeId);
                $feeStmt->execute();
                $fee = $feeStmt->get_result()->fetch_assoc()['fee'];
                $feeStmt->close();
                
                // Calculate end time (assume 1 hour duration for now, can be customized per booking type)
                $endTime = date('H:i:s', strtotime($bookingTime) + 3600);
                
                // Insert booking
                $insertQuery = "INSERT INTO bookings 
                    (user_id, booking_type_id, reference_number, booking_date, booking_time, end_time, purpose, special_requests, status, payment_status, payment_amount, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', ?, NOW())";
                
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("iissssssd", $userId, $bookingTypeId, $refNumber, $bookingDate, $bookingTime, $endTime, $purpose, $specialRequests, $fee);
                
                if ($stmt->execute()) {
                    // Get booking type name
                    $typeQuery = "SELECT name FROM booking_types WHERE id = ?";
                    $typeStmt = $conn->prepare($typeQuery);
                    $typeStmt->bind_param("i", $bookingTypeId);
                    $typeStmt->execute();
                    $bookingType = $typeStmt->get_result()->fetch_assoc()['name'];
                    $typeStmt->close();
                    
                    // Send confirmation email
                    $emailHandler = new EmailHandler();
                    $nameParts = explode(' ', $userName);
                    $firstName = $nameParts[0];
                    $emailHandler->sendBookingConfirmation($userId, $userEmail, $firstName, $refNumber, $bookingType, $bookingDate, $bookingTime);
                    
                    // Create notification
                    $notifQuery = "INSERT INTO notifications (user_id, title, message, type, created_at)
                        VALUES (?, ?, ?, ?, NOW())";
                    $notifStmt = $conn->prepare($notifQuery);
                    $notifTitle = "Booking Request Received";
                    $notifMsg = "Your $bookingType booking has been received. Reference: $refNumber";
                    $notifType = "info";
                    $notifStmt->bind_param("isss", $userId, $notifTitle, $notifMsg, $notifType);
                    $notifStmt->execute();
                    $notifStmt->close();
                    
                    $successMsg = "Appointment booked successfully! Reference Number: <strong>$refNumber</strong>";
                    $formSubmitted = true;
                    $stmt->close();
                } else {
                    $errorMsg = 'Error booking appointment. Please try again.';
                }
                }
            }
        }
    }
}

// Get all booking types
$bookingTypesQuery = "SELECT id, name, description, fee, duration_minutes, requires_approval FROM booking_types WHERE is_active = 1 ORDER BY name";
$bookingTypesResult = $conn->query($bookingTypesQuery);
$bookingTypes = $bookingTypesResult->fetch_all(MYSQLI_ASSOC);

// Get user initials
$nameParts = explode(' ', $userName);
$userInitials = strtoupper(
    substr($nameParts[0], 0, 1) . 
    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
);

// Get blocked dates for calendar
$blockedDatesQuery = "SELECT DATE(date) as blocked_date FROM blocked_dates WHERE is_full_day = 1 AND date >= CURDATE()";
$blockedDatesResult = $conn->query($blockedDatesQuery);
$blockedDates = $blockedDatesResult->fetch_all(MYSQLI_ASSOC);
$blockedDatesList = array_map(function($d) { return $d['blocked_date']; }, $blockedDates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Book Appointment - Parish Church System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/documentSystem/assets/css/common.css" rel="stylesheet">
  <link href="/documentSystem/assets/css/forms.css" rel="stylesheet">
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
        <a href="/documentSystem/client/view-appointments.php" class="nav-link">
          <i class="bi bi-calendar-check"></i>
          <span>View Appointments</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="/documentSystem/client/new-appointment.php" class="nav-link active">
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
      <h1 class="page-title">BOOK AN APPOINTMENT</h1>
      <div class="title-underline"></div>

      <?php if ($successMsg): ?>
        <div class="success-message">
          <div class="icon"><i class="bi bi-check-circle"></i></div>
          <h3>Appointment Booked Successfully!</h3>
          <p>Your appointment request has been received and is pending approval.</p>
          <div class="ref-number"><?php echo $successMsg; ?></div>
          <p style="margin-top: 15px; color: #666; font-size: 14px;">
            You will receive an email confirmation shortly. The parish office will review and approve your booking.
          </p>
          <a href="/documentSystem/client/dashboard.php" class="btn-primary" style="display: inline-block; margin-top: 15px;">← Back to Dashboard</a>
        </div>
      <?php else: ?>
        <div class="info-box">
          <strong><i class="bi bi-info-circle"></i> Office Hours:</strong> Monday - Saturday, 9:00 AM - 5:00 PM
        </div>

        <?php if ($errorMsg): ?>
          <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($errorMsg); ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="appointmentForm">
          <!-- Select Booking Type -->
          <div class="form-section">
            <div class="form-header">STEP 1: SELECT SERVICE TYPE</div>
            <div class="form-content">
              <div style="max-height: 500px; overflow-y: auto;">
                <?php foreach ($bookingTypes as $type): ?>
                  <div class="booking-type-card" onclick="selectBookingType(<?php echo $type['id']; ?>)">
                    <input type="radio" name="booking_type" value="<?php echo $type['id']; ?>" style="display: none;" id="type_<?php echo $type['id']; ?>">
                    
                    <div class="booking-type-name">
                      <i class="bi bi-check-circle" style="margin-right: 8px; display: none;" class="selection-icon"></i>
                      <?php echo htmlspecialchars($type['name']); ?>
                    </div>
                    
                    <div class="booking-type-details">
                      <?php echo htmlspecialchars($type['description']); ?>
                    </div>
                    
                    <div class="booking-type-fee">
                      Fee: ₱<?php echo number_format($type['fee'], 2); ?> | Duration: <?php echo $type['duration_minutes']; ?> minutes
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Select Date & Time -->
          <div class="form-section">
            <div class="form-header">STEP 2: SELECT DATE & TIME</div>
            <div class="form-content">
              <div class="form-group">
                <label class="form-label" for="booking_date">Preferred Date <span style="color: #dc3545;">*</span></label>
                <input type="date" class="form-control" id="booking_date" name="booking_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                <small style="color: #666;">Select a date at least 7 days in advance when possible</small>
              </div>

              <div class="form-group">
                <label class="form-label">Preferred Time <span style="color: #dc3545;">*</span></label>
                <div class="time-slot-grid" id="timeSlots">
                  <!-- Generated by JavaScript -->
                </div>
                <input type="hidden" name="booking_time" id="booking_time_input">
              </div>
            </div>
          </div>

          <!-- Booking Details -->
          <div class="form-section">
            <div class="form-header">STEP 3: PROVIDE DETAILS</div>
            <div class="form-content">
              <div class="form-group">
                <label class="form-label" for="purpose">Reason/Details <span style="color: #dc3545;">*</span></label>
                <textarea class="form-control" id="purpose" name="purpose" rows="4" placeholder="Please provide details about your booking request..." required></textarea>
              </div>

              <div class="form-group">
                <label class="form-label" for="special_requests">Special Requests (Optional)</label>
                <textarea class="form-control" id="special_requests" name="special_requests" rows="3" placeholder="Any special requests or accommodations needed?"></textarea>
              </div>
            </div>
          </div>

          <!-- Submit -->
          <div style="margin-bottom: 30px;">
            <button type="submit" class="btn-primary">
              <i class="bi bi-check-lg"></i> SUBMIT BOOKING
            </button>
            <a href="/documentSystem/client/dashboard.php" class="btn-secondary" style="margin-left: 10px;">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-contact">
          <strong>FOR INQUIRIES:</strong><br>
          HOTLINE: 0999 MAYNAY<br>
          EMAIL: maequinas@gmail.com
        </div>
      </div>
      <div class="footer-bottom">
        Parish Church © <?php echo date('Y'); ?>
      </div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/documentSystem/assets/js/common.js"></script>
  <script src="/documentSystem/assets/js/forms.js"></script>
  
  <script>
    const blockedDates = <?php echo json_encode($blockedDatesList); ?>;

    // Booking type selection
    function selectBookingType(typeId) {
      document.getElementById('type_' + typeId).checked = true;
      document.querySelectorAll('.booking-type-card').forEach(card => {
        card.classList.remove('selected');
      });
      event.currentTarget.classList.add('selected');
    }

    // Date validation and time slot generation
    const dateInput = document.getElementById('booking_date');
    dateInput.addEventListener('change', function() {
      generateTimeSlots(this.value);
    });

    async function generateTimeSlots(date) {
      const timeSlots = document.getElementById('timeSlots');
      const hiddenInput = document.getElementById('booking_time_input');
      timeSlots.innerHTML = '';
      hiddenInput.value = '';

      if (!date) return;

      let bookedSlots = [];
      try {
        const res = await fetch(`new-appointment.php?action=booked_slots&date=${encodeURIComponent(date)}`);
        bookedSlots = await res.json();
      } catch (err) {
        bookedSlots = [];
      }
      
      const times = [];
      for (let hour = 9; hour < 17; hour++) {
        times.push(`${String(hour).padStart(2, '0')}:00`);
        times.push(`${String(hour).padStart(2, '0')}:30`);
      }

      times.forEach(time => {
        const slot = document.createElement('button');
        slot.type = 'button';
        slot.className = 'time-slot';
        slot.textContent = time;
        const dbTime = `${time}:00`;
        const isTaken = bookedSlots.includes(dbTime);
        if (isTaken) {
          slot.disabled = true;
          slot.classList.add('disabled');
        }
        slot.onclick = () => selectTimeSlot(slot, dbTime, isTaken);
        timeSlots.appendChild(slot);
      });
    }

    function selectTimeSlot(element, time, isTaken = false) {
      if (isTaken || element.disabled) return;
      event.preventDefault();
      document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('selected');
      });
      element.classList.add('selected');
      document.getElementById('booking_time_input').value = time;
    }

    // Form validation
    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
      const bookingType = document.querySelector('input[name="booking_type"]:checked');
      const bookingDate = document.getElementById('booking_date').value;
      const bookingTime = document.getElementById('booking_time_input').value;
      const purpose = document.getElementById('purpose').value.trim();

      if (!bookingType) {
        e.preventDefault();
        alert('Please select a service type');
        return;
      }
      if (!bookingDate) {
        e.preventDefault();
        alert('Please select a date');
        return;
      }
      if (!bookingTime) {
        e.preventDefault();
        alert('Please select a time');
        return;
      }
      if (!purpose) {
        e.preventDefault();
        alert('Please provide details about your booking');
        return;
      }
    });

    // Disable blocked dates
    dateInput.addEventListener('change', function() {
      const selectedDate = this.value;
      if (blockedDates.includes(selectedDate)) {
        alert('This date is not available for bookings.');
        this.value = '';
      }
    });
  </script>
</body>
</html>
<?php
closeDBConnection($conn);
?>
