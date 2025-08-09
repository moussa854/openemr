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
            body { 
                font-family: Arial, sans-serif; 
                font-size: 11pt; 
                line-height: 1.4;
                margin: 0;
                padding: 20px;
                background: white;
            }
            .container { 
                max-width: 100%; 
                margin: 0 auto; 
                background: white; 
            }
            .header { 
                text-align: center; 
                border-bottom: 2px solid #007bff; 
                padding-bottom: 15px; 
                margin-bottom: 20px; 
            }
            .header h1 { 
                color: #007bff; 
                margin: 0 0 10px 0; 
                font-size: 18pt;
                font-weight: bold;
            }
            .patient-info { 
                background: #f8f9fa; 
                padding: 15px; 
                border: 1px solid #e9ecef; 
                margin-bottom: 20px; 
                border-radius: 5px;
            }
            .info-pair { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 8px; 
            }
            .info-item { 
                flex: 1; 
            }
            .info-item:first-child { 
                margin-right: 20px; 
            }
            .info-label { 
                font-weight: bold; 
                color: #495057; 
                margin-right: 8px;
            }
            .info-value { 
                color: #212529; 
                font-weight: 500; 
            }
            .section { 
                margin-bottom: 20px; 
                border: 1px solid #dee2e6; 
                border-radius: 5px; 
                overflow: hidden; 
                page-break-inside: avoid; 
            }
            .section-title { 
                background: #007bff; 
                color: white; 
                padding: 10px 15px; 
                font-weight: bold; 
                font-size: 12pt; 
                margin: 0;
            }
            .field { 
                padding: 10px 15px; 
                border-bottom: 1px solid #dee2e6; 
                display: flex; 
                align-items: flex-start;
            }
            .field:last-child { 
                border-bottom: none; 
            }
            .field-label { 
                font-weight: bold; 
                color: #495057; 
                min-width: 120px; 
                margin-right: 10px;
                flex-shrink: 0;
            }
            .field-value { 
                color: #212529; 
                font-weight: 500; 
                flex: 1;
                word-wrap: break-word;
            }
            .signature-entry { 
                border: 1px solid #dee2e6; 
                border-radius: 5px; 
                margin-bottom: 15px; 
                padding: 15px; 
                background: #f8f9fa; 
                page-break-inside: avoid; 
            }
            .signature-header { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                margin-bottom: 10px; 
                flex-wrap: wrap; 
            }
            .signature-user { 
                font-weight: bold; 
                color: #007bff; 
                font-size: 11pt; 
            }
            .signature-type { 
                background: #007bff; 
                color: white; 
                padding: 4px 8px; 
                border-radius: 12px; 
                font-size: 9pt; 
                font-weight: bold; 
                text-transform: uppercase; 
            }
            .signature-date { 
                color: #6c757d; 
                font-size: 10pt; 
            }
            .signature-text { 
                margin-top: 8px; 
                padding-top: 8px; 
                border-top: 1px solid #dee2e6; 
            }
            .signature-label { 
                font-weight: bold; 
                color: #495057; 
                margin-right: 8px; 
            }
            .signature-value { 
                color: #212529; 
                font-style: italic; 
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
                            <span class="info-value">' . htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">DOB:</span>
                            <span class="info-value">' . htmlspecialchars(oeFormatShortDate($patient['DOB'] ?? '')) . '</span>
                        </div>
                    </div>
                    <div class="info-pair">
                        <div class="info-item">
                            <span class="info-label">DOS:</span>
                            <span class="info-value">' . oeFormatShortDate($dos_date) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Encounter:</span>
                            <span class="info-value">' . htmlspecialchars($encounter) . '</span>
                        </div>
                    </div>
                    <div class="info-pair">
                        <div class="info-item">
                            <span class="info-label">Provider:</span>
                            <span class="info-value">' . htmlspecialchars($form_data['order_servicing_provider'] ?? 'Moussa El-hallak, M.D.') . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">NPI:</span>
                            <span class="info-value">' . htmlspecialchars($form_data['order_npi'] ?? '1831381524') . '</span>
                        </div>
                    </div>
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

        // Allergies section
        $html .= '<div class="section">
            <div class="section-title">Allergies</div>
            <div class="field">
                <span class="field-label">Allergies:</span>
                <span class="field-value">' . $buildAllergyHtml($pid) . '</span>
            </div>
        </div>';

        // Vital Signs section
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

        // Administration section (expanded)
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
        if (hasValue($form_data['administration_duration'])) {
            $html .= '<div class="field">
                <span class="field-label">Duration:</span>
                <span class="field-value">' . htmlspecialchars($form_data['administration_duration']) . ' hours</span>
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
                
                $html .= '<div class="signature-entry">
                    <div class="signature-header">
                        <span class="signature-user">' . htmlspecialchars(trim($signature['fname'] . ' ' . $signature['lname'])) . '</span>
                        <span class="signature-type">' . htmlspecialchars($signature['type_display_name'] ?? ucfirst($signature['signature_type'])) . '</span>
                        <span class="signature-date">' . htmlspecialchars($formatted_date . ' ' . $formatted_time) . '</span>
                    </div>';
                
                if (!empty(trim($signature['signature_text']))) {
                    $html .= '<div class="signature-text">
                        <span class="signature-label">Signature Text:</span>
                        <span class="signature-value">' . htmlspecialchars($signature['signature_text']) . '</span>
                    </div>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
    }

    $html .= '
        </div>
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
    // Configure mPDF
    $config_mpdf = Config_Mpdf::getConfigMpdf();
    $config_mpdf['margin_left'] = 15;
    $config_mpdf['margin_right'] = 15;
    $config_mpdf['margin_top'] = 15;
    $config_mpdf['margin_bottom'] = 15;
    $config_mpdf['default_font_size'] = 11;
    
    // Create PDF
    $pdf = new Mpdf($config_mpdf);
    
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
