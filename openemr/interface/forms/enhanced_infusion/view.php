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
$form_id = $_GET['id'] ?? '';
$pid = $_GET['pid'] ?? $GLOBALS['pid'] ?? '';
$encounter = $_GET['encounter'] ?? $GLOBALS['encounter'] ?? '';

// If we still don't have pid/encounter, try to get them from the forms table
if (empty($pid) && !empty($form_id)) {
    $forms_query = sqlQuery("SELECT pid, encounter FROM forms WHERE id = ?", [$form_id]);
    if ($forms_query) {
        $pid = $forms_query['pid'];
        $encounter = $forms_query['encounter'];
    }
}

// If we still don't have them, try the form data table
if ((empty($pid) || empty($encounter)) && !empty($form_id)) {
    $form_query = sqlQuery("SELECT pid, encounter FROM form_enhanced_infusion_injection WHERE id = ?", [$form_id]);
    if ($form_query) {
        $pid = $form_query['pid'];
        $encounter = $form_query['encounter'];
    }
}

// Debug logging
error_log("=== DEBUG VIEW: form_id = $form_id, pid = $pid, encounter = $encounter");

// Redirect to the custom module view with proper parameters
$redirect_url = $GLOBALS['web_root'] . "/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php?pid=" . urlencode($pid) . "&encounter=" . urlencode($encounter) . "&id=" . urlencode($form_id);

// Use JavaScript redirect to maintain session
echo "<script>window.location.href = '" . addslashes($redirect_url) . "';</script>";
echo "<p>Redirecting to Enhanced Infusion Form...</p>";
?>
