<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user role is client
if ($_SESSION['role'] !== 'client') {
    header('Location: ../admin/dashboard.php');
    exit;
}

require_once '../config/database.php';
require_once '../src/UI/Layouts/ClientLayout.php';

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingTypeId = $_POST['booking_type_id'] ?? null;
    $bookingDate = $_POST['booking_date'] ?? '';
    $bookingTime = $_POST['booking_time'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');
    $specialRequests = trim($_POST['special_requests'] ?? '');
    
    $errors = [];
    
    if (!$bookingTypeId) {
        $errors[] = 'Please select a booking type.';
    }
    if (empty($bookingDate)) {
        $errors[] = 'Please select a date.';
    }
    if (empty($bookingTime)) {
        $errors[] = 'Please select a time.';
    }
    if (empty($purpose)) {
        $errors[] = 'Please provide the purpose of your appointment.';
    }
    
    // Validate date is not in the past
    if (!empty($bookingDate) && strtotime($bookingDate) < strtotime('today')) {
        $errors[] = 'Cannot book appointments in the past.';
    }
    
    // Check if date is blocked
    if (!empty($bookingDate)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM blocked_dates WHERE date = ?");
        $stmt->bind_param("s", $bookingDate);
        $stmt->execute();
        $blocked = $stmt->get_result()->fetch_assoc();
        if ($blocked['count'] > 0) {
            $errors[] = 'Selected date is not available.';
        }
    }
    
    if (empty($errors)) {
        // Get booking type details
        $stmt = $conn->prepare("SELECT name, fee, duration_minutes, max_bookings_per_day FROM booking_types WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $bookingTypeId);
        $stmt->execute();
        $bookingType = $stmt->get_result()->fetch_assoc();
        
        if ($bookingType) {
            // Check max bookings per day
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE booking_type_id = ? AND booking_date = ? AND status NOT IN ('cancelled', 'rejected')");
            $stmt->bind_param("is", $bookingTypeId, $bookingDate);
            $stmt->execute();
            $currentBookings = $stmt->get_result()->fetch_assoc()['count'];
            
            if ($currentBookings >= $bookingType['max_bookings_per_day']) {
                $errors[] = 'Maximum bookings reached for this date. Please select another date.';
            } else {
                // Check if time slot is already taken
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE booking_date = ? AND booking_time = ? AND status NOT IN ('cancelled', 'rejected')");
                $stmt->bind_param("ss", $bookingDate, $bookingTime);
                $stmt->execute();
                $timeConflict = $stmt->get_result()->fetch_assoc()['count'];
                
                if ($timeConflict > 0) {
                    $errors[] = 'Selected time slot is already booked. Please choose another time.';
                } else {
                    // Generate reference number
                    $refNumber = 'BOOK-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                    
                    // Insert booking
                    $stmt = $conn->prepare("
                        INSERT INTO bookings 
                        (user_id, booking_type_id, reference_number, booking_date, booking_time, purpose, special_requests, amount, status, payment_status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
                    ");
                    $stmt->bind_param("iisssssd", 
                        $userId, 
                        $bookingTypeId, 
                        $refNumber, 
                        $bookingDate, 
                        $bookingTime, 
                        $purpose, 
                        $specialRequests, 
                        $bookingType['fee']
                    );
                    
                    if ($stmt->execute()) {
                        $bookingId = $stmt->insert_id;
                        
                        // Create payment record if fee > 0
                        if ($bookingType['fee'] > 0) {
                            $paymentStmt = $conn->prepare("
                                INSERT INTO payments 
                                (user_id, reference_type, reference_id, amount, payment_method, status, created_at)
                                VALUES (?, 'booking', ?, ?, 'bank_transfer', 'pending', NOW())
                            ");
                            $paymentStmt->bind_param("iid", $userId, $bookingId, $bookingType['fee']);
                            $paymentStmt->execute();
                        }
                        
                        $_SESSION['success_message'] = 'Appointment booked successfully! Reference: ' . $refNumber;
                        header('Location: my-appointments.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to book appointment. Please try again.';
                    }
                }
            }
        } else {
            $errors[] = 'Invalid booking type selected.';
        }
    }
}

// Get active booking types
$bookingTypes = [];
$result = $conn->query("SELECT id, name, description, fee, duration_minutes, max_bookings_per_day FROM booking_types WHERE is_active = 1 ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $bookingTypes[] = $row;
}

// Get blocked dates for calendar
$blockedDates = [];
$result = $conn->query("SELECT date FROM blocked_dates WHERE date >= CURDATE()");
while ($row = $result->fetch_assoc()) {
    $blockedDates[] = $row['date'];
}

// Build content
ob_start();
?>

<?php if (isset($errors) && count($errors) > 0): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-plus"></i> Book Appointment</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="bookingForm">
                    <!-- Booking Type Selection -->
                    <div class="mb-4">
                        <label for="booking_type_id" class="form-label">Appointment Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="booking_type_id" name="booking_type_id" required onchange="updateBookingInfo()">
                            <option value="">-- Select Appointment Type --</option>
                            <?php foreach ($bookingTypes as $type): ?>
                                <option value="<?= $type['id'] ?>" 
                                        data-fee="<?= $type['fee'] ?>" 
                                        data-duration="<?= $type['duration_minutes'] ?>"
                                        data-max="<?= $type['max_bookings_per_day'] ?>"
                                        data-description="<?= htmlspecialchars($type['description']) ?>">
                                    <?= htmlspecialchars($type['name']) ?> - ₱<?= number_format($type['fee'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Booking Info Display -->
                    <div id="bookingInfo" class="alert alert-info d-none">
                        <h6 class="alert-heading">Appointment Information</h6>
                        <p id="bookingDescription" class="mb-2"></p>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Fee:</strong> <span id="bookingFee"></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Duration:</strong> <span id="bookingDuration"></span> min
                            </div>
                            <div class="col-md-4">
                                <strong>Max/Day:</strong> <span id="bookingMax"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Date Selection -->
                    <div class="mb-3">
                        <label for="booking_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="booking_date" name="booking_date" 
                               required min="<?= date('Y-m-d') ?>" onchange="checkAvailability()">
                        <div class="form-text">Select a date for your appointment</div>
                    </div>
                    
                    <!-- Time Selection -->
                    <div class="mb-3">
                        <label for="booking_time" class="form-label">Time <span class="text-danger">*</span></label>
                        <select class="form-select" id="booking_time" name="booking_time" required>
                            <option value="">-- Select Time --</option>
                            <option value="08:00:00">8:00 AM</option>
                            <option value="09:00:00">9:00 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="13:00:00">1:00 PM</option>
                            <option value="14:00:00">2:00 PM</option>
                            <option value="15:00:00">3:00 PM</option>
                            <option value="16:00:00">4:00 PM</option>
                        </select>
                    </div>
                    
                    <div id="availabilityMessage" class="alert d-none"></div>
                    
                    <!-- Purpose -->
                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" required 
                                  placeholder="Please state the purpose of this appointment"></textarea>
                        <div class="form-text">Explain what you need to discuss or accomplish</div>
                    </div>
                    
                    <!-- Special Requests -->
                    <div class="mb-3">
                        <label for="special_requests" class="form-label">Special Requests</label>
                        <textarea class="form-control" id="special_requests" name="special_requests" rows="2" 
                                  placeholder="Any special accommodations or requests"></textarea>
                    </div>
                    
                    <!-- Payment Instructions -->
                    <div class="alert alert-warning">
                        <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Payment Instructions</h6>
                        <p class="mb-2">Please make payment to:</p>
                        <ul class="mb-0">
                            <li><strong>Bank:</strong> Sample Bank</li>
                            <li><strong>Account Name:</strong> Parish Church</li>
                            <li><strong>Account Number:</strong> 1234567890</li>
                        </ul>
                        <p class="mt-2 mb-0"><small>Payment can be made at the parish office or via bank transfer.</small></p>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Book Appointment
                        </button>
                        <a href="my-appointments.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Help Card -->
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-question-circle"></i> Booking Guidelines</h6>
                <ul class="mb-0">
                    <li>Appointments must be booked at least 1 day in advance</li>
                    <li>Please arrive 10 minutes before your scheduled time</li>
                    <li>You can cancel pending appointments from "My Appointments"</li>
                    <li>For urgent matters, call <strong>(123) 456-7890</strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const blockedDates = <?= json_encode($blockedDates) ?>;

function updateBookingInfo() {
    const select = document.getElementById('booking_type_id');
    const option = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('bookingInfo');
    
    if (option.value) {
        document.getElementById('bookingDescription').textContent = option.dataset.description;
        document.getElementById('bookingFee').textContent = '₱' + parseFloat(option.dataset.fee).toFixed(2);
        document.getElementById('bookingDuration').textContent = option.dataset.duration;
        document.getElementById('bookingMax').textContent = option.dataset.max;
        
        infoDiv.classList.remove('d-none');
    } else {
        infoDiv.classList.add('d-none');
    }
}

// Check date availability
document.getElementById('booking_date').addEventListener('change', function() {
    const selectedDate = this.value;
    const messageDiv = document.getElementById('availabilityMessage');
    
    if (blockedDates.includes(selectedDate)) {
        messageDiv.className = 'alert alert-danger';
        messageDiv.textContent = 'This date is not available. Please select another date.';
        messageDiv.classList.remove('d-none');
        this.value = '';
    } else {
        messageDiv.classList.add('d-none');
    }
});

function checkAvailability() {
    const date = document.getElementById('booking_date').value;
    const typeId = document.getElementById('booking_type_id').value;
    
    if (date && typeId) {
        // Could add AJAX call here to check real-time availability
        console.log('Checking availability for', date, typeId);
    }
}
</script>

<?php
$content = ob_get_clean();

// Render layout
$layout = new ClientLayout('Book Appointment', 'Book Appointment', 'appointments');
$layout->setContent($content);
echo $layout->render();

closeDBConnection($conn);
