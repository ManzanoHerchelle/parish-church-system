<?php
/**
 * Booking/Appointment specific UI components
 */

class BookingCard {
    private $booking;
    
    public function __construct($booking) {
        $this->booking = $booking;
    }
    
    public function render() {
        $status = $this->booking['status'];
        $statusClass = "booking-card-$status";
        $formattedDate = date('M d, Y', strtotime($this->booking['booking_date']));
        $formattedTime = date('h:i A', strtotime($this->booking['booking_time']));
        
        return <<<HTML
<div class="card document-card $statusClass">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="document-reference">{$this->booking['reference_number']}</div>
                <small class="text-muted">
                    <i class="bi bi-person"></i> {$this->booking['client_name']}<br>
                    <i class="bi bi-envelope"></i> {$this->booking['user_email']}
                </small>
            </div>
            <div class="col-md-3">
                <div>
                    <strong>{$this->booking['booking_type']}</strong>
                </div>
                <small class="text-muted">
                    <i class="bi bi-calendar"></i> $formattedDate<br>
                    <i class="bi bi-clock"></i> $formattedTime
                </small>
            </div>
            <div class="col-md-2">
                <div>
                    {$this->getStatusBadge()}
                </div>
                <small class="text-muted">
                    Payment: {$this->getPaymentBadge()}
                </small>
            </div>
            <div class="col-md-4">
                <div class="action-buttons">
                    {$this->getActionButtons()}
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }
    
    private function getStatusBadge() {
        $status = $this->booking['status'];
        $badgeClass = match($status) {
            'pending' => 'bg-warning',
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'completed' => 'bg-primary',
            default => 'bg-secondary'
        };
        $statusText = ucfirst($status);
        return "<span class=\"badge $badgeClass status-badge\">$statusText</span>";
    }
    
    private function getPaymentBadge() {
        $paymentStatus = $this->booking['payment_status'];
        $badgeClass = match($paymentStatus) {
            'unpaid' => 'bg-danger',
            'pending' => 'bg-warning',
            'paid' => 'bg-success',
            default => 'bg-secondary'
        };
        $statusText = ucfirst($paymentStatus);
        return "<span class=\"badge $badgeClass\">$statusText</span>";
    }
    
    private function getActionButtons() {
        $buttons = '';
        $bookingId = $this->booking['id'];
        $status = $this->booking['status'];
        
        if ($status === 'pending') {
            $buttons .= <<<HTML
<form method="POST" style="display: inline;">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="booking_id" value="$bookingId">
    <button type="submit" class="btn btn-sm btn-success" title="Approve this appointment">
        <i class="bi bi-check"></i> Approve
    </button>
</form>
<button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
        data-bs-target="#rejectModal$bookingId" title="Reject this appointment">
    <i class="bi bi-x"></i> Reject
</button>
HTML;
        } elseif ($status === 'approved') {
            $buttons .= <<<HTML
<form method="POST" style="display: inline;">
    <input type="hidden" name="action" value="mark_completed">
    <input type="hidden" name="booking_id" value="$bookingId">
    <button type="submit" class="btn btn-sm btn-primary" title="Mark as completed">
        <i class="bi bi-check-circle"></i> Complete
    </button>
</form>
HTML;
        }
        
        $buttons .= <<<HTML
<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
        data-bs-target="#viewModal$bookingId" title="View full details">
    <i class="bi bi-eye"></i> View
</button>
HTML;
        
        return $buttons;
    }
}

class BookingRejectModal {
    private $bookingId;
    
    public function __construct($bookingId) {
        $this->bookingId = $bookingId;
    }
    
    public function render() {
        $id = "rejectModal{$this->bookingId}";
        
        return <<<HTML
<div class="modal fade" id="$id" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="booking_id" value="$this->bookingId">
                    <div class="mb-3">
                        <label for="reason$this->bookingId" class="form-label">Rejection Reason</label>
                        <textarea class="form-control" id="reason$this->bookingId" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;
    }
}

class BookingDetailsModal {
    private $booking;
    
    public function __construct($booking) {
        $this->booking = $booking;
    }
    
    public function render() {
        $id = "viewModal{$this->booking['id']}";
        
        $status = $this->booking['status'];
        $statusBadgeClass = match($status) {
            'pending' => 'bg-warning',
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'completed' => 'bg-primary',
            default => 'bg-secondary'
        };
        $statusBadge = '<span class="badge ' . $statusBadgeClass . '">' . ucfirst($status) . '</span>';
        
        $paymentStatus = $this->booking['payment_status'];
        $paymentBadgeClass = match($paymentStatus) {
            'unpaid' => 'bg-danger',
            'pending' => 'bg-warning',
            'paid' => 'bg-success',
            default => 'bg-secondary'
        };
        $paymentBadge = '<span class="badge ' . $paymentBadgeClass . '">' . ucfirst($paymentStatus) . '</span>';
        
        $rejectionAlert = '';
        if ($status === 'rejected' && !empty($this->booking['rejection_reason'])) {
            $rejectionAlert = <<<HTML
<div class="alert alert-danger">
    <strong>Rejection Reason:</strong><br>
    {$this->booking['rejection_reason']}
</div>
HTML;
        }
        
        $cancellationAlert = '';
        if ($status === 'cancelled' && !empty($this->booking['cancellation_reason'])) {
            $cancellationAlert = <<<HTML
<div class="alert alert-secondary">
    <strong>Cancellation Reason:</strong><br>
    {$this->booking['cancellation_reason']}
</div>
HTML;
        }
        
        $purpose = htmlspecialchars($this->booking['purpose'] ?? '') ?: '<em class="text-muted">None provided</em>';
        $specialRequests = htmlspecialchars($this->booking['special_requests'] ?? '') ?: '<em class="text-muted">None provided</em>';
        $bookingDate = date('M d, Y', strtotime($this->booking['booking_date']));
        $bookingTime = date('h:i A', strtotime($this->booking['booking_time']));
        $createdDate = date('M d, Y h:i A', strtotime($this->booking['created_at']));
        
        return <<<HTML
<div class="modal fade" id="$id" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Reference:</strong><br>
                        <code>{$this->booking['reference_number']}</code>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        $statusBadge
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Client:</strong><br>
                        {$this->booking['client_name']}<br>
                        <small class="text-muted">{$this->booking['user_email']}</small>
                    </div>
                    <div class="col-md-6">
                        <strong>Booking Type:</strong><br>
                        {$this->booking['booking_type']}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Date:</strong><br>
                        $bookingDate
                    </div>
                    <div class="col-md-6">
                        <strong>Time:</strong><br>
                        $bookingTime
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Purpose:</strong><br>
                    $purpose
                </div>
                <div class="mb-3">
                    <strong>Special Requests:</strong><br>
                    $specialRequests
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Booking Date:</strong><br>
                        $createdDate
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Status:</strong><br>
                        $paymentBadge
                    </div>
                </div>
                $rejectionAlert
                $cancellationAlert
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
?>
