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
    
    // Custom CSS for better PDF rendering
    $pdfCSS = "
    <style>
        @page {
            margin: 15mm;
            margin-header: 5mm;
            margin-footer: 5mm;
        }
        body { 
            font-family: 'DejaVu Sans', Arial, sans-serif; 
            font-size: 10pt; 
            line-height: 1.3;
        }
        .container { 
            width: 100%; 
            margin: 0; 
            padding: 0; 
        }
        .header { 
            text-align: center; 
            border-bottom: 2px solid #000; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
        }
        .header h1 { 
            color: #000; 
            margin: 0 0 5px 0; 
            font-size: 16pt; 
        }
        .patient-info { 
            background: #f8f9fa; 
            padding: 10px; 
            border: 1px solid #000; 
            margin-bottom: 15px; 
        }
        .section { 
            margin-bottom: 15px; 
            border: 1px solid #000; 
            page-break-inside: avoid; 
        }
        .section-title { 
            background: #000; 
            color: #fff; 
            padding: 5px 10px; 
            font-weight: bold; 
            font-size: 11pt; 
        }
        .field { 
            padding: 5px 10px; 
            border-bottom: 1px solid #ccc; 
            display: block; 
        }
        .field:last-child { 
            border-bottom: none; 
        }
        .field-label { 
            font-weight: bold; 
            color: #333; 
            display: inline-block; 
            min-width: 120px; 
        }
        .field-value { 
            color: #000; 
        }
        .signature-entry { 
            border: 1px solid #ccc; 
            margin-bottom: 10px; 
            padding: 8px; 
            background: #f8f9fa; 
            page-break-inside: avoid; 
        }
        .signature-header { 
            margin-bottom: 5px; 
        }
        .signature-user { 
            font-weight: bold; 
            color: #000; 
        }
        .signature-type { 
            background: #000; 
            color: #fff; 
            padding: 2px 5px; 
            font-size: 8pt; 
            font-weight: bold; 
            text-transform: uppercase; 
        }
        .signature-date { 
            color: #666; 
            font-size: 9pt; 
        }
        .signature-text { 
            margin-top: 5px; 
            padding-top: 5px; 
            border-top: 1px solid #ccc; 
        }
        .info-pair { 
            margin-bottom: 5px; 
        }
        .info-label { 
            font-weight: bold; 
            color: #333; 
        }
        .info-value { 
            color: #000; 
            font-weight: normal; 
        }
        .print-button { 
            display: none; 
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
