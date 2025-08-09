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
    
    // PDF-specific CSS overrides to enhance the existing report.php styling for PDF
    $pdfCSS = "
    <style>
        /* PDF page settings */
        @page {
            margin: 15mm;
            margin-header: 5mm;
            margin-footer: 5mm;
        }
        
        /* PDF-specific overrides for better rendering */
        body { 
            background: white !important; 
            margin: 0 !important; 
            padding: 0 !important;
        }
        
        .container { 
            box-shadow: none !important; 
            border-radius: 0 !important; 
            margin: 0 !important; 
            padding: 20px !important; 
            max-width: none !important; 
            background: white !important;
        }
        
        .header { 
            margin-bottom: 20px !important; 
            padding-bottom: 15px !important; 
            border-bottom: 2px solid #007bff !important; 
        }
        
        .section { 
            margin-bottom: 20px !important; 
            page-break-inside: avoid !important; 
            border: 1px solid #dee2e6 !important; 
        }
        
        .section-title { 
            background: #007bff !important; 
            color: white !important; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        
        .signature-entry { 
            page-break-inside: avoid !important; 
            border: 1px solid #dee2e6 !important; 
            background: #f8f9fa !important; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        
        .signature-type { 
            background: #007bff !important; 
            color: white !important; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        
        .signature-user {
            color: #007bff !important;
        }
        
        .patient-info { 
            background: #f8f9fa !important; 
            border: 1px solid #e9ecef !important; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        
        /* Hide print buttons */
        .print-button, button { 
            display: none !important; 
        }
        
        /* Force color printing */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
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
