<?php
/**
 * Payment specific UI components
 */

class PaymentCard {
    private $payment;
    
    public function __construct($payment) {
        $this->payment = $payment;
    }
    
    public function render() {
        $status = $this->payment['status'];
        $statusClass = "payment-card-$status";
        $amount = $this->payment['amount'];
        $formattedAmount = '$' . number_format($amount, 2);
        
        return <<<HTML
<div class="card document-card $statusClass">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="document-reference">{$this->payment['transaction_number']}</div>
                <small class="text-muted">
                    <i class="bi bi-person"></i> {$this->payment['client_name']}<br>
                    <i class="bi bi-envelope"></i> {$this->payment['user_email']}
                </small>
            </div>
            <div class="col-md-3">
                <div>
                    <strong>{$this->payment['item_name']}</strong>
                </div>
                <small class="text-muted">
                    Amount: <strong>$formattedAmount</strong><br>
                    Method: {$this->getPaymentMethodBadge()}
                </small>
            </div>
            <div class="col-md-2">
                <div>
                    {$this->getStatusBadge()}
                </div>
                <small class="text-muted">
                    {$this->getFormattedDate()}
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
        $status = $this->payment['status'];
        $badgeClass = match($status) {
            'pending' => 'bg-warning',
            'paid' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-secondary'
        };
        $statusText = ucfirst($status);
        return "<span class=\"badge $badgeClass status-badge\">$statusText</span>";
    }
    
    private function getPaymentMethodBadge() {
        $method = $this->payment['payment_method'];
        $badgeClass = match($method) {
            'bank_transfer' => 'badge bg-primary',
            'gcash' => 'badge bg-info',
            'paymaya' => 'badge bg-info',
            'over_counter' => 'badge bg-secondary',
            default => 'badge bg-secondary'
        };
        $methodName = match($method) {
            'bank_transfer' => 'Bank Transfer',
            'gcash' => 'GCash',
            'paymaya' => 'PayMaya',
            'over_counter' => 'Over Counter',
            default => ucfirst($method)
        };
        return "<span class=\"$badgeClass\">$methodName</span>";
    }
    
    private function getFormattedDate() {
        return date('M d, Y', strtotime($this->payment['created_at']));
    }
    
    private function getActionButtons() {
        $buttons = '';
        $paymentId = $this->payment['id'];
        $status = $this->payment['status'];
        
        if ($status === 'pending') {
            $buttons .= <<<HTML
<button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" 
        data-bs-target="#verifyModal$paymentId" title="Verify this payment">
    <i class="bi bi-check-circle"></i> Verify
</button>
<button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
        data-bs-target="#rejectModal$paymentId" title="Reject this payment">
    <i class="bi bi-x-circle"></i> Reject
</button>
HTML;
        }
        
        if (!empty($this->payment['payment_proof'])) {
            $buttons .= <<<HTML
<a href="/documentSystem/uploads/payments/{$this->payment['payment_proof']}" 
   target="_blank" class="btn btn-sm btn-secondary" title="View payment proof">
    <i class="bi bi-image"></i> Proof
</a>
HTML;
        }
        
        $buttons .= <<<HTML
<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
        data-bs-target="#viewModal$paymentId" title="View full details">
    <i class="bi bi-eye"></i> View
</button>
HTML;
        
        return $buttons;
    }
}

class PaymentVerifyModal {
    private $paymentId;
    
    public function __construct($paymentId) {
        $this->paymentId = $paymentId;
    }
    
    public function render() {
        $id = "verifyModal{$this->paymentId}";
        
        return <<<HTML
<div class="modal fade" id="$id" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="payment_id" value="$this->paymentId">
                    <div class="mb-3">
                        <label for="notes$this->paymentId" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes$this->paymentId" name="notes" rows="2"></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Verifying this payment will mark it as paid and notify the client.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Verify Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;
    }
}

class PaymentRejectModal {
    private $paymentId;
    
    public function __construct($paymentId) {
        $this->paymentId = $paymentId;
    }
    
    public function render() {
        $id = "rejectModal{$this->paymentId}";
        
        return <<<HTML
<div class="modal fade" id="$id" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="payment_id" value="$this->paymentId">
                    <div class="mb-3">
                        <label for="notes$this->paymentId" class="form-label">Rejection Reason</label>
                        <textarea class="form-control" id="notes$this->paymentId" name="notes" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;
    }
}

class PaymentDetailsModal {
    private $payment;
    
    public function __construct($payment) {
        $this->payment = $payment;
    }
    
    public function render() {
        $id = "viewModal{$this->payment['id']}";
        
        $status = $this->payment['status'];
        $badgeClass = match($status) {
            'pending' => 'bg-warning',
            'paid' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-secondary'
        };
        $statusBadge = '<span class="badge ' . $badgeClass . '">' . ucfirst($status) . '</span>';
        
        $notesDisplay = '';
        if (!empty($this->payment['notes'])) {
            $notesType = ($status === 'rejected') ? 'alert-danger' : 'alert-info';
            $notesDisplay = <<<HTML
<div class="alert $notesType">
    <strong>Notes:</strong><br>
    {$this->payment['notes']}
</div>
HTML;
        }
        
        $proofDisplay = '';
        if (!empty($this->payment['payment_proof'])) {
            $proofDisplay = <<<HTML
<div class="mb-3">
    <strong>Payment Proof:</strong><br>
    <a href="/documentSystem/uploads/payments/{$this->payment['payment_proof']}" target="_blank" class="btn btn-sm btn-primary">
        <i class="bi bi-image"></i> View Proof
    </a>
</div>
HTML;
        }
        
        $formattedAmount = '$' . number_format($this->payment['amount'], 2);
        $createdDate = date('M d, Y h:i A', strtotime($this->payment['created_at']));
        
        $methodName = match($this->payment['payment_method']) {
            'bank_transfer' => 'Bank Transfer',
            'gcash' => 'GCash',
            'paymaya' => 'PayMaya',
            'over_counter' => 'Over Counter',
            default => ucfirst($this->payment['payment_method'])
        };
        
        return <<<HTML
<div class="modal fade" id="$id" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Transaction Number:</strong><br>
                        <code>{$this->payment['transaction_number']}</code>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        $statusBadge
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Client:</strong><br>
                        {$this->payment['client_name']}<br>
                        <small class="text-muted">{$this->payment['user_email']}</small>
                    </div>
                    <div class="col-md-6">
                        <strong>Amount:</strong><br>
                        <h4>$formattedAmount</h4>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Payment Method:</strong><br>
                        <span class="badge bg-primary">$methodName</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Date:</strong><br>
                        $createdDate
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Related Item:</strong><br>
                    {$this->payment['item_name']}
                </div>
                $proofDisplay
                $notesDisplay
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
?>
