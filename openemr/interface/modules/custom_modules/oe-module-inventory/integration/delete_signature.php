<?php
/**
 * Enhanced Infusion Form - Delete Signature
 * Handles deleting electronic signatures for the enhanced infusion form
 */

// Include OpenEMR globals
require_once(dirname(__FILE__) . "/../../../../../globals.php");
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

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$signature_id = intval($_POST['signature_id'] ?? 0);
$user_id = $_SESSION['authUserID'];

// Validate signature ID
if ($signature_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid signature ID']);
    exit;
}

try {
    // Get signature details and check permissions
    $signature_sql = "SELECT s.*, u.fname, u.lname 
                      FROM form_enhanced_infusion_signatures s
                      LEFT JOIN users u ON s.user_id = u.id
                      WHERE s.id = ? AND s.is_active = 1";
    $signature_result = sqlStatement($signature_sql, [$signature_id]);
    $signature_data = sqlFetchArray($signature_result);
    
    if (!$signature_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Signature not found']);
        exit;
    }

    // Check if user can delete this signature
    $can_delete = ($signature_data['user_id'] == $user_id || $_SESSION['userauthorized'] == 2);
    if (!$can_delete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this signature']);
        exit;
    }

    // Soft delete the signature (set is_active = 0)
    $delete_sql = "UPDATE form_enhanced_infusion_signatures 
                   SET is_active = 0, updated_at = CURRENT_TIMESTAMP 
                   WHERE id = ?";
    $delete_result = sqlStatement($delete_sql, [$signature_id]);

    if ($delete_result) {
        // Log the deletion activity
        $log_sql = "INSERT INTO form_enhanced_infusion_signature_log 
                    (form_id, signature_id, user_id, action, details, ip_address) 
                    VALUES (?, ?, ?, 'delete', ?, ?)";
        sqlStatement($log_sql, [
            $signature_data['form_id'], 
            $signature_id, 
            $user_id, 
            "Deleted signature by " . trim($signature_data['fname'] . ' ' . $signature_data['lname']), 
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Signature deleted successfully',
            'signature_id' => $signature_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete signature']);
    }

} catch (Exception $e) {
    error_log("Enhanced Infusion Delete Signature Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
