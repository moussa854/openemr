-- Enhanced Infusion/Injection Form Electronic Signatures
-- Database schema for multi-user electronic signature system

USE openemr;

-- Main signatures table for enhanced infusion forms
CREATE TABLE IF NOT EXISTS form_enhanced_infusion_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    user_id INT NOT NULL,
    signature_text TEXT,
    signature_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    signature_type ENUM('primary', 'witness', 'reviewer', 'custom') DEFAULT 'primary',
    signature_order INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_form_id (form_id),
    INDEX idx_user_id (user_id),
    INDEX idx_signature_type (signature_type),
    INDEX idx_is_active (is_active)
);

-- Signature types configuration table
CREATE TABLE IF NOT EXISTS signature_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    is_required TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default signature types
INSERT IGNORE INTO signature_types (type_name, display_name, is_required, sort_order) VALUES
('primary', 'Primary Provider', 1, 1),
('witness', 'Witness', 0, 2),
('reviewer', 'Reviewer', 0, 3),
('custom', 'Custom', 0, 4);

-- Add foreign key constraints (if they don't exist)
-- Note: These will be added after table creation to avoid errors if tables don't exist

-- Audit log table for signature activities
CREATE TABLE IF NOT EXISTS form_enhanced_infusion_signature_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    signature_id INT,
    user_id INT NOT NULL,
    action ENUM('create', 'update', 'delete', 'view') NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_form_id (form_id),
    INDEX idx_signature_id (signature_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Show table creation results
SHOW TABLES LIKE '%enhanced_infusion%';
DESCRIBE form_enhanced_infusion_signatures;
DESCRIBE signature_types;
DESCRIBE form_enhanced_infusion_signature_log;
