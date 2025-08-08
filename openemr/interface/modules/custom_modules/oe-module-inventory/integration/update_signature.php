<?php
/**
 * Enhanced Infusion Form - Update Signature
 * Handles updating electronic signatures for the enhanced infusion form
 */

// Include OpenEMR globals
require_once(dirname(__FILE__) . "/../../../../globals.php");
require_once(dirname(__FILE__) . "/../../../../library/sql.inc.php");
require_once(dirname(__FILE__) . "/../../../../library/forms.inc.php");

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
$signature_text = trim($_POST['signature_text'] ?? '');
$signature_date = $_POST['signature_date'] ?? date('Y-m-d H:i:s');
$user_id = $_SESSION['authUserID'];

// Validate required fields
if ($signature_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid signature ID']);
    exit;
}

// Signature text is now optional - if empty, use user's name
if (empty($signature_text)) {
    // Get user's name to use as default signature text
    $user_sql = "SELECT fname, lname FROM users WHERE id = ?";
    $user_result = sqlStatement($user_sql, [$user_id]);
    $user_data = sqlFetchArray($user_result);
    $signature_text = trim($user_data['fname'] . ' ' . $user_data['lname']);
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

    // Check if user can update this signature
    $can_update = ($signature_data['user_id'] == $user_id || $_SESSION['userauthorized'] == 2);
    if (!$can_update) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this signature']);
        exit;
    }

    // Update the signature
    $update_sql = "UPDATE form_enhanced_infusion_signatures 
                   SET signature_text = ?, signature_date = ?, updated_at = CURRENT_TIMESTAMP 
                   WHERE id = ?";
    $update_result = sqlStatement($update_sql, [$signature_text, $signature_date, $signature_id]);

    if ($update_result) {
        // Log the update activity
        $log_sql = "INSERT INTO form_enhanced_infusion_signature_log 
                    (form_id, signature_id, user_id, action, details, ip_address) 
                    VALUES (?, ?, ?, 'update', ?, ?)";
        sqlStatement($log_sql, [
            $signature_data['form_id'], 
            $signature_id, 
            $user_id, 
            "Updated signature text and date", 
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Signature updated successfully',
            'signature_id' => $signature_id,
            'signature_text' => $signature_text,
            'signature_date' => $signature_date
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update signature']);
    }

} catch (Exception $e) {
    error_log("Enhanced Infusion Update Signature Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
