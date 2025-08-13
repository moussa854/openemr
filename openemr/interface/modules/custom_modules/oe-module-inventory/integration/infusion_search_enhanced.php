<?php
/**
 * Enhanced Infusion Form with Inventory Integration
 * 
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Initialize OpenEMR
$ignoreAuth = false;
require_once(dirname(__FILE__) . "/../../../../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../../../../library/forms.inc.php");
require_once(dirname(__FILE__) . "/../../../../../library/patient.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Check if user is authenticated
if (!isset($_SESSION['authUserID'])) {
    die("Authentication required");
}

// Get patient ID
$pid = $_GET['pid'] ?? $GLOBALS['pid'];
$encounter = $_GET['encounter'] ?? '';

// Check if we're loading an existing form
$form_id = $_GET['id'] ?? null;
$saved_data = null;
$saved_message = '';

if ($form_id) {
    // Load existing form data from database
    $form_sql = "SELECT * FROM form_enhanced_infusion_injection WHERE id = ? AND pid = ?"; // Removed encounter from WHERE clause
    $form_result = sqlStatement($form_sql, [$form_id, $pid]);
    if ($row = sqlFetchArray($form_result)) {
        $saved_data = $row;
        // Map database columns to form fields
        $saved_data["order_route"] = $saved_data["administration_route"] ?? "";
        // Get encounter from saved data if not provided in URL
        if (empty($encounter)) {
            $encounter = $saved_data['encounter'] ?? '';
        }
    }
    
    // Load secondary medications
    $secondary_medications = [];
    $medications_sql = "SELECT * FROM form_enhanced_infusion_medications WHERE form_id = ? ORDER BY medication_order";
    $medications_result = sqlStatement($medications_sql, [$form_id]);
    while ($medication_row = sqlFetchArray($medications_result)) {
        $secondary_medications[] = $medication_row;
    }
    
    // Check if this is a redirect after save
    if (isset($_GET['saved']) && $_GET['saved'] == '1') {
        $saved_message = '<div class="alert alert-success" style="margin-bottom: 20px;"><i class="fa fa-check-circle"></i> Form saved successfully!</div>';
    }
} else {
    // This is a new form - get the previous assessment value for this patient
    $previous_assessment_sql = "SELECT assessment FROM form_enhanced_infusion_injection 
                               WHERE pid = ? AND assessment IS NOT NULL AND assessment != '' 
                               ORDER BY date DESC, id DESC LIMIT 1";
    $previous_result = sqlStatement($previous_assessment_sql, [$pid]);
    if ($previous_row = sqlFetchArray($previous_result)) {
        $saved_data = ['assessment' => $previous_row['assessment']];
    }
}

// If encounter is still empty, try to get from session
if (empty($encounter)) {
    $encounter = $_SESSION['encounter'] ?? '';
}

// If still empty, try to get from current encounter
if (empty($encounter) && isset($GLOBALS['encounter'])) {
    $encounter = $GLOBALS['encounter'];
}

// Load wastage reasons for dropdown
$wastage_reasons = [];
$reasons_sql = "SELECT reason_code, reason_description FROM infusion_wastage_reasons WHERE is_active = 1 ORDER BY reason_description";
$reasons_result = sqlStatement($reasons_sql);
while ($row = sqlFetchArray($reasons_result)) {
    $wastage_reasons[] = $row;
}

// Function to build allergies HTML from system
function buildAllergyHtml($pid) {
    if (empty($pid)) {
        return '<p>No patient selected.</p>';
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
        return '<p>No known allergies recorded.</p>';
    }

    $html = '<ul class="list-unstyled mb-0">';
    foreach ($items as $item) {
        $html .= '<li>&#8226; ' . $item . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

// Generate CSRF token
$csrf_token = CsrfUtils::collectCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <script src="../../../../../library/formatting_DateToYYYYMMDD_js.js.php"></script>
    <title>Enhanced Infusion and Injection Form</title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/public/themes/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .form-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .search-container {
            position: relative;
        }
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .suggestion-item:hover {
            background-color: #f8f9fa;
        }
        .suggestion-item.selected {
            background-color: #007bff;
            color: white;
        }
        .drug-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .status-available { color: #28a745; font-weight: bold; }
        .status-unavailable { color: #dc3545; font-weight: bold; }
        .status-expiring { color: #ffc107; font-weight: bold; }
        .form-group {
            margin-bottom: 15px;
        }
        .control-label {
            font-weight: bold;
            color: #495057;
        }
        .btn-save {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 10px 30px;
            font-size: 16px;
        }
        .btn-save:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-md-12 {
            padding: 0 10px;
            box-sizing: border-box;
        }
        .col-md-3 { width: 25%; }
        .col-md-4 { width: 33.333%; }
        .col-md-6 { width: 50%; }
        .col-md-8 { width: 66.667%; }
        .col-md-12 { width: 100%; }
        .text-center { text-align: center; }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .input-group {
            display: flex;
            align-items: center;
        }
        .input-group-append {
            margin-left: -1px;
        }
        .input-group-text {
            padding: 8px 12px;
            background-color: #e9ecef;
            border: 1px solid #ddd;
            border-left: none;
        }
        
        /* Signature Styles */
        .signatures-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .signature-table th,
        .signature-table td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
        }
        .signature-table th {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .signature-form {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
        }
        .signature-actions {
            display: flex;
            gap: 5px;
        }
        .signature-actions .btn {
            padding: 4px 8px;
            font-size: 12px;
        }
        .signature-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .signature-type-primary { background-color: #007bff; color: white; }
        .signature-type-witness { background-color: #28a745; color: white; }
        .signature-type-reviewer { background-color: #ffc107; color: #212529; }
        .signature-type-custom { background-color: #6c757d; color: white; }
        .mt-3 { margin-top: 15px; }
        
        /* Multi-Medication Styles */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .medication-section {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .primary-medication {
            border-left: 4px solid #007bff;
        }
        
        .secondary-medication {
            border-left: 4px solid #17a2b8;
        }
        
        .medication-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .medication-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .medication-type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-primary { background-color: #007bff; color: white; }
        .badge-info { background-color: #17a2b8; color: white; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        
        .medication-actions {
            display: flex;
            gap: 5px;
        }
        
        .medication-actions .btn {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .medication-content {
            padding: 20px;
        }
        
        .subsection-title {
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .input-group {
            display: flex;
            align-items: stretch;
            width: 100%;
        }
        .input-group .form-control {
            position: relative;
            flex: 1 1 auto;
            width: 1%;
            min-width: 0;
        }
        .input-group-append {
            display: flex;
            margin-left: -1px;
        }
        .input-group-text {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            color: #495057;
            text-align: center;
            white-space: nowrap;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0 4px 4px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fa fa-tint"></i> Enhanced Infusion and Injection Treatment Form</h2>
                
                <?php echo $saved_message; ?>
                
                <form method="POST" action="save_enhanced.php" id="enhanced-infusion-form">
                    <input type="hidden" name="csrf_token_form" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="pid" value="<?php echo htmlspecialchars($pid); ?>">
                    <input type="hidden" name="encounter" value="<?php echo htmlspecialchars($encounter); ?>">
                    <?php if ($form_id): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($form_id); ?>">
                    <?php endif; ?>
                    
                    <!-- Assessment Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fa fa-stethoscope"></i> Assessment</h3>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="assessment" class="control-label">Patient Assessment:</label>
                                    <textarea name="assessment" id="assessment" class="form-control" rows="4" 
                                              placeholder="Enter patient assessment, symptoms, and clinical findings..."><?php echo htmlspecialchars($saved_data['assessment'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Allergies Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fa fa-exclamation-triangle"></i> Allergies</h3>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">Known Allergies:</label>
                                    <div class="allergies-display">
                                        <?php echo buildAllergyHtml($pid); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vital Signs Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fa fa-heartbeat"></i> Vital Signs</h3>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bp_systolic" class="control-label">BP Systolic:</label>
                                    <input type="number" name="bp_systolic" id="bp_systolic" class="form-control" 
                                           placeholder="Systolic" value="<?php echo htmlspecialchars($saved_data['bp_systolic'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bp_diastolic" class="control-label">BP Diastolic:</label>
                                    <input type="number" name="bp_diastolic" id="bp_diastolic" class="form-control" 
                                           placeholder="Diastolic" value="<?php echo htmlspecialchars($saved_data['bp_diastolic'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="pulse" class="control-label">Pulse:</label>
                                    <input type="number" name="pulse" id="pulse" class="form-control" 
                                           placeholder="BPM" value="<?php echo htmlspecialchars($saved_data['pulse'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="temperature_f" class="control-label">Temperature (°F):</label>
                                    <input type="number" step="0.1" name="temperature_f" id="temperature_f" class="form-control" 
                                           placeholder="°F" value="<?php echo htmlspecialchars($saved_data['temperature_f'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="oxygen_saturation" class="control-label">O2 Saturation (%):</label>
                                    <input type="number" step="0.1" name="oxygen_saturation" id="oxygen_saturation" class="form-control" 
                                           placeholder="%" value="<?php echo htmlspecialchars($saved_data['oxygen_saturation'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="respiratory_rate" class="control-label">Respiratory Rate:</label>
                                    <input type="number" name="respiratory_rate" id="respiratory_rate" class="form-control" 
                                           placeholder="Breaths/min" value="<?php echo htmlspecialchars($saved_data['respiratory_rate'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="weight" class="control-label">Weight (kg):</label>
                                    <input type="number" step="0.1" name="weight" id="weight" class="form-control" 
                                           placeholder="kg" value="<?php echo htmlspecialchars($saved_data['weight'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="height" class="control-label">Height (cm):</label>
                                    <input type="number" step="0.1" name="height" id="height" class="form-control" 
                                           placeholder="cm" value="<?php echo htmlspecialchars($saved_data['height'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IV Access Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fa fa-tint"></i> IV Access</h3>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="iv_access_type" class="control-label">Access Type:</label>
                                    <select name="iv_access_type" id="iv_access_type" class="form-control">
                                        <option value="">Select type</option>
                                        <option value="peripheral" <?php echo ($saved_data['iv_access_type'] ?? '') == 'peripheral' ? 'selected' : ''; ?>>Peripheral IV</option>
                                        <option value="central" <?php echo ($saved_data['iv_access_type'] ?? '') == 'central' ? 'selected' : ''; ?>>Central Line</option>
                                        <option value="picc" <?php echo ($saved_data['iv_access_type'] ?? '') == 'picc' ? 'selected' : ''; ?>>PICC Line</option>
                                        <option value="port" <?php echo ($saved_data['iv_access_type'] ?? '') == 'port' ? 'selected' : ''; ?>>Port-a-Cath</option>
                                        <option value="midline" <?php echo ($saved_data['iv_access_type'] ?? '') == 'midline' ? 'selected' : ''; ?>>Midline</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="iv_access_location" class="control-label">Access Location:</label>
                                    <select name="iv_access_location" id="iv_access_location" class="form-control">
                                        <option value="">Select location</option>
                                        <option value="right_hand" <?php echo ($saved_data['iv_access_location'] ?? '') == 'right_hand' ? 'selected' : ''; ?>>Right Hand</option>
                                        <option value="left_hand" <?php echo ($saved_data['iv_access_location'] ?? '') == 'left_hand' ? 'selected' : ''; ?>>Left Hand</option>
                                        <option value="right_forearm" <?php echo ($saved_data['iv_access_location'] ?? '') == 'right_forearm' ? 'selected' : ''; ?>>Right Forearm</option>
                                        <option value="left_forearm" <?php echo ($saved_data['iv_access_location'] ?? '') == 'left_forearm' ? 'selected' : ''; ?>>Left Forearm</option>
                                        <option value="right_upper_arm" <?php echo ($saved_data['iv_access_location'] ?? '') == 'right_upper_arm' ? 'selected' : ''; ?>>Right Upper Arm</option>
                                        <option value="left_upper_arm" <?php echo ($saved_data['iv_access_location'] ?? '') == 'left_upper_arm' ? 'selected' : ''; ?>>Left Upper Arm</option>
                                        <option value="right_antecubital" <?php echo ($saved_data['iv_access_location'] ?? '') == 'right_antecubital' ? 'selected' : ''; ?>>Right Antecubital</option>
                                        <option value="left_antecubital" <?php echo ($saved_data['iv_access_location'] ?? '') == 'left_antecubital' ? 'selected' : ''; ?>>Left Antecubital</option>
                                        <option value="right_foot" <?php echo ($saved_data['iv_access_location'] ?? '') == 'right_foot' ? 'selected' : ''; ?>>Right Foot</option>
                                        <option value="left_foot" <?php echo ($saved_data['iv_access_location'] ?? '') == 'left_foot' ? 'selected' : ''; ?>>Left Foot</option>
                                        <option value="right_ankle" <?php echo ($saved_data['iv_access_location'] ?? '') == 'right_ankle' ? 'selected' : ''; ?>>Right Ankle</option>
                                        <option value="left_ankle" <?php echo ($saved_data['iv_access_location'] ?? '') == 'left_ankle' ? 'selected' : ''; ?>>Left Ankle</option>
                                        <option value="subclavian" <?php echo ($saved_data['iv_access_location'] ?? '') == 'subclavian' ? 'selected' : ''; ?>>Subclavian</option>
                                        <option value="jugular" <?php echo ($saved_data['iv_access_location'] ?? '') == 'jugular' ? 'selected' : ''; ?>>Jugular</option>
                                        <option value="femoral" <?php echo ($saved_data['iv_access_location'] ?? '') == 'femoral' ? 'selected' : ''; ?>>Femoral</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="iv_access_needle_gauge" class="control-label">Needle Gauge:</label>
                                    <select name="iv_access_needle_gauge" id="iv_access_needle_gauge" class="form-control">
                                        <option value="">Select gauge</option>
                                        <option value="N/A">N/A</option>
                                        <option value="14" <?php echo ($saved_data['iv_access_needle_gauge'] ?? '') == '14' ? 'selected' : ''; ?>>14G</option>
                                        <option value="16" <?php echo ($saved_data['iv_access_needle_gauge'] ?? '') == '16' ? 'selected' : ''; ?>>16G</option>
                                        <option value="18" <?php echo ($saved_data['iv_access_needle_gauge'] ?? '') == '18' ? 'selected' : ''; ?>>18G</option>
                                        <option value="20" <?php echo ($saved_data['iv_access_needle_gauge'] ?? '') == '20' ? 'selected' : ''; ?>>20G</option>
                                        <option value="22" <?php echo ($saved_data['iv_access_needle_gauge'] ?? '') == '22' ? 'selected' : ''; ?>>22G</option>
                                        <option value="24" <?php echo ($saved_data['iv_access_needle_gauge'] ?? '') == '24' ? 'selected' : ''; ?>>24G</option>
                                        <option value="26" <?php echo ($saved_data['iv_access_needle_gauge'] ?? '') == '26' ? 'selected' : ''; ?>>26G</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="iv_access_blood_return" class="control-label">Blood Return:</label>
                                    <select name="iv_access_blood_return" id="iv_access_blood_return" class="form-control">
                                        <option value="">Select status</option>
                                        <option value="yes" <?php echo ($saved_data['iv_access_blood_return'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="no" <?php echo ($saved_data['iv_access_blood_return'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                                        <option value="partial" <?php echo ($saved_data['iv_access_blood_return'] ?? '') == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="iv_access_attempts" class="control-label">Number of Attempts:</label>
                                    <input type="number" name="iv_access_attempts" id="iv_access_attempts" class="form-control" 
                                           placeholder="Number of attempts" value="<?php echo htmlspecialchars($saved_data['iv_access_attempts'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="iv_access_date" class="control-label">Access Date:</label>
                                    <input type="datetime-local" name="iv_access_date" id="iv_access_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d') . 'T' . date('H:i', strtotime($saved_data["iv_access_date"] ?? 'now'))); ?>" placeholder="Date and time">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="iv_access_comments" class="control-label">IV Access Comments:</label>
                                    <textarea name="iv_access_comments" id="iv_access_comments" class="form-control" rows="2" 
                                              placeholder="Additional comments about IV access..."><?php echo htmlspecialchars($saved_data['iv_access_comments'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ICD-10 Diagnosis Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fa fa-stethoscope"></i> ICD-10 Diagnosis</h3>
                        
                        <!-- ICD-10 Diagnosis Search -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">ICD-10 Diagnosis:</label>
                                    <div class="search-container">
                                        <input type="text" id="icd10-search" class="form-control" 
                                               placeholder="Search ICD-10 diagnosis codes..." value="<?php echo htmlspecialchars($saved_data['icd10_code'] ?? ''); ?>">
                                        <div id="icd10-suggestions" class="search-suggestions" style="max-height: 150px;"></div>
                                    </div>
                                    <input type="hidden" name="diagnoses_codes" id="diagnoses_codes" value="<?php echo htmlspecialchars($saved_data['diagnoses'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selected Diagnoses -->
                        <div id="selected-diagnoses" class="row" style="display: none;">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <div id="diagnoses-list"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medications Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fa fa-prescription"></i> Medications</h3>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addMedication()">
                                <i class="fa fa-plus"></i> Add Secondary/PRN Medication
                            </button>
                        </div>
                        
                        <!-- Primary Medication -->
                        <div id="medication-1" class="medication-section primary-medication" data-medication-id="1">
                            <div class="medication-header">
                                <h4 class="medication-title">
                                    <span class="medication-type-badge badge badge-primary">Primary</span>
                                    <span class="medication-name">Primary Medication</span>
                                </h4>
                            </div>
                            
                            <!-- Primary Medication Order Section -->
                            <div class="medication-content">
                                <h4 class="subsection-title"><i class="fa fa-prescription"></i> Medication Order</h4>
                        
                        <!-- Search Inventory -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="medication-search" class="control-label">Search Inventory:</label>
                                    <div class="search-container">
                                        <input type="text" id="medication-search" class="form-control" 
                                               placeholder="Enter medication name, NDC, or barcode..." value="<?php echo htmlspecialchars($saved_data['order_medication'] ?? ''); ?>">
                                        <div id="search-suggestions" class="search-suggestions"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="search-type" class="control-label">Search Type:</label>
                                    <select id="search-type" class="form-control">
                                        <option value="name" <?php echo ($saved_data['search_type'] ?? '') == 'name' ? 'selected' : ''; ?>>Medication Name</option>
                                        <option value="ndc" <?php echo ($saved_data['search_type'] ?? '') == 'ndc' ? 'selected' : ''; ?>>NDC Code</option>
                                        <option value="barcode" <?php echo ($saved_data['search_type'] ?? '') == 'barcode' ? 'selected' : ''; ?>>Barcode</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selected Drug Information -->
                        <div id="selected-drug-info" class="drug-info" style="display: none;">
                            <h4><i class="fa fa-check-circle"></i> Selected Drug Information</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Name:</strong><br>
                                    <span id="drug-name-display"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Strength:</strong><br>
                                    <span id="drug-strength-display"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Lot:</strong><br>
                                    <span id="drug-lot-display"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>NDC:</strong><br>
                                    <span id="drug-ndc-display"></span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Expiration:</strong><br>
                                    <span id="drug-expiration-display"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Available:</strong><br>
                                    <span id="drug-quantity-display"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Form:</strong><br>
                                    <span id="drug-form-display"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Status:</strong><br>
                                    <span id="drug-status-display"></span>
                                </div>
                            </div>
                            <input type="hidden" id="selected-drug-id" name="inventory_drug_id" value="<?php echo htmlspecialchars($saved_data['inventory_drug_id'] ?? ''); ?>">
                            <input type="hidden" id="selected-drug-lot" name="inventory_lot_number" value="<?php echo htmlspecialchars($saved_data['inventory_lot_number'] ?? ''); ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="order_medication" class="control-label">Medication Name:</label>
                                    <input type="text" name="order_medication" id="order_medication" class="form-control" 
                                           placeholder="Medication name" value="<?php echo htmlspecialchars($saved_data['order_medication'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="order_dose" class="control-label">Dose:</label>
                                    <input type="text" name="order_dose" id="order_dose" class="form-control" 
                                           placeholder="Dose and unit" value="<?php echo htmlspecialchars($saved_data['order_dose'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="order_strength" class="control-label">Strength:</label>
                                    <input type="text" name="order_strength" id="order_strength" class="form-control" 
                                           placeholder="Medication strength" value="<?php echo htmlspecialchars($saved_data['order_strength'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="order_route" class="control-label">Route:</label>
                                    <input type="text" name="order_route" id="order_route" class="form-control" 
                                           placeholder="Route of administration" value="<?php echo htmlspecialchars($saved_data['order_route'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="order_lot_number" class="control-label">Lot Number:</label>
                                    <input type="text" name="order_lot_number" id="order_lot_number" class="form-control" 
                                           placeholder="Lot number" value="<?php echo htmlspecialchars($saved_data['order_lot_number'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="order_expiration_date" class="control-label">Expiration Date:</label>
                                    <input type="date" name="order_expiration_date" id="order_expiration_date" class="form-control" value="<?php echo htmlspecialchars($saved_data["order_expiration_date"] ?? ""); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="order_ndc" class="control-label">NDC:</label>
                                    <input type="text" name="order_ndc" id="order_ndc" class="form-control" 
                                           placeholder="NDC code" value="<?php echo htmlspecialchars($saved_data['order_ndc'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="order_every_value" class="control-label">Frequency Value:</label>
                                    <input type="number" name="order_every_value" id="order_every_value" class="form-control" 
                                           placeholder="Value" value="<?php echo htmlspecialchars($saved_data['order_every_value'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="order_every_unit" class="control-label">Frequency Unit:</label>
                                    <select name="order_every_unit" id="order_every_unit" class="form-control">
                                        <option value="">Select unit</option>
                                        <option value="hours" <?php echo ($saved_data['order_every_unit'] ?? '') == 'hours' ? 'selected' : ''; ?>>Hours</option>
                                        <option value="days" <?php echo ($saved_data['order_every_unit'] ?? '') == 'days' ? 'selected' : ''; ?>>Days</option>
                                        <option value="weeks" <?php echo ($saved_data['order_every_unit'] ?? '') == 'weeks' ? 'selected' : ''; ?>>Weeks</option>
                                        <option value="once" <?php echo ($saved_data['order_every_unit'] ?? '') == 'once' ? 'selected' : ''; ?>>Once</option>
                                        <option value="prn" <?php echo ($saved_data['order_every_unit'] ?? '') == 'prn' ? 'selected' : ''; ?>>PRN</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="order_end_date" class="control-label">End Date:</label>
                                    <input type="date" name="order_end_date" id="order_end_date" class="form-control" value="<?php echo htmlspecialchars($saved_data["order_end_date"] ?? ""); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="order_servicing_provider" class="control-label">Provider:</label>
                                    <input type="text" name="order_servicing_provider" id="order_servicing_provider" class="form-control" 
                                           placeholder="Provider name" value="<?php echo htmlspecialchars($saved_data['order_servicing_provider'] ?? 'Moussa El-hallak, M.D.'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="order_npi" class="control-label">NPI:</label>
                                    <input type="text" name="order_npi" id="order_npi" class="form-control" 
                                           placeholder="NPI number" value="<?php echo htmlspecialchars($saved_data['order_npi'] ?? '1831381524'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="order_note" class="control-label">Order Notes:</label>
                                    <textarea name="order_note" id="order_note" class="form-control" rows="2" 
                                              placeholder="Additional order notes..."><?php echo htmlspecialchars($saved_data['order_note'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                                <!-- Primary Administration Section -->
                                <h4 class="subsection-title"><i class="fa fa-clock-o"></i> Administration</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="administration_start" class="control-label">Start Time:</label>
                                    <input type="datetime-local" name="administration_start" id="administration_start" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d') . 'T' . date('H:i', strtotime($saved_data["administration_start"] ?? 'now'))); ?>" onchange="calculateDuration()" placeholder="Date and time">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="administration_end" class="control-label">End Time:</label>
                                    <input type="datetime-local" name="administration_end" id="administration_end" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d') . 'T' . date('H:i', strtotime($saved_data["administration_end"] ?? 'now'))); ?>" onchange="calculateDuration()" placeholder="Date and time">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="administration_duration" class="control-label">Duration:</label>
                                    <div class="input-group">
                                        <input type="text" name="administration_duration" id="administration_duration" class="form-control" readonly placeholder="Calculated automatically" value="<?php echo htmlspecialchars($saved_data['administration_duration'] ?? ''); ?>">
                                        <div class="input-group-append">
                                            <span class="input-group-text">hours:minutes</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inventory_quantity_used" class="control-label">Quantity Used:</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="inventory_quantity_used" id="inventory_quantity_used" 
                                               class="form-control" placeholder="Enter quantity used" value="<?php echo htmlspecialchars($saved_data['inventory_quantity_used'] ?? ''); ?>">
                                        <div class="input-group-append">
                                            <span class="input-group-text" style="display: inline-block;"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inventory_wastage_quantity" class="control-label">Quantity Wasted:</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="inventory_wastage_quantity" id="inventory_wastage_quantity" 
                                               class="form-control" placeholder="Enter quantity wasted" value="<?php echo htmlspecialchars($saved_data['inventory_wastage_quantity'] ?? ''); ?>">
                                        <div class="input-group-append">
                                            <span class="input-group-text" style="display: inline-block;"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inventory_wastage_reason" class="control-label">Wastage Reason:</label>
                                    <select name="inventory_wastage_reason" id="inventory_wastage_reason" class="form-control">
                                        <option value="">Select reason</option>
                                        <?php foreach ($wastage_reasons as $reason): ?>
                                            <option value="<?php echo htmlspecialchars($reason['reason_code']); ?>" <?php echo ($saved_data['inventory_wastage_reason'] ?? '') == $reason['reason_code'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($reason['reason_description']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inventory_wastage_notes" class="control-label">Wastage Notes:</label>
                                    <textarea name="inventory_wastage_notes" id="inventory_wastage_notes" class="form-control" rows="2" 
                                              placeholder="Additional notes about wastage"><?php echo htmlspecialchars($saved_data['inventory_wastage_notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="administration_note" class="control-label">Administration Notes:</label>
                                    <textarea name="administration_note" id="administration_note" class="form-control" rows="3" 
                                              placeholder="Notes about administration, patient response, complications..."><?php echo htmlspecialchars($saved_data['administration_note'] ?? ''); ?></textarea>
                                </div>
                            </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Secondary/PRN Medications Container -->
                        <div id="secondary-medications-container">
                            <!-- Dynamic secondary/PRN medications will be added here -->
                        </div>
                    </div>

                    <!-- Electronic Signatures Section -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fa fa-pencil"></i> Electronic Signatures</h3>
                        
                        <!-- Existing Signatures Display -->
                        <div id="existing-signatures" class="signatures-container">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i> No signatures yet. Add the first signature below.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Signature Form -->
                        <div class="signature-form">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="signature_type" class="control-label">Signature Type:</label>
                                        <select id="signature_type" class="form-control">
                                            <option value="primary">Primary Provider</option>
                                            <option value="witness">Witness</option>
                                            <option value="reviewer">Reviewer</option>
                                            <option value="custom">Custom</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="signature_datetime" class="control-label">Date & Time:</label>
                                        <input type="datetime-local" id="signature_datetime" class="form-control" value="<?php echo date('Y-m-d') . 'T' . date('H:i'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="signature_text" class="control-label">Signature Text:</label>
                                        <input type="text" id="signature_text" class="form-control" placeholder="Enter signature text (optional)">
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-primary" onclick="addSignature()">
                                        <i class="fa fa-pencil"></i> Add Signature
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save and Cancel Buttons -->
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <button type="submit" class="btn btn-save" style="margin-right: 10px;">
                                <i class="fa fa-save"></i> Save
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelForm()">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Medication Template for Secondary/PRN Medications -->
    <template id="medication-template">
        <div class="medication-section secondary-medication" data-medication-id="">
            <div class="medication-header">
                <h4 class="medication-title">
                    <span class="medication-type-badge"></span>
                    <span class="medication-name">New Medication</span>
                </h4>
                <div class="medication-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editMedication(this)">
                        <i class="fa fa-edit"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteMedication(this)">
                        <i class="fa fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            
            <!-- Collapsible Content -->
            <div class="medication-content" style="display: none;">
                <!-- Medication Order Section -->
                <div class="medication-order-section">
                    <h4 class="subsection-title"><i class="fa fa-prescription"></i> Medication Order</h4>
                    
                    <!-- Search Inventory -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="control-label">Search Inventory:</label>
                                <div class="search-container">
                                    <input type="text" class="medication-search form-control" 
                                           placeholder="Enter medication name, NDC, or barcode...">
                                    <div class="search-suggestions"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Search Type:</label>
                                <select class="search-type form-control">
                                    <option value="name">Medication Name</option>
                                    <option value="ndc">NDC Code</option>
                                    <option value="barcode">Barcode</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Selected Drug Information -->
                    <div class="drug-info" style="display: none;">
                        <h5><i class="fa fa-check-circle"></i> Selected Drug Information</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Name:</strong><br>
                                <span class="drug-name-display"></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Strength:</strong><br>
                                <span class="drug-strength-display"></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Lot:</strong><br>
                                <span class="drug-lot-display"></span>
                            </div>
                            <div class="col-md-3">
                                <strong>NDC:</strong><br>
                                <span class="drug-ndc-display"></span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Expiration:</strong><br>
                                <span class="drug-expiration-display"></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Available:</strong><br>
                                <span class="drug-quantity-display"></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Form:</strong><br>
                                <span class="drug-form-display"></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Status:</strong><br>
                                <span class="drug-status-display"></span>
                            </div>
                        </div>
                        <input type="hidden" class="selected-drug-id" name="secondary_medications[][inventory_drug_id]">
                        <input type="hidden" class="selected-drug-lot" name="secondary_medications[][inventory_lot_number]">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Medication Name:</label>
                                <input type="text" class="order-medication form-control" name="secondary_medications[][order_medication]" 
                                       placeholder="Medication name">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Dose:</label>
                                <input type="text" class="order-dose form-control" name="secondary_medications[][order_dose]" 
                                       placeholder="Dose and unit">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Strength:</label>
                                <input type="text" class="order-strength form-control" name="secondary_medications[][order_strength]" 
                                       placeholder="Medication strength">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Route:</label>
                                <input type="text" class="order-route form-control" name="secondary_medications[][order_route]" 
                                       placeholder="Route of administration">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Lot Number:</label>
                                <input type="text" class="order-lot-number form-control" name="secondary_medications[][order_lot_number]" 
                                       placeholder="Lot number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">NDC:</label>
                                <input type="text" class="order-ndc form-control" name="secondary_medications[][order_ndc]" 
                                       placeholder="NDC code">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Expiration Date:</label>
                                <input type="date" class="order-expiration-date form-control" name="secondary_medications[][order_expiration_date]">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Every:</label>
                                <div class="input-group">
                                    <input type="text" class="order-every-value form-control" name="secondary_medications[][order_every_value]" 
                                           placeholder="Value">
                                    <select class="order-every-unit form-control" name="secondary_medications[][order_every_unit]">
                                        <option value="">Unit</option>
                                        <option value="hours">Hours</option>
                                        <option value="days">Days</option>
                                        <option value="weeks">Weeks</option>
                                        <option value="as needed">As Needed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Servicing Provider:</label>
                                <input type="text" class="order-servicing-provider form-control" name="secondary_medications[][order_servicing_provider]" 
                                       placeholder="Provider name">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">NPI:</label>
                                <input type="text" class="order-npi form-control" name="secondary_medications[][order_npi]" 
                                       placeholder="NPI number">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">End Date:</label>
                                <input type="date" class="order-end-date form-control" name="secondary_medications[][order_end_date]">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">Order Notes:</label>
                                <textarea class="order-note form-control" name="secondary_medications[][order_note]" rows="2" 
                                          placeholder="Additional notes about the medication order"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Administration Section -->
                <div class="administration-section">
                    <h4 class="subsection-title"><i class="fa fa-clock-o"></i> Administration</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Start Time:</label>
                                <input type="datetime-local" class="administration-start form-control" name="secondary_medications[][administration_start]" 
                                       onchange="calculateSecondaryDuration(this)" placeholder="Date and time">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">End Time:</label>
                                <input type="datetime-local" class="administration-end form-control" name="secondary_medications[][administration_end]" 
                                       onchange="calculateSecondaryDuration(this)" placeholder="Date and time">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Duration:</label>
                                <div class="input-group">
                                    <input type="text" class="administration-duration form-control" name="secondary_medications[][administration_duration]" 
                                           readonly placeholder="Calculated automatically">
                                    <div class="input-group-append">
                                        <span class="input-group-text">hours:minutes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">Quantity Used:</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="inventory-quantity-used form-control" 
                                           name="secondary_medications[][inventory_quantity_used]" placeholder="Enter quantity used">
                                    <div class="input-group-append">
                                        <span class="input-group-text" style="display: inline-block;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">Quantity Wasted:</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="inventory-wastage-quantity form-control" 
                                           name="secondary_medications[][inventory_wastage_quantity]" placeholder="Enter quantity wasted">
                                    <div class="input-group-append">
                                        <span class="input-group-text" style="display: inline-block;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">Wastage Reason:</label>
                                <select class="inventory-wastage-reason form-control" name="secondary_medications[][inventory_wastage_reason]">
                                    <option value="">Select reason</option>
                                    <?php foreach ($wastage_reasons as $reason): ?>
                                        <option value="<?php echo htmlspecialchars($reason['reason_code']); ?>">
                                            <?php echo htmlspecialchars($reason['reason_description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">Wastage Notes:</label>
                                <textarea class="inventory-wastage-notes form-control" name="secondary_medications[][inventory_wastage_notes]" rows="2" 
                                          placeholder="Additional notes about wastage"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">Administration Notes:</label>
                                <textarea class="administration-note form-control" name="secondary_medications[][administration_note]" rows="3" 
                                          placeholder="Notes about administration, patient response, complications..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        // Version for cache busting
        const SCRIPT_VERSION = '<?php echo time(); ?>';
        console.log('Script version:', SCRIPT_VERSION);
        
        // Multi-Medication Functionality
        let medicationCounter = 1;
        let medications = [];
        
        // Search functionality
        let searchTimeout;
        const searchInput = document.getElementById('medication-search');
        const searchSuggestions = document.getElementById('search-suggestions');
        const searchType = document.getElementById('search-type');
        let selectedIndex = -1;

        // ICD-10 search functionality
        let icd10SearchTimeout;
        const icd10SearchInput = document.getElementById('icd10-search');
        const icd10Suggestions = document.getElementById('icd10-suggestions');
        let selectedDiagnoses = [];
        let icd10SelectedIndex = -1;
        
        // Check if elements exist
        if (!icd10SearchInput) {
            console.error('icd10-search element not found');
        }
        if (!icd10Suggestions) {
            console.error('icd10-suggestions element not found');
        }

        // Medication search functionality
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchSuggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        searchInput.addEventListener('keydown', function(e) {
            const suggestions = searchSuggestions.querySelectorAll('.suggestion-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
                updateSelection();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0 && suggestions[selectedIndex]) {
                    selectDrug(suggestions[selectedIndex]);
                }
            } else if (e.key === 'Escape') {
                searchSuggestions.style.display = 'none';
                selectedIndex = -1;
            }
        });

        // ICD-10 search functionality
        if (icd10SearchInput) {
            icd10SearchInput.addEventListener('input', function() {
                clearTimeout(icd10SearchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    if (icd10Suggestions) {
                        icd10Suggestions.style.display = 'none';
                    }
                    return;
                }
                
                icd10SearchTimeout = setTimeout(() => {
                    performICD10Search(query);
                }, 300);
            });

            icd10SearchInput.addEventListener('keydown', function(e) {
                if (!icd10Suggestions) return;
                
                const suggestions = icd10Suggestions.querySelectorAll('.suggestion-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    icd10SelectedIndex = Math.min(icd10SelectedIndex + 1, suggestions.length - 1);
                    updateICD10Selection();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    icd10SelectedIndex = Math.max(icd10SelectedIndex - 1, -1);
                    updateICD10Selection();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (icd10SelectedIndex >= 0 && suggestions[icd10SelectedIndex]) {
                        selectICD10Diagnosis(suggestions[icd10SelectedIndex]);
                    }
                } else if (e.key === 'Escape') {
                    icd10Suggestions.style.display = 'none';
                    icd10SelectedIndex = -1;
                }
            });
        }

        function updateSelection() {
            const suggestions = searchSuggestions.querySelectorAll('.suggestion-item');
            suggestions.forEach((item, index) => {
                if (index === selectedIndex) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        }

        function updateICD10Selection() {
            const suggestions = icd10Suggestions.querySelectorAll('.suggestion-item');
            suggestions.forEach((item, index) => {
                if (index === icd10SelectedIndex) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        }

        function performSearch(query) {
            const type = searchType.value;
            const url = '/interface/modules/custom_modules/oe-module-inventory/get-drug-for-infusion.php?search=' + encodeURIComponent(query) + '&type=' + type;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.results) {
                        displaySuggestions(data.results);
                    } else {
                        displaySuggestions([]);
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    searchSuggestions.style.display = 'none';
                });
        }

        function performICD10Search(query) {
            console.log('Performing ICD-10 search for:', query);
            const url = '/interface/modules/custom_modules/oe-module-inventory/search-icd10.php?search=' + encodeURIComponent(query);
            console.log('Search URL:', url);
            
            fetch(url)
                .then(response => {
                    console.log('ICD-10 search response status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('ICD-10 search response data:', data);
                    if (data.success && data.results) {
                        displayICD10Suggestions(data.results);
                    } else {
                        console.log('No results or error in response');
                        displayICD10Suggestions([]);
                    }
                })
                .catch(error => {
                    console.error('ICD-10 search error:', error);
                    icd10Suggestions.style.display = 'none';
                    // Show error to user
                    if (icd10Suggestions) {
                        icd10Suggestions.innerHTML = '<div class="suggestion-item" style="color: red;">Error loading diagnoses. Please try again.</div>';
                        icd10Suggestions.style.display = 'block';
                    }
                });
        }

        function searchICD10() {
            const query = icd10SearchInput.value.trim();
            if (query.length >= 2) {
                performICD10Search(query);
            }
        }

        function displaySuggestions(drugs) {
            searchSuggestions.innerHTML = '';
            
            if (drugs.length === 0) {
                searchSuggestions.style.display = 'none';
                return;
            }
            
            drugs.forEach(drug => {
                // Skip out-of-stock drugs
                if (drug.quantity <= 0) {
                    return; // do not show in suggestions
                }
                const item = document.createElement('div');
                item.className = 'suggestion-item';
                item.innerHTML = '<strong>' + drug.name + '</strong><br><small>' + drug.form + ' | Lot: ' + (drug.lot_number || 'N/A') + ' | Available: ' + drug.quantity + ' ' + drug.quantity_unit + ' | Exp: ' + (drug.expiration_date || 'N/A') + '</small>';
                item.addEventListener('click', () => selectDrug(item, drug));
                searchSuggestions.appendChild(item);
            });
            
            searchSuggestions.style.display = 'block';
            selectedIndex = -1;
        }

        function displayICD10Suggestions(diagnoses) {
            console.log('Displaying ICD-10 suggestions:', diagnoses);
            
            if (!icd10Suggestions) {
                console.error('icd10Suggestions element not found');
                return;
            }
            
            icd10Suggestions.innerHTML = '';
            
            if (!diagnoses || diagnoses.length === 0) {
                icd10Suggestions.style.display = 'none';
                return;
            }
            
            diagnoses.forEach(diagnosis => {
                const item = document.createElement('div');
                item.className = 'suggestion-item';
                item.innerHTML = '<strong>' + (diagnosis.code || 'N/A') + '</strong><br><small>' + (diagnosis.description || 'No description') + '</small>';
                item.addEventListener('click', () => selectICD10Diagnosis(item, diagnosis));
                icd10Suggestions.appendChild(item);
            });
            
            icd10Suggestions.style.display = 'block';
            icd10SelectedIndex = -1;
        }

        function selectDrug(item, drug) {
            console.log('Selected drug data:', drug);
            
            // Auto-populate form fields
            document.getElementById('order_medication').value = drug.name;
            
            // Use dose field for dose, size only for strength
            const doseValue = drug.dose || '';
            const unitValue = drug.unit || '';
            const combinedDose = doseValue && unitValue ? doseValue + ' ' + unitValue : doseValue || unitValue;
            console.log('Dose value:', combinedDose);
            document.getElementById('order_dose').value = combinedDose;
            
            document.getElementById('order_route').value = 'IV'; // Default route for infusion
            
            // Use size only for strength (concentration percentage)
            const strengthValue = drug.size || '';
            console.log('Strength value:', strengthValue);
            document.getElementById('order_strength').value = strengthValue;
            
            document.getElementById('order_lot_number').value = drug.lot_number || '';
            document.getElementById('order_ndc').value = drug.ndc_11 || drug.ndc_10 || '';
            document.getElementById("order_expiration_date").value = oeFormatShortDate_js(drug.expiration_date || "");
            
            // Set hidden fields
            document.getElementById('selected-drug-id').value = drug.drug_id;
            document.getElementById('selected-drug-lot').value = drug.lot_number || '';
            
            // Display selected drug info
            document.getElementById('drug-name-display').textContent = drug.name;
            document.getElementById('drug-strength-display').textContent = strengthValue || 'N/A';
            document.getElementById('drug-lot-display').textContent = drug.lot_number || 'N/A';
            document.getElementById('drug-ndc-display').textContent = drug.ndc_11 || drug.ndc_10 || 'N/A';
            document.getElementById('drug-expiration-display').textContent = drug.expiration_date || 'N/A';
            document.getElementById('drug-quantity-display').textContent = drug.quantity + ' ' + drug.quantity_unit;
            document.getElementById('drug-form-display').textContent = drug.form || 'N/A';
            document.getElementById('drug-status-display').textContent = getStatusText(drug);
            
            // Update unit text based on selected drug
            updateQuantityUnits(drug.unit || 'mg');
            
            document.getElementById('selected-drug-info').style.display = 'block';
            searchSuggestions.style.display = 'none';
            searchInput.value = drug.name;
        }

        function selectICD10Diagnosis(item, diagnosis) {
            // Check if diagnosis is already selected
            const existingIndex = selectedDiagnoses.findIndex(d => d.code === diagnosis.code);
            if (existingIndex === -1) {
                selectedDiagnoses.push(diagnosis);
                updateDiagnosesList();
            }
            
            icd10Suggestions.style.display = 'none';
            icd10SearchInput.value = '';
        }



        function removeDiagnosis(index) {
            selectedDiagnoses.splice(index, 1);
            updateDiagnosesList();
        }

        function getStatusText(drug) {
            if (drug.quantity <= 0) return 'Out of Stock';
            if (drug.expiration_date && new Date(drug.expiration_date) < new Date()) return 'Expired';
            if (drug.expiration_date && new Date(drug.expiration_date) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)) return 'Expiring Soon';
            return 'Available';
        }

        function updateQuantityUnits(unit) {
            // Update unit text in both quantity fields
            const quantityUsedUnit = document.querySelector('#inventory_quantity_used').nextElementSibling.querySelector('.input-group-text');
            const quantityWastedUnit = document.querySelector('#inventory_wastage_quantity').nextElementSibling.querySelector('.input-group-text');
            
            if (quantityUsedUnit) {
                quantityUsedUnit.textContent = unit;
            }
            if (quantityWastedUnit) {
                quantityWastedUnit.textContent = unit;
            }
            
            console.log('Updated quantity units to:', unit);
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
                selectedIndex = -1;
            }
            if (!icd10SearchInput.contains(e.target) && !icd10Suggestions.contains(e.target)) {
                icd10Suggestions.style.display = 'none';
                icd10SelectedIndex = -1;
            }
        });

        // Handle multi-select diagnosis functionality
        function updateDiagnosesList() {
            const diagnosesList = document.getElementById('diagnoses-list');
            const selectedDiagnosesDiv = document.getElementById('selected-diagnoses');
            const diagnosesCodesInput = document.getElementById('diagnoses_codes');
            
            if (selectedDiagnoses.length === 0) {
                selectedDiagnosesDiv.style.display = 'none';
                diagnosesCodesInput.value = '';
                return;
            }
            
            selectedDiagnosesDiv.style.display = 'block';
            diagnosesList.innerHTML = '';
            
            // Build the codes string for the hidden input
            const codesArray = selectedDiagnoses.map(d => d.code + ' - ' + d.description);
            diagnosesCodesInput.value = codesArray.join('|');
            
            selectedDiagnoses.forEach((diagnosis, index) => {
                const diagnosisDiv = document.createElement('div');
                diagnosisDiv.className = 'row';
                diagnosisDiv.style.marginBottom = '10px';
                diagnosisDiv.innerHTML = '<div class="col-md-8"><strong>' + diagnosis.code + '</strong> - ' + diagnosis.description + '</div><div class="col-md-4"><button type="button" class="btn btn-sm btn-danger" onclick="removeDiagnosis(' + index + ')"><i class="fa fa-times"></i> Remove</button></div>';
                diagnosesList.appendChild(diagnosisDiv);
            });
        }
        
        function removeDiagnosis(index) {
            selectedDiagnoses.splice(index, 1);
            updateDiagnosesList();
        }
        
        // Initialize form when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize diagnoses from saved data
            const savedDiagnoses = document.getElementById('diagnoses_codes').value;
            if (savedDiagnoses) {
                const codesArray = savedDiagnoses.split('|');
                codesArray.forEach(codeDesc => {
                    if (codeDesc.trim()) {
                        const parts = codeDesc.split(' - ');
                        if (parts.length >= 2) {
                            selectedDiagnoses.push({
                                code: parts[0].trim(),
                                description: parts.slice(1).join(' - ').trim()
                            });
                        }
                    }
                });
                updateDiagnosesList();
            }
            
            // Load previous diagnoses and medication only for new forms
            // Only load previous diagnoses for new forms
            const formIdInput = document.querySelector("input[name=\"id\"]");
            if (!formIdInput) {
                loadPreviousDiagnoses();
            }
            loadPreviousMedication();
            
            // Load existing secondary medications for saved forms
            if (formIdInput) {
                loadExistingSecondaryMedications();
            }
            
            // Calculate duration if start and end times are already filled
            const startInput = document.getElementById('administration_start');
            const endInput = document.getElementById('administration_end');
            if (startInput && endInput && startInput.value && endInput.value) {
                calculateDuration();
            }
            
            // Handle form submission
            document.getElementById('enhanced-infusion-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                console.log('Form submission started');
// Convert dates from display format to database format
                const endDateInput = document.getElementById("order_end_date");
                const expirationDateInput = document.getElementById("order_expiration_date");
                
                if (endDateInput && endDateInput.value) {
                    endDateInput.value = DateToYYYYMMDD_js(endDateInput.value);
                }
                if (expirationDateInput && expirationDateInput.value) {
                    expirationDateInput.value = DateToYYYYMMDD_js(expirationDateInput.value);
                if (document.getElementById("iv_access_date") && document.getElementById("iv_access_date").value) {
                    // Removed DateToYYYYMMDD_js for datetime-local input
                }
                if (document.getElementById("administration_start") && document.getElementById("administration_start").value) {
                    // Removed DateToYYYYMMDD_js for datetime-local input
                }
                if (document.getElementById("administration_end") && document.getElementById("administration_end").value) {
                    // Removed DateToYYYYMMDD_js for datetime-local input
                }
                }
                
                // Show loading state
                const submitBtn = document.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
                
                // Log form data
                const formData = new FormData(this);
                console.log('Form data:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                // Submit form via AJAX
                console.log('Submitting to save_enhanced.php');
                fetch('save_enhanced.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        // Show success message
                        const successDiv = document.createElement('div');
                        successDiv.className = 'alert alert-success';
                        successDiv.style.marginBottom = '20px';
                        successDiv.innerHTML = '<i class="fa fa-check-circle"></i> ' + data.message;
                        
                        // Insert at the top of the form
                        const form = document.getElementById('enhanced-infusion-form');
                        form.parentNode.insertBefore(successDiv, form);
                        
                        // Update form ID if it's a new form
                        if (data.form_id) {
                            const idInput = document.querySelector('input[name="id"]');
                            if (!idInput) {
                                const newIdInput = document.createElement('input');
                                newIdInput.type = 'hidden';
                                newIdInput.name = 'id';
                                newIdInput.value = data.form_id;
                                form.appendChild(newIdInput);
                            } else {
                                idInput.value = data.form_id;
                            }
                        }
                        
                        // Scroll to top to show success message
                        window.scrollTo(0, 0);
                        
                        // Show success message for 2 seconds, then close the form
                        setTimeout(() => {
                            // Close the form window
                            if (window.opener) {
                                // If opened from another window, close this window
                                window.close();
                            } else {
                                // If not opened from another window, redirect to encounter
                                window.location.href = '<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/encounter/encounter_top.php?set_encounter=<?php echo $encounter; ?>';
                            }
                        }, 2000);
                    } else {
                        // Show error message
                        console.error('Save failed:', data.message);
                        alert('Error saving form: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving form. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        });

        // Multi-Medication Management Functions
        function addMedication() {
            medicationCounter++;
            
            // Clone template
            const template = document.getElementById('medication-template');
            const newMedication = template.content.cloneNode(true);
            
            // Configure new medication
            const medicationSection = newMedication.querySelector('.medication-section');
            medicationSection.id = 'medication-' + medicationCounter;
            medicationSection.dataset.medicationId = medicationCounter;
            
            // Set medication type
            const typeBadge = medicationSection.querySelector('.medication-type-badge');
            typeBadge.textContent = medicationCounter === 2 ? 'Secondary' : 'PRN';
            typeBadge.className = 'medication-type-badge badge ' + (medicationCounter === 2 ? 'badge-info' : 'badge-warning');
            
            // Update field names with medication ID
            updateFieldNames(medicationSection, medicationCounter);
            
            // Add to container
            document.getElementById('secondary-medications-container').appendChild(newMedication);
            
            // Initialize functionality
            initializeMedicationSection(medicationSection);
            
            // Store reference
            medications.push({
                id: medicationCounter,
                type: medicationCounter === 2 ? 'secondary' : 'prn',
                element: medicationSection
            });
            
            console.log('Added medication:', medicationCounter, 'Type:', medicationCounter === 2 ? 'secondary' : 'prn');
        }

        function updateFieldNames(medicationSection, medicationId) {
            const inputs = medicationSection.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.name) {
                    // Update array index for secondary medications
                    const nameMatch = input.name.match(/secondary_medications\[\](\[.*\])?/);
                    if (nameMatch) {
                        input.name = input.name.replace('[]', '[' + (medicationId - 2) + '][]');
                    }
                }
                if (input.id) {
                    input.id = input.id + '_' + medicationId;
                }
            });
        }

        function initializeMedicationSection(medicationSection) {
            // Initialize search functionality for this medication
            const searchInput = medicationSection.querySelector('.medication-search');
            const searchSuggestions = medicationSection.querySelector('.search-suggestions');
            const searchType = medicationSection.querySelector('.search-type');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();
                    
                    if (query.length < 2) {
                        searchSuggestions.style.display = 'none';
                        return;
                    }
                    
                    searchTimeout = setTimeout(() => {
                        performMedicationSearch(query, searchType.value, searchSuggestions, searchInput, medicationSection);
                    }, 300);
                });
            }
            
            // Initialize duration calculation
            const startInput = medicationSection.querySelector('.administration-start');
            const endInput = medicationSection.querySelector('.administration-end');
            const durationInput = medicationSection.querySelector('.administration-duration');
            
            if (startInput && endInput && durationInput) {
                startInput.addEventListener('change', () => calculateSecondaryDuration(medicationSection));
                endInput.addEventListener('change', () => calculateSecondaryDuration(medicationSection));
            }
        }

        function editMedication(button) {
            const medicationSection = button.closest('.medication-section');
            const content = medicationSection.querySelector('.medication-content');
            const isExpanded = content.style.display !== 'none';
            
            if (isExpanded) {
                content.style.display = 'none';
                button.innerHTML = '<i class="fa fa-edit"></i> Edit';
            } else {
                content.style.display = 'block';
                button.innerHTML = '<i class="fa fa-compress"></i> Collapse';
            }
        }

        function deleteMedication(button) {
            if (confirm('Are you sure you want to delete this medication?')) {
                const medicationSection = button.closest('.medication-section');
                const medicationId = parseInt(medicationSection.dataset.medicationId);
                
                // Remove from DOM
                medicationSection.remove();
                
                // Remove from medications array
                medications = medications.filter(m => m.id !== medicationId);
                
                // Reorder remaining medications
                reorderMedications();
                
                console.log('Deleted medication:', medicationId);
            }
        }

        function reorderMedications() {
            const secondaryContainer = document.getElementById('secondary-medications-container');
            const medicationSections = secondaryContainer.querySelectorAll('.medication-section');
            
            medicationSections.forEach((section, index) => {
                const newId = index + 2; // Start from 2 (secondary)
                section.dataset.medicationId = newId;
                section.id = 'medication-' + newId;
                
                // Update type badge
                const typeBadge = section.querySelector('.medication-type-badge');
                typeBadge.textContent = newId === 2 ? 'Secondary' : 'PRN';
                typeBadge.className = 'medication-type-badge badge ' + (newId === 2 ? 'badge-info' : 'badge-warning');
                
                // Update field names
                updateFieldNames(section, newId);
            });
            
            // Update medications array
            medications = medications.map((med, index) => ({
                ...med,
                id: index + 2,
                type: index === 0 ? 'secondary' : 'prn'
            }));
        }

        function calculateSecondaryDuration(medicationSection) {
            const startInput = medicationSection.querySelector('.administration-start');
            const endInput = medicationSection.querySelector('.administration-end');
            const durationInput = medicationSection.querySelector('.administration-duration');

            if (!startInput || !endInput || !durationInput) {
                console.error('Inputs for secondary duration calculation not found.');
                return;
            }

            const startDateTime = new Date(startInput.value);
            const endDateTime = new Date(endInput.value);

            if (isNaN(startDateTime.getTime()) || isNaN(endDateTime.getTime())) {
                durationInput.value = '';
                return;
            }

            const diffInMilliseconds = endDateTime.getTime() - startDateTime.getTime();
            const diffInMinutes = Math.floor(diffInMilliseconds / (1000 * 60));
            const hours = Math.floor(diffInMinutes / 60);
            const minutes = diffInMinutes % 60;

            durationInput.value = hours + ':' + minutes.toString().padStart(2, '0');
        }

        function performMedicationSearch(query, searchType, suggestionsContainer, searchInput, medicationSection) {
            // Use the same search logic as the primary medication
            fetch('search_inventory.php?query=' + encodeURIComponent(query) + '&type=' + encodeURIComponent(searchType))
                .then(response => response.json())
                .then(data => {
                    suggestionsContainer.innerHTML = '';
                    
                    if (data.success && data.results && data.results.length > 0) {
                        data.results.forEach((drug, index) => {
                            const item = document.createElement('div');
                            item.className = 'suggestion-item';
                            item.textContent = (drug.name + ' - ' + (drug.strength || '') + ' (' + (drug.lot_number || '') + ')');
                            item.dataset.drug = JSON.stringify(drug);
                            
                            item.addEventListener('click', () => {
                                selectSecondaryDrug(drug, medicationSection);
                                suggestionsContainer.style.display = 'none';
                            });
                            
                            suggestionsContainer.appendChild(item);
                        });
                        
                        suggestionsContainer.style.display = 'block';
                    } else {
                        suggestionsContainer.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error searching inventory:', error);
                    suggestionsContainer.style.display = 'none';
                });
        }

        function selectSecondaryDrug(drug, medicationSection) {
            // Populate the medication fields
            medicationSection.querySelector('.order-medication').value = drug.name;
            medicationSection.querySelector('.order-strength').value = drug.strength;
            medicationSection.querySelector('.order-lot-number').value = drug.lot_number;
            medicationSection.querySelector('.order-ndc').value = drug.ndc;
            medicationSection.querySelector('.order-expiration-date').value = drug.expiration_date;
            medicationSection.querySelector('.selected-drug-id').value = drug.id;
            medicationSection.querySelector('.selected-drug-lot').value = drug.lot_number;
            
            // Show drug info
            const drugInfo = medicationSection.querySelector('.drug-info');
            medicationSection.querySelector('.drug-name-display').textContent = drug.name;
            medicationSection.querySelector('.drug-strength-display').textContent = drug.strength;
            medicationSection.querySelector('.drug-lot-display').textContent = drug.lot_number;
            medicationSection.querySelector('.drug-ndc-display').textContent = drug.ndc;
            medicationSection.querySelector('.drug-expiration-display').textContent = drug.expiration_date;
            medicationSection.querySelector('.drug-quantity-display').textContent = drug.quantity;
            medicationSection.querySelector('.drug-form-display').textContent = drug.form;
            medicationSection.querySelector('.drug-status-display').textContent = drug.status;
            
            drugInfo.style.display = 'block';
            
            console.log('Selected secondary drug:', drug);
        }

        function loadExistingSecondaryMedications() {
            const secondaryMedications = <?php echo json_encode($secondary_medications ?? []); ?>;
            
            console.log('Loading existing secondary medications:', secondaryMedications);
            
            secondaryMedications.forEach((medication, index) => {
                // Skip primary medication (order 1)
                if (medication.medication_order <= 1) {
                    return;
                }
                
                // Add medication using the existing function
                addMedication();
                
                // Get the newly added medication section
                const medicationSection = document.querySelector('[data-medication-id="' + medication.medication_order + '"]');
                if (!medicationSection) {
                    console.error('Medication section not found for order:', medication.medication_order);
                    return;
                }
                
                // Populate the fields
                populateSecondaryMedicationFields(medicationSection, medication);
                
                console.log('Loaded secondary medication:', medication.medication_order, medication.order_medication);
            });
        }

        function populateSecondaryMedicationFields(medicationSection, medicationData) {
            // Populate medication order fields
            medicationSection.querySelector('.order-medication').value = medicationData.order_medication || '';
            medicationSection.querySelector('.order-dose').value = medicationData.order_dose || '';
            medicationSection.querySelector('.order-strength').value = medicationData.order_strength || '';
            medicationSection.querySelector('.order-lot-number').value = medicationData.order_lot_number || '';
            medicationSection.querySelector('.order-ndc').value = medicationData.order_ndc || '';
            medicationSection.querySelector('.order-expiration-date').value = medicationData.order_expiration_date || '';
            medicationSection.querySelector('.order-every-value').value = medicationData.order_every_value || '';
            medicationSection.querySelector('.order-every-unit').value = medicationData.order_every_unit || '';
            medicationSection.querySelector('.order-servicing-provider').value = medicationData.order_servicing_provider || '';
            medicationSection.querySelector('.order-npi').value = medicationData.order_npi || '';
            medicationSection.querySelector('.order-end-date').value = medicationData.order_end_date || '';
            medicationSection.querySelector('.order-note').value = medicationData.order_note || '';
            medicationSection.querySelector('.selected-drug-id').value = medicationData.inventory_drug_id || '';
            medicationSection.querySelector('.selected-drug-lot').value = medicationData.inventory_lot_number || '';
            
            // Populate administration fields
            medicationSection.querySelector('.administration-start').value = medicationData.administration_start || '';
            medicationSection.querySelector('.administration-end').value = medicationData.administration_end || '';
            medicationSection.querySelector('.administration-duration').value = medicationData.administration_duration || '';
            medicationSection.querySelector('.inventory-quantity-used').value = medicationData.inventory_quantity_used || '';
            medicationSection.querySelector('.inventory-wastage-quantity').value = medicationData.inventory_wastage_quantity || '';
            medicationSection.querySelector('.inventory-wastage-reason').value = medicationData.inventory_wastage_reason || '';
            medicationSection.querySelector('.inventory-wastage-notes').value = medicationData.inventory_wastage_notes || '';
            medicationSection.querySelector('.administration-note').value = medicationData.administration_note || '';
            
            // Show drug info if inventory data exists
            if (medicationData.inventory_drug_id) {
                const drugInfo = medicationSection.querySelector('.drug-info');
                medicationSection.querySelector('.drug-name-display').textContent = medicationData.order_medication || '';
                medicationSection.querySelector('.drug-strength-display').textContent = medicationData.order_strength || '';
                medicationSection.querySelector('.drug-lot-display').textContent = medicationData.order_lot_number || '';
                medicationSection.querySelector('.drug-ndc-display').textContent = medicationData.order_ndc || '';
                medicationSection.querySelector('.drug-expiration-display').textContent = medicationData.order_expiration_date || '';
                drugInfo.style.display = 'block';
            }
        }

        function loadPreviousDiagnoses() {
            const pid = <?php echo $pid; ?>;
            const currentEncounter = <?php echo $encounter; ?>;
            
            console.log('Loading previous diagnoses for PID:', pid, 'Current Encounter:', currentEncounter);
            
            // Fetch diagnoses from previous encounters using simple API
            fetch('get_previous_diagnoses_simple.php?pid=' + pid + '&current_encounter=' + currentEncounter)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Previous diagnoses response:', data);
                    if (data.success && data.diagnoses && data.diagnoses.length > 0) {
                        console.log('Found', data.diagnoses.length, 'previous diagnoses');
                        // Add previous diagnoses to the list
                        data.diagnoses.forEach(diagnosis => {
                            const existingIndex = selectedDiagnoses.findIndex(d => d.code === diagnosis.code);
                            if (existingIndex === -1) {
                                selectedDiagnoses.push(diagnosis);
                                console.log('Added diagnosis:', diagnosis);
                            }
                        });
                        console.log('Updated selectedDiagnoses:', selectedDiagnoses);
                        updateDiagnosesList();
                        
                        // Show notification
                        showDiagnosesNotification(data.diagnoses.length);
                    } else {
                        console.log('No previous diagnoses found or API returned error');
                    }
                })
                .catch(error => {
                    console.error('Error loading previous diagnoses:', error);
                });
        }

        function showDiagnosesNotification(count) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'alert alert-info';
            notification.style.marginBottom = '15px';
            notification.innerHTML = '<i class="fa fa-info-circle"></i> <strong>Previous Diagnoses Loaded:</strong> ' + count + ' diagnosis(es) from previous encounters have been added to this form. <button type="button" class="close" onclick="this.parentElement.remove()" style="float: right; background: none; border: none; font-size: 18px;">&times;</button>';
            
            // Insert before the ICD-10 Diagnosis section
            const diagnosisSection = document.querySelector('.form-section');
            if (diagnosisSection) {
                diagnosisSection.parentNode.insertBefore(notification, diagnosisSection);
            }
        }

        function cancelForm() {
            if (window.opener) {
                window.close();
            } else {
                window.location.href = '<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/encounter/encounter_top.php?set_encounter=<?php echo $encounter; ?>';
            }
        }

        function calculateDuration() {
            const startInput = document.getElementById('administration_start');
            const endInput = document.getElementById('administration_end');
            const durationInput = document.getElementById('administration_duration');

            if (!startInput || !endInput || !durationInput) {
                console.error('Inputs for duration calculation not found.');
                return;
            }

            const startDateTime = new Date(startInput.value);
            const endDateTime = new Date(endInput.value);

            if (isNaN(startDateTime.getTime()) || isNaN(endDateTime.getTime())) {
                durationInput.value = ''; // Clear duration if dates are invalid
                return;
            }

            const diffInMilliseconds = endDateTime.getTime() - startDateTime.getTime();
            const diffInMinutes = Math.floor(diffInMilliseconds / (1000 * 60));
            const hours = Math.floor(diffInMinutes / 60);
            const minutes = diffInMinutes % 60;

            durationInput.value = hours + ':' + minutes.toString().padStart(2, '0');
        }

        function loadPreviousMedication() {
            const pid = <?php echo $pid; ?>;
            const currentEncounter = <?php echo $encounter; ?>;
            
            console.log('Loading previous medication orders for PID:', pid, 'Current Encounter:', currentEncounter);
            
            // Fetch medication orders from previous encounters
            fetch('get_previous_medication.php?pid=' + pid + '&current_encounter=' + currentEncounter)
                .then(response => {
                    console.log('Medication response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Previous medication response:', data);
                    if (data.success && data.medications && data.medications.length > 0) {
                        console.log('Found', data.medications.length, 'previous medication orders');
                        
                        // Get the most recent medication order (first in the array)
                        const latestMedication = data.medications[0];
                        console.log('Latest medication:', latestMedication);
                        
                        // Populate the form fields with the previous medication data
                        populateMedicationFields(latestMedication);
                        
                        // Show notification
                        showMedicationNotification(latestMedication.medication);
                    } else {
                        console.log('No previous medication orders found');
                    }
                })
                .catch(error => {
                    console.error('Error loading previous medication orders:', error);
                });
        }
        
        function populateMedicationFields(medication) {
            // Populate medication order fields
            if (medication.medication) {
                document.getElementById('order_medication').value = medication.medication;
            }
            if (medication.dose) {
                document.getElementById('order_dose').value = medication.dose;
            }
            if (medication.lot_number) {
                document.getElementById('order_lot_number').value = medication.lot_number;
            }
            if (medication.ndc) {
                document.getElementById('order_ndc').value = medication.ndc;
            }
            if (medication.expiration_date) {
                document.getElementById("order_expiration_date").value = oeFormatShortDate_js(medication.expiration_date);
            }
            if (medication.frequency_value) {
                document.getElementById('order_every_value').value = medication.frequency_value;
            }
            if (medication.frequency_unit) {
                document.getElementById('order_every_unit').value = medication.frequency_unit;
            }
            if (medication.provider) {
                document.getElementById('order_servicing_provider').value = medication.provider;
            }
            if (medication.npi) {
                document.getElementById('order_npi').value = medication.npi;
            }
            if (medication.end_date) {
                document.getElementById("order_end_date").value = oeFormatShortDate_js(medication.end_date);
            }
            if (medication.note) {
                document.getElementById('order_note').value = medication.note;
            }
            
            console.log('Medication fields populated with data from encounter:', medication.encounter);
        }
        
        function showMedicationNotification(medicationName) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'alert alert-info alert-dismissible fade show';
            notification.innerHTML = '<strong>Previous Medication Loaded!</strong> Medication order for "' + medicationName + '" has been loaded from the previous encounter. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            
            // Insert at the top of the form
            const form = document.querySelector('form');
            if (form) {
                form.insertBefore(notification, form.firstChild);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            }
        }
        
        // Date formatting function
        function oeFormatShortDate_js(dateString) {
            if (!dateString) return '';
            
            // If it's already in YYYY-MM-DD format, convert to display format
            if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                const date = new Date(dateString + 'T00:00:00');
                return date.toLocaleDateString('en-US', {
                    month: '2-digit',
                    day: '2-digit',
                    year: 'numeric'
                });
            }
            
            // If it's already in display format, return as is
            if (dateString.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                return dateString;
            }
            
            // Try to parse and format
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return dateString; // Return original if can't parse
            }
            
            return date.toLocaleDateString('en-US', {
                month: '2-digit',
                day: '2-digit',
                year: 'numeric'
            });
        }
        
        // Signature Management Functions
        let currentFormId = <?php echo $form_id ?: 'null'; ?>;
        
        // Load existing signatures when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (currentFormId) {
                loadExistingSignatures();
            }
        });
        
        function loadExistingSignatures() {
            if (!currentFormId) return;
            
            fetch('get_signatures.php?form_id=' + currentFormId + '&site=default', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySignatures(data.signatures);
                    } else {
                        console.error('Error loading signatures:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading signatures:', error);
                });
        }
        
        function displaySignatures(signatures) {
            const container = document.getElementById('existing-signatures');
            
            if (signatures.length === 0) {
                container.innerHTML = '<div class="row"><div class="col-md-12"><div class="alert alert-info"><i class="fa fa-info-circle"></i> No signatures yet. Add the first signature below.</div></div></div>';
                return;
            }
            
            let tableHtml = '<table class="signature-table"><thead><tr><th>User</th><th>Type</th><th>Date & Time</th><th>Signature Text</th><th>Actions</th></tr></thead><tbody>';
            
            signatures.forEach(signature => {
                const dateTime = new Date(signature.signature_date).toLocaleString();
                const typeBadge = '<span class="signature-type-badge signature-type-' + signature.signature_type + '">' + signature.type_display_name + '</span>';
                
                let actionsHtml = '';
                if (signature.can_edit) {
                    actionsHtml = '<div class="signature-actions"><button class="btn btn-sm btn-warning" onclick="editSignature(' + signature.id + ')"><i class="fa fa-edit"></i> Edit</button><button class="btn btn-sm btn-danger" onclick="deleteSignature(' + signature.id + ')"><i class="fa fa-trash"></i> Delete</button></div>';
                }
                
                tableHtml += '<tr><td>' + signature.user_name + '</td><td>' + typeBadge + '</td><td>' + dateTime + '</td><td>' + signature.signature_text + '</td><td>' + actionsHtml + '</td></tr>';
            });
            
            tableHtml += '</tbody></table>';
            container.innerHTML = tableHtml;
        }
        
        function addSignature() {
            const signatureType = document.getElementById('signature_type').value;
            const signatureDateTime = document.getElementById('signature_datetime').value;
            const signatureText = document.getElementById('signature_text').value.trim();
            
            // Signature text is now optional - if empty, it will use the user's name
            
            if (!currentFormId) {
                alert('Form must be saved before adding signatures');
                return;
            }
            
            const formData = new FormData();
            formData.append('form_id', currentFormId);
            formData.append('signature_type', signatureType);
            formData.append('signature_date', signatureDateTime);
            formData.append('signature_text', signatureText);
            
            fetch('save_signature.php?site=default', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear the form
                    document.getElementById('signature_text').value = '';
                    document.getElementById('signature_datetime').value = new Date().toISOString().slice(0, 16);
                    
                    // Reload signatures
                    loadExistingSignatures();
                    
                    // Show success message
                    showNotification('Signature added successfully!', 'success');
                } else {
                    showNotification('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error adding signature:', error);
                showNotification('Error adding signature', 'danger');
            });
        }
        
        function deleteSignature(signatureId) {
            if (!confirm('Are you sure you want to delete this signature?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('signature_id', signatureId);
            
            fetch('delete_signature.php?site=default', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadExistingSignatures();
                    showNotification('Signature deleted successfully!', 'success');
                } else {
                    showNotification('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error deleting signature:', error);
                showNotification('Error deleting signature', 'danger');
            });
        }
        
        function editSignature(signatureId) {
            // For now, we'll use a simple prompt
            // In a full implementation, you might want a modal dialog
            const newText = prompt('Enter new signature text:');
            if (newText === null) return;
            
            const newDateTime = prompt('Enter new date and time (YYYY-MM-DD HH:MM:SS):', new Date().toISOString().slice(0, 19).replace('T', ' '));
            if (newDateTime === null) return;
            
            const formData = new FormData();
            formData.append('signature_id', signatureId);
            formData.append('signature_text', newText);
            formData.append('signature_date', newDateTime);
            
            fetch('update_signature.php?site=default', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadExistingSignatures();
                    showNotification('Signature updated successfully!', 'success');
                } else {
                    showNotification('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error updating signature:', error);
                showNotification('Error updating signature', 'danger');
            });
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = 'alert alert-' + type + ' alert-dismissible fade show';
            notification.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            
            // Insert at the top of the form
            const form = document.querySelector('form');
            if (form) {
                form.insertBefore(notification, form.firstChild);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            }
        }
    </script>
</body>
</html> 