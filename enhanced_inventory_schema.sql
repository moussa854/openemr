-- Enhanced Inventory Schema for SDV/MDV Support
-- This file contains the database changes needed to support Single Dose Vial (SDV) 
-- and Multi-Dose Vial (MDV) distinction in the inventory system

-- Add vial type field to drugs table
ALTER TABLE drugs 
ADD COLUMN vial_type ENUM('single_dose', 'multi_dose', 'unknown') 
DEFAULT 'unknown' AFTER form,
ADD COLUMN vial_type_source ENUM('manual', 'auto_detected', 'ndc_lookup') 
DEFAULT 'manual' AFTER vial_type,
ADD COLUMN vial_type_notes TEXT NULL AFTER vial_type_source;

-- Add partial usage tracking to drug_inventory table
ALTER TABLE drug_inventory 
ADD COLUMN partial_usage_remaining DECIMAL(10,2) DEFAULT NULL 
COMMENT 'Remaining amount in partially used vial' AFTER on_hand,
ADD COLUMN partial_usage_unit VARCHAR(20) DEFAULT NULL 
COMMENT 'Unit for partial usage (mg, ml, etc.)' AFTER partial_usage_remaining,
ADD COLUMN partial_usage_opened_date DATETIME DEFAULT NULL 
COMMENT 'Date when vial was first opened' AFTER partial_usage_unit,
ADD COLUMN partial_usage_expires_after_hours INT DEFAULT NULL 
COMMENT 'Hours after opening when partial usage expires' AFTER partial_usage_opened_date;

-- Add vial type to custom inventory module drugs table (if exists)
-- Note: This assumes the custom inventory module uses a separate drugs table
-- If it uses the main drugs table, this is already covered above

-- Create index for vial type queries
ALTER TABLE drugs ADD INDEX idx_vial_type (vial_type);

-- Create index for partial usage queries
ALTER TABLE drug_inventory ADD INDEX idx_partial_usage (partial_usage_remaining, partial_usage_opened_date);

-- Add sample data for common vial types
-- Update common infusion medications to be SDV
UPDATE drugs SET vial_type = 'single_dose', vial_type_source = 'auto_detected' 
WHERE name LIKE '%ertapenem%' OR name LIKE '%vancomycin%' OR name LIKE '%ceftriaxone%';

-- Update common multi-dose medications
UPDATE drugs SET vial_type = 'multi_dose', vial_type_source = 'auto_detected' 
WHERE name LIKE '%insulin%' OR name LIKE '%heparin%' OR name LIKE '%lidocaine%';

-- Create a lookup table for NDC-based vial type detection
CREATE TABLE IF NOT EXISTS ndc_vial_type_lookup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ndc_10 VARCHAR(20) NOT NULL,
    ndc_11 VARCHAR(20) NOT NULL,
    vial_type ENUM('single_dose', 'multi_dose', 'unknown') NOT NULL,
    confidence_level ENUM('high', 'medium', 'low') DEFAULT 'medium',
    source VARCHAR(100) DEFAULT 'manual',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ndc_10 (ndc_10),
    INDEX idx_ndc_11 (ndc_11),
    INDEX idx_vial_type (vial_type)
);

-- Insert some common NDC patterns for vial type detection
INSERT INTO ndc_vial_type_lookup (ndc_10, ndc_11, vial_type, confidence_level, source) VALUES
('60505-6196-0', '60505-06196-00', 'single_dose', 'high', 'manufacturer_data'),
('00071-1010-01', '00071-01010-01', 'single_dose', 'high', 'manufacturer_data'),
('00071-1010-02', '00071-01010-02', 'multi_dose', 'high', 'manufacturer_data');

-- Create a table to track vial usage history
CREATE TABLE IF NOT EXISTS vial_usage_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    drug_id INT NOT NULL,
    inventory_id INT NOT NULL,
    form_id BIGINT NULL,
    patient_id INT NULL,
    encounter_id INT NULL,
    usage_type ENUM('full_vial', 'partial_vial', 'wastage') NOT NULL,
    quantity_used DECIMAL(10,2) NOT NULL,
    quantity_unit VARCHAR(20) NOT NULL,
    remaining_after_usage DECIMAL(10,2) NULL,
    usage_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_id INT NOT NULL,
    notes TEXT NULL,
    INDEX idx_drug_id (drug_id),
    INDEX idx_inventory_id (inventory_id),
    INDEX idx_form_id (form_id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_usage_date (usage_date),
    FOREIGN KEY (drug_id) REFERENCES drugs(drug_id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES drug_inventory(inventory_id) ON DELETE CASCADE
);
