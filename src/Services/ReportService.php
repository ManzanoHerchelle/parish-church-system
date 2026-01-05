<?php
/**
 * Report Service
 * Handles report generation, analytics, and statistics
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';

class ReportService {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get revenue statistics
     */
    public function getRevenueStats($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                COUNT(DISTINCT CASE WHEN status = 'verified' THEN id END) as total_transactions,
                SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = 'verified' THEN amount ELSE NULL END) as average_payment,
                COUNT(DISTINCT CASE WHEN status = 'pending' THEN id END) as pending_payments,
                COUNT(DISTINCT CASE WHEN status = 'rejected' THEN id END) as rejected_payments
            FROM payments
            WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get daily revenue chart data
     */
    public function getDailyRevenueData($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-m-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as revenue,
                COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified_count
            FROM payments
            WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get revenue by payment method
     */
    public function getRevenueByMethod($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_amount,
                ROUND(100.0 * SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) / 
                      SUM(SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END)) OVER (), 2) as percentage
            FROM payments
            WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY payment_method
            ORDER BY total_amount DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get popular document types
     */
    public function getPopularDocuments($startDate = null, $endDate = null, $limit = 10) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                dt.name as document_type,
                COUNT(dr.id) as total_requests,
                COUNT(CASE WHEN dr.status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN dr.status = 'rejected' THEN 1 END) as rejected,
                SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END) as revenue,
                ROUND(100.0 * COUNT(CASE WHEN dr.status = 'completed' THEN 1 END) / COUNT(dr.id), 2) as completion_rate
            FROM document_types dt
            LEFT JOIN document_requests dr ON dt.id = dr.document_type_id AND dr.created_at >= ? AND dr.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            LEFT JOIN payments p ON dr.id = p.reference_id AND p.reference_type = 'document_request'
            WHERE dt.is_active = 1
            GROUP BY dt.id, dt.name
            HAVING total_requests > 0
            ORDER BY total_requests DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get popular booking types
     */
    public function getPopularBookings($startDate = null, $endDate = null, $limit = 10) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                bt.name as booking_type,
                COUNT(b.id) as total_bookings,
                COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN b.status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled,
                SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END) as revenue,
                ROUND(100.0 * COUNT(CASE WHEN b.status = 'completed' THEN 1 END) / COUNT(b.id), 2) as completion_rate
            FROM booking_types bt
            LEFT JOIN bookings b ON bt.id = b.booking_type_id AND b.created_at >= ? AND b.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            LEFT JOIN payments p ON b.id = p.reference_id AND p.reference_type = 'booking'
            WHERE bt.is_active = 1
            GROUP BY bt.id, bt.name
            HAVING total_bookings > 0
            ORDER BY total_bookings DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get document request statistics
     */
    public function getDocumentStats($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing,
                COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                ROUND(100.0 * COUNT(CASE WHEN status = 'completed' THEN 1 END) / COUNT(*), 2) as completion_rate,
                ROUND(100.0 * COUNT(CASE WHEN status = 'rejected' THEN 1 END) / COUNT(*), 2) as rejection_rate
            FROM document_requests
            WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get booking statistics
     */
    public function getBookingStats($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                COUNT(*) as total_bookings,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                ROUND(100.0 * COUNT(CASE WHEN status = 'completed' THEN 1 END) / COUNT(*), 2) as completion_rate,
                ROUND(100.0 * COUNT(CASE WHEN status = 'rejected' THEN 1 END) / COUNT(*), 2) as rejection_rate
            FROM bookings
            WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get user registration statistics
     */
    public function getUserStats($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN role = 'client' THEN 1 END) as total_clients,
                COUNT(CASE WHEN role = 'staff' THEN 1 END) as total_staff,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                COUNT(CASE WHEN email_verified = 1 THEN 1 END) as verified_users,
                COUNT(CASE WHEN created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY) THEN 1 END) as new_users_period
            FROM users
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get monthly comparison data
     */
    public function getMonthlyComparison($monthsBack = 12) {
        $query = "
            SELECT 
                DATE_FORMAT(p.created_at, '%Y-%m') as month,
                COUNT(CASE WHEN p.status = 'verified' THEN 1 END) as transactions,
                SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END) as revenue,
                COUNT(DISTINCT dr.id) as document_requests,
                COUNT(DISTINCT b.id) as bookings
            FROM payments p
            LEFT JOIN document_requests dr ON p.reference_type = 'document_request' AND p.reference_id = dr.id AND p.created_at = dr.created_at
            LEFT JOIN bookings b ON p.reference_type = 'booking' AND p.reference_id = b.id AND p.created_at = b.created_at
            WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
            ORDER BY month ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $monthsBack);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get top clients by spending
     */
    public function getTopClients($startDate = null, $endDate = null, $limit = 10) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                CONCAT(u.first_name, ' ', u.last_name) as client_name,
                u.email,
                COUNT(DISTINCT p.id) as transaction_count,
                SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END) as total_spent,
                COUNT(DISTINCT dr.id) as documents_requested,
                COUNT(DISTINCT b.id) as bookings_made
            FROM users u
            LEFT JOIN payments p ON u.id = p.user_id AND p.created_at >= ? AND p.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            LEFT JOIN document_requests dr ON u.id = dr.user_id AND dr.created_at >= ? AND dr.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            LEFT JOIN bookings b ON u.id = b.user_id AND b.created_at >= ? AND b.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            WHERE u.role = 'client'
            GROUP BY u.id, u.first_name, u.last_name, u.email
            HAVING total_spent > 0 OR documents_requested > 0 OR bookings_made > 0
            ORDER BY total_spent DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssssssi", $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get payment status breakdown
     */
    public function getPaymentStatusBreakdown($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-01-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $query = "
            SELECT 
                status,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                ROUND(100.0 * COUNT(*) / (SELECT COUNT(*) FROM payments WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)), 2) as percentage
            FROM payments
            WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY status
            ORDER BY count DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssss", $startDate, $endDate, $startDate, $endDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Generate PDF report (basic version)
     */
    public function generatePDFReport($startDate, $endDate, $title = 'System Report') {
        require_once __DIR__ . '/../../includes/TCPDF-main/tcpdf.php';
        
        $pdf = new \TCPDF();
        $pdf->setCreator('Parish Church System');
        $pdf->setTitle($title);
        $pdf->setMargins(10, 10, 10);
        $pdf->addPage();
        
        // Header
        $pdf->setFont('helvetica', 'B', 16);
        $pdf->cell(0, 10, $title, 0, 1, 'C');
        $pdf->setFont('helvetica', '', 10);
        $pdf->cell(0, 5, 'Report Period: ' . date('F d, Y', strtotime($startDate)) . ' to ' . date('F d, Y', strtotime($endDate)), 0, 1, 'C');
        $pdf->ln(5);
        
        // Get data
        $revenueStats = $this->getRevenueStats($startDate, $endDate);
        $docStats = $this->getDocumentStats($startDate, $endDate);
        $bookingStats = $this->getBookingStats($startDate, $endDate);
        
        // Revenue Section
        $pdf->setFont('helvetica', 'B', 12);
        $pdf->cell(0, 8, 'Revenue Summary', 0, 1);
        $pdf->setFont('helvetica', '', 10);
        
        $pdf->cell(80, 6, 'Total Transactions:', 0, 0);
        $pdf->cell(0, 6, $revenueStats['total_transactions'] ?? 0, 0, 1);
        
        $pdf->cell(80, 6, 'Total Revenue:', 0, 0);
        $pdf->cell(0, 6, '₱' . number_format($revenueStats['total_revenue'] ?? 0, 2), 0, 1);
        
        $pdf->cell(80, 6, 'Average Payment:', 0, 0);
        $pdf->cell(0, 6, '₱' . number_format($revenueStats['average_payment'] ?? 0, 2), 0, 1);
        
        $pdf->ln(5);
        
        // Document Stats Section
        $pdf->setFont('helvetica', 'B', 12);
        $pdf->cell(0, 8, 'Document Request Statistics', 0, 1);
        $pdf->setFont('helvetica', '', 10);
        
        $pdf->cell(80, 6, 'Total Requests:', 0, 0);
        $pdf->cell(0, 6, $docStats['total_requests'] ?? 0, 0, 1);
        
        $pdf->cell(80, 6, 'Completion Rate:', 0, 0);
        $pdf->cell(0, 6, ($docStats['completion_rate'] ?? 0) . '%', 0, 1);
        
        $pdf->cell(80, 6, 'Rejection Rate:', 0, 0);
        $pdf->cell(0, 6, ($docStats['rejection_rate'] ?? 0) . '%', 0, 1);
        
        $pdf->ln(5);
        
        // Booking Stats Section
        $pdf->setFont('helvetica', 'B', 12);
        $pdf->cell(0, 8, 'Booking Statistics', 0, 1);
        $pdf->setFont('helvetica', '', 10);
        
        $pdf->cell(80, 6, 'Total Bookings:', 0, 0);
        $pdf->cell(0, 6, $bookingStats['total_bookings'] ?? 0, 0, 1);
        
        $pdf->cell(80, 6, 'Completion Rate:', 0, 0);
        $pdf->cell(0, 6, ($bookingStats['completion_rate'] ?? 0) . '%', 0, 1);
        
        $pdf->cell(80, 6, 'Cancellation Rate:', 0, 0);
        $pdf->cell(0, 6, ($bookingStats['rejection_rate'] ?? 0) . '%', 0, 1);
        
        return $pdf;
    }
}
