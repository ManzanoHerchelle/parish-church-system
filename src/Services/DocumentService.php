<?php
/**
 * Document Service
 * Handles all document request operations (CRUD, filtering, status changes)
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Document.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/CertificateService.php';

use Models\Document;

class DocumentService {
    private $conn;
    private $emailService;
    private $certificateService;
    
    /**
     * Constructor - initialize database connection
     */
    public function __construct() {
        $this->conn = getDBConnection();
        $this->emailService = new EmailService();
        $this->certificateService = new CertificateService();
    }
    
    /**
     * Get all document requests with optional filtering
     * @param array $filters - filters: status, userId, limit, offset
     * @return array of Document objects
     */
    public function getDocuments($filters = []) {
        $status = $filters['status'] ?? null;
        $userId = $filters['userId'] ?? null;
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        
        $query = "
            SELECT dr.*, dt.name as document_type, 
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email
            FROM document_requests dr
            JOIN document_types dt ON dr.document_type_id = dt.id
            JOIN users u ON dr.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if ($status && $status !== 'all') {
            $query .= " AND dr.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if ($userId) {
            $query .= " AND dr.user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }
        
        $query .= " ORDER BY dr.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = new Document($row);
        }
        
        return $documents;
    }
    
    /**
     * Get a single document by ID
     */
    public function getDocumentById($id) {
        $query = "
            SELECT dr.*, dt.name as document_type, dt.fee as document_fee,
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email
            FROM document_requests dr
            JOIN document_types dt ON dr.document_type_id = dt.id
            JOIN users u ON dr.user_id = u.id
            WHERE dr.id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        // Return as associative array for compatibility with notification helpers
        return $result->fetch_assoc();
    }
    
    /**
     * Get pending documents count
     */
    public function getPendingCount() {
        $query = "SELECT COUNT(*) as count FROM document_requests WHERE status = 'pending'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    /**
     * Approve a document request (change status to processing)
     */
    public function approveDocument($id, $adminId) {
        $query = "
            UPDATE document_requests 
            SET status = 'processing', processed_by = ?, processed_date = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $adminId, $id);
        $result = $stmt->execute();
        
        if ($result) {
            $this->notifyDocumentApproved($id);
        }
        
        return $result;
    }
    
    /**
     * Reject a document request
     */
    public function rejectDocument($id, $adminId, $rejectionReason) {
        $query = "
            UPDATE document_requests 
            SET status = 'rejected', processed_by = ?, processed_date = NOW(), 
                rejection_reason = ?
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isi', $adminId, $rejectionReason, $id);
        $result = $stmt->execute();
        
        if ($result) {
            $this->notifyDocumentRejected($id);
        }
        
        return $result;
    }
    
    /**
     * Mark document as ready for pickup
     */
    public function markAsReady($id, $adminId) {
        $query = "
            UPDATE document_requests 
            SET status = 'ready', processed_by = ?, processed_date = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $adminId, $id);
        $result = $stmt->execute();
        
        if ($result) {
            $this->notifyDocumentReady($id);
        }
        
        return $result;
    }
    
    /**
     * Mark document as completed
     */
    public function markAsCompleted($id, $adminId) {
        $query = "
            UPDATE document_requests 
            SET status = 'completed', processed_by = ?, processed_date = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $adminId, $id);
        $result = $stmt->execute();
        
        return $result;
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $status, $amount = null) {
        $query = "
            UPDATE document_requests 
            SET payment_status = ?";
        
        if ($amount !== null) {
            $query .= ", payment_amount = ?";
        }
        
        $query .= " WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        
        if ($amount !== null) {
            $stmt->bind_param('sdi', $status, $amount, $id);
        } else {
            $stmt->bind_param('si', $status, $id);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Send notification to user - document approved
     */
    private function notifyDocumentApproved($documentId) {
        $doc = $this->getDocumentById($documentId);
        if (!$doc) return false;
        
        // Create database notification
        $message = "Your document request ({$doc['reference_number']}) has been approved and is being processed.";
        $this->createNotification($doc['user_id'], 'Document Approved', $message, 'success');
        
        // Get user email
        $stmt = $this->conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $doc['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Send email notification
        if ($user) {
            $this->emailService->sendDocumentApprovalEmail(
                $user['email'],
                $user['first_name'],
                $doc['document_type'],
                $doc['reference_number']
            );
        }
        
        return true;
    }
    
    /**
     * Send notification - document ready
     */
    private function notifyDocumentReady($documentId) {
        $doc = $this->getDocumentById($documentId);
        if (!$doc) return false;
        
        // Create database notification
        $message = "Your document ({$doc['reference_number']}) is ready for pickup!";
        $this->createNotification($doc['user_id'], 'Document Ready', $message, 'success');
        
        // Get user email
        $stmt = $this->conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $doc['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Send email notification
        if ($user) {
            $this->emailService->sendDocumentReadyEmail(
                $user['email'],
                $user['first_name'],
                $doc['document_type'],
                $doc['reference_number']
            );
        }
        
        // Generate certificate automatically
        $this->certificateService->generateCertificate(
            $documentId,
            $doc['document_type'],
            trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
        );
        
        return true;
    }
    
    /**
     * Send notification - document rejected
     */
    private function notifyDocumentRejected($documentId) {
        $doc = $this->getDocumentById($documentId);
        if (!$doc) return false;
        
        // Create database notification
        $message = "Your document request ({$doc['reference_number']}) was rejected. Reason: {$doc['rejection_reason']}";
        $this->createNotification($doc['user_id'], 'Document Rejected', $message, 'error');
        
        // Get user email
        $stmt = $this->conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $doc['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Send email notification
        if ($user) {
            $this->emailService->sendDocumentRejectionEmail(
                $user['email'],
                $user['first_name'],
                $doc['document_type'],
                $doc['reference_number'],
                $doc['rejection_reason']
            );
        }
        
        return true;
    }
    
    /**
     * Helper - create notification
     */
    private function createNotification($userId, $title, $message, $type = 'info') {
        $query = "
            INSERT INTO notifications (user_id, title, message, type, is_read)
            VALUES (?, ?, ?, ?, 0)
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isss', $userId, $title, $message, $type);
        return $stmt->execute();
    }
}
?>
