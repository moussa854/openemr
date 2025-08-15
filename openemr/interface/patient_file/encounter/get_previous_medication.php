<?php
/**
 * Get previous medication orders for a patient
 * Used by Enhanced Infusion Form
 */
require_once(dirname(__FILE__) . '/../../globals.php');

header('Content-Type: application/json');

$pid = (int)($_GET['pid'] ?? 0);
$current_encounter = (int)($_GET['current_encounter'] ?? 0);

if (!$pid) {
    echo json_encode(['success' => false, 'error' => 'Invalid patient ID']);
    exit;
}

try {
    // Get medication orders from form_enhanced_infusion_injection for this patient
    $sql = "SELECT 
                id,
                encounter,
                order_medication as medication,
                order_dose as dose,
                order_strength as strength,
                order_route as route,
                order_lot_number as lot_number,
                order_expiration_date as expiration_date,
                order_ndc as ndc,
                order_every_value,
                order_every_unit,
                order_end_date,
                order_servicing_provider as servicing_provider,
                order_npi as npi,
                order_note as note,
                administration_start,
                administration_end,
                administration_duration,
                inventory_quantity_used,
                inventory_wastage_quantity,
                inventory_wastage_reason,
                inventory_wastage_notes,
                administration_note,
                date as form_date
            FROM form_enhanced_infusion_injection 
            WHERE pid = ? 
            AND encounter != ?
            ORDER BY encounter DESC, date DESC
            LIMIT 10";
    
    $result = sqlStatement($sql, [$pid, $current_encounter]);
    
    $medications = [];
    while ($row = sqlFetchArray($result)) {
        $medications[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'medications' => $medications,
        'count' => count($medications)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_previous_medication.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
