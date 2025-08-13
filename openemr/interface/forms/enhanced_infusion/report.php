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
    $count = 0;
    $data = formFetch("form_enhanced_infusion_injection", $id);
    
    if ($data) {
        // Get secondary medications
        $secondary_medications = [];
        $sql = "SELECT * FROM form_enhanced_infusion_medications WHERE form_id = ? ORDER BY medication_order";
        $result = sqlStatement($sql, [$id]);
        while ($row = sqlFetchArray($result)) {
            $secondary_medications[] = $row;
        }
        
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
