<?php

/**
 * Enhanced Infusion and Injection Form PDF Report Generator
 * 
 * This file generates a PDF download of the Enhanced Infusion and Injection Form.
 * It uses mPDF library for PDF generation.
 */

require_once("../../globals.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/forms.inc.php");

use Mpdf\Mpdf;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Pdf\Config_Mpdf;

// Check authorization
if (!AclMain::aclCheckCore('encounters', 'auth_a')) {
    echo "Access denied.";
    exit;
}

// Get parameters
$pid = $_GET['pid'] ?? 0;
$encounter = $_GET['encounter'] ?? 0;
$id = $_GET['id'] ?? 0;

if (!$pid || !$encounter || !$id) {
    echo "Missing required parameters.";
    exit;
}

// Include the report functions
require_once("report.php");

// Function to build HTML content with exact styling matching the web interface
function buildPDFHTML($patient, $form_data, $dos_date, $encounter, $pid, $id) {
    // Note: hasValue() function is already declared in report.php, so we use it directly
    
    // Helper function to format datetime with AM/PM (local to this function)
    $formatDateTimeAmPm = function($datetime) {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
            return '';
        }
        
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }
        
        $formatted_date = oeFormatShortDate(date('Y-m-d', $timestamp));
        $formatted_time = date('g:i A', $timestamp);
        
        return $formatted_date . ' ' . $formatted_time;
    };

    // Helper function to extract unit from dose (local to this function)
    $extractUnitFromDose = function($dose) {
        $units = ['mg', 'mL', 'gram', 'grams', 'g', 'mcg', 'units', 'IU', 'mEq', 'mmol'];
        
        foreach ($units as $unit) {
            if (stripos($dose, $unit) !== false) {
                return $unit;
            }
        }
        
        $parts = explode(' ', trim($dose));
        if (count($parts) > 1) {
            return end($parts);
        }
        
        return '';
    };

    // Build allergies HTML (local to this function)
    $buildAllergyHtml = function($pid) {
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
    };

    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Infusion & Injection Form</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11pt; margin: 20px; }
            .section { margin-bottom: 15px; border: 1px solid #ccc; }
            .section-title { background: #007bff; color: white; padding: 8px 12px; font-weight: bold; font-size: 12pt; margin: 0; }
            .field { padding: 8px 12px; border-bottom: 1px solid #eee; }
            .field-label { font-weight: bold; color: #333; display: inline-block; width: 150px; }
            .field-value { color: #000; }
            .patient-info { background: #f9f9f9; padding: 12px; border: 1px solid #ddd; margin-bottom: 15px; }
            .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 15px; }
            .header h1 { color: #007bff; margin: 0 0 8px 0; font-size: 16pt; font-weight: bold; }

        </style>
    </head>
    <body>
        <div class="header">
            <h1>Infusion & Injection Form</h1>
            <div class="patient-info">
                <strong>Patient:</strong> ' . htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) . '<br>
                <strong>DOB:</strong> ' . htmlspecialchars(oeFormatShortDate($patient['DOB'] ?? '')) . '<br>
                <strong>DOS:</strong> ' . oeFormatShortDate($dos_date) . '<br>
                <strong>Encounter:</strong> ' . htmlspecialchars($encounter) . '<br>
                <strong>Provider:</strong> ' . htmlspecialchars($form_data['order_servicing_provider'] ?? 'Moussa El-hallak, M.D.') . '<br>
                <strong>NPI:</strong> ' . htmlspecialchars($form_data['order_npi'] ?? '1831381524') . '
            </div>
        </div>';

    if ($form_data) {
        // Assessment section
        if (hasValue($form_data['assessment'])) {
            $html .= '<div class="section">
                <div class="section-title">Assessment</div>
                <div class="field">
                    <span class="field-label">Assessment:</span>
                    <span class="field-value">' . htmlspecialchars($form_data['assessment']) . '</span>
                </div>
            </div>';
        }

        // Diagnoses section
        if (hasValue($form_data['diagnoses'])) {
            $html .= '<div class="section">
                <div class="section-title">Diagnoses</div>';
            
            $diagnoses_array = explode('|', $form_data['diagnoses']);
            $clean_diagnoses = [];
            foreach ($diagnoses_array as $diagnosis) {
                $diagnosis = trim($diagnosis);
                if (!empty($diagnosis)) {
                    $clean_diagnoses[] = $diagnosis;
                }
            }
            
            if (!empty($clean_diagnoses)) {
                $html .= '<div class="field">
                    <span class="field-label">Diagnosis:</span>
                    <span class="field-value">' . htmlspecialchars($clean_diagnoses[0]) . '</span>
                </div>';
                
                for ($i = 1; $i < count($clean_diagnoses); $i++) {
                    $html .= '<div class="field">
                        <span class="field-label"></span>
                        <span class="field-value">' . htmlspecialchars($clean_diagnoses[$i]) . '</span>
                    </div>';
                }
            }
            $html .= '</div>';
        }

        // Order Details section
        $html .= '<div class="section">
            <div class="section-title">Order Details</div>';
        
        if (hasValue($form_data['order_medication'])) {
            $html .= '<div class="field">
                <span class="field-label">Medication:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_medication']) . '</span>
            </div>';
        }
        
        if (hasValue($form_data['order_dose'])) {
            $html .= '<div class="field">
                <span class="field-label">Dose:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_dose']) . '</span>
            </div>';
        }
        
        if (hasValue($form_data['order_every_value']) || hasValue($form_data['order_every_unit'])) {
            $frequency = htmlspecialchars($form_data["order_every_value"] ?? "") . " " . 
                        htmlspecialchars(($form_data["order_every_value"] == 1 ? 
                        rtrim($form_data["order_every_unit"] ?? "", "s") : 
                        $form_data["order_every_unit"] ?? ""));
            $html .= '<div class="field">
                <span class="field-label">Frequency:</span>
                <span class="field-value">' . $frequency . '</span>
            </div>';
        }
        
        if (hasValue($form_data['order_servicing_provider'])) {
            $html .= '<div class="field">
                <span class="field-label">Provider:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_servicing_provider']) . '</span>
            </div>';
        }
        
        if (hasValue($form_data['order_npi'])) {
            $html .= '<div class="field">
                <span class="field-label">NPI:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_npi']) . '</span>
            </div>';
        }
        
        $html .= '</div>';

        // Only add other sections if they have data
        
        // Allergies section
        $html .= '<div class="section">
            <div class="section-title">Allergies</div>
            <div class="field">
                <span class="field-label">Allergies:</span>
                <span class="field-value">' . $buildAllergyHtml($pid) . '</span>
            </div>
        </div>';

        // Vital Signs section - complete with all parameters
        $html .= '<div class="section">
            <div class="section-title">Vital Signs</div>';
        
        if (hasValue($form_data['bp_systolic']) || hasValue($form_data['bp_diastolic'])) {
            $html .= '<div class="field">
                <span class="field-label">Blood Pressure:</span>
                <span class="field-value">' . htmlspecialchars($form_data['bp_systolic'] ?? '') . '/' . htmlspecialchars($form_data['bp_diastolic'] ?? '') . ' mmHg</span>
            </div>';
        }
        if (hasValue($form_data['pulse'])) {
            $html .= '<div class="field">
                <span class="field-label">Pulse:</span>
                <span class="field-value">' . htmlspecialchars($form_data['pulse']) . ' bpm</span>
            </div>';
        }
        if (hasValue($form_data['temperature_f'])) {
            $html .= '<div class="field">
                <span class="field-label">Temperature:</span>
                <span class="field-value">' . htmlspecialchars($form_data['temperature_f']) . ' °F</span>
            </div>';
        }
        if (hasValue($form_data['oxygen_saturation'])) {
            $html .= '<div class="field">
                <span class="field-label">Oxygen Sat:</span>
                <span class="field-value">' . htmlspecialchars($form_data['oxygen_saturation']) . '%</span>
            </div>';
        }
        if (hasValue($form_data['respiratory_rate'])) {
            $html .= '<div class="field">
                <span class="field-label">Respiratory Rate:</span>
                <span class="field-value">' . htmlspecialchars($form_data['respiratory_rate']) . ' breaths/min</span>
            </div>';
        }
        if (hasValue($form_data['weight'])) {
            $html .= '<div class="field">
                <span class="field-label">Weight:</span>
                <span class="field-value">' . htmlspecialchars($form_data['weight']) . ' lbs</span>
            </div>';
        }
        if (hasValue($form_data['height'])) {
            $html .= '<div class="field">
                <span class="field-label">Height:</span>
                <span class="field-value">' . htmlspecialchars($form_data['height']) . ' in</span>
            </div>';
        }
        
        $html .= '</div>';

        // IV Access section
        $html .= '<div class="section">
            <div class="section-title">IV Access</div>';
        
        if (hasValue($form_data['iv_access_type'])) {
            $html .= '<div class="field">
                <span class="field-label">Access Type:</span>
                <span class="field-value">' . htmlspecialchars($form_data['iv_access_type']) . '</span>
            </div>';
        }
        if (hasValue($form_data['iv_access_location'])) {
            $html .= '<div class="field">
                <span class="field-label">Location:</span>
                <span class="field-value">' . htmlspecialchars($form_data['iv_access_location']) . '</span>
            </div>';
        }
        if (hasValue($form_data['iv_access_needle_gauge'])) {
            $html .= '<div class="field">
                <span class="field-label">Needle Gauge:</span>
                <span class="field-value">' . htmlspecialchars($form_data['iv_access_needle_gauge']) . '</span>
            </div>';
        }
        if (hasValue($form_data['iv_access_blood_return'])) {
            $html .= '<div class="field">
                <span class="field-label">Blood Return:</span>
                <span class="field-value">' . htmlspecialchars($form_data['iv_access_blood_return']) . '</span>
            </div>';
        }
        if (hasValue($form_data['iv_access_attempts'])) {
            $html .= '<div class="field">
                <span class="field-label">Attempts:</span>
                <span class="field-value">' . htmlspecialchars($form_data['iv_access_attempts']) . '</span>
            </div>';
        }
        if (hasValue($form_data['iv_access_date'])) {
            $html .= '<div class="field">
                <span class="field-label">Date:</span>
                <span class="field-value">' . htmlspecialchars($formatDateTimeAmPm($form_data['iv_access_date'] ?? '')) . '</span>
            </div>';
        }
        if (hasValue($form_data['iv_access_comments'])) {
            $html .= '<div class="field">
                <span class="field-label">Comments:</span>
                <span class="field-value">' . htmlspecialchars($form_data['iv_access_comments']) . '</span>
            </div>';
        }
        
        $html .= '</div>';

        // Administration section
        $html .= '<div class="section">
            <div class="section-title">Administration</div>';
            
        if (hasValue($form_data['order_medication'])) {
            $html .= '<div class="field">
                <span class="field-label">Medication:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_medication']) . '</span>
            </div>';
        }
        if (hasValue($form_data['order_dose'])) {
            $html .= '<div class="field">
                <span class="field-label">Dose:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_dose']) . '</span>
            </div>';
        }
        if (hasValue($form_data['order_strength'])) {
            $html .= '<div class="field">
                <span class="field-label">Strength:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_strength'] . ' ' . $extractUnitFromDose($form_data['order_dose'] ?? '')) . '</span>
            </div>';
        }
        if (hasValue($form_data['administration_route'])) {
            $html .= '<div class="field">
                <span class="field-label">Route:</span>
                <span class="field-value">' . htmlspecialchars($form_data['administration_route']) . '</span>
            </div>';
        }
        if (hasValue($form_data['order_lot_number'])) {
            $html .= '<div class="field">
                <span class="field-label">Lot Number:</span>
                <span class="field-value">' . htmlspecialchars($form_data['order_lot_number']) . '</span>
            </div>';
        }
        if (hasValue($form_data['order_expiration_date'])) {
            $html .= '<div class="field">
                <span class="field-label">Expiration Date:</span>
                <span class="field-value">' . htmlspecialchars(oeFormatShortDate($form_data['order_expiration_date'] ?? '')) . '</span>
            </div>';
        }
        if (hasValue($form_data['administration_start'])) {
            $html .= '<div class="field">
                <span class="field-label">Start Time:</span>
                <span class="field-value">' . htmlspecialchars($formatDateTimeAmPm($form_data['administration_start'])) . '</span>
            </div>';
        }
        if (hasValue($form_data['administration_end'])) {
            $html .= '<div class="field">
                <span class="field-label">End Time:</span>
                <span class="field-value">' . htmlspecialchars($formatDateTimeAmPm($form_data['administration_end'])) . '</span>
            </div>';
        }
        // Always show duration if we have start and end times
        $calculated_duration = '';
        if (hasValue($form_data['administration_start']) && hasValue($form_data['administration_end'])) {
            $start = strtotime($form_data['administration_start']);
            $end = strtotime($form_data['administration_end']);
            
            if ($start !== false && $end !== false && $end > $start) {
                $duration_seconds = $end - $start;
                $duration_minutes = round($duration_seconds / 60);
                
                $hours = floor($duration_minutes / 60);
                $minutes = $duration_minutes % 60;
                
                if ($hours == 0) {
                    $calculated_duration = $minutes . ' minute' . ($minutes != 1 ? 's' : '');
                } else if ($minutes == 0) {
                    $calculated_duration = $hours . ' hour' . ($hours != 1 ? 's' : '');
                } else {
                    $calculated_duration = $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ' . $minutes . ' minute' . ($minutes != 1 ? 's' : '');
                }
            }
        }
        
        if (!empty($calculated_duration)) {
            $html .= '<div class="field">
                <span class="field-label">Duration:</span>
                <span class="field-value">' . htmlspecialchars($calculated_duration) . '</span>
            </div>';
        }
        if (hasValue($form_data['inventory_quantity_used'])) {
            $html .= '<div class="field">
                <span class="field-label">Quantity Used:</span>
                <span class="field-value">' . htmlspecialchars($form_data['inventory_quantity_used']) . ' ' . htmlspecialchars($extractUnitFromDose($form_data['order_dose'] ?? '')) . '</span>
            </div>';
        }
        if (hasValue($form_data['inventory_wastage_quantity']) && (floatval($form_data['inventory_wastage_quantity'] ?? 0) > 0)) {
            $html .= '<div class="field">
                <span class="field-label">Quantity Wasted:</span>
                <span class="field-value">' . htmlspecialchars($form_data['inventory_wastage_quantity']) . ' ' . htmlspecialchars($extractUnitFromDose($form_data['order_dose'] ?? '')) . '</span>
            </div>';
            
            if (hasValue($form_data['inventory_wastage_reason'])) {
                $html .= '<div class="field">
                    <span class="field-label">Wastage Reason:</span>
                    <span class="field-value">' . htmlspecialchars($form_data['inventory_wastage_reason']) . '</span>
                </div>';
            }
            if (hasValue($form_data['inventory_wastage_notes'])) {
                $html .= '<div class="field">
                    <span class="field-label">Wastage Notes:</span>
                    <span class="field-value">' . htmlspecialchars($form_data['inventory_wastage_notes']) . '</span>
                </div>';
            }
        }
        if (hasValue($form_data['administration_comments'])) {
            $html .= '<div class="field">
                <span class="field-label">Administration Comments:</span>
                <span class="field-value">' . htmlspecialchars($form_data['administration_comments']) . '</span>
            </div>';
        }
        
        $html .= '</div>';

        // Electronic Signatures section
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
        
        $signatures_result = sqlStatement($signatures_sql, [$id]);
        $signatures = [];
        while ($row = sqlFetchArray($signatures_result)) {
            $signatures[] = $row;
        }
        
        if (!empty($signatures)) {
            $html .= '<div class="section">
                <div class="section-title">Electronic Signatures</div>';
            
            foreach ($signatures as $signature) {
                $signature_date = $signature['signature_date'] ?? '';
                $formatted_date = oeFormatShortDate($signature_date);
                $formatted_time = date('g:i A', strtotime($signature_date));
                
                $html .= '<div class="field">
                    <span class="field-label">Signed by:</span>
                    <span class="field-value">' . htmlspecialchars(trim($signature['fname'] . ' ' . $signature['lname'])) . ' (' . htmlspecialchars($signature['type_display_name'] ?? ucfirst($signature['signature_type'])) . ') - ' . htmlspecialchars($formatted_date . ' ' . $formatted_time) . '</span>
                </div>';
                
                if (!empty(trim($signature['signature_text']))) {
                    $html .= '<div class="field">
                        <span class="field-label">Signature Text:</span>
                        <span class="field-value">' . htmlspecialchars($signature['signature_text']) . '</span>
                    </div>';
                }
            }
            
            $html .= '</div>';
        }

    }

    $html .= '
    </body>
    </html>';

    return $html;
}

// Get form data directly from database instead of using report.php
$patient = getPatientData($pid);
$encounter_date = sqlQuery("SELECT date FROM form_encounter WHERE pid = ? AND encounter = ?", [$pid, $encounter]);
$dos_date = $encounter_date['date'] ?? date('Y-m-d');

// Get form data from the database
$sql = "SELECT * FROM form_enhanced_infusion_injection WHERE id = ? AND pid = ?";
$form_data = sqlQuery($sql, [$id, $pid]);

// Build custom HTML with proper styling for PDF
$htmlContent = buildPDFHTML($patient, $form_data, $dos_date, $encounter, $pid, $id);

try {
    // Configure mPDF with strict numeric margins (ChatGPT's PHP 8 fix)
    $pdf = new Mpdf([
        'mode'           => 'utf-8',
        'format'         => 'A4',
        'margin_left'    => 15,    // numbers only, no "mm" strings
        'margin_right'   => 15,
        'margin_top'     => 35,    // increased for header space on first page
        'margin_bottom'  => 25,
        'margin_header'  => 20,    // space for header
        'margin_footer'  => 10,    // reserve space for footer
        'default_font_size' => 11,
    ]);
    
    // Set auto margin properties to avoid calculation conflicts
    $pdf->setAutoBottomMargin = 'stretch';
    $pdf->autoMarginPadding = 0.0;
    
    // Set professional header for first page only
    $logo_path = '/var/www/emr.carepointinfusion.com/sites/default/images/practice_logo.png';
    $header_html = '
        <table width="100%" style="font-size: 10pt; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px;">
            <tr>
                <td width="25%" style="vertical-align: top;">';
    
    // Check if logo exists and add it
    if (file_exists($logo_path)) {
        $header_html .= '<img src="' . $logo_path . '" style="max-height: 70px; max-width: 140px;" alt="CarePoint Logo">';
    } else {
        $header_html .= '<div style="width: 140px; height: 70px; border: 1px solid #ccc; text-align: center; line-height: 70px; font-size: 8pt;">Logo</div>';
    }
    
    $header_html .= '</td>
                <td width="75%" style="text-align: right; vertical-align: top;">
                    <div style="font-weight: bold; font-size: 12pt; color: #007bff; margin-bottom: 3px;">CarePoint Infusion Center</div>
                    <div style="line-height: 1.3;">
                        23215 Commerce Park Suite 318<br>
                        Beachwood, OH 44122-5803<br>
                        Phone: (216) 755-4044<br>
                        Fax: (330) 967-0571
                    </div>
                </td>
            </tr>
        </table>
    ';
    
    // Set header for first page only
    $pdf->SetHTMLHeader($header_html, 'O'); // 'O' for odd pages (first page)
    
    // Now safely set footer with patient info
    $patient_dob = !empty($patient['DOB']) ? oeFormatShortDate($patient['DOB']) : '';
    $footer_left = $patient['fname'] . ' ' . $patient['lname'];
    if (!empty($patient_dob)) {
        $footer_left .= ' (' . $patient_dob . ')';
    }
    
    // Use SetHTMLFooter for more control and PHP 8 compatibility
    $pdf->SetHTMLFooter('
        <table width="100%" style="font-size: 9pt; color: #333;">
            <tr>
                <td style="text-align: left;">' . htmlspecialchars($footer_left) . '</td>
                <td style="text-align: right;">Page {PAGENO} of {nb}</td>
            </tr>
        </table>
    ');
    
    // Set document info
    $patient_name = $patient['fname'] . '_' . $patient['lname'];
    $date_str = date('Y-m-d', strtotime($dos_date));
    
    $filename = "Enhanced_Infusion_Form_{$patient_name}_{$date_str}_Encounter_{$encounter}.pdf";
    
    $pdf->SetTitle("Enhanced Infusion & Injection Form - {$patient['fname']} {$patient['lname']}");
    $pdf->SetAuthor("OpenEMR");
    $pdf->SetSubject("Enhanced Infusion & Injection Form");
    $pdf->SetKeywords("OpenEMR, Enhanced Infusion, Injection, Medical Form");
    
    // Write HTML to PDF
    $pdf->WriteHTML($htmlContent);
    
    // Output PDF for download
    $pdf->Output($filename, 'D'); // 'D' for download
    
} catch (Exception $e) {
    error_log("PDF generation error: " . $e->getMessage());
    echo "Error generating PDF: " . htmlspecialchars($e->getMessage());
}
