<?php
/**
 * Enhanced Infusion Form Report
 * 
 * This file handles the display of Enhanced Infusion Form data in encounter reports.
 */

require_once("../../globals.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/forms.inc.php");

use OpenEMR\Common\Forms\FormLocator;

function enhanced_infusion_report($pid, $encounter, $cols, $id) {
    // DEBUG: Log the function call
    error_log("=== DEBUG REPORT: Function called - PID: $pid, Encounter: $encounter, ID: $id");
    
    $count = 0;
    
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
        error_log("=== DEBUG REPORT: Form data - PID: " . ($data['pid'] ?? 'NOT_SET'));
        error_log("=== DEBUG REPORT: Form data - Encounter: " . ($data['encounter'] ?? 'NOT_SET'));
    } else {
        error_log("=== DEBUG REPORT: No data returned from formFetch");
    }
    
    if ($data) {
        error_log("=== DEBUG REPORT: Displaying form data");
        
        // Get secondary medications
        $secondary_medications = [];
        $sql = "SELECT * FROM form_enhanced_infusion_medications WHERE form_id = ? ORDER BY medication_order";
        $result = sqlStatement($sql, [$realId]);
        while ($row = sqlFetchArray($result)) {
            $secondary_medications[] = $row;
        }
        error_log("=== DEBUG REPORT: Found " . count($secondary_medications) . " secondary medications");
        
        print "<table><tr>";
        
        // Primary medication information
        print "<td><span class=bold>" . xlt("Primary Medication") . ":</span></td>";
        print "<td>" . text($data['order_medication']) . "</td>";
        print "<td><span class=bold>" . xlt("Dose") . ":</span></td>";
        print "<td>" . text($data['order_dose']) . "</td>";
        print "</tr><tr>";
        
        print "<td><span class=bold>" . xlt("Strength") . ":</span></td>";
        print "<td>" . text($data['order_strength']) . "</td>";
        print "<td><span class=bold>" . xlt("Frequency") . ":</span></td>";
        print "<td>" . text($data['order_every_value']) . " " . text($data['order_every_unit']) . "</td>";
        print "</tr><tr>";
        
        print "<td><span class=bold>" . xlt("Start Time") . ":</span></td>";
        print "<td>" . text($data['administration_start']) . "</td>";
        print "<td><span class=bold>" . xlt("End Time") . ":</span></td>";
        print "<td>" . text($data['administration_end']) . "</td>";
        print "</tr><tr>";
        
        print "<td><span class=bold>" . xlt("Duration") . ":</span></td>";
        print "<td>" . text($data['administration_duration']) . "</td>";
        print "<td><span class=bold>" . xlt("Quantity Used") . ":</span></td>";
        print "<td>" . text($data['inventory_quantity_used']) . "</td>";
        print "</tr>";
        
        // IV Access Information
        if (!empty($data['iv_access_type']) || !empty($data['iv_access_location']) || !empty($data['iv_access_comments'])) {
            print "<tr><td colspan='4'><span class=bold>" . xlt("IV Access Information") . ":</span></td></tr>";
            if (!empty($data['iv_access_type'])) {
                print "<tr><td><span class=bold>" . xlt("Access Type") . ":</span></td>";
                print "<td>" . text($data['iv_access_type']) . "</td>";
                if (!empty($data['iv_access_location'])) {
                    print "<td><span class=bold>" . xlt("Location") . ":</span></td>";
                    print "<td>" . text($data['iv_access_location']) . "</td>";
                } else {
                    print "<td></td><td></td>";
                }
                print "</tr>";
            }
            if (!empty($data['iv_access_comments'])) {
                print "<tr><td><span class=bold>" . xlt("IV Comments") . ":</span></td>";
                print "<td colspan='3'>" . text($data['iv_access_comments']) . "</td></tr>";
            }
        }
        
        // Diagnosis Information
        if (!empty($data['diagnoses'])) {
            print "<tr><td><span class=bold>" . xlt("Diagnoses") . ":</span></td>";
            print "<td colspan='3'>" . text($data['diagnoses']) . "</td></tr>";
        }
        
        // Secondary medications
        if (!empty($secondary_medications)) {
            print "<tr><td colspan='4'><span class=bold>" . xlt("Secondary/PRN Medications") . ":</span></td></tr>";
            foreach ($secondary_medications as $med) {
                print "<tr>";
                print "<td>&nbsp;&nbsp;&nbsp;&nbsp;" . xlt("Medication") . ":</td>";
                print "<td>" . text($med['order_medication']) . "</td>";
                print "<td>" . xlt("Dose") . ":</td>";
                print "<td>" . text($med['order_dose']) . "</td>";
                print "</tr><tr>";
                print "<td>&nbsp;&nbsp;&nbsp;&nbsp;" . xlt("Start") . ":</td>";
                print "<td>" . text($med['administration_start']) . "</td>";
                print "<td>" . xlt("End") . ":</td>";
                print "<td>" . text($med['administration_end']) . "</td>";
                print "</tr>";
            }
        }
        
        // Notes
        if (!empty($data['administration_note'])) {
            print "<tr><td><span class=bold>" . xlt("Administration Notes") . ":</span></td>";
            print "<td colspan='3'>" . text($data['administration_note']) . "</td></tr>";
        }
        
        if (!empty($data['order_note'])) {
            print "<tr><td><span class=bold>" . xlt("Order Notes") . ":</span></td>";
            print "<td colspan='3'>" . text($data['order_note']) . "</td></tr>";
        }
        
        print "</table>";
    }
}
?>
