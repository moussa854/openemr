<?php
/**
 * Step-Up MFA enforcement during encounter (forms.php) views.
 * Enhanced for Ohio compliance requirements for controlled substances.
 * Included by forms.php right after the standard includes so $pid and $encounter are available.
 */

use OpenEMR\Services\StepupMfaService;

if (!class_exists(StepupMfaService::class)) {
    require_once dirname(__DIR__) . '/src/Services/StepupMfaService.php';
}

function oe_stepup_mfa_forms_check(): void
{
    global $pid, $encounter, $GLOBALS;
    error_log('StepUpMFA forms interceptor hit pid=' . ($pid??'') . ' enc=' . ($encounter??''));
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

    // Enhanced sensitivity check that includes controlled substance detection
    if ($encounter) {
        $requiresMfa = $svc->requiresMfaForEncounter((int)$encounter);
        error_log('StepUpMFA forms requires MFA? ' . ($requiresMfa ? 'yes' : 'no'));
        if ($requiresMfa) {
            $_SESSION['stepup_mfa_redirect'] = $_SERVER['REQUEST_URI'];
            $svc->logEvent('MFA_REQUIRED', 'Step-up MFA required during forms.php for sensitive encounter');
            header('Location: ' . $GLOBALS['webroot'] . '/interface/stepup_mfa_verify.php?pid=' . urlencode((string)$pid));
            exit;
        }
    }
}

oe_stepup_mfa_forms_check();
