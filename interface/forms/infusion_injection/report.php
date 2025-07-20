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
    // Validate form ID
    if (!$id || !is_numeric($id)) {
        $output = "<div>" . xlt('Error: Form ID not provided or invalid.') . "</div>";
        if ($print) {
            echo $output;
        } else {
            return $output;
        }
        return;
    }
    
    $count = 0;
    $data = formFetch("form_infusion_injection", $id);
    
    // Pull patient active allergies
    if ($pid) {
        $allergyRows = sqlStatement("SELECT title, reaction FROM lists WHERE pid = ? AND type='allergy' AND (enddate IS NULL OR enddate = '' OR enddate = '0000-00-00') AND activity = 1", [$pid]);
        $allergies = [];
        while ($row = sqlFetchArray($allergyRows)) {
            $label = $row['title'];
            $reaction = trim($row['reaction']);
            if ($reaction !== '') {
                $label .= " (" . $reaction . ")";
            }
            $allergies[] = $label;
        }
        $data['allergies'] = implode(', ', $allergies);
    }
    $output = "";
    if ($data) {
        $output .= "<table><tr>";
        
        // Define the fields we want to display and their display names
        $display_fields = array(
            'assessment' => 'Assessment',
            // Vital Signs
            'bp' => 'Blood Pressure', // custom key for composite
            'pulse' => 'Pulse',
            'temperature_f' => 'Temperature',
            'oxygen_saturation' => 'Oxygen Saturation %',
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
            'administration_end' => 'End Time',
            'allergies' => 'Allergies',
        );

        foreach ($display_fields as $field => $label) {
            // Handle composite Blood Pressure field
            if ($field === 'bp') {
                $systolic = $data['bp_systolic'] ?? '';
                $diastolic = $data['bp_diastolic'] ?? '';
                $value = '';
                if ($systolic !== '' || $diastolic !== '') {
                    $value = trim($systolic) . '/' . trim($diastolic);
                }
            } else {
                $value = $data[$field] ?? '';
            }
            
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
            
            // Add units for certain vitals
            $displayValue = $value;
            if ($field === 'pulse') {
                $displayValue .= ' ' . xlt('per min');
            } elseif ($field === 'temperature_f') {
                $displayValue .= ' F';
            } elseif ($field === 'oxygen_saturation') {
                $displayValue .= ' %';
            } elseif ($field === 'allergies') {
                // already built as string
                $displayValue = $value;
                if ($displayValue === '') {
                    continue; // skip if no allergies
                }
            }

            $output .= "<td><div class='font-weight-bold d-inline-block'>" . xlt($label) . ": </div></td>";
            $output .= "<td><div class='text' style='display:inline-block'>" . text($displayValue) . "</div></td>";
            
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
