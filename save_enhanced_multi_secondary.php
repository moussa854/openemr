<?php
/**
 * Enhanced Save Script for Multiple Secondary Medications
 * This is an addition to the existing save script to handle up to 4 secondary medications
 */

// Function to save multiple secondary medications
function saveMultipleSecondaryMedications($form_id, $post_data) {
    try {
        // First, delete existing secondary medications for this form
        $delete_sql = "DELETE FROM form_enhanced_infusion_medications WHERE form_id = ? AND medication_type IN ('secondary', 'prn')";
        $delete_result = sqlStatement($delete_sql, [$form_id]);
        error_log("Enhanced infusion - Deleted existing secondary medications for form_id: $form_id");
        
        $secondary_count = 0;
        
        // Process up to 4 secondary medications
        for ($i = 1; $i <= 4; $i++) {
            $medication_name = $post_data["secondary_medication_$i"] ?? '';
            
            // Skip if medication name is empty
            if (empty(trim($medication_name))) {
                continue;
            }
            
            $secondary_count++;
            
            // Prepare secondary medication data
            $secondary_data = [
                'form_id' => $form_id,
                'medication_order' => $i,
                'medication_type' => 'secondary',
                'order_medication' => trim($medication_name),
                'order_dose' => $post_data["secondary_dose_$i"] ?? '',
                'order_strength' => $post_data["secondary_strength_$i"] ?? '',
                'administration_route' => $post_data["secondary_route_$i"] ?? '',
                'order_lot_number' => $post_data["secondary_lot_number_$i"] ?? '',
                'order_expiration_date' => !empty($post_data["secondary_expiration_date_$i"]) ? $post_data["secondary_expiration_date_$i"] : null,
                'order_ndc' => $post_data["secondary_ndc_$i"] ?? '',
                'order_every_value' => '', // Not used for secondary meds
                'order_every_unit' => $post_data["secondary_frequency_$i"] ?? '',
                'order_end_date' => null,
                'order_servicing_provider' => $post_data['order_servicing_provider'] ?? '',
                'order_npi' => $post_data['order_npi'] ?? '',
                'order_note' => '',
                'inventory_drug_id' => $post_data["secondary_inventory_drug_id_$i"] ?? null,
                'inventory_lot_number' => $post_data["secondary_inventory_lot_number_$i"] ?? '',
                'administration_start' => !empty($post_data["secondary_admin_start_$i"]) ? $post_data["secondary_admin_start_$i"] : null,
                'administration_end' => !empty($post_data["secondary_admin_end_$i"]) ? $post_data["secondary_admin_end_$i"] : null,
                'administration_rate' => '',
                'administration_rate_unit' => '',
                'administration_site' => '',
                'administration_comments' => '',
                'administration_duration' => '',
                'administration_note' => $post_data["secondary_admin_notes_$i"] ?? '',
                'inventory_quantity_used' => null,
                'inventory_wastage_quantity' => null,
                'inventory_wastage_reason' => '',
                'inventory_wastage_notes' => '',
                'created_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ];
            
            // Build the INSERT SQL
            $columns = array_keys($secondary_data);
            $placeholders = str_repeat('?,', count($columns));
            $placeholders = rtrim($placeholders, ',');
            
            $insert_sql = "INSERT INTO form_enhanced_infusion_medications (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            
            error_log("Enhanced infusion - Secondary med $i SQL: $insert_sql");
            error_log("Enhanced infusion - Secondary med $i data: " . json_encode(array_values($secondary_data)));
            
            $insert_result = sqlStatement($insert_sql, array_values($secondary_data));
            
            if ($insert_result) {
                error_log("Enhanced infusion - Secondary medication $i saved successfully");
            } else {
                error_log("Enhanced infusion - Failed to save secondary medication $i");
                throw new Exception("Failed to save secondary medication $i");
            }
        }
        
        error_log("Enhanced infusion - Saved $secondary_count secondary medications for form_id: $form_id");
        return $secondary_count;
        
    } catch (Exception $e) {
        error_log("Enhanced infusion - Error saving secondary medications: " . $e->getMessage());
        throw new Exception("Error saving secondary medications: " . $e->getMessage());
    }
}

// Function to load existing secondary medications for editing
function loadExistingSecondaryMedications($form_id) {
    try {
        $sql = "SELECT * FROM form_enhanced_infusion_medications 
                WHERE form_id = ? AND medication_type IN ('secondary', 'prn') 
                ORDER BY medication_order ASC";
        
        $result = sqlStatement($sql, [$form_id]);
        $medications = [];
        
        while ($row = sqlFetchArray($result)) {
            $medications[$row['medication_order']] = $row;
        }
        
        error_log("Enhanced infusion - Loaded " . count($medications) . " secondary medications for form_id: $form_id");
        return $medications;
        
    } catch (Exception $e) {
        error_log("Enhanced infusion - Error loading secondary medications: " . $e->getMessage());
        return [];
    }
}

// JavaScript for loading existing secondary medications into the form
function generateSecondaryMedicationJS($existing_medications) {
    if (empty($existing_medications)) {
        return '';
    }
    
    $js = "<script>\n";
    $js .= "document.addEventListener('DOMContentLoaded', function() {\n";
    
    foreach ($existing_medications as $order => $med) {
        if ($order > 1) {
            // Add additional medication blocks if needed
            $js .= "    if (secondaryMedCount < $order) {\n";
            $js .= "        for (let i = secondaryMedCount + 1; i <= $order; i++) {\n";
            $js .= "            addSecondaryMedication();\n";
            $js .= "        }\n";
            $js .= "    }\n";
        }
        
        // Populate the fields
        $js .= "    // Populate secondary medication $order\n";
        $js .= "    document.getElementById('secondary_medication_$order').value = " . json_encode($med['order_medication']) . ";\n";
        $js .= "    document.getElementById('secondary_dose_$order').value = " . json_encode($med['order_dose']) . ";\n";
        $js .= "    document.getElementById('secondary_strength_$order').value = " . json_encode($med['order_strength']) . ";\n";
        $js .= "    document.getElementById('secondary_route_$order').value = " . json_encode($med['administration_route']) . ";\n";
        $js .= "    document.getElementById('secondary_lot_number_$order').value = " . json_encode($med['order_lot_number']) . ";\n";
        $js .= "    document.getElementById('secondary_ndc_$order').value = " . json_encode($med['order_ndc']) . ";\n";
        $js .= "    document.getElementById('secondary_frequency_$order').value = " . json_encode($med['order_every_unit']) . ";\n";
        
        if (!empty($med['order_expiration_date']) && $med['order_expiration_date'] !== '0000-00-00') {
            $js .= "    document.getElementById('secondary_expiration_date_$order').value = " . json_encode($med['order_expiration_date']) . ";\n";
        }
        
        if (!empty($med['administration_start']) && $med['administration_start'] !== '0000-00-00 00:00:00') {
            $start_time = date('Y-m-d\TH:i', strtotime($med['administration_start']));
            $js .= "    document.getElementById('secondary_admin_start_$order').value = " . json_encode($start_time) . ";\n";
        }
        
        if (!empty($med['administration_end']) && $med['administration_end'] !== '0000-00-00 00:00:00') {
            $end_time = date('Y-m-d\TH:i', strtotime($med['administration_end']));
            $js .= "    document.getElementById('secondary_admin_end_$order').value = " . json_encode($end_time) . ";\n";
        }
        
        if (!empty($med['administration_note'])) {
            $js .= "    document.getElementById('secondary_admin_notes_$order').value = " . json_encode($med['administration_note']) . ";\n";
        }
        
        // If inventory data exists, show the selected drug display
        if (!empty($med['inventory_drug_id'])) {
            $js .= "    document.getElementById('secondary_selected_drug_id_$order').value = " . json_encode($med['inventory_drug_id']) . ";\n";
            $js .= "    document.getElementById('secondary_selected_drug_lot_$order').value = " . json_encode($med['inventory_lot_number']) . ";\n";
            $js .= "    document.getElementById('secondary_drug_name_display_$order').textContent = " . json_encode($med['order_medication']) . ";\n";
            $js .= "    document.getElementById('secondary_drug_quantity_display_$order').textContent = 'N/A';\n";
            $js .= "    document.getElementById('secondary_drug_form_display_$order').textContent = 'N/A';\n";
            $js .= "    document.getElementById('secondary_drug_status_display_$order').textContent = 'Selected';\n";
            $js .= "    document.getElementById('secondary_selected_drug_$order').style.display = 'block';\n";
        }
        
        $js .= "\n";
    }
    
    $js .= "});\n";
    $js .= "</script>\n";
    
    return $js;
}

// Integration instructions for the main save script:
/*
 * To integrate this into your main save script, add this code after the primary form is saved:
 * 
 * // Save multiple secondary medications
 * if ($form_id) {
 *     try {
 *         $secondary_count = saveMultipleSecondaryMedications($form_id, $_POST);
 *         error_log("Enhanced infusion - Saved $secondary_count secondary medications");
 *     } catch (Exception $e) {
 *         error_log("Enhanced infusion - Secondary medication save error: " . $e->getMessage());
 *         // Continue execution - don't fail the entire form save for secondary med issues
 *     }
 * }
 * 
 * And in your form loading section, add:
 * 
 * // Load existing secondary medications for editing
 * $existing_secondary_medications = [];
 * if ($form_id) {
 *     $existing_secondary_medications = loadExistingSecondaryMedications($form_id);
 * }
 * 
 * // Add this before the closing </body> tag in your form:
 * echo generateSecondaryMedicationJS($existing_secondary_medications);
 */

?>
