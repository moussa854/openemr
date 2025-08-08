<?php
/**
 * Enhanced Infusion Form - Get Signatures
 * Retrieves existing signatures for the enhanced infusion form
 */

// Include OpenEMR globals
require_once(dirname(__FILE__) . "/../../../../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../../../../library/sql.inc.php");
require_once(dirname(__FILE__) . "/../../../../../library/forms.inc.php");

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['authUserID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get form ID from request
$form_id = intval($_GET['form_id'] ?? 0);

// Validate form ID
if ($form_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid form ID']);
    exit;
}

try {
    // Check if form exists
    $form_sql = "SELECT id FROM form_enhanced_infusion_injection WHERE id = ?";
    $form_result = sqlStatement($form_sql, [$form_id]);
    if (!sqlFetchArray($form_result)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Form not found']);
        exit;
    }

    // Get all active signatures for this form
    $signatures_sql = "SELECT 
                        s.id,
                        s.signature_text,
                        s.signature_date,
                        s.signature_type,
                        s.signature_order,
                        s.created_at,
                        u.fname,
                        u.lname,
                        st.display_name as type_display_name
                       FROM form_enhanced_infusion_signatures s
                       LEFT JOIN users u ON s.user_id = u.id
                       LEFT JOIN signature_types st ON s.signature_type = st.type_name
                       WHERE s.form_id = ? AND s.is_active = 1
                       ORDER BY s.signature_order ASC, s.created_at ASC";
    
    $signatures_result = sqlStatement($signatures_sql, [$form_id]);
    $signatures = [];
    
    while ($row = sqlFetchArray($signatures_result)) {
        $signatures[] = [
            'id' => $row['id'],
            'signature_text' => $row['signature_text'],
            'signature_date' => $row['signature_date'],
            'signature_type' => $row['signature_type'],
            'type_display_name' => $row['type_display_name'] ?? ucfirst($row['signature_type']),
            'signature_order' => $row['signature_order'],
            'user_name' => trim($row['fname'] . ' ' . $row['lname']),
            'created_at' => $row['created_at'],
            'can_edit' => ($row['user_id'] == $_SESSION['authUserID'] || $_SESSION['userauthorized'] == 2) // User owns signature or is admin
        ];
    }

    // Get available signature types
    $types_sql = "SELECT type_name, display_name, is_required, sort_order 
                  FROM signature_types 
                  WHERE is_active = 1 
                  ORDER BY sort_order ASC";
    $types_result = sqlStatement($types_sql);
    $signature_types = [];
    
    while ($row = sqlFetchArray($types_result)) {
        $signature_types[] = [
            'type_name' => $row['type_name'],
            'display_name' => $row['display_name'],
            'is_required' => (bool)$row['is_required'],
            'sort_order' => $row['sort_order']
        ];
    }

    // Log the view activity
    $log_sql = "INSERT INTO form_enhanced_infusion_signature_log 
                (form_id, user_id, action, details, ip_address) 
                VALUES (?, ?, 'view', ?, ?)";
    sqlStatement($log_sql, [
        $form_id, 
        $_SESSION['authUserID'], 
        "Viewed signatures for form ID: $form_id", 
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    echo json_encode([
        'success' => true,
        'signatures' => $signatures,
        'signature_types' => $signature_types,
        'total_signatures' => count($signatures)
    ]);

} catch (Exception $e) {
    error_log("Enhanced Infusion Get Signatures Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
