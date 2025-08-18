<?php
// Search All Drugs API - Fixed Version with Real-time Inventory
// Uses drug_inventory table for accurate quantities

header('Content-Type: application/json');

try {
    // Direct database connection with correct credentials
    $host = 'localhost';
    $dbname = 'openemr';
    $username = 'openemr';
    $password = 'cfvcfv33';  // Use the correct password
    
    $dsn = "mysql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    
    // Query for active drugs with real inventory data from drug_inventory table
    $stmt = $pdo->query("
        SELECT 
            d.drug_id,
            d.name,
            d.barcode,
            d.ndc_10,
            d.ndc_11,
            d.form,
            d.size,
            d.unit,
            d.dose,
            d.route,
            COALESCE(SUM(di.on_hand), 0) as quantity,
            d.quantity_unit,
            d.lot_number as primary_lot_number,
            d.expiration_date as primary_expiration_date,
            d.is_controlled_substance,
            d.vial_type,
            d.vial_type_source,
            d.status,
            d.active,
            GROUP_CONCAT(
                CONCAT('Lot: ', di.lot_number, ' (', di.on_hand, ' units, exp: ', di.expiration, ')')
                ORDER BY di.expiration ASC
                SEPARATOR '; '
            ) as inventory_details,
            MIN(di.expiration) as earliest_expiration
        FROM drugs d 
        LEFT JOIN drug_inventory di ON d.drug_id = di.drug_id AND di.on_hand > 0
        WHERE d.active = 1 AND d.status = 'active'
        GROUP BY d.drug_id, d.name, d.barcode, d.ndc_10, d.ndc_11, d.form, d.size, d.unit, d.dose, d.route, d.quantity_unit, d.lot_number, d.expiration_date, d.is_controlled_substance, d.vial_type, d.vial_type_source, d.status, d.active
        ORDER BY d.name ASC
    ");
    $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("Search All Drugs: Found " . count($drugs) . " drugs with real-time inventory");
    
    // Return JSON
    echo json_encode($drugs);
    
} catch (Exception $e) {
    error_log("Get all drugs error: " . $e->getMessage());
    echo json_encode([]);
}
?>
