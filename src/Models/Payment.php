<?php
/**
 * Payment Model
 * Represents a payment record
 */

namespace Models;

class Payment {
    public $id;
    public $userId;
    public $referenceType; // document_request, booking
    public $referenceId;
    public $transactionNumber;
    public $amount;
    public $paymentMethod; // cash, online, bank_transfer, gcash, paymaya
    public $paymentProof;
    public $status; // pending, verified, rejected
    public $verifiedBy;
    public $verifiedDate;
    public $notes;
    public $createdAt;
    public $updatedAt;
    
    // Related data
    public $clientName;
    public $userEmail;
    public $itemName;
    public $referenceNumber;
    
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
    
    public function isVerified() {
        return $this->status === 'verified';
    }
    
    public function isRejected() {
        return $this->status === 'rejected';
    }
    
    public function getStatusBadgeClass() {
        $classes = [
            'pending' => 'badge-warning',
            'verified' => 'badge-success',
            'rejected' => 'badge-danger'
        ];
        return $classes[$this->status] ?? 'badge-secondary';
    }
    
    public function getPaymentMethodBadge() {
        $methods = [
            'cash' => '<span class="badge bg-success"><i class="bi bi-cash"></i> Cash</span>',
            'online' => '<span class="badge bg-primary"><i class="bi bi-credit-card"></i> Online</span>',
            'bank_transfer' => '<span class="badge bg-info"><i class="bi bi-bank"></i> Bank Transfer</span>',
            'gcash' => '<span class="badge bg-primary"><i class="bi bi-phone"></i> GCash</span>',
            'paymaya' => '<span class="badge bg-success"><i class="bi bi-phone"></i> PayMaya</span>'
        ];
        return $methods[$this->paymentMethod] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
    
    public function getFormattedAmount() {
        return '₱' . number_format($this->amount, 2);
    }
}
?>
