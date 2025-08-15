<?php
/**
 * Get signatures for a form
 * Used by Enhanced Infusion Form
 */
require_once(dirname(__FILE__) . '/../../globals.php');

header('Content-Type: application/json');

$form_id = (int)($_GET['form_id'] ?? 0);

if (!$form_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid form ID']);
    exit;
}

try {
    // For now, return empty signatures array as this is just a placeholder
    // The actual signature functionality would need to be implemented
    echo json_encode([
        'success' => true,
        'signatures' => []
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_signatures.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
