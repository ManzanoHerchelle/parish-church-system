<?php
/**
 * SLA Alert Handler
 * Sends notifications when items approach/exceed SLA thresholds
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email_handler.php';

class SLAAlertHandler {
    private $conn;
    private $emailHandler;
    
    // SLA thresholds (in hours)
    const DOC_SLA_HOURS = 48;
    const DOC_WARNING_HOURS = 36;
    const BOOKING_SLA_HOURS = 24;
    const BOOKING_WARNING_HOURS = 18;
    const PAYMENT_SLA_HOURS = 24;
    const PAYMENT_WARNING_HOURS = 18;
    
    public function __construct() {
        $this->conn = getDBConnection();
        $this->emailHandler = new EmailHandler();
    }
    
    /**
     * Check all items and send alerts if needed
     */
    public function checkAndAlert() {
        $this->checkDocuments();
        $this->checkBookings();
        $this->checkPayments();
    }
    
    /**
     * Check document requests for SLA breaches
     */
    private function checkDocuments() {
        $query = "
            SELECT 
                dr.*,
                dt.name as document_type,
                u.email, u.first_name, u.last_name,
                TIMESTAMPDIFF(HOUR, dr.created_at, NOW()) as hours_pending
            FROM document_requests dr
            JOIN document_types dt ON dr.document_type_id = dt.id
            JOIN users u ON dr.user_id = u.id
            WHERE dr.status = 'pending'
            AND TIMESTAMPDIFF(HOUR, dr.created_at, NOW()) >= ?
            AND (dr.sla_alert_sent IS NULL OR dr.sla_alert_sent = 0)
        ";
        
        $stmt = $this->conn->prepare($query);
        $warningHours = self::DOC_WARNING_HOURS;
        $stmt->bind_param('i', $warningHours);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $hours = $row['hours_pending'];
            $severity = $hours >= self::DOC_SLA_HOURS ? 'critical' : 'warning';
            
            // Send alert to admin/staff
            $this->sendStaffAlert('document', $row, $severity);
            
            // Mark as alerted
            $this->markAlertSent('document_requests', $row['id']);
        }
    }
    
    /**
     * Check bookings for SLA breaches
     */
    private function checkBookings() {
        $query = "
            SELECT 
                b.*,
                bt.name as booking_type,
                u.email, u.first_name, u.last_name,
                TIMESTAMPDIFF(HOUR, b.created_at, NOW()) as hours_pending
            FROM bookings b
            JOIN booking_types bt ON b.booking_type_id = bt.id
            JOIN users u ON b.user_id = u.id
            WHERE b.status = 'pending'
            AND TIMESTAMPDIFF(HOUR, b.created_at, NOW()) >= ?
            AND (b.sla_alert_sent IS NULL OR b.sla_alert_sent = 0)
        ";
        
        $stmt = $this->conn->prepare($query);
        $warningHours = self::BOOKING_WARNING_HOURS;
        $stmt->bind_param('i', $warningHours);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $hours = $row['hours_pending'];
            $severity = $hours >= self::BOOKING_SLA_HOURS ? 'critical' : 'warning';
            
            $this->sendStaffAlert('booking', $row, $severity);
            $this->markAlertSent('bookings', $row['id']);
        }
    }
    
    /**
     * Check payments for SLA breaches
     */
    private function checkPayments() {
        $query = "
            SELECT 
                p.*,
                u.email, u.first_name, u.last_name,
                TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_pending
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'pending'
            AND TIMESTAMPDIFF(HOUR, p.created_at, NOW()) >= ?
            AND (p.sla_alert_sent IS NULL OR p.sla_alert_sent = 0)
        ";
        
        $stmt = $this->conn->prepare($query);
        $warningHours = self::PAYMENT_WARNING_HOURS;
        $stmt->bind_param('i', $warningHours);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $hours = $row['hours_pending'];
            $severity = $hours >= self::PAYMENT_SLA_HOURS ? 'critical' : 'warning';
            
            $this->sendStaffAlert('payment', $row, $severity);
            $this->markAlertSent('payments', $row['id']);
        }
    }
    
    /**
     * Send alert to admin/staff
     */
    private function sendStaffAlert($type, $item, $severity) {
        // Get all admin and staff emails
        $query = "SELECT email, first_name FROM users WHERE role IN ('admin', 'staff')";
        $result = $this->conn->query($query);
        
        $subject = "[" . strtoupper($severity) . "] SLA Alert: " . ucfirst($type);
        
        while ($staff = $result->fetch_assoc()) {
            $body = $this->generateAlertEmail($type, $item, $severity, $staff['first_name']);
            $this->emailHandler->sendEmail(
                $staff['email'],
                $staff['first_name'],
                $subject,
                $body
            );
        }
        
        // Log the alert
        $this->logAlert($type, $item['id'], $severity);
    }
    
    /**
     * Generate HTML email for alert
     */
    private function generateAlertEmail($type, $item, $severity, $recipientName) {
        $color = $severity === 'critical' ? '#ef4444' : '#f59e0b';
        $icon = $severity === 'critical' ? '🔴' : '⚠️';
        
        $itemDetails = '';
        switch ($type) {
            case 'document':
                $itemDetails = "
                    <strong>Document Type:</strong> {$item['document_type']}<br>
                    <strong>Reference:</strong> {$item['reference_number']}<br>
                    <strong>Client:</strong> {$item['first_name']} {$item['last_name']}<br>
                    <strong>Time Pending:</strong> {$item['hours_pending']} hours
                ";
                break;
            case 'booking':
                $itemDetails = "
                    <strong>Booking Type:</strong> {$item['booking_type']}<br>
                    <strong>Reference:</strong> {$item['reference_number']}<br>
                    <strong>Client:</strong> {$item['first_name']} {$item['last_name']}<br>
                    <strong>Time Pending:</strong> {$item['hours_pending']} hours
                ";
                break;
            case 'payment':
                $itemDetails = "
                    <strong>Transaction ID:</strong> {$item['transaction_number']}<br>
                    <strong>Amount:</strong> ₱" . number_format($item['amount'], 2) . "<br>
                    <strong>Client:</strong> {$item['first_name']} {$item['last_name']}<br>
                    <strong>Time Pending:</strong> {$item['hours_pending']} hours
                ";
                break;
        }
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert-box { 
                    background: #fff; 
                    border: 2px solid {$color}; 
                    border-radius: 8px; 
                    padding: 20px;
                    margin: 20px 0;
                }
                .header { 
                    background: {$color}; 
                    color: white; 
                    padding: 15px; 
                    border-radius: 6px;
                    font-size: 18px;
                    font-weight: bold;
                }
                .details { 
                    background: #f9fafb; 
                    padding: 15px; 
                    border-radius: 6px;
                    margin: 15px 0;
                }
                .button {
                    display: inline-block;
                    background: {$color};
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='alert-box'>
                    <div class='header'>
                        {$icon} SLA {$severity} Alert
                    </div>
                    
                    <p>Hello {$recipientName},</p>
                    
                    <p>A {$type} request has " . ($severity === 'critical' ? 'exceeded' : 'approached') . " the SLA threshold and requires immediate attention.</p>
                    
                    <div class='details'>
                        {$itemDetails}
                    </div>
                    
                    <p><strong>Action Required:</strong> Please review and process this request as soon as possible.</p>
                    
                    <a href='http://localhost/documentSystem/staff/sla-dashboard.php' class='button'>
                        View SLA Dashboard
                    </a>
                    
                    <p style='margin-top: 20px; font-size: 12px; color: #6b7280;'>
                        This is an automated SLA alert from the Parish Document System.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
    
    /**
     * Mark item as alert sent
     */
    private function markAlertSent($table, $id) {
        $query = "UPDATE {$table} SET sla_alert_sent = 1, sla_alert_sent_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    
    /**
     * Log alert in database
     */
    private function logAlert($type, $itemId, $severity) {
        $query = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, created_at) 
                  VALUES (1, 'sla_alert', ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $description = "SLA {$severity} alert sent for {$type} #{$itemId}";
        $stmt->bind_param('sis', $type, $itemId, $description);
        $stmt->execute();
    }
    
    public function __destruct() {
        closeDBConnection($this->conn);
    }
}

// If run directly (via cron), execute the check
if (php_sapi_name() === 'cli') {
    $handler = new SLAAlertHandler();
    $handler->checkAndAlert();
    echo "SLA alert check completed at " . date('Y-m-d H:i:s') . "\n";
}
