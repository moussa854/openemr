<?php
require_once($GLOBALS["fileroot"] . "/library/formatting.inc.php");
require_once($GLOBALS["fileroot"] . "/src/Services/Utils/DateFormatterUtils.php");
/**
 * Enhanced Infusion Form Report (encounter summary)
 * Mirrors logic of enhanced_infusion_injection_report but maps
 * forms.id ⇒ forms.form_id so the correct data row is fetched.
 */

// Re-use helpers from injection report if already declared
if (!function_exists('hasValue')) {
    function hasValue($value) { return !empty($value) && $value !== null && $value !== ''; }
}
if (!function_exists('formatDateTimeAmPm')) {
    function formatDateTimeAmPm($datetime) {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
            return '';
        }
        $ts = strtotime($datetime); if ($ts === false) { return $datetime; }
        return oeFormatShortDate(date('Y-m-d',$ts)) . ' ' . date('g:i A',$ts);
    }
}

function enhanced_infusion_report($pid, $encounter, $cols, $id)
{
    // Output lightweight CSS once for encounter summary styling
    if (!defined('ENH_INFUSION_CSS_ADDED')) {
        echo "<style>
            .inf-section-title{background:#007bff;color:#fff;font-weight:bold;padding:4px 8px;margin-top:12px;border-radius:4px;}
            .inf-field-label{font-weight:bold;min-width:120px;display:inline-block;}
            .inf-section-box{border:1px solid #dee2e6;background:#f8f9fa;padding:8px 12px;border-radius:4px;margin-bottom:12px;}
            .inf-secondary-table th{font-weight:bold;text-align:left;padding-right:12px;}
            .inf-secondary-table td{padding-right:12px;}
        </style>";
        define('ENH_INFUSION_CSS_ADDED', true);
    }

    // Map forms.id→form_id
    $mapRow   = sqlQuery("SELECT form_id FROM forms WHERE id = ?", [$id]);
    $realId   = $mapRow['form_id'] ?? $id;
    
    error_log("=== DEBUG REPORT: forms.id=$id, mapped to form_id=$realId, pid=$pid, encounter=$encounter");

    $data = sqlQuery("SELECT * FROM form_enhanced_infusion_injection WHERE id = ? AND pid = ?", [$realId, $pid]);
    if(!$data){
        error_log("=== DEBUG REPORT: No row with id $realId and pid $pid, trying without pid");
        $data = sqlQuery("SELECT * FROM form_enhanced_infusion_injection WHERE id = ?", [$realId]);
    }
    if (!$data) { 
        error_log("=== DEBUG REPORT: No primary data found for form_id $realId");
        echo xlt('No data found'); 
        return; 
    }
    
    error_log("=== DEBUG REPORT: Primary data found: " . json_encode($data));

    // Secondary / PRN meds
    $secondary = sqlStatement("SELECT * FROM form_enhanced_infusion_medications WHERE form_id = ? ORDER BY medication_order", [$realId]);

    // Primary Medication section
    echo "<div class='inf-section-title'>" . xlt('Primary Medication') . "</div>";
    echo "<div class='inf-section-box'>";
    echo "<span class='inf-field-label'>" . xlt('Medication') . ":</span> " . text($data['order_medication']) . "<br>";
    echo "<span class='inf-field-label'>" . xlt('Dose') . ":</span> " . text($data['order_dose']) . "<br>";
    echo "<span class='inf-field-label'>" . xlt('Strength') . ":</span> " . text($data['order_strength']) . "<br>";
    echo "<span class='inf-field-label'>" . xlt('Frequency') . ":</span> " . text($data['order_every_value']) . ' ' . text($data['order_every_unit']) . "<br>";
    echo "<span class='inf-field-label'>" . xlt('Start Time') . ":</span> " . text($data['administration_start']) . "<br>";
    echo "<span class='inf-field-label'>" . xlt('End Time') . ":</span> " . text($data['administration_end']) . "<br>";
    echo "<span class='inf-field-label'>" . xlt('Duration') . ":</span> " . text($data['administration_duration']) . "<br>";
    echo "</div>";

    // Secondary meds listing
    $secondaryRows = sqlStatement("SELECT * FROM form_enhanced_infusion_medications WHERE form_id = ? ORDER BY medication_order", [$realId]);
    $secondaryCount = sqlNumRows($secondaryRows);
    error_log("=== DEBUG REPORT: Secondary medications query for form_id $realId returned $secondaryCount rows");
    
    if ($secondaryRows && $secondaryCount > 0) {
        echo "<div class='inf-section-title'>" . xlt('Secondary / PRN Medications') . "</div>";
        echo "<div class='inf-section-box'>";
        echo "<table class='inf-secondary-table'>";
        echo "<tr><th>" . xlt('Medication') . "</th><th>" . xlt('Dose') . "</th><th>" . xlt('Strength') . "</th><th>" . xlt('Route') . "</th></tr>";
        
        $rowIndex = 0;
        while ($m = sqlFetchArray($secondaryRows)) {
            error_log("=== DEBUG REPORT: Secondary med row $rowIndex: " . json_encode($m));
            echo "<tr><td>" . text($m['order_medication']) . "</td><td>" . text($m['order_dose']) . "</td><td>" . text($m['order_strength']) . "</td><td>" . text($m['administration_route']) . "</td></tr>";
            $rowIndex++;
        }
        echo "</table></div>";
    } else {
        error_log("=== DEBUG REPORT: No secondary medications found for form_id $realId");
    }

    // Diagnosis Codes
    if (hasValue($data['diagnoses'])) {
        echo "<div class='inf-section-title'>" . xlt('Diagnosis Codes') . "</div>";
        echo "<div class='inf-section-box'>";
        echo nl2br(text($data['diagnoses']));
        echo "</div>";
    }

    // Assessment and Notes
    if (hasValue($data['assessment'])) {
        echo "<div class='inf-section-title'>" . xlt('Assessment') . "</div>";
        echo "<div class='inf-section-box'>";
        echo nl2br(text($data['assessment']));
        echo "</div>";
    }
    
    if (hasValue($data['administration_note']) || hasValue($data['order_note'])) {
        echo "<div class='inf-section-title'>" . xlt('Notes') . "</div>";
        echo "<div class='inf-section-box'>";
        if (hasValue($data['administration_note'])) {
            echo "<span class='inf-field-label'>" . xlt('Administration Notes') . ":</span> " . text($data['administration_note']) . "<br>";
        }
        if (hasValue($data['order_note'])) {
            echo "<span class='inf-field-label'>" . xlt('Order Notes') . ":</span> " . text($data['order_note']) . "<br>";
        }
        echo "</div>";
    }
}
?>
