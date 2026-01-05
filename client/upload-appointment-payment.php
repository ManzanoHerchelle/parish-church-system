<?php
/**
 * Client - Upload Appointment Payment Proof
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../handlers/email_handler.php';

startSecureSession();

// Require login
if (!isLoggedIn()) {
    header('Location: /documentSystem/client/login.php');
    exit;
}

// Check if user role is client
$userRole = $_SESSION['user_role'] ?? 'guest';
if ($userRole !== 'client') {
    $_SESSION['error_message'] = 'Unauthorized access';
    header('Location: /documentSystem/client/view-appointments.php');
    exit;
}

$userId = getCurrentUserId();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /documentSystem/client/view-appointments.php');
    exit;
}

$bookingId = $_POST['booking_id'] ?? null;
$paymentMethod = $_POST['payment_method'] ?? '';
$transactionNumber = $_POST['transaction_number'] ?? '';
$notes = $_POST['notes'] ?? '';

// Generate transaction number for cash payments (they don't have one yet)
if ($paymentMethod === 'over_counter' && empty($transactionNumber)) {
    $transactionNumber = 'CASH-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . time();
}

if (!$bookingId) {
    $_SESSION['error_message'] = 'Invalid appointment booking';
    header('Location: /documentSystem/client/view-appointments.php');
    exit;
}

// Verify ownership and get appointment details
$stmt = $conn->prepare("
    SELECT b.*, bt.name as booking_type, bt.fee 
    FROM bookings b 
    JOIN booking_types bt ON b.booking_type_id = bt.id 
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    $_SESSION['error_message'] = 'Appointment booking not found';
    error_log("Booking not found - ID: $bookingId, User ID: $userId");
    header('Location: /documentSystem/client/view-appointments.php');
    exit;
}

// Debug log
error_log("Processing payment - Booking ID: $bookingId, Current Status: " . $booking['payment_status']);

// Check if already paid
if ($booking['payment_status'] === 'paid') {
    $_SESSION['error_message'] = 'This appointment has already been paid';
    header('Location: /documentSystem/client/view-appointments.php');
    exit;
}

// Check if payment proof is required based on payment method
$requiresProof = in_array($paymentMethod, ['bank_transfer', 'gcash', 'paymaya']);

// Validate file upload if required
$filePath = null;
$relativeFilePath = null;

if ($requiresProof) {
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'Please upload a payment proof for online payment methods';
        header('Location: /documentSystem/client/view-appointments.php');
        exit;
    }

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $fileType = $_FILES['payment_proof']['type'];
    $fileSize = $_FILES['payment_proof']['size'];

    if (!in_array($fileType, $allowedTypes)) {
        $_SESSION['error_message'] = 'Invalid file type. Only JPG, PNG, and PDF are allowed';
        header('Location: /documentSystem/client/view-appointments.php');
        exit;
    }

    if ($fileSize > $maxSize) {
        $_SESSION['error_message'] = 'File too large. Maximum size is 5MB';
        header('Location: /documentSystem/client/view-appointments.php');
        exit;
    }
}

// Generate unique filename
$fileExtension = '';
$referenceNumber = $booking['reference_number'];
$timestamp = time();
$filename = '';

if ($requiresProof) {
    $fileExtension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
    $filename = "{$referenceNumber}_payment_{$timestamp}.{$fileExtension}";

    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../uploads/payments/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Move uploaded file
    $filePath = $uploadDir . $filename;
    if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $filePath)) {
        $_SESSION['error_message'] = 'Failed to upload file. Please try again';
        header('Location: /documentSystem/client/view-appointments.php');
        exit;
    }

    // Store relative path for database
    $relativeFilePath = 'uploads/payments/' . $filename;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update booking with payment status (only update payment_proof if we have a file)
    error_log("Attempting to update booking $bookingId with requires_proof=$requiresProof");
    
    if ($requiresProof) {
        error_log("Updating with file - relative path: $relativeFilePath");
        $updateBookingStmt = $conn->prepare("
            UPDATE bookings 
            SET payment_proof = ?, 
                payment_status = 'pending'
            WHERE id = ?
        ");
        if (!$updateBookingStmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        $updateBookingStmt->bind_param("si", $relativeFilePath, $bookingId);
    } else {
        error_log("Updating without file for cash payment");
        $updateBookingStmt = $conn->prepare("
            UPDATE bookings 
            SET payment_status = 'pending'
            WHERE id = ?
        ");
        if (!$updateBookingStmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        $updateBookingStmt->bind_param("i", $bookingId);
    }
    
    if (!$updateBookingStmt->execute()) {
        error_log("Execute failed: " . $updateBookingStmt->error . " - Rows affected: " . $updateBookingStmt->affected_rows);
        throw new Exception('Failed to update booking payment information: ' . $updateBookingStmt->error);
    }
    
    error_log("Update successful - Affected rows: " . $updateBookingStmt->affected_rows);
    $updateBookingStmt->close();

    // Check if payment record exists
    $checkPaymentStmt = $conn->prepare("
        SELECT id FROM payments 
        WHERE reference_type = 'booking' AND reference_id = ?
    ");
    $checkPaymentStmt->bind_param("i", $bookingId);
    $checkPaymentStmt->execute();
    $paymentExists = $checkPaymentStmt->get_result()->fetch_assoc();
    $checkPaymentStmt->close();

    if ($paymentExists) {
        // Update existing payment record
        if ($requiresProof) {
            $updatePaymentStmt = $conn->prepare("
                UPDATE payments 
                SET payment_proof = ?,
                    payment_method = ?,
                    transaction_number = ?,
                    status = 'pending',
                    notes = ?
                WHERE reference_type = 'booking' AND reference_id = ?
            ");
            $updatePaymentStmt->bind_param("ssssi", $relativeFilePath, $paymentMethod, $transactionNumber, $notes, $bookingId);
        } else {
            $updatePaymentStmt = $conn->prepare("
                UPDATE payments 
                SET payment_method = ?,
                    transaction_number = ?,
                    status = 'pending',
                    notes = ?
                WHERE reference_type = 'booking' AND reference_id = ?
            ");
            $updatePaymentStmt->bind_param("sssi", $paymentMethod, $transactionNumber, $notes, $bookingId);
        }
        
        if (!$updatePaymentStmt->execute()) {
            error_log("Update payment error: " . $updatePaymentStmt->error);
            throw new Exception('Failed to update payment record: ' . $updatePaymentStmt->error);
        }
        $updatePaymentStmt->close();
    } else {
        // Create new payment record
        $insertPaymentStmt = $conn->prepare("
            INSERT INTO payments 
            (user_id, reference_type, reference_id, amount, payment_method, transaction_number, payment_proof, status, notes, created_at)
            VALUES (?, 'booking', ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $amount = $booking['payment_amount'] ?? $booking['fee'];
        $insertPaymentStmt->bind_param("iidssss", $userId, $bookingId, $amount, $paymentMethod, $transactionNumber, $relativeFilePath, $notes);
        
        if (!$insertPaymentStmt->execute()) {
            throw new Exception('Failed to create payment record');
        }
        $insertPaymentStmt->close();
    }

    // Log activity
    $activityStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, description, created_at)
        VALUES (?, 'appointment_payment_upload', ?, NOW())
    ");
    $description = "Uploaded payment proof for appointment booking " . $booking['reference_number'];
    $activityStmt->bind_param("is", $userId, $description);
    $activityStmt->execute();
    $activityStmt->close();

    // Send confirmation email to customer
    $userEmail = getCurrentUserEmail();
    $userName = $_SESSION['user_name'] ?? 'Valued Client';
    
    // Determine payment method display name
    $paymentMethodDisplay = [
        'bank_transfer' => 'Bank Transfer',
        'gcash' => 'GCash',
        'paymaya' => 'PayMaya',
        'over_counter' => 'Cash Payment at Office'
    ];
    $paymentMethodName = $paymentMethodDisplay[$paymentMethod] ?? $paymentMethod;
    
    $emailSubject = "Payment Received - Appointment " . $booking['reference_number'];
    $emailBody = "
Dear " . htmlspecialchars($userName) . ",<br><br>

We have received your appointment payment submission for your <strong>" . htmlspecialchars($booking['booking_type']) . "</strong> appointment.<br><br>

<strong>Appointment Details:</strong><br>
Reference Number: " . htmlspecialchars($booking['reference_number']) . "<br>
Booking Date: " . date('F d, Y', strtotime($booking['booking_date'])) . "<br>
Booking Time: " . date('g:i A', strtotime($booking['booking_time'])) . "<br>
Amount: ₱" . number_format($booking['payment_amount'] ?? $booking['fee'], 2) . "<br><br>

<strong>Payment Information:</strong><br>
Payment Method: <strong>" . $paymentMethodName . "</strong><br>" .
($paymentMethod !== 'over_counter' ? "Transaction Reference: " . htmlspecialchars($transactionNumber) . "<br>" : "") . "
Status: <strong>Pending Approval</strong><br><br>

" . 
($paymentMethod === 'over_counter' ? 
"<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 15px 0;'>
<strong><i>Cash Payment Notice:</i></strong><br>
You have chosen to pay at our office. Please bring the following when you come to pay:<br>
<ul>
<li>Your appointment reference number: <strong>" . htmlspecialchars($booking['reference_number']) . "</strong></li>
<li>A valid ID</li>
<li>The payment amount: <strong>₱" . number_format($booking['payment_amount'] ?? $booking['fee'], 2) . "</strong></li>
</ul>
Office Hours: Monday - Friday, 8:00 AM - 5:00 PM<br>
Our staff will issue you a receipt upon payment.
</div>" 
: 
"<div style='background: #fef3c7; padding: 15px; border-radius: 5px; margin: 15px 0;'>
<strong><i>Payment Under Review:</i></strong><br>
Your payment proof has been submitted and is now subject to staff approval. We will review your submission and notify you of the status within 24 business hours.
</div>") . "

<strong>What happens next?</strong><br>
<ul>
<li>Our staff will verify your payment</li>
<li>Once approved, your appointment status will be updated</li>
<li>You will receive a confirmation email</li>
</ul><br>

If you have any questions, please contact us at:<br>
📞 Hotline: 0999 MAYNAY<br>
📧 Email: maequinas@gmail.com<br><br>

Thank you for using Parish Ease!<br><br>

Best regards,<br>
<strong>Parish Church Administration</strong>
    ";
    
    // Send email
    $emailHandler = new EmailHandler();
    $emailHandler->sendEmail($userEmail, $emailSubject, $emailBody, $userId);

    // Commit transaction
    $conn->commit();

    $_SESSION['success_message'] = 'Payment proof uploaded successfully! Your payment will be verified by our staff.';
    
    // Debug: Log the update
    error_log("Payment updated successfully - Booking ID: $bookingId, Payment Status should be: pending");
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Delete uploaded file if exists
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    error_log("Payment upload error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error uploading payment: ' . $e->getMessage();
}

closeDBConnection($conn);
header('Location: /documentSystem/client/view-appointments.php');
exit;
