<?php
/**
 * Booking Model
 * Represents an appointment/booking request
 */

namespace Models;

class Booking {
    public $id;
    public $userId;
    public $bookingTypeId;
    public $referenceNumber;
    public $bookingDate;
    public $bookingTime;
    public $endTime;
    public $purpose;
    public $specialRequests;
    public $status; // pending, approved, rejected, completed, cancelled
    public $paymentStatus; // unpaid, pending, paid
    public $paymentAmount;
    public $paymentProof;
    public $approvedBy;
    public $approvedDate;
    public $rejectionReason;
    public $cancellationReason;
    public $createdAt;
    public $updatedAt;
    
    // Related data
    public $clientName;
    public $bookingType;
    public $userEmail;
    public $fee;
    
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    public function isPending() {
        return $this->status === 'pending';
    }
    
    public function isApproved() {
        return $this->status === 'approved';
    }
    
    public function isRejected() {
        return $this->status === 'rejected';
    }
    
    public function isCompleted() {
        return $this->status === 'completed';
    }
    
    public function isCancelled() {
        return $this->status === 'cancelled';
    }
    
    public function isPaid() {
        return $this->paymentStatus === 'paid';
    }
    
    public function getStatusBadgeClass() {
        $classes = [
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'completed' => 'badge-primary',
            'cancelled' => 'badge-secondary'
        ];
        return $classes[$this->status] ?? 'badge-secondary';
    }
    
    public function getPaymentStatusBadgeClass() {
        $classes = [
            'unpaid' => 'badge-danger',
            'pending' => 'badge-warning',
            'paid' => 'badge-success'
        ];
        return $classes[$this->paymentStatus] ?? 'badge-secondary';
    }
    
    public function getFormattedDate() {
        return date('M d, Y', strtotime($this->bookingDate));
    }
    
    public function getFormattedTime() {
        return date('h:i A', strtotime($this->bookingTime));
    }
}
?>
