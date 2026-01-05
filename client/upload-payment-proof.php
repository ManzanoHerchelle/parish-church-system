<?php
/**
 * Client - Upload Payment Proof
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
if (($_SESSION['user_role'] ?? 'client') !== 'client') {
    $_SESSION['error_message'] = 'Unauthorized access';
    header('Location: /documentSystem/client/dashboard.php');
    exit;
}

$userId = getCurrentUserId();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /documentSystem/client/view-documents.php');
    exit;
}

$documentRequestId = $_POST['document_request_id'] ?? null;
$paymentMethod = $_POST['payment_method'] ?? '';
$transactionReference = $_POST['transaction_reference'] ?? '';
$paymentNotes = $_POST['payment_notes'] ?? '';

if (!$documentRequestId) {
    $_SESSION['error_message'] = 'Invalid request';
    header('Location: /documentSystem/client/view-documents.php');
    exit;
}

// Verify ownership
$stmt = $conn->prepare("SELECT dr.*, dt.name as document_name, dt.fee FROM document_requests dr JOIN document_types dt ON dr.document_type_id = dt.id WHERE dr.id = ? AND dr.user_id = ?");
$stmt->bind_param("ii", $documentRequestId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$documentRequest = $result->fetch_assoc();
$stmt->close();

if (!$documentRequest) {
    $_SESSION['error_message'] = 'Document request not found';
    header('Location: /documentSystem/client/view-documents.php');
    exit;
}

// Handle file upload (required only for non-cash methods)
$relativeFilePath = null;

if ($paymentMethod !== 'over_counter') {
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'Please upload a valid payment proof file';
        header('Location: /documentSystem/client/view-documents.php');
        exit;
    }

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $fileType = $_FILES['payment_proof']['type'];
    $fileSize = $_FILES['payment_proof']['size'];

    if (!in_array($fileType, $allowedTypes)) {
        $_SESSION['error_message'] = 'Invalid file type. Only JPG, PNG, and PDF are allowed';
        header('Location: /documentSystem/client/view-documents.php');
        exit;
    }

    if ($fileSize > $maxSize) {
        $_SESSION['error_message'] = 'File too large. Maximum size is 5MB';
        header('Location: /documentSystem/client/view-documents.php');
        exit;
    }

    // Upload file
    $uploadDir = __DIR__ . '/../uploads/payments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
    $fileName = $documentRequest['reference_number'] . '_payment_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
        $_SESSION['error_message'] = 'Failed to upload file. Please try again';
        header('Location: /documentSystem/client/view-documents.php');
        exit;
    }

    $relativeFilePath = 'uploads/payments/' . $fileName;
}

// Determine status: all submissions stay pending for staff verification
$documentPaymentStatus = 'pending';
$paymentsTableStatus = 'pending';

// Update document request payment proof/status
if ($relativeFilePath) {
    $updateStmt = $conn->prepare("UPDATE document_requests SET payment_proof = ?, payment_status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("ssi", $relativeFilePath, $documentPaymentStatus, $documentRequestId);
} else {
    $updateStmt = $conn->prepare("UPDATE document_requests SET payment_status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("si", $documentPaymentStatus, $documentRequestId);
}
$updateStmt->execute();
$updateStmt->close();

// Ensure payment record exists; update or insert
$paymentCheckStmt = $conn->prepare("SELECT id FROM payments WHERE reference_type = 'document_request' AND reference_id = ?");
$paymentCheckStmt->bind_param("i", $documentRequestId);
$paymentCheckStmt->execute();
$paymentExists = $paymentCheckStmt->get_result()->fetch_assoc();
$paymentCheckStmt->close();

// Auto-generate transaction ref for over_counter if missing
if (empty($transactionReference) && $paymentMethod === 'over_counter') {
    $transactionReference = 'OTC-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . time();
}

if ($paymentExists) {
    $paymentUpdateStmt = $conn->prepare("
        UPDATE payments 
        SET payment_proof = ?, 
            payment_method = ?, 
            transaction_number = ?, 
            status = ?,
            updated_at = NOW()
        WHERE reference_type = 'document_request' AND reference_id = ?
    ");
    $paymentUpdateStmt->bind_param("ssssi", $relativeFilePath, $paymentMethod, $transactionReference, $paymentsTableStatus, $documentRequestId);
    $paymentUpdateStmt->execute();
    $paymentUpdateStmt->close();
} else {
    $paymentInsertStmt = $conn->prepare("
        INSERT INTO payments (user_id, reference_type, reference_id, transaction_number, amount, payment_method, payment_proof, status, created_at)
        VALUES (?, 'document_request', ?, ?, ?, ?, ?, ?, NOW())
    ");
    $paymentInsertStmt->bind_param("iisdsss", $userId, $documentRequestId, $transactionReference, $documentRequest['fee'], $paymentMethod, $relativeFilePath, $paymentsTableStatus);
    $paymentInsertStmt->execute();
    $paymentInsertStmt->close();
}

// Log activity
$logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'payment_upload', ?, NOW())");
$logDesc = "Uploaded payment proof for document request: " . $documentRequest['reference_number'];
$logStmt->bind_param("is", $userId, $logDesc);
$logStmt->execute();
$logStmt->close();

// Send notification to staff
$emailHandler = new EmailHandler();
$staffQuery = "SELECT email FROM users WHERE role IN ('admin', 'staff')";
$staffResult = $conn->query($staffQuery);
while ($staff = $staffResult->fetch_assoc()) {
    // You can add email notification here if needed
}

closeDBConnection($conn);

$_SESSION['success_message'] = 'Payment submitted. Your payment will be verified by staff shortly.';
header('Location: /documentSystem/client/view-documents.php');
exit;
