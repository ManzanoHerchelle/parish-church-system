<?php
/**
 * Download Certificate
 * Secure certificate download endpoint for clients
 */

session_start();
require_once '../config/database.php';
require_once '../src/Services/DocumentService.php';

use Services\DocumentService;

// Verify user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    http_response_code(401);
    exit('Unauthorized');
}

$documentId = $_GET['id'] ?? null;
if (!$documentId) {
    http_response_code(400);
    exit('Missing document ID');
}

try {
    $conn = getDBConnection();
    $docService = new DocumentService();
    
    // Get document details
    $query = "SELECT dr.*, dt.name as document_type FROM document_requests dr 
              JOIN document_types dt ON dr.document_type_id = dt.id 
              WHERE dr.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $documentId);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    
    if (!$document) {
        http_response_code(404);
        exit('Document not found');
    }
    
    // Verify ownership
    if ($document['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        exit('Access denied');
    }
    
    // Verify document is ready (has been processed)
    if ($document['status'] !== 'ready' && $document['status'] !== 'completed') {
        http_response_code(403);
        exit('Document is not ready for download');
    }
    
    // Get certificate details
    $certQuery = "SELECT * FROM certificates WHERE document_request_id = ?";
    $certStmt = $conn->prepare($certQuery);
    $certStmt->bind_param("i", $documentId);
    $certStmt->execute();
    $certificate = $certStmt->get_result()->fetch_assoc();
    
    if (!$certificate) {
        http_response_code(404);
        exit('Certificate not found');
    }
    
    $filePath = $certificate['file_path'];
    
    // Verify file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('Certificate file not found');
    }
    
    // Security: Prevent directory traversal
    $realPath = realpath($filePath);
    $uploadsDir = realpath(__DIR__ . '/../uploads/certificates/');
    
    if ($realPath === false || strpos($realPath, $uploadsDir) !== 0) {
        http_response_code(403);
        exit('Invalid file path');
    }
    
    // Log download activity
    $logQuery = "INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $action = 'DOWNLOAD_CERTIFICATE';
    $description = "Downloaded certificate for document: {$document['reference_number']}";
    $logStmt->bind_param("iss", $_SESSION['user_id'], $action, $description);
    $logStmt->execute();
    
    // Send file to client
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $certificate['certificate_file'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    error_log('Certificate Download Error: ' . $e->getMessage());
    http_response_code(500);
    exit('Error downloading certificate');
}
