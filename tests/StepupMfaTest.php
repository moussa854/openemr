<?php
/**
 * Simple test script for Step-Up MFA functionality
 * Run this to verify the implementation is working correctly
 */

require_once(__DIR__ . '/../globals.php');
require_once(__DIR__ . '/../src/Services/StepupMfaService.php');

use OpenEMR\Services\StepupMfaService;

echo "=== Step-Up MFA Test Script ===\n\n";

$service = new StepupMfaService();

// Test 1: Check if feature is enabled
echo "1. Feature Status:\n";
echo "   Enabled: " . ($service->isEnabled() ? 'YES' : 'NO') . "\n";
echo "   Timeout: " . $service->getTimeout() . " seconds\n";
echo "   Sensitive Categories: " . implode(', ', $service->getSensitiveCategoryIds()) . "\n\n";

// Test 2: Check controlled substance detection
echo "2. Controlled Substance Detection:\n";
echo "   Enabled: " . (($GLOBALS['stepup_mfa_check_controlled_substances'] ?? false) ? 'YES' : 'NO') . "\n";
echo "   Ohio Compliance Logging: " . (($GLOBALS['stepup_mfa_ohio_compliance_logging'] ?? false) ? 'YES' : 'NO') . "\n\n";

// Test 3: Check for sensitive encounters
echo "3. Sensitive Encounters Test:\n";
$sql = "SELECT encounter, pid, reason, sensitivity, pc_catid FROM form_encounter ORDER BY encounter DESC LIMIT 5";
$result = sqlStatement($sql);
$found = 0;

while ($row = sqlFetchArray($result)) {
    $isSensitive = $service->isSensitiveEncounter($row['encounter']);
    $requiresMfa = $service->requiresMfaForEncounter($row['encounter']);
    
    if ($isSensitive || $requiresMfa) {
        $found++;
        echo "   Encounter {$row['encounter']} (PID: {$row['pid']}):\n";
        echo "     Reason: " . ($row['reason'] ?: 'N/A') . "\n";
        echo "     Sensitivity: " . ($row['sensitivity'] ?: 'Standard') . "\n";
        echo "     Category ID: " . ($row['pc_catid'] ?: 'N/A') . "\n";
        echo "     Sensitive: " . ($isSensitive ? 'YES' : 'NO') . "\n";
        echo "     Requires MFA: " . ($requiresMfa ? 'YES' : 'NO') . "\n\n";
    }
}

if ($found === 0) {
    echo "   No sensitive encounters found in recent encounters.\n\n";
}

// Test 4: Check for controlled substances
echo "4. Controlled Substances Test:\n";
$sql = "SELECT p.encounter, p.drug, p.dosage FROM prescriptions p 
        JOIN form_encounter fe ON p.encounter = fe.encounter 
        WHERE p.drug LIKE '%ketamine%' OR p.drug LIKE '%opioid%' 
        ORDER BY p.encounter DESC LIMIT 3";
$result = sqlStatement($sql);
$found = 0;

while ($row = sqlFetchArray($result)) {
    $found++;
    echo "   Encounter {$row['encounter']}:\n";
    echo "     Drug: {$row['drug']}\n";
    echo "     Dosage: " . ($row['dosage'] ?: 'N/A') . "\n";
    echo "     Has Controlled Substances: " . ($service->hasControlledSubstances($row['encounter']) ? 'YES' : 'NO') . "\n\n";
}

if ($found === 0) {
    echo "   No controlled substances found in recent prescriptions.\n\n";
}

// Test 5: Configuration summary
echo "5. Configuration Summary:\n";
echo "   Step-Up MFA Enabled: " . ($service->isEnabled() ? 'YES' : 'NO') . "\n";
echo "   Controlled Substance Detection: " . (($GLOBALS['stepup_mfa_check_controlled_substances'] ?? false) ? 'YES' : 'NO') . "\n";
echo "   Ohio Compliance Logging: " . (($GLOBALS['stepup_mfa_ohio_compliance_logging'] ?? false) ? 'YES' : 'NO') . "\n";
echo "   Grace Period: " . $service->getTimeout() . " seconds\n";
echo "   Sensitive Categories Count: " . count($service->getSensitiveCategoryIds()) . "\n\n";

echo "=== Test Complete ===\n";
echo "To enable the feature, go to Administration → Step-Up MFA Settings\n";
echo "Make sure users have MFA enabled in their MFA Management settings.\n"; 