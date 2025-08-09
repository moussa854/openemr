<?php

/**
 * Debug PDF Report - Test what HTML is being generated
 */

require_once("../../globals.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/forms.inc.php");

// Get parameters
$pid = $_GET['pid'] ?? 91;
$encounter = $_GET['encounter'] ?? 889;
$id = $_GET['id'] ?? 6;

echo "<h1>Debug: Enhanced Infusion PDF Generation</h1>";
echo "<p><strong>PID:</strong> $pid</p>";
echo "<p><strong>Encounter:</strong> $encounter</p>";
echo "<p><strong>ID:</strong> $id</p>";

echo "<hr><h2>Generated HTML:</h2>";

// Include the report functions
require_once("report.php");

// Generate the HTML content
ob_start();
enhanced_infusion_injection_report($pid, $encounter, null, $id, true);
$htmlContent = ob_get_clean();

echo "<pre>" . htmlspecialchars($htmlContent) . "</pre>";

echo "<hr><h2>Raw HTML Preview:</h2>";
echo $htmlContent;
