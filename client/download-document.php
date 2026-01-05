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

require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$docId = $_GET['id'] ?? null;

if (!$docId) {
    $_SESSION['error_message'] = 'Invalid document ID.';
    header('Location: my-documents.php');
    exit;
}

$conn = getDBConnection();

// Get document details and verify ownership
$stmt = $conn->prepare("
    SELECT dr.id, dr.user_id, dr.status, dr.payment_status, dr.document_file, dr.reference_number,
           dt.name as document_type
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    WHERE dr.id = ?
");
$stmt->bind_param("i", $docId);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();

if (!$document) {
    $_SESSION['error_message'] = 'Document not found.';
    header('Location: my-documents.php');
    exit;
}

// Verify ownership
if ($document['user_id'] != $userId) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    header('Location: my-documents.php');
    exit;
}

// Check if document is ready
if ($document['status'] !== 'ready' && $document['status'] !== 'completed') {
    $_SESSION['error_message'] = 'Document is not ready for download yet.';
    header('Location: my-documents.php');
    exit;
}

// Check if payment is verified
if ($document['payment_status'] !== 'paid' && $document['payment_status'] !== 'waived') {
    $_SESSION['error_message'] = 'Payment must be verified before downloading.';
    header('Location: my-documents.php');
    exit;
}

// Check if document file exists
if (empty($document['document_file'])) {
    $_SESSION['error_message'] = 'Document file not available.';
    header('Location: my-documents.php');
    exit;
}

$filePath = __DIR__ . '/../' . $document['document_file'];

if (!file_exists($filePath)) {
    $_SESSION['error_message'] = 'Document file not found on server.';
    header('Location: my-documents.php');
    exit;
}

// Mark as completed if it was ready
if ($document['status'] === 'ready') {
    $updateStmt = $conn->prepare("UPDATE document_requests SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $docId);
    $updateStmt->execute();
}

closeDBConnection($conn);

// Prepare file for download
$fileName = $document['reference_number'] . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $document['document_type']) . '.' . pathinfo($filePath, PATHINFO_EXTENSION);

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Clear output buffer
ob_clean();
flush();

// Read and output file
readfile($filePath);
exit;
