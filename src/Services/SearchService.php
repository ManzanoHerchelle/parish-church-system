<?php
/**
 * Search Service
 * Handles advanced search and filtering for documents, bookings, and payments
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';

class SearchService {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Search documents with advanced filters
     */
    public function searchDocuments($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $status = $filters['status'] ?? '';
        $clientId = $filters['client_id'] ?? '';
        $documentType = $filters['document_type'] ?? '';
        $paymentStatus = $filters['payment_status'] ?? '';
        $startDate = $filters['start_date'] ?? '';
        $endDate = $filters['end_date'] ?? '';
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        
        $query = "
            SELECT dr.*, dt.name as document_type, 
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email
            FROM document_requests dr
            JOIN document_types dt ON dr.document_type_id = dt.id
            JOIN users u ON dr.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        // Keyword search (reference number or client name)
        if (!empty($keyword)) {
            $query .= " AND (dr.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$keyword}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        // Status filter
        if (!empty($status)) {
            $query .= " AND dr.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        // Client filter
        if (!empty($clientId)) {
            $query .= " AND dr.user_id = ?";
            $params[] = $clientId;
            $types .= 'i';
        }
        
        // Document type filter
        if (!empty($documentType)) {
            $query .= " AND dr.document_type_id = ?";
            $params[] = $documentType;
            $types .= 'i';
        }
        
        // Payment status filter
        if (!empty($paymentStatus)) {
            $query .= " AND dr.payment_status = ?";
            $params[] = $paymentStatus;
            $types .= 's';
        }
        
        // Date range filter
        if (!empty($startDate)) {
            $query .= " AND dr.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $query .= " AND dr.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
            $types .= 's';
        }
        
        // Add ordering and limit
        $query .= " ORDER BY dr.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        // Prepare and execute
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get total count for document search results
     */
    public function countDocuments($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $status = $filters['status'] ?? '';
        $clientId = $filters['client_id'] ?? '';
        $documentType = $filters['document_type'] ?? '';
        $paymentStatus = $filters['payment_status'] ?? '';
        $startDate = $filters['start_date'] ?? '';
        $endDate = $filters['end_date'] ?? '';
        
        $query = "
            SELECT COUNT(*) as total
            FROM document_requests dr
            JOIN document_types dt ON dr.document_type_id = dt.id
            JOIN users u ON dr.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($keyword)) {
            $query .= " AND (dr.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$keyword}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($status)) {
            $query .= " AND dr.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($clientId)) {
            $query .= " AND dr.user_id = ?";
            $params[] = $clientId;
            $types .= 'i';
        }
        
        if (!empty($documentType)) {
            $query .= " AND dr.document_type_id = ?";
            $params[] = $documentType;
            $types .= 'i';
        }
        
        if (!empty($paymentStatus)) {
            $query .= " AND dr.payment_status = ?";
            $params[] = $paymentStatus;
            $types .= 's';
        }
        
        if (!empty($startDate)) {
            $query .= " AND dr.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $query .= " AND dr.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
            $types .= 's';
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc()['total'];
    }
    
    /**
     * Search bookings with advanced filters
     */
    public function searchBookings($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $status = $filters['status'] ?? '';
        $clientId = $filters['client_id'] ?? '';
        $bookingType = $filters['booking_type'] ?? '';
        $paymentStatus = $filters['payment_status'] ?? '';
        $startDate = $filters['start_date'] ?? '';
        $endDate = $filters['end_date'] ?? '';
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        
        $query = "
            SELECT b.*, bt.name as booking_type, 
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email
            FROM bookings b
            JOIN booking_types bt ON b.booking_type_id = bt.id
            JOIN users u ON b.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($keyword)) {
            $query .= " AND (b.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$keyword}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($status)) {
            $query .= " AND b.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($clientId)) {
            $query .= " AND b.user_id = ?";
            $params[] = $clientId;
            $types .= 'i';
        }
        
        if (!empty($bookingType)) {
            $query .= " AND b.booking_type_id = ?";
            $params[] = $bookingType;
            $types .= 'i';
        }
        
        if (!empty($paymentStatus)) {
            $query .= " AND b.payment_status = ?";
            $params[] = $paymentStatus;
            $types .= 's';
        }
        
        if (!empty($startDate)) {
            $query .= " AND b.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $query .= " AND b.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
            $types .= 's';
        }
        
        $query .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get total count for booking search results
     */
    public function countBookings($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $status = $filters['status'] ?? '';
        $clientId = $filters['client_id'] ?? '';
        $bookingType = $filters['booking_type'] ?? '';
        $paymentStatus = $filters['payment_status'] ?? '';
        $startDate = $filters['start_date'] ?? '';
        $endDate = $filters['end_date'] ?? '';
        
        $query = "
            SELECT COUNT(*) as total
            FROM bookings b
            JOIN booking_types bt ON b.booking_type_id = bt.id
            JOIN users u ON b.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($keyword)) {
            $query .= " AND (b.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$keyword}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($status)) {
            $query .= " AND b.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($clientId)) {
            $query .= " AND b.user_id = ?";
            $params[] = $clientId;
            $types .= 'i';
        }
        
        if (!empty($bookingType)) {
            $query .= " AND b.booking_type_id = ?";
            $params[] = $bookingType;
            $types .= 'i';
        }
        
        if (!empty($paymentStatus)) {
            $query .= " AND b.payment_status = ?";
            $params[] = $paymentStatus;
            $types .= 's';
        }
        
        if (!empty($startDate)) {
            $query .= " AND b.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $query .= " AND b.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
            $types .= 's';
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc()['total'];
    }
    
    /**
     * Search payments with advanced filters
     */
    public function searchPayments($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $status = $filters['status'] ?? '';
        $clientId = $filters['client_id'] ?? '';
        $paymentMethod = $filters['payment_method'] ?? '';
        $amountMin = $filters['amount_min'] ?? '';
        $amountMax = $filters['amount_max'] ?? '';
        $startDate = $filters['start_date'] ?? '';
        $endDate = $filters['end_date'] ?? '';
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        
        $query = "
            SELECT p.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as client_name,
                   u.email as user_email,
                   CASE 
                       WHEN p.reference_type = 'document_request' THEN 
                           (SELECT CONCAT(dt.name, ' - ', dr.reference_number) 
                            FROM document_requests dr 
                            JOIN document_types dt ON dr.document_type_id = dt.id 
                            WHERE dr.id = p.reference_id)
                       WHEN p.reference_type = 'booking' THEN 
                           (SELECT CONCAT(bt.name, ' - ', b.reference_number) 
                            FROM bookings b 
                            JOIN booking_types bt ON b.booking_type_id = bt.id 
                            WHERE b.id = p.reference_id)
                   END as item_name
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($keyword)) {
            $query .= " AND (p.transaction_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$keyword}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($status)) {
            $query .= " AND p.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($clientId)) {
            $query .= " AND p.user_id = ?";
            $params[] = $clientId;
            $types .= 'i';
        }
        
        if (!empty($paymentMethod)) {
            $query .= " AND p.payment_method = ?";
            $params[] = $paymentMethod;
            $types .= 's';
        }
        
        if (!empty($amountMin)) {
            $query .= " AND p.amount >= ?";
            $params[] = (float)$amountMin;
            $types .= 'd';
        }
        
        if (!empty($amountMax)) {
            $query .= " AND p.amount <= ?";
            $params[] = (float)$amountMax;
            $types .= 'd';
        }
        
        if (!empty($startDate)) {
            $query .= " AND p.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $query .= " AND p.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
            $types .= 's';
        }
        
        $query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get total count for payment search results
     */
    public function countPayments($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $status = $filters['status'] ?? '';
        $clientId = $filters['client_id'] ?? '';
        $paymentMethod = $filters['payment_method'] ?? '';
        $amountMin = $filters['amount_min'] ?? '';
        $amountMax = $filters['amount_max'] ?? '';
        $startDate = $filters['start_date'] ?? '';
        $endDate = $filters['end_date'] ?? '';
        
        $query = "
            SELECT COUNT(*) as total
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($keyword)) {
            $query .= " AND (p.transaction_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$keyword}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($status)) {
            $query .= " AND p.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($clientId)) {
            $query .= " AND p.user_id = ?";
            $params[] = $clientId;
            $types .= 'i';
        }
        
        if (!empty($paymentMethod)) {
            $query .= " AND p.payment_method = ?";
            $params[] = $paymentMethod;
            $types .= 's';
        }
        
        if (!empty($amountMin)) {
            $query .= " AND p.amount >= ?";
            $params[] = (float)$amountMin;
            $types .= 'd';
        }
        
        if (!empty($amountMax)) {
            $query .= " AND p.amount <= ?";
            $params[] = (float)$amountMax;
            $types .= 'd';
        }
        
        if (!empty($startDate)) {
            $query .= " AND p.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $query .= " AND p.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
            $types .= 's';
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc()['total'];
    }
}
