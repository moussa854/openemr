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

// Generate the HTML content
ob_start();
enhanced_infusion_injection_report($pid, $encounter, null, $id, true);
$htmlContent = ob_get_clean();

try {
    // Configure mPDF
    $config_mpdf = Config_Mpdf::getConfigMpdf();
    $config_mpdf['margin_left'] = 15;
    $config_mpdf['margin_right'] = 15;
    $config_mpdf['margin_top'] = 15;
    $config_mpdf['margin_bottom'] = 15;
    $config_mpdf['default_font_size'] = 10;
    
    // Create PDF
    $pdf = new Mpdf($config_mpdf);
    
    // Set document info
    $patient = getPatientData($pid);
    $patient_name = $patient['fname'] . '_' . $patient['lname'];
    $encounter_date = sqlQuery("SELECT date FROM form_encounter WHERE pid = ? AND encounter = ?", [$pid, $encounter]);
    $date_str = date('Y-m-d', strtotime($encounter_date['date'] ?? 'now'));
    
    $filename = "Enhanced_Infusion_Form_{$patient_name}_{$date_str}_Encounter_{$encounter}.pdf";
    
    $pdf->SetTitle("Enhanced Infusion & Injection Form - {$patient['fname']} {$patient['lname']}");
    $pdf->SetAuthor("OpenEMR");
    $pdf->SetSubject("Enhanced Infusion & Injection Form");
    $pdf->SetKeywords("OpenEMR, Enhanced Infusion, Injection, Medical Form");
    
    // Custom CSS to match the exact design from the image
    $pdfCSS = "
    <style>
        @page {
            margin: 15mm;
            margin-header: 5mm;
            margin-footer: 5mm;
        }
        body { 
            font-family: 'DejaVu Sans', Arial, sans-serif; 
            font-size: 11pt; 
            line-height: 1.4;
            background: #f5f5f5;
        }
        .container { 
            width: 100%; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
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
        .print-button { 
            display: none; 
        }
        .no-data { 
            color: #6c757d; 
            font-style: italic; 
        }
    </style>";
    
    // Clean the HTML and add PDF-specific CSS
    $cleanedHTML = $pdfCSS . $htmlContent;
    
    // Remove any remaining print buttons
    $cleanedHTML = preg_replace('/<button[^>]*class="print-button"[^>]*>.*?<\/button>/is', '', $cleanedHTML);
    
    // Write HTML to PDF
    $pdf->WriteHTML($cleanedHTML);
    
    // Output PDF for download
    $pdf->Output($filename, 'D'); // 'D' for download
    
} catch (Exception $e) {
    error_log("PDF generation error: " . $e->getMessage());
    echo "Error generating PDF: " . htmlspecialchars($e->getMessage());
}
