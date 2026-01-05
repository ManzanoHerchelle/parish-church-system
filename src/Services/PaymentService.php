<?php
/**
 * Payment Service
 * Handles all payment operations
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Payment.php';
require_once __DIR__ . '/EmailService.php';

use Models\Payment;

class PaymentService {
    private $conn;
    private $emailService;
    
    public function __construct() {
        $this->conn = getDBConnection();
        $this->emailService = new EmailService();
    }
    
    /**
     * Get all payments with optional filtering
     */
    public function getPayments($filters = []) {
        $status = $filters['status'] ?? null;
        $userId = $filters['userId'] ?? null;
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        
        $query = "
            SELECT p.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email,
                   CASE 
                       WHEN p.reference_type = 'document_request' THEN 
                           (SELECT CONCAT(dt.name, ' - ', dr.reference_number) 
                            FROM document_requests dr 
                            JOIN document_types dt ON dr.document_type_id = dt.id 
                            WHERE dr.id = p.reference_id)
                       WHEN p.reference_type = 'booking' THEN 
                           (SELECT CONCAT(bt.name, ' - ', b.reference_number) 
                            FROM bookings b 
                            JOIN booking_types bt ON b.booking_type_id = bt.id 
                            WHERE b.id = p.reference_id)
                   END as item_name,
                   CASE 
                       WHEN p.reference_type = 'document_request' THEN 
                           (SELECT reference_number FROM document_requests WHERE id = p.reference_id)
                       WHEN p.reference_type = 'booking' THEN 
                           (SELECT reference_number FROM bookings WHERE id = p.reference_id)
                   END as reference_number
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if ($status && $status !== 'all') {
            $query .= " AND p.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if ($userId) {
            $query .= " AND p.user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }
        
        $query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row; // return associative arrays for UI components
        }
        
        return $payments;
    }
    
    /**
     * Get a single payment by ID
     */
    public function getPaymentById($id) {
        $query = "
            SELECT p.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get pending payments count
     */
    public function getPendingCount() {
        $query = "SELECT COUNT(*) as count FROM payments WHERE status = 'pending'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    /**
     * Verify a payment
     */
    public function verifyPayment($id, $adminId, $notes = '') {
        $query = "
            UPDATE payments 
            SET status = 'verified', verified_by = ?, verified_date = NOW(), notes = ?
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isi', $adminId, $notes, $id);
        $result = $stmt->execute();
        
        if ($result) {
            $this->updateRelatedItemPaymentStatus($id, 'paid');
            $this->notifyPaymentVerified($id);
        }
        
        return $result;
    }
    
    /**
     * Reject a payment
     */
    public function rejectPayment($id, $adminId, $notes) {
        $query = "
            UPDATE payments 
            SET status = 'rejected', verified_by = ?, verified_date = NOW(), notes = ?
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isi', $adminId, $notes, $id);
        $result = $stmt->execute();
        
        if ($result) {
            $this->updateRelatedItemPaymentStatus($id, 'unpaid');
            $this->notifyPaymentRejected($id);
        }
        
        return $result;
    }
    
    /**
     * Update payment status of related document or booking
     */
    private function updateRelatedItemPaymentStatus($paymentId, $status) {
        $payment = $this->getPaymentById($paymentId);
        if (!$payment) return false;
        
        if ($payment['reference_type'] === 'document_request') {
            $query = "UPDATE document_requests SET payment_status = ? WHERE id = ?";
        } else {
            $query = "UPDATE bookings SET payment_status = ? WHERE id = ?";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $status, $payment['reference_id']);
        return $stmt->execute();
    }
    
    /**
     * Send notification - payment verified
     */
    private function notifyPaymentVerified($paymentId) {
        $payment = $this->getPaymentById($paymentId);
        if (!$payment) return false;
        
        // Create database notification
        $message = "Your payment of PHP " . number_format($payment['amount'], 2) . " (Transaction: {$payment['transaction_number']}) has been verified!";
        $this->createNotification($payment['user_id'], 'Payment Verified', $message, 'success');
        
        // Get user email
        $stmt = $this->conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $payment['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Send email notification
        if ($user) {
            $this->emailService->sendPaymentVerifiedEmail(
                $user['email'],
                $user['first_name'],
                $payment['amount'],
                $payment['transaction_number']
            );
        }
        
        return true;
    }
    
    /**
     * Send notification - payment rejected
     */
    private function notifyPaymentRejected($paymentId) {
        $payment = $this->getPaymentById($paymentId);
        if (!$payment) return false;
        
        // Create database notification
        $message = "Your payment (Transaction: {$payment['transaction_number']}) was rejected. Reason: {$payment['notes']}";
        $this->createNotification($payment['user_id'], 'Payment Rejected', $message, 'error');
        
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
