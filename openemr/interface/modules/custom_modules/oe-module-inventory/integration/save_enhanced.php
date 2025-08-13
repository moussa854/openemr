<?php
/**
 * Save Enhanced Infusion Form with Multi-Medication Support
 * 
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Initialize OpenEMR
$ignoreAuth = false;
require_once(dirname(__FILE__) . "/../../../../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../../../../library/forms.inc.php");
require_once(dirname(__FILE__) . "/../../../../../library/patient.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Check if user is authenticated
if (!isset($_SESSION['authUserID'])) {
    die(json_encode(['success' => false, 'message' => 'Authentication required']));
}

// Verify CSRF token
if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
    die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

// Get form data
$formData = $_POST;

// DEBUG: Log the form data being received
error_log("=== DEBUG SAVE: Form data received - PID: " . ($formData['pid'] ?? 'NOT_SET'));
error_log("=== DEBUG SAVE: Form data received - Encounter: " . ($formData['encounter'] ?? 'NOT_SET'));
error_log("=== DEBUG SAVE: Form data received - Assessment: " . ($formData['assessment'] ?? 'NOT_SET'));
error_log("=== DEBUG SAVE: Form data received - Order Medication: " . ($formData['order_medication'] ?? 'NOT_SET'));

// DEBUG: Log all form data for debugging
error_log("=== DEBUG SAVE: All form data: " . print_r($formData, true));

try {
    // Start transaction
    sqlStatement("START TRANSACTION");
    
    // Prepare main form data
    $mainFormData = [
        'date' => date('Y-m-d H:i:s'),
        'pid' => $formData['pid'] ?? 0,
        'encounter' => $formData['encounter'] ?? 0,
        'user' => $_SESSION['authUser'],
        'groupname' => $_SESSION['authProvider'],
        'authorized' => 1,
        'activity' => 1,
        'assessment' => $formData['assessment'] ?? '',
        'iv_access_type' => $formData['iv_access_type'] ?? '',
        'iv_access_location' => $formData['iv_access_location'] ?? '',
        'iv_access_blood_return' => $formData['iv_access_blood_return'] ?? '',
        'iv_access_needle_gauge' => $formData['iv_access_needle_gauge'] ?? '',
        'iv_access_attempts' => $formData['iv_access_attempts'] ?? '',
        'iv_access_date' => $formData['iv_access_date'] ?? null,
        'iv_access_comments' => $formData['iv_access_comments'] ?? '',
        'order_medication' => $formData['order_medication'] ?? '',
        'order_dose' => $formData['order_dose'] ?? '',
        'order_strength' => $formData['order_strength'] ?? '',
        'order_lot_number' => $formData['order_lot_number'] ?? '',
        'order_ndc' => $formData['order_ndc'] ?? '',
        'order_expiration_date' => $formData['order_expiration_date'] ?? null,
        'order_every_value' => $formData['order_every_value'] ?? '',
        'order_every_unit' => $formData['order_every_unit'] ?? '',
        'order_servicing_provider' => $formData['order_servicing_provider'] ?? '',
        'order_npi' => $formData['order_npi'] ?? '',
        'order_end_date' => $formData['order_end_date'] ?? null,
        'order_note' => $formData['order_note'] ?? '',
        'administration_start' => $formData['administration_start'] ?? null,
        'administration_end' => $formData['administration_end'] ?? null,
        'administration_rate' => $formData['administration_rate'] ?? '',
        'administration_rate_unit' => $formData['administration_rate_unit'] ?? '',
        'administration_route' => $formData['administration_route'] ?? '',
        'administration_site' => $formData['administration_site'] ?? '',
        'administration_comments' => $formData['administration_comments'] ?? '',
        'administration_duration' => $formData['administration_duration'] ?? '',
        'wastage_quantity' => $formData['wastage_quantity'] ?? null,
        'wastage_reason' => $formData['wastage_reason'] ?? '',
        'wastage_notes' => $formData['wastage_notes'] ?? '',
        'administration_note' => $formData['administration_note'] ?? '',
        'diagnoses' => $formData['diagnoses'] ?? '',
        'bp_systolic' => $formData['bp_systolic'] ?? '',
        'bp_diastolic' => $formData['bp_diastolic'] ?? '',
        'pulse' => $formData['pulse'] ?? '',
        'temperature_f' => $formData['temperature_f'] ?? '',
        'oxygen_saturation' => $formData['oxygen_saturation'] ?? '',
        'respiratory_rate' => $formData['respiratory_rate'] ?? '',
        'weight' => $formData['weight'] ?? '',
        'height' => $formData['height'] ?? '',
        'inventory_drug_id' => $formData['inventory_drug_id'] ?? null,
        'inventory_lot_number' => $formData['inventory_lot_number'] ?? '',
        'inventory_quantity_used' => $formData['inventory_quantity_used'] ?? null,
        'inventory_wastage_quantity' => $formData['inventory_wastage_quantity'] ?? null,
        'inventory_wastage_reason' => $formData['inventory_wastage_reason'] ?? '',
        'inventory_wastage_notes' => $formData['inventory_wastage_notes'] ?? '',
        'inventory_reservation_id' => $formData['inventory_reservation_id'] ?? null
    ];
    
    // Check if this is an update or new form
    $formId = $formData['id'] ?? null;
    
    if ($formId) {
        // Update existing form
        $updateSql = "UPDATE form_enhanced_infusion_injection SET ";
        $updateFields = [];
        $updateValues = [];
        
        foreach ($mainFormData as $field => $value) {
            if ($field !== 'id') {
                $updateFields[] = "$field = ?";
                $updateValues[] = $value;
            }
        }
        
        $updateSql .= implode(', ', $updateFields);
        $updateSql .= " WHERE id = ?";
        $updateValues[] = $formId;
        
        sqlStatement($updateSql, $updateValues);
        
        // Delete existing secondary medications
        sqlStatement("DELETE FROM form_enhanced_infusion_medications WHERE form_id = ?", [$formId]);
        
        // Update form registration in forms table for encounter report
        error_log("=== DEBUG SAVE: Updating form registration - Encounter: " . $formData['encounter'] . ", PID: " . $formData['pid'] . ", FormID: " . $formId);
        $addFormResult = addForm($formData['encounter'], "Enhanced Infusion Form", $formId, "enhanced_infusion_injection", $formData['pid'], 1);
        error_log("=== DEBUG SAVE: addForm result: " . ($addFormResult ? 'SUCCESS' : 'FAILED'));
        
    } else {
        // Insert new form
        $insertSql = "INSERT INTO form_enhanced_infusion_injection (";
        $insertSql .= implode(', ', array_keys($mainFormData));
        $insertSql .= ") VALUES (" . str_repeat('?,', count($mainFormData) - 1) . "?)";
        
        sqlStatement($insertSql, array_values($mainFormData));
        $formId = sqlGetLastInsertId();
        error_log("=== DEBUG SAVE: New form created - FormID: " . $formId . ", Encounter: " . $formData['encounter'] . ", PID: " . $formData['pid']);
        // Register form in forms table for encounter report
        $addFormResult = addForm($formData['encounter'], "Enhanced Infusion Form", $formId, "enhanced_infusion_injection", $formData['pid'], 1);
        error_log("=== DEBUG SAVE: addForm result: " . ($addFormResult ? 'SUCCESS' : 'FAILED'));
    }
    
    // Save secondary medications
    if (isset($formData['secondary_medications']) && is_array($formData['secondary_medications'])) {
        foreach ($formData['secondary_medications'] as $index => $medicationData) {
            if (empty($medicationData['order_medication'])) {
                continue; // Skip empty medications
            }
            
            $medicationOrder = $index + 2; // Start from 2 (secondary)
            $medicationType = $medicationOrder === 2 ? 'secondary' : 'prn';
            
            $medicationInsertSql = "INSERT INTO form_enhanced_infusion_medications (
                form_id, medication_order, medication_type,
                order_medication, order_dose, order_strength, order_lot_number, order_ndc,
                order_expiration_date, order_every_value, order_every_unit, order_servicing_provider,
                order_npi, order_end_date, order_note, inventory_drug_id, inventory_lot_number,
                administration_start, administration_end, administration_rate, administration_rate_unit,
                administration_route, administration_site, administration_comments, administration_duration,
                administration_note, inventory_quantity_used, inventory_wastage_quantity,
                inventory_wastage_reason, inventory_wastage_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            sqlStatement($medicationInsertSql, [
                $formId,
                $medicationOrder,
                $medicationType,
                $medicationData['order_medication'] ?? '',
                $medicationData['order_dose'] ?? '',
                $medicationData['order_strength'] ?? '',
                $medicationData['order_lot_number'] ?? '',
                $medicationData['order_ndc'] ?? '',
                $medicationData['order_expiration_date'] ?? null,
                $medicationData['order_every_value'] ?? '',
                $medicationData['order_every_unit'] ?? '',
                $medicationData['order_servicing_provider'] ?? '',
                $medicationData['order_npi'] ?? '',
                $medicationData['order_end_date'] ?? null,
                $medicationData['order_note'] ?? '',
                $medicationData['inventory_drug_id'] ?? null,
                $medicationData['inventory_lot_number'] ?? '',
                $medicationData['administration_start'] ?? null,
                $medicationData['administration_end'] ?? null,
                $medicationData['administration_rate'] ?? '',
                $medicationData['administration_rate_unit'] ?? '',
                $medicationData['administration_route'] ?? '',
                $medicationData['administration_site'] ?? '',
                $medicationData['administration_comments'] ?? '',
                $medicationData['administration_duration'] ?? '',
                $medicationData['administration_note'] ?? '',
                $medicationData['inventory_quantity_used'] ?? null,
                $medicationData['inventory_wastage_quantity'] ?? null,
                $medicationData['inventory_wastage_reason'] ?? '',
                $medicationData['inventory_wastage_notes'] ?? ''
            ]);
        }
    }
    
    // Commit transaction
    sqlStatement("COMMIT");
    
    // Return success response
    ob_clean(); echo json_encode([
        'success' => true,
        'message' => 'Form saved successfully!',
        'form_id' => $formId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    sqlStatement("ROLLBACK");
    
    error_log("Error saving enhanced infusion form: " . $e->getMessage());
    ob_clean(); echo json_encode([
        'success' => false,
        'message' => 'Error saving form: ' . $e->getMessage()
    ]);
}
?>
