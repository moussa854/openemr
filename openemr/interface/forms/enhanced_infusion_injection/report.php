<?php
require_once($GLOBALS["fileroot"] . "/library/formatting.inc.php");
require_once($GLOBALS["fileroot"] . "/src/Services/Utils/DateFormatterUtils.php");
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

// Function to format datetime with AM/PM
function formatDateTimeAmPm($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
        return '';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime; // Return original if can't parse
    }
    
    $formatted_date = oeFormatShortDate(date('Y-m-d', $timestamp));
    $formatted_time = date('g:i A', $timestamp);
    
    return $formatted_date . ' ' . $formatted_time;
}

function extractUnitFromDose($dose) {
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

// Helper function to format duration from start and end times
function formatDuration($start_time, $end_time) {
    if (empty($start_time) || empty($end_time) || 
        $start_time === '0000-00-00 00:00:00' || $end_time === '0000-00-00 00:00:00') {
        return '';
    }
    
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    
    if ($start === false || $end === false || $end <= $start) {
        return '';
    }
    
    $duration_seconds = $end - $start;
    $duration_minutes = round($duration_seconds / 60);
    
    $hours = floor($duration_minutes / 60);
    $minutes = $duration_minutes % 60;
    
    if ($hours == 0) {
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
    } else if ($minutes == 0) {
        return $hours . ' hour' . ($hours != 1 ? 's' : '');
    } else {
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ' . $minutes . ' minute' . ($minutes != 1 ? 's' : '');
    }
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
    // DEBUG: Log the function call
    error_log("=== DEBUG REPORT: Enhanced styled injection report called - PID: $pid, Encounter: $encounter, ID: $id");
    
    // Get patient data
    $patient = getPatientData($pid);
    
    // Get encounter date
    $encounter_date = sqlQuery("SELECT date FROM form_encounter WHERE pid = ? AND encounter = ?", [$pid, $encounter]);
    $dos_date = $encounter_date['date'] ?? date('Y-m-d');
    
    // Map the generic forms table id ($id) to the actual record id in
    // form_enhanced_infusion_injection.  In the `forms` table, the
    // column `form_id` stores the primary-key value of the specific
    // form's data table.  Without this lookup the report tries to load
    // the wrong id and shows no data.

    $mapRow = sqlQuery("SELECT form_id FROM forms WHERE id = ?", [$id]);
    $realFormId = $mapRow['form_id'] ?? $id; // Fallback to original id just in case
    
    error_log("=== DEBUG REPORT: Received forms.id=$id, mapped to form_id=$realFormId");

    // Now fetch the enhanced infusion record using the mapped id
    $sql = "SELECT * FROM form_enhanced_infusion_injection WHERE id = ? AND pid = ?";
    $form_data = sqlQuery($sql, [$realFormId, $pid]);
    
    // DEBUG: Log the data retrieved
    error_log("=== DEBUG REPORT: formFetch result: " . ($form_data ? 'YES' : 'NO'));
    if ($form_data) {
        error_log("=== DEBUG REPORT: Form data - Assessment: " . ($form_data['assessment'] ?? 'NOT_SET'));
        error_log("=== DEBUG REPORT: Form data - Order Medication: " . ($form_data['order_medication'] ?? 'NOT_SET'));
        error_log("=== DEBUG REPORT: Form data - IV Access Type: " . ($form_data['iv_access_type'] ?? 'NOT_SET'));
        error_log("=== DEBUG REPORT: Form data - Route: " . ($form_data['administration_route'] ?? 'NOT_SET'));
    } else {
        error_log("=== DEBUG REPORT: No data returned from query");
    }
    
    if ($print) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Enhanced Infusion & Injection Form</title>
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
                .print-button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; margin-right: 10px; font-size: 14px; font-weight: bold; }
                .no-data { color: #6c757d; font-style: italic; }
                .signatures-container { margin-top: 15px; }
                .signature-entry { border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 15px; padding: 15px; background: #f8f9fa; }
                .signature-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
                .signature-user { font-weight: bold; color: #007bff; font-size: 14px; }
                .signature-type { background: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
                .signature-date { color: #6c757d; font-size: 12px; }
                .signature-text { margin-top: 8px; padding-top: 8px; border-top: 1px solid #dee2e6; }
                .signature-label { font-weight: bold; color: #495057; margin-right: 8px; }
                .signature-value { color: #212529; font-style: italic; }
                @media print {
                    .print-button, button { display: none; }
                    body { background: white; margin: 0; padding: 0; }
                    .container { 
                        box-shadow: none; 
                        border-radius: 0; 
                        margin: 0; 
                        padding: 20px; 
                        max-width: none; 
                        background: white;
                    }
                    .header { 
                        margin-bottom: 20px; 
                        padding-bottom: 15px; 
                        border-bottom: 2px solid #000; 
                    }
                    .section { 
                        margin-bottom: 20px; 
                        page-break-inside: avoid; 
                        border: 1px solid #000; 
                    }
                    .section-title { 
                        background: #000 !important; 
                        color: white !important; 
                        -webkit-print-color-adjust: exact; 
                        print-color-adjust: exact; 
                    }
                    .signature-entry { 
                        page-break-inside: avoid; 
                        border: 1px solid #000; 
                        background: #f8f9fa !important; 
                        -webkit-print-color-adjust: exact; 
                        print-color-adjust: exact; 
                    }
                    .signature-type { 
                        background: #000 !important; 
                        color: white !important; 
                        -webkit-print-color-adjust: exact; 
                        print-color-adjust: exact; 
                    }
                    .patient-info { 
                        background: #f8f9fa !important; 
                        border: 1px solid #000; 
                        -webkit-print-color-adjust: exact; 
                        print-color-adjust: exact; 
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <button class="print-button" onclick="downloadPDF();">📄 Download PDF</button>
                <button class="print-button" onclick="window.print();" style="background: #007bff;">🖨️ Print</button>
                <div class="header">
                    <h1>Enhanced Infusion & Injection Form</h1>
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
                        <?php if (hasValue($form_data['order_strength'])): ?>
                        <div class="field">
                            <span class="field-label">Strength:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_strength']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_every_value']) || hasValue($form_data['order_every_unit'])): ?>
                        <div class="field">
                            <span class="field-label">Frequency:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data["order_every_value"] ?? "") . " " . htmlspecialchars(($form_data["order_every_value"] == 1 ? rtrim($form_data["order_every_unit"] ?? "", "s") : $form_data["order_every_unit"] ?? "")); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_route'])): ?>
                        <div class="field">
                            <span class="field-label">Route:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_route']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_end_date'])): ?>
                        <div class="field">
                            <span class="field-label">End Date:</span>
                            <span class="field-value"><?php echo htmlspecialchars(oeFormatShortDate($form_data['order_end_date'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_expiration_date'])): ?>
                        <div class="field">
                            <span class="field-label">Expiration Date:</span>
                            <span class="field-value"><?php echo htmlspecialchars(oeFormatShortDate($form_data['order_expiration_date'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_lot_number'])): ?>
                        <div class="field">
                            <span class="field-label">Lot Number:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_lot_number']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['order_ndc'])): ?>
                        <div class="field">
                            <span class="field-label">NDC:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['order_ndc']); ?></span>
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
                    
                    <?php if (hasValue($form_data['bp_systolic']) || hasValue($form_data['bp_diastolic']) || hasValue($form_data['pulse']) || hasValue($form_data['temperature_f']) || hasValue($form_data['oxygen_saturation']) || hasValue($form_data['respiratory_rate']) || hasValue($form_data['weight']) || hasValue($form_data['height'])): ?>
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
                    <?php endif; ?>
                    
                    <?php if (hasValue($form_data['iv_access_type']) || hasValue($form_data['iv_access_location']) || hasValue($form_data['iv_access_needle_gauge']) || hasValue($form_data['iv_access_blood_return']) || hasValue($form_data['iv_access_attempts']) || hasValue($form_data['iv_access_date']) || hasValue($form_data['iv_access_comments'])): ?>
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
                            <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($form_data['iv_access_date'] ?? '')); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['iv_access_comments'])): ?>
                        <div class="field">
                            <span class="field-label">Comments:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['iv_access_comments']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasValue($form_data['administration_start']) || hasValue($form_data['administration_end']) || hasValue($form_data['inventory_quantity_used']) || hasValue($form_data['inventory_wastage_quantity']) || hasValue($form_data['administration_site']) || hasValue($form_data['administration_comments']) || hasValue($form_data['administration_note'])): ?>
                    <div class="section">
                        <div class="section-title">Administration</div>
                        <?php if (hasValue($form_data['administration_start'])): ?>
                        <div class="field">
                            <span class="field-label">Start Time:</span>
                            <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($form_data['administration_start'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_end'])): ?>
                        <div class="field">
                            <span class="field-label">End Time:</span>
                            <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($form_data['administration_end'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['administration_start']) && hasValue($form_data['administration_end'])): ?>
                        <div class="field">
                            <span class="field-label">Duration:</span>
                            <span class="field-value"><?php 
                                $calculated_duration = formatDuration($form_data['administration_start'] ?? '', $form_data['administration_end'] ?? '');
                                echo htmlspecialchars($calculated_duration ?: 'Not calculated'); 
                            ?></span>
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
                        <?php if (hasValue($form_data['inventory_wastage_reason']) && (floatval($form_data['inventory_wastage_quantity'] ?? 0) > 0)): ?>
                        <div class="field">
                            <span class="field-label">Wastage Reason:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['inventory_wastage_reason']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (hasValue($form_data['inventory_wastage_notes']) && (floatval($form_data['inventory_wastage_quantity'] ?? 0) > 0)): ?>
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
                        <?php if (hasValue($form_data['administration_note'])): ?>
                        <div class="field">
                            <span class="field-label">Administration Notes:</span>
                            <span class="field-value"><?php echo htmlspecialchars($form_data['administration_note']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Get secondary medications
                    $secondary_medications = [];
                    $sql = "SELECT * FROM form_enhanced_infusion_medications WHERE form_id = ? ORDER BY medication_order";
                    $result = sqlStatement($sql, [$realFormId]);
                    while ($row = sqlFetchArray($result)) {
                        $secondary_medications[] = $row;
                    }
                    
                    if (!empty($secondary_medications)): ?>
                    <div class="section">
                        <div class="section-title">Secondary/PRN Medications</div>
                        <?php foreach ($secondary_medications as $med): ?>
                        <div class="field">
                            <span class="field-label">Medication:</span>
                            <span class="field-value"><?php echo htmlspecialchars($med['order_medication']); ?></span>
                        </div>
                        <div class="field">
                            <span class="field-label">Dose:</span>
                            <span class="field-value"><?php echo htmlspecialchars($med['order_dose']); ?></span>
                        </div>
                        <div class="field">
                            <span class="field-label">Start:</span>
                            <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($med['administration_start'])); ?></span>
                        </div>
                        <div class="field">
                            <span class="field-label">End:</span>
                            <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($med['administration_end'])); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Electronic Signatures Section -->
                    <?php
                    // Get signatures for this form
                    $signatures_sql = "SELECT 
                                        s.signature_text,
                                        s.signature_date,
                                        s.signature_type,
                                        s.signature_order,
                                        u.fname,
                                        u.lname,
                                        st.display_name as type_display_name
                                       FROM form_enhanced_infusion_signatures s
                                       LEFT JOIN users u ON s.user_id = u.id
                                       LEFT JOIN signature_types st ON s.signature_type = st.type_name
                                       WHERE s.form_id = ? AND s.is_active = 1
                                       ORDER BY s.signature_order ASC, s.created_at ASC";
                    
                    $signatures_result = sqlStatement($signatures_sql, [$realFormId]);
                    $signatures = [];
                    while ($row = sqlFetchArray($signatures_result)) {
                        $signatures[] = $row;
                    }
                    
                    if (!empty($signatures)): ?>
                    <div class="section">
                        <div class="section-title">Electronic Signatures</div>
                        <div class="signatures-container">
                            <?php foreach ($signatures as $signature): ?>
                            <div class="signature-entry">
                                <div class="signature-header">
                                    <span class="signature-user"><?php echo htmlspecialchars(trim($signature['fname'] . ' ' . $signature['lname'])); ?></span>
                                    <span class="signature-type"><?php echo htmlspecialchars($signature['type_display_name'] ?? ucfirst($signature['signature_type'])); ?></span>
                                    <span class="signature-date"><?php 
                                        $signature_date = $signature['signature_date'] ?? '';
                                        $formatted_date = oeFormatShortDate($signature_date);
                                        $formatted_time = date('g:i A', strtotime($signature_date));
                                        echo htmlspecialchars($formatted_date . ' ' . $formatted_time);
                                    ?></span>
                                </div>
                                <?php if (!empty(trim($signature['signature_text']))): ?>
                                <div class="signature-text">
                                    <span class="signature-label">Signature Text:</span>
                                    <span class="signature-value"><?php echo htmlspecialchars($signature['signature_text']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="section">
                        <div class="section-title">No Form Data Found</div>
                        <p class="no-data">No Enhanced Infusion and Injection Form data was found for this encounter.</p>
                    </div>
                <?php endif; ?>
                
            </div>
            
            <script>
            function downloadPDF() {
                // Get current URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const pid = urlParams.get('pid') || <?php echo json_encode($pid); ?>;
                const encounter = urlParams.get('encounter') || <?php echo json_encode($encounter); ?>;
                const id = urlParams.get('id') || <?php echo json_encode($id); ?>;
                
                // Build PDF download URL - path relative to where report is called from
                const pdfUrl = '../../forms/enhanced_infusion_injection/pdf_report.php?' + 
                    'pid=' + encodeURIComponent(pid) + 
                    '&encounter=' + encodeURIComponent(encounter) + 
                    '&id=' + encodeURIComponent(id);
                
                // Create hidden link to trigger download
                const link = document.createElement('a');
                link.href = pdfUrl;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            </script>
        </body>
        </html>
        <?php
    }
    
    return $form_data;
} 