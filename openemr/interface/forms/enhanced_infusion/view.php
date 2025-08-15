<?php
/**
 * Enhanced Infusion Form View
 * 
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Initialize OpenEMR
require_once(dirname(__FILE__) . "/../../globals.php");

// Get parameters - try GET parameters first, then session/globals
$id_param = $_GET['id'] ?? '';  // This could be either forms.id OR form_enhanced_infusion_injection.id
$pid = $_GET['pid'] ?? $GLOBALS['pid'] ?? '';
$encounter = $_GET['encounter'] ?? $GLOBALS['encounter'] ?? '';

// Determine if the ID is a forms.id or form_enhanced_infusion_injection.id
$form_id = '';
if (!empty($id_param)) {
    // First try: assume it's a forms.id
    $forms_query = sqlQuery("SELECT form_id, pid, encounter FROM forms WHERE id = ? AND formdir = 'enhanced_infusion'", [$id_param]);
    if ($forms_query) {
        // It's a forms.id - get the actual form_id
        $form_id = $forms_query['form_id'];
        if (empty($pid)) {
            $pid = $forms_query['pid'];
        }
        if (empty($encounter)) {
            $encounter = $forms_query['encounter'];
        }
        error_log("=== DEBUG VIEW: Received forms.id = $id_param, mapped to form_id = $form_id");
    } else {
        // Second try: assume it's already a form_enhanced_infusion_injection.id
        $form_query = sqlQuery("SELECT pid, encounter FROM form_enhanced_infusion_injection WHERE id = ?", [$id_param]);
        if ($form_query) {
            $form_id = $id_param;  // It's already the form data ID
            if (empty($pid)) {
                $pid = $form_query['pid'];
            }
            if (empty($encounter)) {
                $encounter = $form_query['encounter'];
            }
            error_log("=== DEBUG VIEW: Received form_enhanced_infusion_injection.id = $id_param directly");
        }
    }
}

// If we still don't have pid/encounter, try the form data table
if ((empty($pid) || empty($encounter)) && !empty($form_id)) {
    $form_query = sqlQuery("SELECT pid, encounter FROM form_enhanced_infusion_injection WHERE id = ?", [$form_id]);
    if ($form_query) {
        $pid = $form_query['pid'];
        $encounter = $form_query['encounter'];
    }
}

// Debug logging
error_log("=== DEBUG VIEW: id_param = $id_param, form_id = $form_id, pid = $pid, encounter = $encounter");

// Redirect to the custom module view with proper parameters
$redirect_url = $GLOBALS['web_root'] . "/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php?pid=" . urlencode($pid) . "&encounter=" . urlencode($encounter) . "&id=" . urlencode($form_id);

// Use JavaScript redirect to maintain session
echo "<script>window.location.href = '" . addslashes($redirect_url) . "';</script>";
echo "<p>Redirecting to Enhanced Infusion Form...</p>";
?>