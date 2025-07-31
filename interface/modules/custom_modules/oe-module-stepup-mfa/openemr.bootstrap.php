<?php
// Step-Up MFA Module Bootstrap – runs on every OpenEMR request when module is enabled.

// TEST: This should appear in the log if module bootstrap loads
file_put_contents('/tmp/stepup_mfa_module_test.log', date('Y-m-d H:i:s') . ' - Module bootstrap loaded - START' . PHP_EOL, FILE_APPEND);

// Check if globals.php exists
$globalsPath = dirname(__FILE__, 4) . '/globals.php';
file_put_contents('/tmp/stepup_mfa_module_test.log', date('Y-m-d H:i:s') . ' - Checking globals path: ' . $globalsPath . PHP_EOL, FILE_APPEND);

if (!file_exists($globalsPath)) {
    file_put_contents('/tmp/stepup_mfa_module_test.log', date('Y-m-d H:i:s') . ' - ERROR: globals.php not found at ' . $globalsPath . PHP_EOL, FILE_APPEND);
    return;
}

require_once($globalsPath);

use OpenEMR\Services\StepupMfaService;

// Exit early if feature disabled or user not logged in.
if (!StepupMfaService::isEnabled() || empty($_SESSION['authUserID'])) {
    return;
}

$service = new StepupMfaService();

// Determine current patient/context
$pid = $_GET['pid'] ?? ($_SESSION['pid'] ?? null);
if (!$pid) {
    return; // nothing to protect
}

$needsMfa = false;
// debug log
error_log('StepUpMFA bootstrap start pid=' . ($pid ?? 'null') . ' URI=' . $_SERVER['REQUEST_URI']);

// Check by calendar event id (eid)
if (isset($_GET['eid']) && ctype_digit($_GET['eid'])) {
    $needsMfa = $service->isSensitiveByEventId((int)$_GET['eid']);
}

// Check by encounter id (set_encounter or set_encounterid)
if (!$needsMfa) {
    $encId = null;
    if (isset($_GET['set_encounter']) && ctype_digit($_GET['set_encounter'])) {
        $encId = (int)$_GET['set_encounter'];
    } elseif (isset($_GET['set_encounterid']) && ctype_digit($_GET['set_encounterid'])) {
        $encId = (int)$_GET['set_encounterid'];
    }
    if ($encId !== null) {
        error_log('StepUpMFA check encounter ' . $encId);
        $needsMfa = $service->isSensitiveEncounter($encId);
    }
}

// If sensitive and not recently verified – redirect to MFA page
if ($needsMfa && !$service->hasRecentVerification((int)$pid)) {
    $_SESSION['stepup_mfa_redirect'] = $_SERVER['REQUEST_URI'];
    $verifyUrl = $GLOBALS['webroot'] . '/interface/stepup_mfa_verify.php?pid=' . urlencode((string)$pid);
    header('Location: ' . $verifyUrl);
    exit;
}
