-- Configuration Script for Existing Drugs Vial Types
-- Run this after deploying the schema changes

-- Configure common SDV (Single Dose Vial) medications
UPDATE drugs SET 
    vial_type = 'single_dose', 
    vial_type_source = 'manual', 
    vial_type_notes = 'Configured as SDV - use entire vial regardless of dose'
WHERE 
    name LIKE '%ertapenem%' OR 
    name LIKE '%vancomycin%' OR 
    name LIKE '%ceftriaxone%' OR 
    name LIKE '%cefepime%' OR 
    name LIKE '%aztreonam%' OR 
    name LIKE '%ampicillin%' OR 
    name LIKE '%penicillin%';

-- Configure common MDV (Multi-Dose Vial) medications
UPDATE drugs SET 
    vial_type = 'multi_dose', 
    vial_type_source = 'manual', 
    vial_type_notes = 'Configured as MDV - can be used partially and reused'
WHERE 
    name LIKE '%insulin%' OR 
    name LIKE '%heparin%' OR 
    name LIKE '%lidocaine%' OR 
    name LIKE '%normal saline%' OR 
    name LIKE '%sodium chloride%' OR 
    name LIKE '%dextrose%' OR 
    name LIKE '%bacteriostatic%';

-- Add some sample NDC lookup data for automatic detection
INSERT IGNORE INTO ndc_vial_type_lookup (ndc_10, ndc_11, vial_type, confidence_level, source) VALUES
-- Ertapenem (commonly SDV)
('60505-6196-0', '60505-06196-00', 'single_dose', 'high', 'manufacturer_data'),
('60505-6196-1', '60505-06196-01', 'single_dose', 'high', 'manufacturer_data'),

-- Vancomycin (commonly SDV for IV)
('00409-6080-1', '00409-06080-01', 'single_dose', 'high', 'manufacturer_data'),
('00409-6080-2', '00409-06080-02', 'single_dose', 'high', 'manufacturer_data'),

-- Insulin (commonly MDV)
('00002-8215-1', '00002-08215-01', 'multi_dose', 'high', 'manufacturer_data'),
('00002-8215-2', '00002-08215-02', 'multi_dose', 'high', 'manufacturer_data'),

-- Heparin (commonly MDV)
('25021-0400-1', '25021-00400-01', 'multi_dose', 'high', 'manufacturer_data'),
('25021-0400-2', '25021-00400-02', 'multi_dose', 'high', 'manufacturer_data');

-- Display summary of configured vial types
SELECT 
    vial_type,
    COUNT(*) as drug_count,
    GROUP_CONCAT(DISTINCT LEFT(name, 20) ORDER BY name SEPARATOR ', ') as sample_drugs
FROM drugs 
WHERE vial_type != 'unknown' 
GROUP BY vial_type;

-- Display drugs that still need vial type configuration
SELECT 
    drug_id,
    name,
    form,
    size,
    unit,
    ndc_10,
    ndc_11
FROM drugs 
WHERE vial_type = 'unknown' 
    AND active = 1 
    AND status = 'active'
ORDER BY name;
