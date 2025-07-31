<?php
// Step-Up MFA Module Bootstrap – runs on every OpenEMR request when module is enabled.

// TEST: This should appear in the log if bootstrap loads
file_put_contents('/tmp/stepup_mfa_test.log', date('Y-m-d H:i:s') . ' - Bootstrap loaded' . PHP_EOL, FILE_APPEND);

require_once(dirname(__FILE__, 4) . '/globals.php');

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
        error_log('StepUpMFA needsMfa: ' . ($needsMfa ? 'true' : 'false'));
    }
}

// If sensitive and not recently verified – redirect to MFA page
if ($needsMfa && !$service->hasRecentVerification((int)$pid)) {
    error_log('StepUpMFA: Redirecting to MFA verification page');
    error_log('StepUpMFA: hasRecentVerification: ' . ($service->hasRecentVerification((int)$pid) ? 'true' : 'false'));
    $_SESSION['stepup_mfa_redirect'] = $_SERVER['REQUEST_URI'];
    $verifyUrl = $GLOBALS['webroot'] . '/interface/stepup_mfa_verify.php?pid=' . urlencode((string)$pid);
    header('Location: ' . $verifyUrl);
    exit;
}
