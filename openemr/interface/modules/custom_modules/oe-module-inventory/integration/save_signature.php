<?php
/**
 * Enhanced Infusion Form - Save Signature
 * Handles saving electronic signatures for the enhanced infusion form
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

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$form_id = intval($_POST['form_id'] ?? 0);
$signature_text = trim($_POST['signature_text'] ?? '');
$signature_type = $_POST['signature_type'] ?? 'primary';
$signature_date = $_POST['signature_date'] ?? date('Y-m-d H:i:s');
$user_id = $_SESSION['authUserID'];

// Validate required fields
if ($form_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid form ID']);
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

// Validate signature type
$valid_types = ['primary', 'witness', 'reviewer', 'custom'];
if (!in_array($signature_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid signature type']);
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

    // Check if user already has a signature of this type for this form
    $existing_sql = "SELECT id FROM form_enhanced_infusion_signatures 
                     WHERE form_id = ? AND user_id = ? AND signature_type = ? AND is_active = 1";
    $existing_result = sqlStatement($existing_sql, [$form_id, $user_id, $signature_type]);
    
    if (sqlFetchArray($existing_result)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'User already has a signature of this type for this form']);
        exit;
    }

    // Get the next signature order
    $order_sql = "SELECT MAX(signature_order) as max_order FROM form_enhanced_infusion_signatures 
                  WHERE form_id = ? AND is_active = 1";
    $order_result = sqlStatement($order_sql, [$form_id]);
    $order_row = sqlFetchArray($order_result);
    $signature_order = ($order_row['max_order'] ?? 0) + 1;

    // Insert the signature
    $insert_sql = "INSERT INTO form_enhanced_infusion_signatures 
                   (form_id, user_id, signature_text, signature_date, signature_type, signature_order) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    
    $insert_result = sqlStatement($insert_sql, [
        $form_id, 
        $user_id, 
        $signature_text, 
        $signature_date, 
        $signature_type, 
        $signature_order
    ]);

    if ($insert_result) {
        $signature_id = sqlInsertID();
        
        // Log the signature activity
        $log_sql = "INSERT INTO form_enhanced_infusion_signature_log 
                    (form_id, signature_id, user_id, action, details, ip_address) 
                    VALUES (?, ?, ?, 'create', ?, ?)";
        sqlStatement($log_sql, [
            $form_id, 
            $signature_id, 
            $user_id, 
            "Added signature of type: $signature_type", 
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        // Get user information for response
        $user_sql = "SELECT fname, lname FROM users WHERE id = ?";
        $user_result = sqlStatement($user_sql, [$user_id]);
        $user_data = sqlFetchArray($user_result);
        $user_name = ($user_data['fname'] ?? '') . ' ' . ($user_data['lname'] ?? '');

        echo json_encode([
            'success' => true,
            'message' => 'Signature saved successfully',
            'signature_id' => $signature_id,
            'user_name' => trim($user_name),
            'signature_type' => $signature_type,
            'signature_date' => $signature_date,
            'signature_text' => $signature_text
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save signature']);
    }

} catch (Exception $e) {
    error_log("Enhanced Infusion Signature Save Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
