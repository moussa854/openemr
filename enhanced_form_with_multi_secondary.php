<?php
/**
 * Integration instructions for adding multiple secondary medications to the existing Enhanced Infusion Form
 * 
 * STEP 1: Insert the multi-secondary medication HTML section
 * Insert the content from enhanced_infusion_form_multi_secondary.php 
 * AFTER line 809 (end of administration section) and BEFORE line 811 (signatures section)
 * 
 * STEP 2: Update the save script 
 * Add the secondary medication save logic from save_enhanced_multi_secondary.php
 * 
 * STEP 3: Update the form loading logic
 * Add the secondary medication loading logic
 */

// Here's the complete integration code that needs to be added to the existing files:

// FOR SAVE SCRIPT (add after primary form save):
echo "
// ===== ADD THIS TO SAVE SCRIPT AFTER PRIMARY FORM IS SAVED =====
// Save multiple secondary medications
if (\$form_id) {
    try {
        // Delete existing secondary medications
        \$delete_sql = \"DELETE FROM form_enhanced_infusion_medications WHERE form_id = ? AND medication_type IN ('secondary', 'prn')\";
        sqlStatement(\$delete_sql, [\$form_id]);
        
        \$secondary_count = 0;
        
        // Process up to 4 secondary medications
        for (\$i = 1; \$i <= 4; \$i++) {
            \$medication_name = \$_POST[\"secondary_medication_\$i\"] ?? '';
            
            if (empty(trim(\$medication_name))) {
                continue;
            }
            
            \$secondary_count++;
            
            \$secondary_data = [
                'form_id' => \$form_id,
                'medication_order' => \$i,
                'medication_type' => 'secondary',
                'order_medication' => trim(\$medication_name),
                'order_dose' => \$_POST[\"secondary_dose_\$i\"] ?? '',
                'order_strength' => \$_POST[\"secondary_strength_\$i\"] ?? '',
                'administration_route' => \$_POST[\"secondary_route_\$i\"] ?? '',
                'order_lot_number' => \$_POST[\"secondary_lot_number_\$i\"] ?? '',
                'order_expiration_date' => !empty(\$_POST[\"secondary_expiration_date_\$i\"]) ? \$_POST[\"secondary_expiration_date_\$i\"] : null,
                'order_ndc' => \$_POST[\"secondary_ndc_\$i\"] ?? '',
                'order_every_value' => '',
                'order_every_unit' => \$_POST[\"secondary_frequency_\$i\"] ?? '',
                'order_end_date' => null,
                'order_servicing_provider' => \$_POST['order_servicing_provider'] ?? '',
                'order_npi' => \$_POST['order_npi'] ?? '',
                'order_note' => '',
                'inventory_drug_id' => \$_POST[\"secondary_inventory_drug_id_\$i\"] ?? null,
                'inventory_lot_number' => \$_POST[\"secondary_inventory_lot_number_\$i\"] ?? '',
                'administration_start' => !empty(\$_POST[\"secondary_admin_start_\$i\"]) ? \$_POST[\"secondary_admin_start_\$i\"] : null,
                'administration_end' => !empty(\$_POST[\"secondary_admin_end_\$i\"]) ? \$_POST[\"secondary_admin_end_\$i\"] : null,
                'administration_rate' => '',
                'administration_rate_unit' => '',
                'administration_site' => '',
                'administration_comments' => '',
                'administration_duration' => '',
                'administration_note' => \$_POST[\"secondary_admin_notes_\$i\"] ?? '',
                'inventory_quantity_used' => null,
                'inventory_wastage_quantity' => null,
                'inventory_wastage_reason' => '',
                'inventory_wastage_notes' => '',
                'created_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ];
            
            \$columns = array_keys(\$secondary_data);
            \$placeholders = str_repeat('?,', count(\$columns));
            \$placeholders = rtrim(\$placeholders, ',');
            
            \$insert_sql = \"INSERT INTO form_enhanced_infusion_medications (\" . implode(', ', \$columns) . \") VALUES (\$placeholders)\";
            
            \$insert_result = sqlStatement(\$insert_sql, array_values(\$secondary_data));
            
            if (!\$insert_result) {
                error_log(\"Enhanced infusion - Failed to save secondary medication \$i\");
            }
        }
        
        error_log(\"Enhanced infusion - Saved \$secondary_count secondary medications for form_id: \$form_id\");
        
    } catch (Exception \$e) {
        error_log(\"Enhanced infusion - Secondary medication save error: \" . \$e->getMessage());
    }
}
// ===== END SAVE SCRIPT ADDITION =====
";

// FOR FORM LOADING (add after form_id is determined):
echo "
// ===== ADD THIS TO FORM LOADING SECTION =====
// Load existing secondary medications for editing
\$existing_secondary_medications = [];
if (\$form_id) {
    \$sql = \"SELECT * FROM form_enhanced_infusion_medications 
             WHERE form_id = ? AND medication_type IN ('secondary', 'prn') 
             ORDER BY medication_order ASC\";
    
    \$result = sqlStatement(\$sql, [\$form_id]);
    
    while (\$row = sqlFetchArray(\$result)) {
        \$existing_secondary_medications[\$row['medication_order']] = \$row;
    }
}
// ===== END FORM LOADING ADDITION =====
";

// FOR FORM HTML (add before signatures section):
echo "
<!-- ===== ADD THIS HTML BEFORE THE SIGNATURES SECTION ===== -->
<!-- Multiple Secondary/PRN Medications Section -->
<div class=\"form-section\">
    <h3 class=\"section-title\"><i class=\"fa fa-plus-circle\"></i> Secondary / PRN Medications (Up to 4)</h3>
    
    <div class=\"alert alert-info\">
        <i class=\"fa fa-info-circle\"></i> Add up to 4 secondary or PRN medications for this infusion.
    </div>

    <!-- Secondary Medications Container -->
    <div id=\"secondary-medications-container\">
        <?php for (\$i = 1; \$i <= 4; \$i++): ?>
        <div class=\"secondary-medication-block\" id=\"secondary-med-<?php echo \$i; ?>\" style=\"border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; <?php echo \$i > 1 ? 'display: none;' : ''; ?>\">
            <div class=\"row\">
                <div class=\"col-md-10\">
                    <h5 class=\"text-primary\"><i class=\"fa fa-medkit\"></i> Secondary Medication #<?php echo \$i; ?></h5>
                </div>
                <div class=\"col-md-2 text-right\">
                    <button type=\"button\" class=\"btn btn-sm btn-danger\" onclick=\"clearSecondaryMedication(<?php echo \$i; ?>)\" title=\"Clear this medication\">
                        <i class=\"fa fa-trash\"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Medication Details -->
            <div class=\"row\">
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_medication_<?php echo \$i; ?>\" class=\"control-label\">Medication Name:</label>
                        <input type=\"text\" name=\"secondary_medication_<?php echo \$i; ?>\" id=\"secondary_medication_<?php echo \$i; ?>\" class=\"form-control\" 
                               placeholder=\"Medication name\" value=\"<?php echo htmlspecialchars(\$existing_secondary_medications[\$i]['order_medication'] ?? ''); ?>\">
                    </div>
                </div>
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_dose_<?php echo \$i; ?>\" class=\"control-label\">Dose:</label>
                        <input type=\"text\" name=\"secondary_dose_<?php echo \$i; ?>\" id=\"secondary_dose_<?php echo \$i; ?>\" class=\"form-control\" 
                               placeholder=\"Dose and unit\" value=\"<?php echo htmlspecialchars(\$existing_secondary_medications[\$i]['order_dose'] ?? ''); ?>\">
                    </div>
                </div>
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_strength_<?php echo \$i; ?>\" class=\"control-label\">Strength:</label>
                        <input type=\"text\" name=\"secondary_strength_<?php echo \$i; ?>\" id=\"secondary_strength_<?php echo \$i; ?>\" class=\"form-control\" 
                               placeholder=\"Medication strength\" value=\"<?php echo htmlspecialchars(\$existing_secondary_medications[\$i]['order_strength'] ?? ''); ?>\">
                    </div>
                </div>
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_route_<?php echo \$i; ?>\" class=\"control-label\">Route:</label>
                        <input type=\"text\" name=\"secondary_route_<?php echo \$i; ?>\" id=\"secondary_route_<?php echo \$i; ?>\" class=\"form-control\" 
                               placeholder=\"Route of administration\" value=\"<?php echo htmlspecialchars(\$existing_secondary_medications[\$i]['administration_route'] ?? ''); ?>\">
                    </div>
                </div>
            </div>

            <div class=\"row\">
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_lot_number_<?php echo \$i; ?>\" class=\"control-label\">Lot Number:</label>
                        <input type=\"text\" name=\"secondary_lot_number_<?php echo \$i; ?>\" id=\"secondary_lot_number_<?php echo \$i; ?>\" class=\"form-control\" 
                               placeholder=\"Lot number\" value=\"<?php echo htmlspecialchars(\$existing_secondary_medications[\$i]['order_lot_number'] ?? ''); ?>\">
                    </div>
                </div>
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_expiration_date_<?php echo \$i; ?>\" class=\"control-label\">Expiration Date:</label>
                        <input type=\"date\" name=\"secondary_expiration_date_<?php echo \$i; ?>\" id=\"secondary_expiration_date_<?php echo \$i; ?>\" class=\"form-control\" 
                               value=\"<?php echo \$existing_secondary_medications[\$i]['order_expiration_date'] ?? ''; ?>\">
                    </div>
                </div>
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_ndc_<?php echo \$i; ?>\" class=\"control-label\">NDC:</label>
                        <input type=\"text\" name=\"secondary_ndc_<?php echo \$i; ?>\" id=\"secondary_ndc_<?php echo \$i; ?>\" class=\"form-control\" 
                               placeholder=\"NDC code\" value=\"<?php echo htmlspecialchars(\$existing_secondary_medications[\$i]['order_ndc'] ?? ''); ?>\">
                    </div>
                </div>
                <div class=\"col-md-3\">
                    <div class=\"form-group\">
                        <label for=\"secondary_frequency_<?php echo \$i; ?>\" class=\"control-label\">Frequency:</label>
                        <select name=\"secondary_frequency_<?php echo \$i; ?>\" id=\"secondary_frequency_<?php echo \$i; ?>\" class=\"form-control\">
                            <option value=\"\">Select frequency</option>
                            <option value=\"once\" <?php echo (\$existing_secondary_medications[\$i]['order_every_unit'] ?? '') == 'once' ? 'selected' : ''; ?>>Once</option>
                            <option value=\"prn\" <?php echo (\$existing_secondary_medications[\$i]['order_every_unit'] ?? '') == 'prn' ? 'selected' : ''; ?>>PRN (as needed)</option>
                            <option value=\"q4h\" <?php echo (\$existing_secondary_medications[\$i]['order_every_unit'] ?? '') == 'q4h' ? 'selected' : ''; ?>>Every 4 hours</option>
                            <option value=\"q6h\" <?php echo (\$existing_secondary_medications[\$i]['order_every_unit'] ?? '') == 'q6h' ? 'selected' : ''; ?>>Every 6 hours</option>
                            <option value=\"q8h\" <?php echo (\$existing_secondary_medications[\$i]['order_every_unit'] ?? '') == 'q8h' ? 'selected' : ''; ?>>Every 8 hours</option>
                            <option value=\"q12h\" <?php echo (\$existing_secondary_medications[\$i]['order_every_unit'] ?? '') == 'q12h' ? 'selected' : ''; ?>>Every 12 hours</option>
                            <option value=\"daily\" <?php echo (\$existing_secondary_medications[\$i]['order_every_unit'] ?? '') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Administration Details -->
            <div class=\"row\">
                <div class=\"col-md-4\">
                    <div class=\"form-group\">
                        <label for=\"secondary_admin_start_<?php echo \$i; ?>\" class=\"control-label\">Administration Start:</label>
                        <input type=\"datetime-local\" name=\"secondary_admin_start_<?php echo \$i; ?>\" id=\"secondary_admin_start_<?php echo \$i; ?>\" class=\"form-control\" 
                               value=\"<?php echo !empty(\$existing_secondary_medications[\$i]['administration_start']) ? date('Y-m-d\TH:i', strtotime(\$existing_secondary_medications[\$i]['administration_start'])) : ''; ?>\">
                    </div>
                </div>
                <div class=\"col-md-4\">
                    <div class=\"form-group\">
                        <label for=\"secondary_admin_end_<?php echo \$i; ?>\" class=\"control-label\">Administration End:</label>
                        <input type=\"datetime-local\" name=\"secondary_admin_end_<?php echo \$i; ?>\" id=\"secondary_admin_end_<?php echo \$i; ?>\" class=\"form-control\" 
                               value=\"<?php echo !empty(\$existing_secondary_medications[\$i]['administration_end']) ? date('Y-m-d\TH:i', strtotime(\$existing_secondary_medications[\$i]['administration_end'])) : ''; ?>\">
                    </div>
                </div>
                <div class=\"col-md-4\">
                    <div class=\"form-group\">
                        <label for=\"secondary_admin_notes_<?php echo \$i; ?>\" class=\"control-label\">Administration Notes:</label>
                        <textarea name=\"secondary_admin_notes_<?php echo \$i; ?>\" id=\"secondary_admin_notes_<?php echo \$i; ?>\" class=\"form-control\" rows=\"2\" 
                                  placeholder=\"Notes about this medication administration\"><?php echo htmlspecialchars(\$existing_secondary_medications[\$i]['administration_note'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Add Medication Button -->
    <div class=\"row\">
        <div class=\"col-md-12 text-center\">
            <button type=\"button\" id=\"add-secondary-med-btn\" class=\"btn btn-success\" onclick=\"addSecondaryMedication()\">
                <i class=\"fa fa-plus\"></i> Add Another Secondary Medication
            </button>
            <small class=\"text-muted d-block\">You can add up to 4 secondary medications</small>
        </div>
    </div>
</div>

<script>
// JavaScript for multiple secondary medications functionality
var secondaryMedCount = <?php echo count(\$existing_secondary_medications); ?>;
var maxSecondaryMeds = 4;

if (secondaryMedCount === 0) {
    secondaryMedCount = 1;
}

// Show existing medication blocks
document.addEventListener('DOMContentLoaded', function() {
    for (let i = 1; i <= secondaryMedCount; i++) {
        const block = document.getElementById('secondary-med-' + i);
        if (block) {
            block.style.display = 'block';
        }
    }
    updateAddButtonVisibility();
});

function addSecondaryMedication() {
    if (secondaryMedCount >= maxSecondaryMeds) {
        alert('You can only add up to ' + maxSecondaryMeds + ' secondary medications.');
        return;
    }

    secondaryMedCount++;
    
    const block = document.getElementById('secondary-med-' + secondaryMedCount);
    if (block) {
        block.style.display = 'block';
    }
    
    updateAddButtonVisibility();
}

function clearSecondaryMedication(index) {
    const block = document.getElementById('secondary-med-' + index);
    if (block) {
        const inputs = block.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        
        if (index > 1) {
            block.style.display = 'none';
            if (index === secondaryMedCount) {
                secondaryMedCount--;
            }
        }
    }
    
    updateAddButtonVisibility();
}

function updateAddButtonVisibility() {
    const addBtn = document.getElementById('add-secondary-med-btn');
    if (secondaryMedCount >= maxSecondaryMeds) {
        addBtn.style.display = 'none';
    } else {
        addBtn.style.display = 'inline-block';
    }
}
</script>
<!-- ===== END MULTI-SECONDARY MEDICATION HTML ===== -->
";

echo "
INTEGRATION COMPLETE!

To add multiple secondary medications to your working Enhanced Infusion Form:

1. **Edit the main form file** (infusion_search_enhanced.php):
   - Insert the HTML section above after line 809 (end of administration section)
   - Add the form loading code after the form_id is determined

2. **Edit the save script** (save_enhanced.php):
   - Add the secondary medication save logic after the primary form is saved

3. **Test the functionality**:
   - Create a new form and test adding multiple secondary medications
   - Edit an existing form to verify medications load correctly
   - Verify all data saves and displays properly

The system will now support up to 4 secondary/PRN medications with full CRUD functionality!
";

?>
