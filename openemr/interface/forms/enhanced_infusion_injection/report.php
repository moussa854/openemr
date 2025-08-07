<?php
require_once($GLOBALS["fileroot"] . "/library/formatting.inc.php");
/**
 * Enhanced Infusion and Injection Form Report
 * 
 * This file generates a printable report for the Enhanced Infusion and Injection Form.
 * It displays all form data in a structured, readable format.
 */

// Function to check if a field has meaningful data
function hasValue($value) {
    return !empty($value) && $value !== null && $value !== '';
}

function extractUnitFromDose($dose) {
    if (empty($dose)) return '';
    
    // Common units to look for
    $units = ['mg', 'mL', 'gram', 'grams', 'g', 'mcg', 'units', 'IU', 'mEq', 'mmol'];
    
    foreach ($units as $unit) {
        if (stripos($dose, $unit) !== false) {
            return $unit;
        }
    }
    
    // If no specific unit found, try to extract anything after the last space
    $parts = explode(' ', trim($dose));
    if (count($parts) > 1) {
        return end($parts);
    }
    
    return '';
}

// Function to build allergies HTML from system
function buildAllergyHtml($pid) {
    if (empty($pid)) {
        return 'No patient selected.';
    }
    $rows = sqlStatement("SELECT title,reaction FROM lists WHERE pid = ? AND type = 'allergy' AND (enddate IS NULL OR enddate = '' OR enddate = '0000-00-00') AND activity = 1", [$pid]);
    $items = [];
    while ($row = sqlFetchArray($rows)) {
        $label = htmlspecialchars($row['title']);
        $reaction = trim($row['reaction']);
        if ($reaction !== '') {
            $label .= ' (' . htmlspecialchars($reaction) . ')';
        }
        $items[] = $label;
    }
    if (empty($items)) {
        return 'No known allergies recorded.';
    }
    $html = '';
    foreach ($items as $item) {
        $html .= '• ' . $item . '<br>';
    }
    return $html;
}

function enhanced_infusion_injection_report($pid, $encounter, $cols, $id, $print = true) {
    // Get patient data
    $patient = getPatientData($pid);
    
    // Get encounter date
    $encounter_date = sqlQuery("SELECT date FROM form_encounter WHERE pid = ? AND encounter = ?", [$pid, $encounter]);
    $dos_date = $encounter_date['date'] ?? date('Y-m-d');
    
    // Get form data from the database
    $sql = "SELECT * FROM form_enhanced_infusion_injection WHERE id = ? AND pid = ?";
    $form_data = sqlQuery($sql, [$id, $pid]);
    
    if ($print) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Infusion & Injection Form</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { color: #007bff; margin: 0 0 10px 0; }
                .patient-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: left; border: 1px solid #e9ecef; }
                .patient-info p { margin: 8px 0; line-height: 1.4; }
                .patient-info .info-pair { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
                .patient-info .info-pair:last-child { margin-bottom: 0; }
                .patient-info .info-item { flex: 1; }
                .patient-info .info-item:first-child { margin-right: 20px; }
                .patient-info .info-label { font-weight: bold; color: #495057; }
                .patient-info .info-value { color: #212529; font-weight: 500; }
                .field-label { font-weight: bold; min-width: 120px; color: #495057; display: inline-block; }
                .field-value { color: #212529; font-weight: 500; }
                .section { margin-bottom: 30px; border: 1px solid #dee2e6; border-radius: 5px; overflow: hidden; }
                .section-title { background: #007bff; color: white; padding: 10px 15px; font-weight: bold; font-size: 16px; }
                .field { padding: 10px 15px; border-bottom: 1px solid #dee2e6; display: flex; }
                .field:last-child { border-bottom: none; }
                .print-button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
                .no-data { color: #6c757d; font-style: italic; }
                @media print {
                    .print-button, button { display: none; }
                    body { background: white; }
                    .container { box-shadow: none; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Infusion & Injection Form</h1>
                    <div class="patient-info">
                        <div class="info-pair">
                            <div class="info-item">
                                <span class="info-label">Patient:</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['fname'] . ' ' . $patient['lname']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">DOB:</span>
                                <span class="info-value"><?php echo htmlspecialchars(oeFormatShortDate($patient['DOB'] ?? '')); ?></span>
                            </div>
                        </div>
                        <div class="info-pair">
                            <div class="info-item">
                                <span class="info-label">DOS:</span>
                                <span class="info-value"><?php echo oeFormatShortDate($dos_date); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Encounter:</span>
                                <span class="info-value"><?php echo htmlspecialchars($encounter); ?></span>
                            </div>
                        </div>
                        <div class="info-pair">
                            <div class="info-item">
                                <span class="info-label">Provider:</span>
                                <span class="info-value"><?php echo htmlspecialchars($form_data['order_servicing_provider'] ?? 'Moussa El-hallak, M.D.'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">NPI:</span>
                                <span class="info-value"><?php echo htmlspecialchars($form_data['order_npi'] ?? '1831381524'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                

                
                <?php if ($form_data): ?>
                    <?php if (hasValue($form_data['assessment'])): ?>
                    <div class="section">
                        <div class="section-title">Assessment</div>
                        <div class="field">
                            <span class="field-label">Assessment:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['assessment']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasValue($form_data['diagnoses'])): ?>
                    <div class="section">
                        <div class="section-title">Diagnoses</div>
                        <?php 
                        $diagnoses_array = explode('|', $form_data['diagnoses']);
                        $clean_diagnoses = [];
                        foreach ($diagnoses_array as $diagnosis) {
                            $diagnosis = trim($diagnosis);
                            if (!empty($diagnosis)) {
                                $clean_diagnoses[] = $diagnosis;
                            }
                        }
                        if (!empty($clean_diagnoses)) {
                        ?>
                        <div class="field">
                            <span class="field-label">Diagnosis:</span>
                            <span class="field-value"><?php echo htmlspecialchars($clean_diagnoses[0]); ?></span>
                        </div>
                        <?php 
                            // Display additional diagnoses without the label
                            for ($i = 1; $i < count($clean_diagnoses); $i++) {
                        ?>
                        <div class="field">
                            <span class="field-label"></span>
                            <span class="field-value"><?php echo htmlspecialchars($clean_diagnoses[$i]); ?></span>
                        </div>
                        <?php 
                            }
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="section">
                        <div class="section-title">Order Details</div>
                        <?php if (hasValue($form_data['order_medication'])): ?>
                        <div class="field">
                            <span class="field-label">Medication:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_medication']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_dose'])): ?>
                        <div class="field">
                            <span class="field-label">Dose:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_dose']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_every_value']) || hasValue($form_data['order_every_unit'])): ?>
                        <div class="field">
                            <span class="field-label">Frequency:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_every_value'] ?? '') . ' ' . htmlspecialchars($form_data['order_every_unit'] ?? ''); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_end_date'])): ?>
                        <div class="field">
                            <span class="field-label">End Date:</span>
                            <span class="field-value"><?php echo htmlspecialchars(oeFormatShortDate($form_data['order_end_date'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_servicing_provider'])): ?>
                        <div class="field">
                            <span class="field-label">Provider:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_servicing_provider']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_npi'])): ?>
                        <div class="field">
                            <span class="field-label">NPI:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_npi']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_note'])): ?>
                        <div class="field">
                            <span class="field-label">Notes:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_note']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Allergies</div>
                        <div class="field">
                            <span class="field-label">Allergies:</span>
                            <span class="field-value"><?php echo buildAllergyHtml($pid); ?></span>
                        </div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Vital Signs</div>
                        <?php if (hasValue($form_data['bp_systolic']) || hasValue($form_data['bp_diastolic'])): ?>
                        <div class="field">
                            <span class="field-label">Blood Pressure:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['bp_systolic'] ?? '') . '/' . htmlspecialchars($form_data['bp_diastolic'] ?? ''); ?> mmHg</span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['pulse'])): ?>
                        <div class="field">
                            <span class="field-label">Pulse:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['pulse']); ?> bpm</span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['temperature_f'])): ?>
                        <div class="field">
                            <span class="field-label">Temperature:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['temperature_f']); ?> °F</span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['oxygen_saturation'])): ?>
                        <div class="field">
                            <span class="field-label">Oxygen Sat:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['oxygen_saturation']); ?>%</span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['respiratory_rate'])): ?>
                        <div class="field">
                            <span class="field-label">Respiratory Rate:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['respiratory_rate']); ?> breaths/min</span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['weight'])): ?>
                        <div class="field">
                            <span class="field-label">Weight:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['weight']); ?> lbs</span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['height'])): ?>
                        <div class="field">
                            <span class="field-label">Height:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['height']); ?> in</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">IV Access</div>
                        <?php if (hasValue($form_data['iv_access_type'])): ?>
                        <div class="field">
                            <span class="field-label">Access Type:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['iv_access_type']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['iv_access_location'])): ?>
                        <div class="field">
                            <span class="field-label">Location:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['iv_access_location']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['iv_access_needle_gauge'])): ?>
                        <div class="field">
                            <span class="field-label">Needle Gauge:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['iv_access_needle_gauge']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['iv_access_blood_return'])): ?>
                        <div class="field">
                            <span class="field-label">Blood Return:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['iv_access_blood_return']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['iv_access_attempts'])): ?>
                        <div class="field">
                            <span class="field-label">Attempts:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['iv_access_attempts']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['iv_access_date'])): ?>
                        <div class="field">
                            <span class="field-label">Date:</span>
                            <span class="field-value"><?php echo htmlspecialchars(oeFormatShortDate($form_data['iv_access_date'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['iv_access_comments'])): ?>
                        <div class="field">
                            <span class="field-label">Comments:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['iv_access_comments']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Administration</div>
                        <?php if (hasValue($form_data['order_medication'])): ?>
                        <div class="field">
                            <span class="field-label">Medication:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_medication']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_dose'])): ?>
                        <div class="field">
                            <span class="field-label">Dose:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_dose']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_strength'])): ?>
                        <div class="field">
                            <span class="field-label">Strength:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_strength']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_route'])): ?>
                        <div class="field">
                            <span class="field-label">Route:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_route']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_lot_number'])): ?>
                        <div class="field">
                            <span class="field-label">Lot Number:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_lot_number']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_expiration_date'])): ?>
                        <div class="field">
                            <span class="field-label">Expiration Date:</span>
                            <span class="field-value"><?php echo htmlspecialchars(oeFormatShortDate($form_data['order_expiration_date'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_ndc'])): ?>
                        <div class="field">
                            <span class="field-label">NDC:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_ndc']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_start'])): ?>
                        <div class="field">
                            <span class="field-label">Start Time:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_start']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_end'])): ?>
                        <div class="field">
                            <span class="field-label">End Time:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_end']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_duration'])): ?>
                        <div class="field">
                            <span class="field-label">Duration:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_duration']); ?> hours</span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['inventory_quantity_used'])): ?>
                        <div class="field">
                            <span class="field-label">Quantity Used:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['inventory_quantity_used']); ?> <?php echo htmlspecialchars(extractUnitFromDose($form_data['order_dose'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['inventory_wastage_quantity'])): ?>
                        <div class="field">
                            <span class="field-label">Quantity Wasted:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['inventory_wastage_quantity']); ?> <?php echo htmlspecialchars(extractUnitFromDose($form_data['order_dose'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['inventory_wastage_reason'])): ?>
                        <div class="field">
                            <span class="field-label">Wastage Reason:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['inventory_wastage_reason']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['inventory_wastage_notes'])): ?>
                        <div class="field">
                            <span class="field-label">Wastage Notes:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['inventory_wastage_notes']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_site'])): ?>
                        <div class="field">
                            <span class="field-label">Site:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_site']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_comments'])): ?>
                        <div class="field">
                            <span class="field-label">Administration Comments:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_comments']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="section">
                        <div class="section-title">No Form Data Found</div>
                        <p class="no-data">No Enhanced Infusion and Injection Form data was found for this encounter.</p>
                    </div>
                <?php endif; ?>
                
            </div>
        </body>
        </html>
        <?php
    }
    
    return $form_data;
} 