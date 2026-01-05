<?php
/**
 * Document Model
 * Represents a document request with data validation and manipulation
 */

namespace Models;

class Document {
    public $id;
    public $userId;
    public $documentTypeId;
    public $referenceNumber;
    public $purpose;
    public $additionalNotes;
    public $status; // pending, processing, ready, completed, rejected
    public $paymentStatus; // unpaid, pending, paid
    public $paymentAmount;
    public $paymentProof;
    public $processedBy;
    public $processedDate;
    public $rejectionReason;
    public $documentFile;
    public $createdAt;
    public $updatedAt;
    
    // Related data (populated from joins)
    public $clientName;
    public $documentType;
    public $userEmail;
    public $userName;
    
    /**
     * Constructor
     */
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Check if document is pending approval
     */
    public function isPending() {
        return $this->status === 'pending';
    }
    
    /**
     * Check if document is ready for pickup
     */
    public function isReady() {
        return $this->status === 'ready';
    }
    
    /**
     * Check if document is completed
     */
    public function isCompleted() {
        return $this->status === 'completed';
    }
    
    /**
     * Check if document was rejected
     */
    public function isRejected() {
        return $this->status === 'rejected';
    }
    
    /**
     * Check if payment is pending
     */
    public function isPaidPending() {
        return $this->paymentStatus === 'pending' || $this->paymentStatus === 'unpaid';
    }
    
    /**
     * Check if payment is confirmed
     */
    public function isPaid() {
        return $this->paymentStatus === 'paid';
    }
    
    /**
     * Get status badge class for display
     */
    public function getStatusBadgeClass() {
        $classes = [
            'pending' => 'badge-warning',
            'processing' => 'badge-info',
            'ready' => 'badge-success',
            'completed' => 'badge-success',
            'rejected' => 'badge-danger'
        ];
        return $classes[$this->status] ?? 'badge-secondary';
    }
    
    /**
     * Get payment status badge class
     */
    public function getPaymentStatusBadgeClass() {
        $classes = [
            'unpaid' => 'badge-danger',
            'pending' => 'badge-warning',
            'paid' => 'badge-success'
        ];
        return $classes[$this->paymentStatus] ?? 'badge-secondary';
    }
}
?>
