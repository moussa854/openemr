<?php
/**
 * Enhanced Infusion Form Save Handler
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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Verify CSRF token
if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get form data
$formData = $_POST;
error_log("=== DEBUG SAVE: Raw POST data " . json_encode($formData));
error_log("=== DEBUG SAVE: Form ID from request: " . ($formId ?? 'null'));
error_log("=== DEBUG SAVE: PID: " . $formData['pid'] . ", Encounter: " . $formData['encounter']);

// If PID is empty but we have an encounter, try to get PID from the encounter
if (empty($formData['pid']) && !empty($formData['encounter'])) {
    $encounter_sql = "SELECT pid FROM form_encounter WHERE encounter = ?";
    $encounter_result = sqlStatement($encounter_sql, [$formData['encounter']]);
    if ($encounter_row = sqlFetchArray($encounter_result)) {
        $formData['pid'] = $encounter_row['pid'];
        error_log("=== DEBUG SAVE: Retrieved PID from encounter: " . $formData['pid']);
    } else {
        error_log("=== DEBUG SAVE: Could not retrieve PID from encounter: " . $formData['encounter']);
    }
}

// Validate required fields
if (empty($formData['pid']) || empty($formData['encounter'])) {
    echo json_encode(['success' => false, 'message' => 'Patient ID and Encounter are required']);
    exit;
}

$formId = $formData['id'] ?? null;
$formsTableId = $formData['forms_id'] ?? null; // For updating the forms table

// Prepare data for form_enhanced_infusion_injection table
$injectionData = [
    'pid' => $formData['pid'],
    'encounter' => $formData['encounter'],
    'assessment' => $formData['assessment'] ?? '',
    'bp_systolic' => $formData['bp_systolic'] ?? '',
    'bp_diastolic' => $formData['bp_diastolic'] ?? '',
    'pulse' => $formData['pulse'] ?? '',
    'temperature_f' => $formData['temperature_f'] ?? '',
    'oxygen_saturation' => $formData['oxygen_saturation'] ?? '',
    'respiratory_rate' => $formData['respiratory_rate'] ?? '',
    'weight' => $formData['weight'] ?? '',
    'height' => $formData['height'] ?? '',
    'iv_access_type' => $formData['iv_access_type'] ?? '',
    'iv_access_location' => $formData['iv_access_location'] ?? '',
    'iv_access_needle_gauge' => $formData['iv_access_needle_gauge'] ?? '',
    'iv_access_blood_return' => $formData['iv_access_blood_return'] ?? '',
    'iv_access_attempts' => $formData['iv_access_attempts'] ?? '',
    'iv_access_date' => $formData['iv_access_date'] ?? '',
    'iv_access_comments' => $formData['iv_access_comments'] ?? '',
    'diagnoses' => $formData['diagnoses_codes'] ?? '',
    'inventory_drug_id' => $formData['inventory_drug_id'] ?? '',
    'inventory_lot_number' => $formData['inventory_lot_number'] ?? '',
    'order_medication' => $formData['order_medication'] ?? '',
    'order_dose' => $formData['order_dose'] ?? '',
    'order_strength' => $formData['order_strength'] ?? '',
    'administration_route' => $formData['order_route'] ?? '', // Map order_route to administration_route
    'order_lot_number' => $formData['order_lot_number'] ?? '',
    'order_expiration_date' => $formData['order_expiration_date'] ?? '',
    'order_ndc' => $formData['order_ndc'] ?? '',
    'order_every_value' => $formData['order_every_value'] ?? '',
    'order_every_unit' => $formData['order_every_unit'] ?? '',
    'order_end_date' => $formData['order_end_date'] ?? '',
    'order_servicing_provider' => $formData['order_servicing_provider'] ?? '',
    'order_npi' => $formData['order_npi'] ?? '',
    'order_note' => $formData['order_note'] ?? '',
    'administration_start' => $formData['administration_start'] ?? '',
    'administration_end' => $formData['administration_end'] ?? '',
    'administration_duration' => $formData['administration_duration'] ?? '',
    'inventory_quantity_used' => $formData['inventory_quantity_used'] ?? '',
    'inventory_wastage_quantity' => $formData['inventory_wastage_quantity'] ?? '',
    'inventory_wastage_reason' => $formData['inventory_wastage_reason'] ?? '',
    'inventory_wastage_notes' => $formData['inventory_wastage_notes'] ?? '',
    'administration_note' => $formData['administration_note'] ?? '',
    'date' => date('Y-m-d H:i:s')
];

try {
    // Check if form exists
    if ($formId) {
        $check_sql = "SELECT id FROM form_enhanced_infusion_injection WHERE id = ? AND pid = ?";
        $check_result = sqlStatement($check_sql, [$formId, $formData['pid']]);
        if (sqlFetchArray($check_result)) {
            // Update existing form
            $update_sql = "UPDATE form_enhanced_infusion_injection SET 
                pid = ?, encounter = ?, assessment = ?, bp_systolic = ?, bp_diastolic = ?, 
                pulse = ?, temperature_f = ?, oxygen_saturation = ?, respiratory_rate = ?, 
                weight = ?, height = ?, iv_access_type = ?, iv_access_location = ?, 
                iv_access_needle_gauge = ?, iv_access_blood_return = ?, iv_access_attempts = ?, 
                iv_access_date = ?, iv_access_comments = ?, diagnoses = ?, 
                inventory_drug_id = ?, inventory_lot_number = ?, order_medication = ?, 
                order_dose = ?, order_strength = ?, administration_route = ?, order_lot_number = ?, 
                order_expiration_date = ?, order_ndc = ?, order_every_value = ?, order_every_unit = ?, 
                order_end_date = ?, order_servicing_provider = ?, order_npi = ?, order_note = ?, 
                administration_start = ?, administration_end = ?, administration_duration = ?, 
                inventory_quantity_used = ?, inventory_wastage_quantity = ?, inventory_wastage_reason = ?, 
                inventory_wastage_notes = ?, administration_note = ?, date = ? 
                WHERE id = ?";
            
            $update_params = array_values($injectionData);
            $update_params[] = $formId;
            
            $update_result = sqlStatement($update_sql, $update_params);
            error_log("=== DEBUG SAVE: Update result: " . ($update_result ? 'SUCCESS' : 'FAILED'));
            
            $addFormResult = addForm($formData['encounter'], "Enhanced Infusion Form", $formId, "enhanced_infusion", $formData['pid'], 1);
            error_log("=== DEBUG SAVE: addForm result: " . ($addFormResult ? 'SUCCESS' : 'FAILED'));
        } else {
            // Form ID exists but record not found, do insert instead
            $formId = null;
        }
    }
    
    if (!$formId) {
            // Insert new form
    // Build dynamic insert based on keys of $injectionData to avoid mismatch errors
    $columns = array_keys($injectionData);
    $placeholders = rtrim(str_repeat('?, ', count($columns)), ', ');
    $colList = implode(', ', $columns);
    $insert_sql = "INSERT INTO form_enhanced_infusion_injection ( $colList ) VALUES ( $placeholders )";
    
    error_log("=== DEBUG SAVE: Primary insert SQL: " . $insert_sql);
    error_log("=== DEBUG SAVE: Primary insert data: " . json_encode(array_values($injectionData)));

    $insert_result = sqlInsert($insert_sql, array_values($injectionData));
        error_log("=== DEBUG SAVE: Insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
        
        if ($insert_result) {
            $formId = sqlGetLastInsertId();
            error_log("=== DEBUG SAVE: New form created - FormID: " . $formId . ", Encounter: " . $formData['encounter'] . ", PID: " . $formData['pid']);
            
            $addFormResult = addForm($formData['encounter'], "Enhanced Infusion Form", $formId, "enhanced_infusion", $formData['pid'], 1);
            error_log("=== DEBUG SAVE: addForm result: " . ($addFormResult ? 'SUCCESS' : 'FAILED'));
        }
    }
    
    // Handle secondary/PRN medications
    if ($formId) {
        // First, delete existing secondary medications for this form
        $delete_secondary_sql = "DELETE FROM form_enhanced_infusion_medications WHERE form_id = ?";
        sqlStatement($delete_secondary_sql, [$formId]);
        
        // Process secondary medications
        $secondary_medications = [];
        $i = 0;
        while (isset($formData["secondary_medication_$i"])) {
            if (!empty($formData["secondary_medication_$i"])) {
                error_log("=== DEBUG SAVE: Secondary index $i medication=".$formData["secondary_medication_$i"]);
                $secondary_medications[] = [
                    'form_id' => $formId,
                    'medication_order' => $i + 1,
                    'medication_type' => 'secondary', // Assuming all secondary are of type 'secondary'
                    'inventory_drug_id' => $formData["secondary_inventory_drug_id_$i"] ?? '',
                    'inventory_lot_number' => $formData["secondary_inventory_lot_number_$i"] ?? '',
                    'order_medication' => $formData["secondary_medication_$i"] ?? '',
                    'order_dose' => $formData["secondary_dose_$i"] ?? '',
                    'order_strength' => $formData["secondary_strength_$i"] ?? '',
                    'administration_route' => $formData["secondary_route_$i"] ?? '',
                    'order_lot_number' => $formData["secondary_lot_number_$i"] ?? '',
                    'order_expiration_date' => $formData["secondary_expiration_date_$i"] ?? '',
                    'order_ndc' => $formData["secondary_ndc_$i"] ?? '',
                    'order_every_value' => $formData["secondary_every_value_$i"] ?? '',
                    'order_every_unit' => $formData["secondary_every_unit_$i"] ?? '',
                    'order_end_date' => $formData["secondary_end_date_$i"] ?? '',
                    'order_servicing_provider' => $formData["secondary_servicing_provider_$i"] ?? '',
                    'order_npi' => $formData["secondary_npi_$i"] ?? '',
                    'order_note' => $formData["secondary_note_$i"] ?? '',
                    'administration_start' => $formData["secondary_administration_start_$i"] ?? '',
                    'administration_end' => $formData["secondary_administration_end_$i"] ?? '',
                    'administration_duration' => $formData["secondary_administration_duration_$i"] ?? '',
                    'administration_note' => $formData["secondary_administration_note_$i"] ?? '',
                    'date' => date('Y-m-d H:i:s')
                ];
            }
            $i++;
        }
        
        // Insert secondary medications
        foreach ($secondary_medications as $medIndex => $med) {
            $insert_secondary_sql = "INSERT INTO form_enhanced_infusion_medications (
                form_id, medication_order, medication_type, inventory_drug_id, inventory_lot_number, order_medication, order_dose, order_strength, administration_route, order_lot_number, order_expiration_date, order_ndc, order_every_value, order_every_unit, order_end_date, order_servicing_provider, order_npi, order_note, administration_start, administration_end, administration_duration, administration_note, created_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            error_log("=== DEBUG SAVE: Secondary med $medIndex insert data: " . json_encode(array_values($med)));
            $secondary_result = sqlStatement($insert_secondary_sql, array_values($med));
            error_log("=== DEBUG SAVE: Secondary med $medIndex insert result: " . ($secondary_result ? 'SUCCESS' : 'FAILED'));
        }
        
        error_log("=== DEBUG SAVE: Processed " . count($secondary_medications) . " secondary medications");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Form saved successfully!',
        'form_id' => $formId
    ]);
    
} catch (Exception $e) {
    error_log("=== DEBUG SAVE: Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving form: ' . $e->getMessage()
    ]);
}
?>
