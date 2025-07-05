<?php

/**
 * infusion_injection report.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2023 Your Name <your.email@example.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");
require_once($GLOBALS["srcdir"] . "/api.inc.php");

function infusion_injection_report($pid, $encounter, $cols, $id, $print = true)
{
    $count = 0;
    $data = formFetch("form_infusion_injection", $id);
    
    $output = "";
    if ($data) {
        $output .= "<table><tr>";
        
        // Define the fields we want to display and their display names
        $display_fields = array(
            'assessment' => 'Assessment',
            'iv_access_type' => 'IV Access Type',
            'iv_access_location' => 'IV Access Location', 
            'iv_access_blood_return' => 'Blood Return',
            'iv_access_needle_gauge' => 'Needle Gauge',
            'iv_access_attempts' => 'Access Attempts',
            'order_medication' => 'Medication',
            'order_dose' => 'Dose',
            'order_lot_number' => 'Lot Number',
            'order_ndc' => 'NDC',
            'order_expiration_date' => 'Expiration Date',
            'order_servicing_provider' => 'Provider',
            'order_npi' => 'NPI',
            'administration_start' => 'Start Time',
            'administration_end' => 'End Time'
        );

        foreach ($display_fields as $field => $label) {
            $value = $data[$field] ?? '';
            
            // Skip empty values
            if (empty($value) || $value == '0000-00-00' || $value == '0000-00-00 00:00:00') {
                continue;
            }
            
            // Format dates
            if (strpos($field, 'date') !== false || strpos($field, 'start') !== false || strpos($field, 'end') !== false) {
                if ($value != '0000-00-00 00:00:00' && $value != '0000-00-00') {
                    $value = oeFormatSDFT(strtotime($value));
                }
            }
            
            $output .= "<td><div class='font-weight-bold d-inline-block'>" . xlt($label) . ": </div></td>";
            $output .= "<td><div class='text' style='display:inline-block'>" . text($value) . "</div></td>";
            
            $count++;
            if ($count == $cols) {
                $count = 0;
                $output .= "</tr><tr>\n";
            }
        }
        
        $output .= "</tr></table>";
    }

    if ($print) {
        echo $output;
    } else {
        return $output;
    }
}
