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

// Redirect to the custom module view
$form_id = $_GET['id'] ?? '';
$pid = $_GET['pid'] ?? '';
$encounter = $_GET['encounter'] ?? '';

// Redirect to the custom module view with proper parameters
$redirect_url = $GLOBALS['web_root'] . "/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php?pid=" . urlencode($pid) . "&encounter=" . urlencode($encounter) . "&id=" . urlencode($form_id);

// Use JavaScript redirect to maintain session
echo "<script>window.location.href = '" . addslashes($redirect_url) . "';</script>";
echo "<p>Redirecting to Enhanced Infusion Form...</p>";
?>
