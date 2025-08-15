<?php
require_once("../../globals.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/forms.inc.php");
require_once($GLOBALS["fileroot"] . "/library/formatting.inc.php");

use OpenEMR\Common\Forms\FormLocator;

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

function enhanced_infusion_report($pid, $encounter, $cols, $id) {
    // DEBUG: Log the function call
    error_log("=== DEBUG REPORT: Enhanced styled report called - PID: $pid, Encounter: $encounter, ID: $id");
    
    // Get patient data
    $patient = getPatientData($pid);
    
    // Get encounter date
    $encounter_date = sqlQuery("SELECT date FROM form_encounter WHERE pid = ? AND encounter = ?", [$pid, $encounter]);
    $dos_date = $encounter_date['date'] ?? date('Y-m-d');
    
    // Handle ID mapping - OpenEMR might pass either forms.id or form_id
    // First try as forms.id
    $mapRow = sqlQuery("SELECT form_id FROM forms WHERE id = ? AND formdir = 'enhanced_infusion'", [$id]);
    if ($mapRow) {
        $realId = $mapRow['form_id'];
        error_log("=== DEBUG REPORT: Received forms.id=$id, mapped to form_id=$realId");
    } else {
        // If not found, try as form_id directly
        $formExists = sqlQuery("SELECT id FROM form_enhanced_infusion_injection WHERE id = ?", [$id]);
        if ($formExists) {
            $realId = $id;
            error_log("=== DEBUG REPORT: Received form_id=$id directly");
        } else {
            // Last resort: find the form by pid/encounter
            $lastForm = sqlQuery("SELECT fe.id FROM form_enhanced_infusion_injection fe JOIN forms f ON f.form_id = fe.id WHERE f.pid = ? AND f.encounter = ? AND f.formdir = 'enhanced_infusion' ORDER BY fe.id DESC LIMIT 1", [$pid, $encounter]);
            $realId = $lastForm ? $lastForm['id'] : $id;
            error_log("=== DEBUG REPORT: Fallback lookup for pid=$pid, encounter=$encounter, found form_id=$realId");
        }
    }
    
    $data = formFetch("form_enhanced_infusion_injection", $realId);
    
    // DEBUG: Log the data retrieved
    error_log("=== DEBUG REPORT: formFetch result: " . ($data ? 'YES' : 'NO'));
    if ($data) {
        error_log("=== DEBUG REPORT: Form data - Assessment: " . ($data['assessment'] ?? 'NOT_SET'));
        error_log("=== DEBUG REPORT: Form data - Order Medication: " . ($data['order_medication'] ?? 'NOT_SET'));
        error_log("=== DEBUG REPORT: Form data - IV Access Type: " . ($data['iv_access_type'] ?? 'NOT_SET'));
        error_log("=== DEBUG REPORT: Form data - Route: " . ($data['administration_route'] ?? 'NOT_SET'));
    } else {
        error_log("=== DEBUG REPORT: No data returned from formFetch");
    }
    
    if ($data) {
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
                                <span class="info-value"><?php echo htmlspecialchars($data['order_servicing_provider'] ?? 'Moussa El-hallak, M.D.'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">NPI:</span>
                                <span class="info-value"><?php echo htmlspecialchars($data['order_npi'] ?? '1831381524'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (hasValue($data['assessment'])): ?>
                <div class="section">
                    <div class="section-title">Assessment</div>
                    <div class="field">
                        <span class="field-label">Assessment:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['assessment']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (hasValue($data['diagnoses'])): ?>
                <div class="section">
                    <div class="section-title">Diagnoses</div>
                    <?php 
                    $diagnoses_array = explode('|', $data['diagnoses']);
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
                    <?php if (hasValue($data['order_medication'])): ?>
                    <div class="field">
                        <span class="field-label">Medication:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_medication']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_dose'])): ?>
                    <div class="field">
                        <span class="field-label">Dose:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_dose']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_strength'])): ?>
                    <div class="field">
                        <span class="field-label">Strength:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_strength']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_every_value']) || hasValue($data['order_every_unit'])): ?>
                    <div class="field">
                        <span class="field-label">Frequency:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data["order_every_value"] ?? "") . " " . htmlspecialchars(($data["order_every_value"] == 1 ? rtrim($data["order_every_unit"] ?? "", "s") : $data["order_every_unit"] ?? "")); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['administration_route'])): ?>
                    <div class="field">
                        <span class="field-label">Route:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['administration_route']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_end_date'])): ?>
                    <div class="field">
                        <span class="field-label">End Date:</span>
                        <span class="field-value"><?php echo htmlspecialchars(oeFormatShortDate($data['order_end_date'] ?? '')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_expiration_date'])): ?>
                    <div class="field">
                        <span class="field-label">Expiration Date:</span>
                        <span class="field-value"><?php echo htmlspecialchars(oeFormatShortDate($data['order_expiration_date'] ?? '')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_lot_number'])): ?>
                    <div class="field">
                        <span class="field-label">Lot Number:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_lot_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_ndc'])): ?>
                    <div class="field">
                        <span class="field-label">NDC:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_ndc']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_servicing_provider'])): ?>
                    <div class="field">
                        <span class="field-label">Provider:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_servicing_provider']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_npi'])): ?>
                    <div class="field">
                        <span class="field-label">NPI:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_npi']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['order_note'])): ?>
                    <div class="field">
                        <span class="field-label">Notes:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['order_note']); ?></span>
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
                
                <?php if (hasValue($data['bp_systolic']) || hasValue($data['bp_diastolic']) || hasValue($data['pulse']) || hasValue($data['temperature_f']) || hasValue($data['oxygen_saturation']) || hasValue($data['respiratory_rate']) || hasValue($data['weight']) || hasValue($data['height'])): ?>
                <div class="section">
                    <div class="section-title">Vital Signs</div>
                    <?php if (hasValue($data['bp_systolic']) || hasValue($data['bp_diastolic'])): ?>
                    <div class="field">
                        <span class="field-label">Blood Pressure:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['bp_systolic'] ?? '') . '/' . htmlspecialchars($data['bp_diastolic'] ?? ''); ?> mmHg</span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['pulse'])): ?>
                    <div class="field">
                        <span class="field-label">Pulse:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['pulse']); ?> bpm</span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['temperature_f'])): ?>
                    <div class="field">
                        <span class="field-label">Temperature:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['temperature_f']); ?> °F</span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['oxygen_saturation'])): ?>
                    <div class="field">
                        <span class="field-label">Oxygen Sat:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['oxygen_saturation']); ?>%</span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['respiratory_rate'])): ?>
                    <div class="field">
                        <span class="field-label">Respiratory Rate:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['respiratory_rate']); ?> breaths/min</span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['weight'])): ?>
                    <div class="field">
                        <span class="field-label">Weight:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['weight']); ?> lbs</span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['height'])): ?>
                    <div class="field">
                        <span class="field-label">Height:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['height']); ?> in</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (hasValue($data['iv_access_type']) || hasValue($data['iv_access_location']) || hasValue($data['iv_access_needle_gauge']) || hasValue($data['iv_access_blood_return']) || hasValue($data['iv_access_attempts']) || hasValue($data['iv_access_date']) || hasValue($data['iv_access_comments'])): ?>
                <div class="section">
                    <div class="section-title">IV Access</div>
                    <?php if (hasValue($data['iv_access_type'])): ?>
                    <div class="field">
                        <span class="field-label">Access Type:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['iv_access_type']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['iv_access_location'])): ?>
                    <div class="field">
                        <span class="field-label">Location:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['iv_access_location']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['iv_access_needle_gauge'])): ?>
                    <div class="field">
                        <span class="field-label">Needle Gauge:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['iv_access_needle_gauge']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['iv_access_blood_return'])): ?>
                    <div class="field">
                        <span class="field-label">Blood Return:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['iv_access_blood_return']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['iv_access_attempts'])): ?>
                    <div class="field">
                        <span class="field-label">Attempts:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['iv_access_attempts']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['iv_access_date'])): ?>
                    <div class="field">
                        <span class="field-label">Date:</span>
                        <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($data['iv_access_date'] ?? '')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['iv_access_comments'])): ?>
                    <div class="field">
                        <span class="field-label">Comments:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['iv_access_comments']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (hasValue($data['administration_start']) || hasValue($data['administration_end']) || hasValue($data['inventory_quantity_used']) || hasValue($data['inventory_wastage_quantity']) || hasValue($data['administration_site']) || hasValue($data['administration_comments']) || hasValue($data['administration_note'])): ?>
                <div class="section">
                    <div class="section-title">Administration</div>
                    <?php if (hasValue($data['administration_start'])): ?>
                    <div class="field">
                        <span class="field-label">Start Time:</span>
                        <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($data['administration_start'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['administration_end'])): ?>
                    <div class="field">
                        <span class="field-label">End Time:</span>
                        <span class="field-value"><?php echo htmlspecialchars(formatDateTimeAmPm($data['administration_end'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['administration_start']) && hasValue($data['administration_end'])): ?>
                    <div class="field">
                        <span class="field-label">Duration:</span>
                        <span class="field-value"><?php 
                            $calculated_duration = formatDuration($data['administration_start'] ?? '', $data['administration_end'] ?? '');
                            echo htmlspecialchars($calculated_duration ?: 'Not calculated'); 
                        ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['inventory_quantity_used'])): ?>
                    <div class="field">
                        <span class="field-label">Quantity Used:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['inventory_quantity_used']); ?> <?php echo htmlspecialchars(extractUnitFromDose($data['order_dose'] ?? '')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['inventory_wastage_quantity'])): ?>
                    <div class="field">
                        <span class="field-label">Quantity Wasted:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['inventory_wastage_quantity']); ?> <?php echo htmlspecialchars(extractUnitFromDose($data['order_dose'] ?? '')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['inventory_wastage_reason']) && (floatval($data['inventory_wastage_quantity'] ?? 0) > 0)): ?>
                    <div class="field">
                        <span class="field-label">Wastage Reason:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['inventory_wastage_reason']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['inventory_wastage_notes']) && (floatval($data['inventory_wastage_quantity'] ?? 0) > 0)): ?>
                    <div class="field">
                        <span class="field-label">Wastage Notes:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['inventory_wastage_notes']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['administration_site'])): ?>
                    <div class="field">
                        <span class="field-label">Site:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['administration_site']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['administration_comments'])): ?>
                    <div class="field">
                        <span class="field-label">Administration Comments:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['administration_comments']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (hasValue($data['administration_note'])): ?>
                    <div class="field">
                        <span class="field-label">Administration Notes:</span>
                        <span class="field-value"><?php echo htmlspecialchars($data['administration_note']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php
                // Get secondary medications
                $secondary_medications = [];
                $sql = "SELECT * FROM form_enhanced_infusion_medications WHERE form_id = ? ORDER BY medication_order";
                $result = sqlStatement($sql, [$realId]);
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
                
            </div>
            
            <script>
            function downloadPDF() {
                // Get current URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const pid = urlParams.get('pid') || <?php echo json_encode($pid); ?>;
                const encounter = urlParams.get('encounter') || <?php echo json_encode($encounter); ?>;
                const id = urlParams.get('id') || <?php echo json_encode($id); ?>;
                
                // Build PDF download URL - create a simple PDF version
                window.print();
            }
            </script>
        </body>
        </html>
        <?php
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Enhanced Infusion & Injection Form</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .no-data { color: #6c757d; font-style: italic; text-align: center; padding: 50px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="no-data">
                    <h2>No Form Data Found</h2>
                    <p>No Enhanced Infusion and Injection Form data was found for this encounter.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
