<?php
/**
 * User Service
 * Handles all user management operations
 */

namespace Services;

require_once __DIR__ . '/../../config/database.php';

class UserService {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get all users with optional filtering
     */
    public function getUsers($filters = []) {
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;
        $limit = $filters['limit'] ?? 20;
        $offset = $filters['offset'] ?? 0;
        
        $query = "SELECT id, first_name, last_name, email, phone, role, status, 
                         email_verified, created_at 
                  FROM users WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if ($role && $role !== 'all') {
            $query .= " AND role = ?";
            $params[] = $role;
            $types .= 's';
        }
        
        if ($status && $status !== 'all') {
            $query .= " AND status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Update user status
     */
    public function updateUserStatus($userId, $status) {
        $query = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $status, $userId);
        return $stmt->execute();
    }
    
    /**
     * Update user role
     */
    public function updateUserRole($userId, $role) {
        $query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $role, $userId);
        return $stmt->execute();
    }
    
    /**
     * Get user statistics (documents, bookings, payments)
     */
    public function getUserStatistics($userId) {
        $stats = [];
        
        // Documents count
        $query = "SELECT COUNT(*) as count FROM document_requests WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stats['documents'] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Bookings count
        $query = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stats['bookings'] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Payments count and total
        $query = "SELECT COUNT(*) as count, IFNULL(SUM(amount), 0) as total 
                  FROM payments WHERE user_id = ? AND status = 'verified'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['payments'] = $result['count'];
        $stats['total_paid'] = $result['total'];
        
        return $stats;
    }
    
    /**
     * Reset user password (admin action)
     */
    public function resetUserPassword($userId, $newPassword) {
        $hashedPassword = hash('sha256', $newPassword);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $hashedPassword, $userId);
        return $stmt->execute();
    }
    
    /**
     * Delete user (soft delete - set status to inactive)
     */
    public function deactivateUser($userId) {
        return $this->updateUserStatus($userId, 'inactive');
    }
    
    /**
     * Activate user
     */
    public function activateUser($userId) {
        return $this->updateUserStatus($userId, 'active');
    }
}
?>
