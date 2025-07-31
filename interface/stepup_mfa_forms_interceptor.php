<?php
/**
 * Step-Up MFA enforcement during encounter (forms.php) views.
 * Included by forms.php right after the standard includes so $pid and $encounter are available.
 */

use OpenEMR\Services\StepupMfaService;

if (!class_exists(StepupMfaService::class)) {
    require_once dirname(__DIR__) . '/src/Services/StepupMfaService.php';
}

function oe_stepup_mfa_forms_check(): void
{
    global $pid, $encounter, $GLOBALS;
    $svc = new StepupMfaService();

    
    if (!$svc->isEnabled()) {
        return;
    }
    if (empty($pid)) {
        return;
    }
    if ($svc->hasRecentVerification((int)$pid)) {
        return;
    }

    // If current encounter reason/name is sensitive, enforce MFA
    if ($encounter && $svc->isSensitiveEncounter((int)$encounter)) {
        $_SESSION['stepup_mfa_redirect'] = $_SERVER['REQUEST_URI'];
        $svc->logEvent('MFA_REQUIRED', 'Step-up MFA required during forms.php');
        header('Location: ' . $GLOBALS['webroot'] . '/interface/stepup_mfa_verify.php?pid=' . urlencode($pid));
        exit;
    }
}

oe_stepup_mfa_forms_check();
