<?php
/**
 * Card Component
 * Reusable card for displaying content
 */

class Card {
    private $header = '';
    private $body = '';
    private $footer = '';
    private $class = '';
    private $style = '';
    
    public function __construct($header = '') {
        $this->header = $header;
    }
    
    public function setHeader($header) {
        $this->header = $header;
        return $this;
    }
    
    public function setBody($body) {
        $this->body = $body;
        return $this;
    }
    
    public function setFooter($footer) {
        $this->footer = $footer;
        return $this;
    }
    
    public function addClass($class) {
        $this->class .= ' ' . $class;
        return $this;
    }
    
    public function setStyle($style) {
        $this->style = $style;
        return $this;
    }
    
    public function render() {
        $style = $this->style ? " style=\"{$this->style}\"" : '';
        $class = trim("card {$this->class}");
        
        $html = "<div class=\"{$class}\"{$style}>";
        
        if ($this->header) {
            $html .= "<div class=\"card-header bg-light\"><h5 class=\"mb-0\">{$this->header}</h5></div>";
        }
        
        if ($this->body) {
            $html .= "<div class=\"card-body\">{$this->body}</div>";
        }
        
        if ($this->footer) {
            $html .= "<div class=\"card-footer bg-light\">{$this->footer}</div>";
        }
        
        $html .= "</div>";
        
        return $html;
    }
}

class DocumentCard {
    private $document;
    
    public function __construct($document) {
        $this->document = $document;
    }
    
    public function render() {
        $statusClass = "document-card-{$this->document['status']}";
        
        return <<<HTML
<div class="card document-card $statusClass">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="document-reference">{$this->document['reference_number']}</div>
                <small class="text-muted">
                    <i class="bi bi-person"></i> {$this->document['client_name']}<br>
                    <i class="bi bi-envelope"></i> {$this->document['user_email']}
                </small>
            </div>
            <div class="col-md-3">
                <div>
                    <strong>{$this->document['document_type']}</strong>
                </div>
                <small class="text-muted">
                    Requested: {$this->getFormattedDate()}
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
            <div class="col-md-3">
                <div class="action-buttons">
                    {$this->getActionButtons()}
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }
    
    private function getFormattedDate() {
        return date('M d, Y', strtotime($this->document['created_at']));
    }
    
    private function getStatusBadge() {
        $status = $this->document['status'];
        $badgeClass = match($status) {
            'pending' => 'bg-warning',
            'processing' => 'bg-info',
            'ready' => 'bg-primary',
            'completed' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-secondary'
        };
        $statusText = ucfirst($status);
        return "<span class=\"badge $badgeClass status-badge\">$statusText</span>";
    }
    
    private function getPaymentBadge() {
        $paymentStatus = $this->document['payment_status'];
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
        $docId = $this->document['id'];
        $status = $this->document['status'];
        
        if ($status === 'pending') {
            $buttons .= <<<HTML
<form method="POST" style="display: inline;">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="document_id" value="$docId">
    <button type="submit" class="btn btn-sm btn-success" title="Approve this request">
        <i class="bi bi-check"></i> Approve
    </button>
</form>
<button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
        data-bs-target="#rejectModal$docId" title="Reject this request">
    <i class="bi bi-x"></i> Reject
</button>
HTML;
        } elseif ($status === 'processing') {
            $buttons .= <<<HTML
<form method="POST" style="display: inline;">
    <input type="hidden" name="action" value="mark_ready">
    <input type="hidden" name="document_id" value="$docId">
    <button type="submit" class="btn btn-sm btn-primary" title="Mark as ready for pickup">
        <i class="bi bi-check-circle"></i> Ready
    </button>
</form>
HTML;
        } elseif ($status === 'ready') {
            $buttons .= <<<HTML
<form method="POST" style="display: inline;">
    <input type="hidden" name="action" value="mark_completed">
    <input type="hidden" name="document_id" value="$docId">
    <button type="submit" class="btn btn-sm btn-success" title="Mark as completed">
        <i class="bi bi-check2-circle"></i> Complete
    </button>
</form>
HTML;
        }
        
        $buttons .= <<<HTML
<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
        data-bs-target="#viewModal$docId" title="View full details">
    <i class="bi bi-eye"></i> View
</button>
HTML;
        
        return $buttons;
    }
}

class StatusFilterTabs {
    private $currentFilter = 'pending';
    private $filters = [];
    
    public function __construct($currentFilter, $filters) {
        $this->currentFilter = $currentFilter;
        $this->filters = $filters;
    }
    
    public function render() {
        $html = '<div class="card mb-4"><div class="card-body"><div class="btn-group" role="group">';
        
        foreach ($this->filters as $key => $label) {
            // Skip count keys, only process status labels
            if (strpos($key, '_count') !== false) {
                continue;
            }
            
            $count = $this->filters[$key . '_count'] ?? 0;
            $active = $this->currentFilter === $key ? 'active' : '';
            $badgeColor = $this->getBadgeColor($key);
            
            $html .= <<<HTML
<a href="?status=$key" class="btn btn-outline-secondary $active">
    $label <span class="badge $badgeColor ms-2">$count</span>
</a>
HTML;
        }
        
        $html .= '</div></div></div>';
        return $html;
    }
    
    private function getBadgeColor($status) {
        $colors = [
            'all' => 'bg-secondary',
            'pending' => 'bg-warning',
            'processing' => 'bg-info',
            'ready' => 'bg-success',
            'completed' => 'bg-primary',
            'rejected' => 'bg-danger'
        ];
        return $colors[$status] ?? 'bg-secondary';
    }
}

class Alert {
    private $message = '';
    private $type = 'info'; // info, success, danger, warning
    private $dismissible = true;
    
    public function __construct($message, $type = 'info') {
        $this->message = $message;
        $this->type = $type;
    }
    
    public function setDismissible($dismissible) {
        $this->dismissible = $dismissible;
        return $this;
    }
    
    public function render() {
        if (!$this->message) return '';
        
        $dismissBtn = $this->dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';
        $class = "alert-" . $this->type;
        $dismissClass = $this->dismissible ? ' alert-dismissible fade show' : '';
        
        return <<<HTML
<div class="alert $class$dismissClass" role="alert">
    {$this->message}
    $dismissBtn
</div>
HTML;
    }
}

class Modal {
    private $id = '';
    private $title = '';
    private $body = '';
    private $footer = '';
    private $size = 'modal-md'; // modal-sm, modal-md, modal-lg, modal-xl
    
    public function __construct($id, $title) {
        $this->id = $id;
        $this->title = $title;
    }
    
    public function setBody($body) {
        $this->body = $body;
        return $this;
    }
    
    public function setFooter($footer) {
        $this->footer = $footer;
        return $this;
    }
    
    public function setSize($size) {
        $this->size = $size;
        return $this;
    }
    
    public function render() {
        $footer = $this->footer ? "<div class=\"modal-footer\">$this->footer</div>" : '';
        
        return <<<HTML
<div class="modal fade" id="$this->id" tabindex="-1">
    <div class="modal-dialog $this->size">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">$this->title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                $this->body
            </div>
            $footer
        </div>
    </div>
</div>
HTML;
    }
}

class RejectModal {
    private $documentId;
    
    public function __construct($documentId) {
        $this->documentId = $documentId;
    }
    
    public function render() {
        $id = "rejectModal{$this->documentId}";
        
        return <<<HTML
<div class="modal fade" id="$id" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Document Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="document_id" value="$this->documentId">
                    <div class="mb-3">
                        <label for="reason$this->documentId" class="form-label">Rejection Reason</label>
                        <textarea class="form-control" id="reason$this->documentId" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;
    }
}

class DocumentDetailsModal {
    private $document;
    
    public function __construct($document) {
        $this->document = $document;
    }
    
    public function render() {
        $id = "viewModal{$this->document['id']}";
        $status = $this->document['status'];
        $statusBadgeClass = match($status) {
            'pending' => 'bg-warning',
            'processing' => 'bg-info',
            'ready' => 'bg-primary',
            'completed' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-secondary'
        };
        $statusBadge = '<span class="badge ' . $statusBadgeClass . '">' . ucfirst($status) . '</span>';
        
        $paymentStatus = $this->document['payment_status'];
        $paymentBadgeClass = match($paymentStatus) {
            'unpaid' => 'bg-danger',
            'pending' => 'bg-warning',
            'paid' => 'bg-success',
            default => 'bg-secondary'
        };
        $paymentBadge = '<span class="badge ' . $paymentBadgeClass . '">' . ucfirst($paymentStatus) . '</span>';
        
        $rejectionAlert = '';
        if ($status === 'rejected' && !empty($this->document['rejection_reason'])) {
            $rejectionAlert = <<<HTML
<div class="alert alert-danger">
    <strong>Rejection Reason:</strong><br>
    {$this->document['rejection_reason']}
</div>
HTML;
        }
        
        $purpose = htmlspecialchars($this->document['purpose'] ?? '') ?: '<em class="text-muted">None provided</em>';
        $notes = htmlspecialchars($this->document['additional_notes'] ?? '') ?: '<em class="text-muted">None provided</em>';
        $processedDate = !empty($this->document['processed_date']) ? date('M d, Y h:i A', strtotime($this->document['processed_date'])) : 'Not yet processed';
        
        return <<<HTML
<div class="modal fade" id="$id" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Document Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Reference:</strong><br>
                        <code>{$this->document['reference_number']}</code>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        $statusBadge
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Client:</strong><br>
                        {$this->document['client_name']}<br>
                        <small class="text-muted">{$this->document['user_email']}</small>
                    </div>
                    <div class="col-md-6">
                        <strong>Document Type:</strong><br>
                        {$this->document['document_type']}
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Purpose:</strong><br>
                    $purpose
                </div>
                <div class="mb-3">
                    <strong>Additional Notes:</strong><br>
                    $notes
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Requested Date:</strong><br>
                        {$this->getFormattedDate()}
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Status:</strong><br>
                        $paymentBadge
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Processed Date:</strong><br>
                        $processedDate
                    </div>
                </div>
                $rejectionAlert
            </div>
        </div>
    </div>
</div>
HTML;
    }
    
    private function getFormattedDate() {
        return date('M d, Y', strtotime($this->document['created_at']));
    }
}

class Pagination {
    private $currentPage;
    private $totalPages;
    private $baseUrl;
    
    public function __construct($currentPage, $totalPages, $baseUrl) {
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->baseUrl = $baseUrl;
    }
    
    public function render() {
        if ($this->totalPages <= 1) return '';
        
        $html = '<nav class="mt-4"><ul class="pagination justify-content-center">';
        
        // Previous button
        if ($this->currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->baseUrl . '&page=1">First</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->baseUrl . '&page=' . ($this->currentPage - 1) . '">Previous</a></li>';
        }
        
        // Page numbers
        $start = max(1, $this->currentPage - 2);
        $end = min($this->totalPages, $this->currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $this->currentPage) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $this->baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
        }
        
        // Next button
        if ($this->currentPage < $this->totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->baseUrl . '&page=' . ($this->currentPage + 1) . '">Next</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->baseUrl . '&page=' . $this->totalPages . '">Last</a></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
}
?>
