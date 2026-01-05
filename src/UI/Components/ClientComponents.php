<?php
class ClientDocumentCard {
    private $document;
    
    public function __construct($document) {
        $this->document = $document;
    }
    
    public function render() {
        $doc = $this->document;
        $statusClass = $this->getStatusClass($doc['status']);
        $statusBadge = $this->getStatusBadge($doc['status']);
        $paymentBadge = $this->getPaymentBadge($doc['payment_status']);
        
        $html = '
        <div class="card document-card ' . $statusClass . ' mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title">
                            <i class="bi bi-file-earmark-text text-primary"></i>
                            ' . htmlspecialchars($doc['document_type']) . '
                        </h5>
                        <p class="text-muted mb-2">
                            <small>Reference: <strong>' . htmlspecialchars($doc['reference_number']) . '</strong></small>
                        </p>
                        <p class="card-text">' . htmlspecialchars($doc['purpose']) . '</p>
                        
                        <div class="d-flex gap-2 mb-2">
                            ' . $statusBadge . '
                            ' . $paymentBadge . '
                        </div>
                        
                        <small class="text-muted">
                            <i class="bi bi-calendar"></i> Requested: ' . date('M d, Y', strtotime($doc['created_at'])) . '
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="mb-2">
                            <h6 class="text-muted mb-1">Fee</h6>
                            <h4 class="text-primary mb-0">₱' . number_format($doc['amount'], 2) . '</h4>
                        </div>';
        
        if ($doc['status'] === 'ready' && ($doc['payment_status'] === 'paid' || $doc['payment_status'] === 'waived')) {
            $html .= '
                        <a href="download-document.php?id=' . $doc['id'] . '" class="btn btn-success btn-sm">
                            <i class="bi bi-download"></i> Download
                        </a>
                        <a href="download-certificate.php?id=' . $doc['id'] . '" class="btn btn-info btn-sm ms-1">
                            <i class="bi bi-file-pdf"></i> Certificate
                        </a>';
        }
        
        if ($doc['payment_status'] === 'pending' && ($doc['status'] === 'pending' || $doc['status'] === 'processing')) {
            $html .= '
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#uploadPaymentModal' . $doc['id'] . '">
                            <i class="bi bi-upload"></i> Upload Proof
                        </button>';
        }
        
        if ($doc['status'] === 'rejected' && !empty($doc['rejection_reason'])) {
            $html .= '
                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectionModal' . $doc['id'] . '">
                            <i class="bi bi-info-circle"></i> View Reason
                        </button>';
        }
        
        $html .= '
                        <button class="btn btn-outline-primary btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#detailsModal' . $doc['id'] . '">
                            <i class="bi bi-eye"></i> Details
                        </button>
                    </div>
                </div>
            </div>
        </div>';
        
        // Details Modal
        $html .= '
        <div class="modal fade" id="detailsModal' . $doc['id'] . '" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Document Request Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Reference Number:</strong><br>
                                ' . htmlspecialchars($doc['reference_number']) . '
                            </div>
                            <div class="col-md-6">
                                <strong>Document Type:</strong><br>
                                ' . htmlspecialchars($doc['document_type']) . '
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Status:</strong><br>
                                ' . $statusBadge . '
                            </div>
                            <div class="col-md-6">
                                <strong>Payment Status:</strong><br>
                                ' . $paymentBadge . '
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong>Purpose:</strong><br>
                            ' . nl2br(htmlspecialchars($doc['purpose'])) . '
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Amount:</strong><br>
                                ₱' . number_format($doc['amount'], 2) . '
                            </div>
                            <div class="col-md-6">
                                <strong>Requested On:</strong><br>
                                ' . date('F d, Y g:i A', strtotime($doc['created_at'])) . '
                            </div>
                        </div>';
        
        if (!empty($doc['admin_notes'])) {
            $html .= '
                        <div class="alert alert-info">
                            <strong>Admin Notes:</strong><br>
                            ' . nl2br(htmlspecialchars($doc['admin_notes'])) . '
                        </div>';
        }
        
        $html .= '
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>';
        
        // Rejection Modal
        if ($doc['status'] === 'rejected' && !empty($doc['rejection_reason'])) {
            $html .= '
            <div class="modal fade" id="rejectionModal' . $doc['id'] . '" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Rejection Reason</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>' . nl2br(htmlspecialchars($doc['rejection_reason'])) . '</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>';
        }
        
        // Upload Payment Proof Modal
        if ($doc['payment_status'] === 'pending') {
            $html .= '
            <div class="modal fade" id="uploadPaymentModal' . $doc['id'] . '" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title">Upload Payment Proof</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="uploadFormDoc' . $doc['id'] . '" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" value="' . htmlspecialchars($doc['reference_number']) . '" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control" value="₱' . number_format($doc['amount'], 2) . '" disabled>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_proof_doc' . $doc['id'] . '" class="form-label">Payment Proof <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="payment_proof_doc' . $doc['id'] . '" name="payment_proof" 
                                           accept=".pdf,.jpg,.jpeg,.png" required>
                                    <div class="form-text">Upload bank receipt or payment confirmation (PDF, JPG, PNG - Max 5MB)</div>
                                </div>
                                <div id="uploadMessageDoc' . $doc['id'] . '"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
            document.getElementById("uploadFormDoc' . $doc['id'] . '").addEventListener("submit", function(e) {
                e.preventDefault();
                const formData = new FormData();
                formData.append("document_id", ' . $doc['id'] . ');
                formData.append("type", "document");
                formData.append("payment_proof", document.getElementById("payment_proof_doc' . $doc['id'] . '").files[0]);
                
                const messageDiv = document.getElementById("uploadMessageDoc' . $doc['id'] . '");
                messageDiv.innerHTML = \'<div class="alert alert-info">Uploading...</div>\';
                
                fetch("upload-payment-proof.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.innerHTML = \'<div class="alert alert-success">\' + data.message + \'</div>\';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        messageDiv.innerHTML = \'<div class="alert alert-danger">\' + data.message + \'</div>\';
                    }
                })
                .catch(error => {
                    messageDiv.innerHTML = \'<div class="alert alert-danger">Upload failed</div>\';
                });
            });
            </script>';
        }
        
        return $html;
    }
    
    private function getStatusClass($status) {
        $classes = [
            'pending' => 'document-card-pending',
            'processing' => 'document-card-processing',
            'ready' => 'document-card-ready',
            'completed' => 'document-card-completed',
            'rejected' => 'document-card-rejected'
        ];
        return $classes[$status] ?? '';
    }
    
    private function getStatusBadge($status) {
        $badges = [
            'pending' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending</span>',
            'processing' => '<span class="badge bg-info"><i class="bi bi-hourglass-split"></i> Processing</span>',
            'ready' => '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Ready</span>',
            'completed' => '<span class="badge bg-secondary"><i class="bi bi-check-all"></i> Completed</span>',
            'rejected' => '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
    
    private function getPaymentBadge($status) {
        $badges = [
            'pending' => '<span class="badge bg-warning text-dark">Payment Pending</span>',
            'paid' => '<span class="badge bg-success">Paid</span>',
            'waived' => '<span class="badge bg-info">Waived</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

class ClientBookingCard {
    private $booking;
    
    public function __construct($booking) {
        $this->booking = $booking;
    }
    
    public function render() {
        $b = $this->booking;
        $statusClass = $this->getStatusClass($b['status']);
        $statusBadge = $this->getStatusBadge($b['status']);
        $paymentBadge = $this->getPaymentBadge($b['payment_status']);
        
        $html = '
        <div class="card booking-card ' . $statusClass . ' mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title">
                            <i class="bi bi-calendar-check text-primary"></i>
                            ' . htmlspecialchars($b['booking_type']) . '
                        </h5>
                        <p class="text-muted mb-2">
                            <small>Reference: <strong>' . htmlspecialchars($b['reference_number']) . '</strong></small>
                        </p>
                        
                        <div class="mb-2">
                            <i class="bi bi-calendar3"></i> ' . date('F d, Y', strtotime($b['booking_date'])) . '<br>
                            <i class="bi bi-clock"></i> ' . date('g:i A', strtotime($b['booking_time'])) . '
                        </div>
                        
                        <p class="card-text">' . htmlspecialchars($b['purpose']) . '</p>
                        
                        <div class="d-flex gap-2 mb-2">
                            ' . $statusBadge . '
                            ' . $paymentBadge . '
                        </div>
                        
                        <small class="text-muted">
                            <i class="bi bi-calendar"></i> Requested: ' . date('M d, Y', strtotime($b['created_at'])) . '
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="mb-2">
                            <h6 class="text-muted mb-1">Fee</h6>
                            <h4 class="text-primary mb-0">₱' . number_format($b['amount'], 2) . '</h4>
                        </div>';
        
        if ($b['status'] === 'pending') {
            $html .= '
                        <form method="POST" style="display: inline;" onsubmit="return confirm(\'Cancel this appointment?\');">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="booking_id" value="' . $b['id'] . '">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                        </form>';
        }
        
        if ($b['payment_status'] === 'pending' && ($b['status'] === 'pending' || $b['status'] === 'approved')) {
            $html .= '
                        <button class="btn btn-warning btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#uploadPaymentModalBook' . $b['id'] . '">
                            <i class="bi bi-upload"></i> Upload Proof
                        </button>';
        }
        
        if ($b['status'] === 'rejected' && !empty($b['rejection_reason'])) {
            $html .= '
                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectionModal' . $b['id'] . '">
                            <i class="bi bi-info-circle"></i> View Reason
                        </button>';
        }
        
        $html .= '
                        <button class="btn btn-outline-primary btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#detailsModal' . $b['id'] . '">
                            <i class="bi bi-eye"></i> Details
                        </button>
                    </div>
                </div>
            </div>
        </div>';
        
        // Details Modal
        $html .= '
        <div class="modal fade" id="detailsModal' . $b['id'] . '" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Appointment Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Reference Number:</strong><br>
                                ' . htmlspecialchars($b['reference_number']) . '
                            </div>
                            <div class="col-md-6">
                                <strong>Booking Type:</strong><br>
                                ' . htmlspecialchars($b['booking_type']) . '
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Date & Time:</strong><br>
                                ' . date('F d, Y', strtotime($b['booking_date'])) . ' at ' . date('g:i A', strtotime($b['booking_time'])) . '
                            </div>
                            <div class="col-md-6">
                                <strong>Duration:</strong><br>
                                ' . $b['duration_minutes'] . ' minutes
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Status:</strong><br>
                                ' . $statusBadge . '
                            </div>
                            <div class="col-md-6">
                                <strong>Payment Status:</strong><br>
                                ' . $paymentBadge . '
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong>Purpose:</strong><br>
                            ' . nl2br(htmlspecialchars($b['purpose'])) . '
                        </div>';
        
        if (!empty($b['special_requests'])) {
            $html .= '
                        <div class="mb-3">
                            <strong>Special Requests:</strong><br>
                            ' . nl2br(htmlspecialchars($b['special_requests'])) . '
                        </div>';
        }
        
        if (!empty($b['admin_notes'])) {
            $html .= '
                        <div class="alert alert-info">
                            <strong>Admin Notes:</strong><br>
                            ' . nl2br(htmlspecialchars($b['admin_notes'])) . '
                        </div>';
        }
        
        $html .= '
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>';
        
        // Rejection Modal
        if ($b['status'] === 'rejected' && !empty($b['rejection_reason'])) {
            $html .= '
            <div class="modal fade" id="rejectionModal' . $b['id'] . '" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Rejection Reason</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>' . nl2br(htmlspecialchars($b['rejection_reason'])) . '</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>';
        }
        
        // Upload Payment Proof Modal
        if ($b['payment_status'] === 'pending') {
            $html .= '
            <div class="modal fade" id="uploadPaymentModalBook' . $b['id'] . '" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title">Upload Payment Proof</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="uploadFormBook' . $b['id'] . '" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" value="' . htmlspecialchars($b['reference_number']) . '" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control" value="₱' . number_format($b['amount'], 2) . '" disabled>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_proof_book' . $b['id'] . '" class="form-label">Payment Proof <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="payment_proof_book' . $b['id'] . '" name="payment_proof" 
                                           accept=".pdf,.jpg,.jpeg,.png" required>
                                    <div class="form-text">Upload bank receipt or payment confirmation (PDF, JPG, PNG - Max 5MB)</div>
                                </div>
                                <div id="uploadMessageBook' . $b['id'] . '"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
            document.getElementById("uploadFormBook' . $b['id'] . '").addEventListener("submit", function(e) {
                e.preventDefault();
                const formData = new FormData();
                formData.append("document_id", ' . $b['id'] . ');
                formData.append("type", "booking");
                formData.append("payment_proof", document.getElementById("payment_proof_book' . $b['id'] . '").files[0]);
                
                const messageDiv = document.getElementById("uploadMessageBook' . $b['id'] . '");
                messageDiv.innerHTML = \'<div class="alert alert-info">Uploading...</div>\';
                
                fetch("upload-payment-proof.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.innerHTML = \'<div class="alert alert-success">\' + data.message + \'</div>\';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        messageDiv.innerHTML = \'<div class="alert alert-danger">\' + data.message + \'</div>\';
                    }
                })
                .catch(error => {
                    messageDiv.innerHTML = \'<div class="alert alert-danger">Upload failed</div>\';
                });
            });
            </script>';
        }
        
        return $html;
    }
    
    private function getStatusClass($status) {
        $classes = [
            'pending' => 'booking-card-pending',
            'approved' => 'booking-card-approved',
            'completed' => 'booking-card-completed',
            'rejected' => 'booking-card-rejected',
            'cancelled' => 'booking-card-cancelled'
        ];
        return $classes[$status] ?? '';
    }
    
    private function getStatusBadge($status) {
        $badges = [
            'pending' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending</span>',
            'approved' => '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Approved</span>',
            'completed' => '<span class="badge bg-secondary"><i class="bi bi-check-all"></i> Completed</span>',
            'rejected' => '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>',
            'cancelled' => '<span class="badge bg-dark"><i class="bi bi-x-circle"></i> Cancelled</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
    
    private function getPaymentBadge($status) {
        $badges = [
            'pending' => '<span class="badge bg-warning text-dark">Payment Pending</span>',
            'paid' => '<span class="badge bg-success">Paid</span>',
            'waived' => '<span class="badge bg-info">Waived</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

class QuickActionCard {
    private $title;
    private $icon;
    private $description;
    private $link;
    private $color;
    
    public function __construct($title, $icon, $description, $link, $color = 'primary') {
        $this->title = $title;
        $this->icon = $icon;
        $this->description = $description;
        $this->link = $link;
        $this->color = $color;
    }
    
    public function render() {
        return '
        <div class="col-md-6 col-lg-3 mb-4">
            <a href="' . htmlspecialchars($this->link) . '" class="text-decoration-none">
                <div class="card quick-action-card h-100 border-' . $this->color . '">
                    <div class="card-body text-center">
                        <div class="display-4 text-' . $this->color . ' mb-3">
                            <i class="bi bi-' . $this->icon . '"></i>
                        </div>
                        <h5 class="card-title text-dark">' . htmlspecialchars($this->title) . '</h5>
                        <p class="card-text text-muted">' . htmlspecialchars($this->description) . '</p>
                    </div>
                </div>
            </a>
        </div>';
    }
}
