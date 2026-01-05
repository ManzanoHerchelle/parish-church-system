-- Payment Configuration Tables
-- Add these tables to support payment methods and account management

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    logo_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    requires_account_info TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_method_id INT NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    account_holder VARCHAR(150),
    branch_name VARCHAR(100),
    instructions TEXT,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    INDEX idx_payment_method (payment_method_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Insert default payment methods
INSERT IGNORE INTO payment_methods (name, code, display_name, description, requires_account_info, sort_order) VALUES
('Cash', 'cash', 'Cash Payment', 'Pay at the parish office', 0, 1),
('Bank Transfer', 'bank_transfer', 'Bank Transfer', 'Transfer to our bank account', 1, 2),
('GCash', 'gcash', 'GCash', 'Mobile money payment via GCash', 1, 3),
('PayMaya', 'paymaya', 'PayMaya', 'Online payment via PayMaya', 1, 4),
('Online Payment', 'online', 'Online Payment Gateway', 'Secure online payment', 0, 5);

-- Insert default payment accounts (BDO as example)
INSERT IGNORE INTO payment_accounts (payment_method_id, account_name, account_number, account_holder, branch_name, instructions, sort_order) VALUES
(2, 'BDO Checking Account', '1234567890', 'Parish Church Foundation', 'BDO - Main Branch', 'Please include your reference number in the transfer remarks', 1);
