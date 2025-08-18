<?php
// Search All Drugs API - Fixed Version
// Uses direct database connection that works

header('Content-Type: application/json');

try {
    // Direct database connection with correct credentials
    $host = 'localhost';
    $dbname = 'openemr';
    $username = 'openemr';
    $password = 'cfvcfv33';  // Use the correct password
    
    $dsn = "mysql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Query for active drugs including vial type
    $stmt = $pdo->query("
        SELECT 
            drug_id,
            name,
            barcode,
            ndc_10,
            ndc_11,
            form,
            size,
            unit,
            dose,
            route,
            quantity,
            quantity_unit,
            lot_number,
            expiration_date,
            is_controlled_substance,
            vial_type,
            vial_type_source,
            status,
            active
        FROM drugs 
        WHERE active = 1 AND status = 'active'
        ORDER BY name ASC
    ");
    $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("Search All Drugs: Found " . count($drugs) . " drugs");
    
    // Return JSON
    echo json_encode($drugs);
    
} catch (Exception $e) {
    error_log("Get all drugs error: " . $e->getMessage());
    echo json_encode([]);
}
?>
