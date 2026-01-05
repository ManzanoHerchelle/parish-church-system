-- System Logos Table
CREATE TABLE IF NOT EXISTS system_logos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    description TEXT,
    is_active TINYINT(1) DEFAULT 0,
    is_archived TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_active (is_active),
    INDEX idx_is_archived (is_archived),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB;
