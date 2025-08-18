<?php
// Search All Drugs API - Fixed Version with Proper Fallback Logic
header('Content-Type: application/json');

try {
    $host = 'localhost';
    $dbname = 'openemr';
    $username = 'openemr';
    $password = 'cfvcfv33';
    
    $dsn = "mysql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Fixed query with proper fallback logic
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
            CASE 
                WHEN SUM(di.on_hand) IS NOT NULL AND SUM(di.on_hand) > 0 THEN SUM(di.on_hand)
                ELSE d.quantity
            END as quantity,
            d.quantity_unit,
            CASE 
                WHEN COUNT(di.inventory_id) > 0 THEN GROUP_CONCAT(DISTINCT di.lot_number ORDER BY di.expiration ASC SEPARATOR ', ')
                ELSE d.lot_number
            END as lot_number,
            CASE 
                WHEN COUNT(di.inventory_id) > 0 THEN MIN(di.expiration)
                ELSE d.expiration_date
            END as expiration_date,
            d.is_controlled_substance,
            d.vial_type,
            d.vial_type_source,
            d.status,
            d.active,
            CASE 
                WHEN COUNT(di.inventory_id) > 0 THEN GROUP_CONCAT(
                    CONCAT('Lot: ', di.lot_number, ' (', di.on_hand, ' units, exp: ', di.expiration, ')')
                    ORDER BY di.expiration ASC
                    SEPARATOR '; '
                )
                ELSE CONCAT('Static: ', d.quantity, ' ', d.quantity_unit)
            END as inventory_details,
            CASE 
                WHEN COUNT(di.inventory_id) > 0 THEN 'dynamic'
                ELSE 'static'
            END as inventory_source
        FROM drugs d 
        LEFT JOIN drug_inventory di ON d.drug_id = di.drug_id AND di.on_hand > 0
        WHERE d.active = 1 AND d.status = 'active'
        GROUP BY d.drug_id
        ORDER BY d.name ASC
    ");
    $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Search All Drugs: Found " . count($drugs) . " drugs (fixed fallback logic)");
    echo json_encode($drugs);
    
} catch (Exception $e) {
    error_log("Get all drugs error: " . $e->getMessage());
    echo json_encode([]);
}
?>
