<?php
/**
 * Enhanced Infusion and Injection Form - Save Handler
 * 
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(dirname(__FILE__) . "/../../../../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../../../../library/api.inc.php");
require_once(dirname(__FILE__) . "/../../../../../library/forms.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Add session handling
if (!isset($_SESSION['site_id'])) {
    $_SESSION['site_id'] = 'default';
}

// Set proper headers for JSON response
header('Content-Type: application/json');

// Add debugging
error_log("Enhanced infusion form save started - POST data: " . print_r($_POST, true));

// Verify CSRF token
if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    error_log("CSRF token verification failed");
    echo json_encode([
        'success' => false,
        'message' => 'CSRF token verification failed'
    ]);
    exit;
}

try {
    // Start transaction
    sqlStatement("START TRANSACTION");
    
    $pid = $_POST['pid'] ?? $_SESSION['pid'] ?? 0;
    $encounter = $_POST['encounter'] ?? $_SESSION['encounter'] ?? 0;
    $form_id = $_POST['id'] ?? null;
    
    error_log("Enhanced infusion form - PID: $pid, Encounter: $encounter, Form ID: $form_id");
    
    if (!$pid || !$encounter) {
        throw new Exception("Patient ID and Encounter ID are required");
    }
    
    // Check if administration_duration column exists, if not add it
    try {
        $check_column = sqlQuery("SHOW COLUMNS FROM form_enhanced_infusion_injection LIKE 'administration_duration'");
        if (!$check_column) {
            error_log("Adding administration_duration column to table");
            sqlStatement("ALTER TABLE form_enhanced_infusion_injection ADD COLUMN administration_duration VARCHAR(10) DEFAULT NULL AFTER administration_comments");
            error_log("administration_duration column added successfully");
        } else {
            error_log("administration_duration column already exists");
        }
    } catch (Exception $e) {
        error_log("Error checking/adding administration_duration column: " . $e->getMessage());
        // Continue anyway, the column might already exist
    }
    
    // Prepare form data
    $form_data = [
        'pid' => $pid,
        'encounter' => $encounter,
        'user' => $_SESSION['authUser'] ?? '',
        'groupname' => $_SESSION['authProvider'] ?? '',
        'authorized' => $_SESSION['userauthorized'] ?? 0,
        'activity' => 1,
        'date' => date('Y-m-d H:i:s'),
        'assessment' => $_POST['assessment'] ?? null,
        'bp_systolic' => $_POST['bp_systolic'] ?? null,
        'bp_diastolic' => $_POST['bp_diastolic'] ?? null,
        'pulse' => $_POST['pulse'] ?? null,
        'temperature_f' => $_POST['temperature_f'] ?? null,
        'oxygen_saturation' => $_POST['oxygen_saturation'] ?? null,
        'iv_access_type' => $_POST['iv_access_type'] ?? null,
        'iv_access_location' => $_POST['iv_access_location'] ?? null,
        'iv_access_blood_return' => $_POST['iv_access_blood_return'] ?? null,
        'iv_access_needle_gauge' => $_POST['iv_access_needle_gauge'] ?? null,
        'iv_access_attempts' => $_POST['iv_access_attempts'] ?? null,
        'iv_access_comments' => $_POST['iv_access_comments'] ?? null,
        'order_medication' => $_POST['order_medication'] ?? null,
        'order_dose' => $_POST['order_dose'] ?? null,
        "order_strength" => $_POST["order_strength"] ?? null,
        "administration_route" => $_POST["order_route"] ?? null,
        'order_lot_number' => $_POST['order_lot_number'] ?? null,
        'order_ndc' => $_POST['order_ndc'] ?? null,
        'order_expiration_date' => empty($_POST['order_expiration_date']) ? null : $_POST['order_expiration_date'],
        'order_every_value' => $_POST['order_every_value'] ?? null,
        'order_every_unit' => $_POST['order_every_unit'] ?? null,
        'order_end_date' => empty($_POST['order_end_date']) ? null : $_POST['order_end_date'],
        'order_servicing_provider' => $_POST['order_servicing_provider'] ?? 'Moussa El-hallak, M.D.',
        'order_npi' => $_POST['order_npi'] ?? '1831381524',
        'order_note' => $_POST['order_note'] ?? null,
        'administration_start' => $_POST['administration_start'] ?? null,
        'administration_end' => $_POST['administration_end'] ?? null,
        'administration_rate' => $_POST['administration_rate'] ?? null,
        'administration_rate_unit' => $_POST['administration_rate_unit'] ?? null,
        "administration_route" => $_POST["order_route"] ?? null,
        'administration_site' => $_POST['administration_site'] ?? null,
        'administration_comments' => $_POST['administration_comments'] ?? null,
        'administration_duration' => $_POST['administration_duration'] ?? null,
        'inventory_quantity_used' => $_POST['inventory_quantity_used'] ?? null,
        'inventory_wastage_quantity' => $_POST['inventory_wastage_quantity'] ?? null,
        'inventory_wastage_reason' => $_POST['inventory_wastage_reason'] ?? null,
        'inventory_wastage_notes' => $_POST['inventory_wastage_notes'] ?? null,
        'wastage_quantity' => $_POST['wastage_quantity'] ?? null,
        'wastage_reason' => $_POST['wastage_reason'] ?? null,
        'wastage_notes' => $_POST['wastage_notes'] ?? null,
        'diagnoses' => $_POST['diagnoses_codes'] ?? $_POST['diagnoses'] ?? null
    ];
    
    // Only add administration_duration if the column exists (we'll check this later)
    // For now, let's skip it to avoid SQL errors
    // 'administration_duration' => $_POST['administration_duration'] ?? null,
    
    error_log("Enhanced infusion form - Form data prepared: " . print_r($form_data, true));
    
    // Remove null values for database insertion
    $form_data = array_filter($form_data, function($value) {
        return $value !== null && $value !== '';
    });
    
    error_log("Enhanced infusion form - Filtered form data: " . print_r($form_data, true));
    
    if (empty($form_id)) {
        // Insert new form
        $fields = implode(', ', array_keys($form_data));
        $placeholders = implode(', ', array_fill(0, count($form_data), '?'));
        
        $sql = "INSERT INTO form_enhanced_infusion_injection ($fields) VALUES ($placeholders)";
        error_log("Enhanced infusion form - Insert SQL: $sql");
        error_log("Enhanced infusion form - Insert values: " . print_r(array_values($form_data), true));
        
        try {
            $insert_result = sqlInsert($sql, array_values($form_data));
            error_log("Enhanced infusion form - Insert result: " . ($insert_result ? "SUCCESS" : "FAILED"));
            
            if ($insert_result) {
                $form_id = sqlGetLastInsertId();
                error_log("Enhanced infusion form - New form ID: $form_id");
                
                // Create entry in the forms table for encounter display
                $formsData = [
                    'date' => date('Y-m-d H:i:s'),
                    'encounter' => $encounter,
                    'pid' => $pid,
                    'user' => $_SESSION['authUser'] ?? '',
                    'groupname' => $_SESSION['authProvider'] ?? '',
                    'authorized' => $_SESSION['userauthorized'] ?? 0,
                    'formdir' => 'enhanced_infusion_injection',
                    'form_id' => $form_id,
                    'form_name' => 'Enhanced Infusion and Injection Form'
                ];
                
                $formsSql = "INSERT INTO forms SET ";
                $first = true;
                foreach ($formsData as $key => $value) {
                    if (!$first) {
                        $formsSql .= ", ";
                    }
                    $formsSql .= "`$key` = ?";
                    $first = false;
                }
                
                error_log("Enhanced infusion form - Forms table SQL: $formsSql");
                $forms_insert_result = sqlInsert($formsSql, array_values($formsData));
                error_log("Enhanced infusion form - Forms table insert result: " . ($forms_insert_result ? "SUCCESS" : "FAILED"));
            } else {
                error_log("Enhanced infusion form - INSERT FAILED - No form ID generated");
                throw new Exception("Database insert failed");
            }
        } catch (Exception $e) {
            error_log("Enhanced infusion form - SQL Error during insert: " . $e->getMessage());
            throw new Exception("Database error: " . $e->getMessage());
        }
    } else {
        // Update existing form
        $updates = [];
        $params = [];
        
        foreach ($form_data as $key => $value) {
            if ($key !== 'id' && $key !== 'pid' && $key !== 'encounter') {
                $updates[] = "`$key` = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            error_log("Enhanced infusion form - No fields to update");
        } else {
            $sql = "UPDATE form_enhanced_infusion_injection SET " . implode(', ', $updates) . " WHERE id = ? AND pid = ?";
            $params[] = $form_id;
            $params[] = $pid;
            
            error_log("Enhanced infusion form - Update SQL: $sql");
            error_log("Enhanced infusion form - Update values: " . print_r($params, true));
            
            try {
                $update_result = sqlStatement($sql, $params);
                error_log("Enhanced infusion form - Update result: " . ($update_result ? "SUCCESS" : "FAILED"));
            } catch (Exception $e) {
                error_log("Enhanced infusion form - SQL Error during update: " . $e->getMessage());
                throw new Exception("Database error: " . $e->getMessage());
            }
        }
        
        // Update the forms table entry
        try {
            $formsUpdateSql = "UPDATE forms SET date = ? WHERE formdir = 'enhanced_infusion_injection' AND form_id = ? AND pid = ?";
            $forms_update_result = sqlStatement($formsUpdateSql, [date('Y-m-d H:i:s'), $form_id, $pid]);
            error_log("Enhanced infusion form - Forms table update result: " . ($forms_update_result ? "SUCCESS" : "FAILED"));
        } catch (Exception $e) {
            error_log("Enhanced infusion form - SQL Error during forms table update: " . $e->getMessage());
            // Don't throw here, this is not critical
        }
    }
    
    // Handle inventory integration if drug was selected
    $inventory_drug_id = $_POST['selected-drug-id'] ?? null;
    $inventory_quantity_used = floatval($_POST['order_every_value'] ?? 0);
    $inventory_wastage_quantity = floatval($_POST['wastage_quantity'] ?? 0);
    $inventory_wastage_reason = $_POST['wastage_reason'] ?? null;
    $inventory_wastage_notes = $_POST['wastage_notes'] ?? null;
    
    if ($inventory_drug_id && $inventory_quantity_used > 0) {
        // Record in infusion_inventory_usage table
        $usage_sql = "INSERT INTO infusion_inventory_usage 
                     (infusion_form_id, drug_id, quantity_used, quantity_wasted, wastage_reason, wastage_notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        sqlStatement($usage_sql, [
            $form_id,
            $inventory_drug_id,
            $inventory_quantity_used,
            $inventory_wastage_quantity,
            $inventory_wastage_reason,
            $inventory_wastage_notes,
            $_SESSION['authUserID']
        ]);
        
        // Deduct from inventory
        $deduct_sql = "UPDATE drugs 
                      SET quantity = quantity - ? 
                      WHERE drug_id = ?";
        
        sqlStatement($deduct_sql, [$inventory_quantity_used, $inventory_drug_id]);
        
        // Record wastage in drug_wastage table if there is wastage
        if ($inventory_wastage_quantity > 0) {
            $wastage_sql = "INSERT INTO drug_wastage 
                           (drug_id, quantity_wasted, reason_code, reason_description, user_id, notes, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            sqlStatement($wastage_sql, [
                $inventory_drug_id,
                $inventory_wastage_quantity,
                'INFUSION_WASTAGE',
                'Wastage from infusion administration',
                $_SESSION['authUserID'],
                $inventory_wastage_notes,
                $_SESSION['authUserID']
            ]);
        }
        
        // Log the infusion administration
        error_log("Enhanced infusion form saved with inventory integration - Form ID: $form_id, Drug ID: $inventory_drug_id, Quantity Used: $inventory_quantity_used, Wastage: $inventory_wastage_quantity");
    }
    
    // Handle multiple secondary medications (up to 4)
    if ($form_id) {
        // First, delete existing secondary medications for this form
        $delete_sql = "DELETE FROM form_enhanced_infusion_medications WHERE form_id = ? AND medication_type IN ('secondary', 'prn')";
        sqlStatement($delete_sql, [$form_id]);
        error_log("Enhanced infusion form - Deleted existing secondary medications for form_id: $form_id");
        
        // Now insert new secondary medications
        for ($i = 1; $i <= 4; $i++) {
            $medication_name = $_POST["secondary_medication_$i"] ?? '';
            $dose = $_POST["secondary_dose_$i"] ?? '';
            $strength = $_POST["secondary_strength_$i"] ?? '';
            $route = $_POST["secondary_route_$i"] ?? '';
            $lot_number = $_POST["secondary_lot_number_$i"] ?? '';
            $expiration_date = $_POST["secondary_expiration_date_$i"] ?? '';
            $ndc = $_POST["secondary_ndc_$i"] ?? '';
            $frequency = $_POST["secondary_frequency_$i"] ?? '';
            $admin_start = $_POST["secondary_admin_start_$i"] ?? '';
            $admin_end = $_POST["secondary_admin_end_$i"] ?? '';
            $admin_notes = $_POST["secondary_admin_notes_$i"] ?? '';
            
            // Get inventory data for this secondary medication
            $inventory_drug_id = $_POST["secondary_inventory_drug_id_$i"] ?? '';
            $inventory_lot_number = $_POST["secondary_inventory_lot_number_$i"] ?? '';
            
            // Only save if at least medication name is provided
            if (!empty($medication_name)) {
                // Convert datetime-local format to database format
                $admin_start_db = !empty($admin_start) ? date('Y-m-d H:i:s', strtotime($admin_start)) : null;
                $admin_end_db = !empty($admin_end) ? date('Y-m-d H:i:s', strtotime($admin_end)) : null;
                
                $secondary_sql = "INSERT INTO form_enhanced_infusion_medications 
                                (form_id, medication_order, medication_type, order_medication, order_dose, 
                                 order_strength, administration_route, order_lot_number, order_expiration_date, 
                                 order_ndc, order_every_unit, administration_start, administration_end, 
                                 administration_note, inventory_drug_id, inventory_lot_number, created_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $secondary_params = [
                    $form_id,
                    $i,  // medication_order
                    'secondary',  // medication_type
                    $medication_name,
                    $dose,
                    $strength,
                    $route,
                    $lot_number,
                    $expiration_date,
                    $ndc,
                    $frequency,
                    $admin_start_db,
                    $admin_end_db,
                    $admin_notes,
                    !empty($inventory_drug_id) ? $inventory_drug_id : null,
                    !empty($inventory_lot_number) ? $inventory_lot_number : null
                ];
                
                try {
                    $secondary_result = sqlStatement($secondary_sql, $secondary_params);
                    error_log("Enhanced infusion form - Saved secondary medication #$i: $medication_name");
                } catch (Exception $e) {
                    error_log("Enhanced infusion form - Error saving secondary medication #$i: " . $e->getMessage());
                    // Don't throw, continue with other medications
                }
            }
        }
    }
    
    // Commit transaction
    sqlStatement("COMMIT");
    error_log("Enhanced infusion form - Transaction committed successfully");
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'message' => 'Form saved successfully!',
        'form_id' => $form_id,
        'pid' => $pid,
        'encounter' => $encounter
    ]);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction
    sqlStatement("ROLLBACK");
    error_log("Error saving enhanced infusion form: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error saving form: ' . $e->getMessage()
    ]);
    exit;
}
?> 