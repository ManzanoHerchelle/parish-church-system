-- Parish Church Document Request and Booking System Database Schema
-- Created: January 5, 2026

CREATE DATABASE IF NOT EXISTS parish_church_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE parish_church_system;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('admin', 'staff', 'client') DEFAULT 'client',
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Document Types Table
CREATE TABLE document_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    fee DECIMAL(10, 2) DEFAULT 0.00,
    processing_days INT DEFAULT 3,
    requirements TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Document Requests Table
CREATE TABLE document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type_id INT NOT NULL,
    reference_number VARCHAR(50) UNIQUE NOT NULL,
    purpose TEXT,
    additional_notes TEXT,
    status ENUM('pending', 'processing', 'ready', 'completed', 'rejected') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'pending', 'paid') DEFAULT 'unpaid',
    payment_amount DECIMAL(10, 2),
    payment_proof VARCHAR(255),
    processed_by INT,
    processed_date DATETIME,
    rejection_reason TEXT,
    document_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_reference (reference_number)
) ENGINE=InnoDB;

-- Document Request Attachments Table
CREATE TABLE document_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_request_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_request_id) REFERENCES document_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Booking Types Table
CREATE TABLE booking_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    fee DECIMAL(10, 2) DEFAULT 0.00,
    duration_minutes INT DEFAULT 60,
    max_bookings_per_day INT DEFAULT 10,
    requires_approval TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bookings Table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_type_id INT NOT NULL,
    reference_number VARCHAR(50) UNIQUE NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    end_time TIME,
    purpose TEXT,
    special_requests TEXT,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'pending', 'paid') DEFAULT 'unpaid',
    payment_amount DECIMAL(10, 2),
    payment_proof VARCHAR(255),
    approved_by INT,
    approved_date DATETIME,
    rejection_reason TEXT,
    cancellation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_type_id) REFERENCES booking_types(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Blocked Dates Table (for holidays and special events)
CREATE TABLE blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    reason VARCHAR(255),
    is_full_day TINYINT(1) DEFAULT 1,
    start_time TIME,
    end_time TIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- Payments Table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reference_type ENUM('document_request', 'booking') NOT NULL,
    reference_id INT NOT NULL,
    transaction_number VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'online', 'bank_transfer', 'gcash', 'paymaya') DEFAULT 'cash',
    payment_proof VARCHAR(255),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT,
    verified_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_transaction (transaction_number)
) ENGINE=InnoDB;

-- Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB;

-- System Settings Table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Insert Default Document Types
INSERT INTO document_types (name, description, fee, processing_days, requirements) VALUES
('Baptismal Certificate', 'Official certificate of baptism', 100.00, 3, 'Valid ID, Birth Certificate'),
('Confirmation Certificate', 'Official certificate of confirmation', 100.00, 3, 'Valid ID, Baptismal Certificate'),
('Marriage Certificate', 'Official certificate of marriage', 150.00, 5, 'Valid ID, Birth Certificates of both parties'),
('Death Certificate', 'Official death certificate from church records', 100.00, 3, 'Valid ID, Death Certificate from Civil Registry'),
('Burial Permit', 'Permit for burial in church cemetery', 200.00, 2, 'Valid ID, Death Certificate'),
('Letter of Recommendation', 'Official church recommendation letter', 50.00, 2, 'Valid ID');

-- Insert Default Booking Types
INSERT INTO booking_types (name, description, fee, duration_minutes, max_bookings_per_day) VALUES
('Baptism', 'Schedule for baptism ceremony', 500.00, 120, 5),
('Wedding', 'Schedule for wedding ceremony', 5000.00, 180, 2),
('Mass Intention', 'Book a mass intention', 200.00, 60, 10),
('Confession', 'Schedule for confession', 0.00, 30, 20),
('Funeral Service', 'Schedule for funeral/memorial service', 2000.00, 120, 3),
('Hall Rental', 'Rent parish hall for events', 3000.00, 240, 1),
('Chapel Rental', 'Rent small chapel for private services', 1500.00, 120, 2);

-- Insert Default Admin User (password: admin123 - should be changed)
INSERT INTO users (first_name, last_name, email, password, role, status, email_verified) VALUES
('Admin', 'User', 'admin@parishchurch.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1);

-- Insert Default System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('church_name', 'Parish Church', 'text', 'Name of the parish church'),
('church_address', '123 Main Street, City', 'text', 'Physical address of the church'),
('church_phone', '+1234567890', 'text', 'Contact phone number'),
('church_email', 'info@parishchurch.com', 'text', 'Contact email address'),
('office_hours', 'Mon-Fri: 8AM-5PM, Sat: 8AM-12PM', 'text', 'Office hours'),
('booking_advance_days', '30', 'number', 'How many days in advance bookings can be made'),
('email_notifications', '1', 'boolean', 'Enable email notifications'),
('sms_notifications', '0', 'boolean', 'Enable SMS notifications'),
('require_payment_proof', '1', 'boolean', 'Require payment proof upload'),
('auto_approve_bookings', '0', 'boolean', 'Automatically approve bookings');
