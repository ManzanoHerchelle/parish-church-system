<?php
/**
 * Booking Service
 * Handles all appointment/booking operations
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Booking.php';
require_once __DIR__ . '/EmailService.php';

use Models\Booking;

class BookingService {
    private $conn;
    private $emailService;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? getDBConnection();
        $this->emailService = new EmailService();
    }
    
    /**
     * Get all bookings with optional filtering
     */
    public function getBookings($filters = []) {
        $status = $filters['status'] ?? null;
        $userId = $filters['userId'] ?? null;
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        
        $query = "
            SELECT b.*, bt.name as booking_type, bt.fee,
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email
            FROM bookings b
            JOIN booking_types bt ON b.booking_type_id = bt.id
            JOIN users u ON b.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if ($status && $status !== 'all') {
            $query .= " AND b.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if ($userId) {
            $query .= " AND b.user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }
        
        $query .= " ORDER BY b.booking_date DESC, b.booking_time DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row; // return associative arrays for UI components
        }

        return $bookings;
    }
    
    /**
     * Get a single booking by ID
     */
    public function getBookingById($id) {
        $query = "
            SELECT b.*, bt.name as booking_type, bt.fee,
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email
            FROM bookings b
            JOIN booking_types bt ON b.booking_type_id = bt.id
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
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
     * Get pending bookings count
     */
    public function getPendingCount() {
        $query = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    /**
     * Approve a booking
     */
    public function approveBooking($id, $adminId) {
        $query = "
            UPDATE bookings 
            SET status = 'approved', approved_by = ?, approved_date = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $adminId, $id);
        $result = $stmt->execute();
        
        if ($result) {
            $this->notifyBookingApproved($id);
        }
        
        return $result;
    }
    
    /**
     * Reject a booking
     */
    public function rejectBooking($id, $adminId, $rejectionReason) {
        $query = "
            UPDATE bookings 
            SET status = 'rejected', approved_by = ?, approved_date = NOW(), 
                rejection_reason = ?
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isi', $adminId, $rejectionReason, $id);
        $result = $stmt->execute();
        
        if ($result) {
            $this->notifyBookingRejected($id);
        }
        
        return $result;
    }
    
    /**
     * Mark booking as completed
     */
    public function markAsCompleted($id, $adminId) {
        $query = "
            UPDATE bookings 
            SET status = 'completed'
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        
        return $result;
    }
    
    /**
     * Cancel a booking
     */
    public function cancelBooking($id, $reason, $userId) {
        $query = "
            UPDATE bookings 
            SET status = 'cancelled', cancellation_reason = ?
            WHERE id = ? AND user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sii', $reason, $id, $userId);
        return $stmt->execute();
    }
    
    /**
     * Send notification - booking approved
     */
    private function notifyBookingApproved($bookingId) {
        $booking = $this->getBookingById($bookingId);
        if (!$booking) return false;
        
        // Create database notification
        $message = "Your appointment ({$booking['reference_number']}) for {$booking['booking_type']} on " . date('M d, Y', strtotime($booking['booking_date'])) . " at " . date('g:i A', strtotime($booking['booking_time'])) . " has been approved!";
        $this->createNotification($booking['user_id'], 'Appointment Approved', $message, 'success');
        
        // Get user email
        $stmt = $this->conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $booking['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Send email notification
        if ($user) {
            $this->emailService->sendBookingApprovalEmail(
                $user['email'],
                $user['first_name'],
                $booking['booking_type'],
                $booking['booking_date'],
                $booking['booking_time'],
                $booking['reference_number']
            );
        }
        
        return true;
    }
    
    /**
     * Send notification - booking rejected
     */
    private function notifyBookingRejected($bookingId) {
        $booking = $this->getBookingById($bookingId);
        if (!$booking) return false;
        
        // Create database notification
        $message = "Your appointment ({$booking['reference_number']}) was rejected. Reason: {$booking['rejection_reason']}";
        $this->createNotification($booking['user_id'], 'Appointment Rejected', $message, 'error');
        
        // Get user email
        $stmt = $this->conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $booking['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Send email notification
        if ($user) {
            $this->emailService->sendBookingRejectionEmail(
                $user['email'],
                $user['first_name'],
                $booking['booking_type'],
                $booking['reference_number'],
                $booking['rejection_reason']
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
