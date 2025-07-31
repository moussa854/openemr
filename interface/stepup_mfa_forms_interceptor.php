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
    
    // Comprehensive debug logging
    error_log('=== StepUpMFA DEBUG START ===');
    error_log('StepUpMFA: REQUEST_URI = ' . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));
    error_log('StepUpMFA: SCRIPT_NAME = ' . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET'));
    error_log('StepUpMFA: PHP_SELF = ' . ($_SERVER['PHP_SELF'] ?? 'NOT SET'));
    error_log('StepUpMFA: pid = ' . ($pid ?? 'NOT SET'));
    error_log('StepUpMFA: encounter = ' . ($encounter ?? 'NOT SET'));
    error_log('StepUpMFA: Session ID = ' . (session_id() ?? 'NOT SET'));
    error_log('StepUpMFA: User ID = ' . ($_SESSION['authUserID'] ?? 'NOT SET'));
    error_log('StepUpMFA: User = ' . ($_SESSION['authUser'] ?? 'NOT SET'));
    
    $svc = new StepupMfaService();

    if (!$svc->isEnabled()) {
        error_log('StepUpMFA: Feature is DISABLED');
        error_log('=== StepUpMFA DEBUG END ===');
        return;
    }
    error_log('StepUpMFA: Feature is ENABLED');
    
    if (empty($pid)) {
        error_log('StepUpMFA: No PID, skipping');
        error_log('=== StepUpMFA DEBUG END ===');
        return;
    }
    
    if ($svc->hasRecentVerification((int)$pid)) {
        error_log('StepUpMFA: Recent verification exists for PID ' . $pid);
        error_log('StepUpMFA: Session verification key = ' . (isset($_SESSION['stepup_mfa_verified_pid_' . $pid]) ? $_SESSION['stepup_mfa_verified_pid_' . $pid] : 'NOT SET'));
        error_log('StepUpMFA: Current time = ' . time());
        error_log('StepUpMFA: Time difference = ' . (isset($_SESSION['stepup_mfa_verified_pid_' . $pid]) ? (time() - $_SESSION['stepup_mfa_verified_pid_' . $pid]) : 'N/A'));
        error_log('StepUpMFA: Timeout = ' . $svc->getTimeout());
        error_log('=== StepUpMFA DEBUG END ===');
        return;
    }
    error_log('StepUpMFA: No recent verification for PID ' . $pid);
    error_log('StepUpMFA: Session verification key = ' . (isset($_SESSION['stepup_mfa_verified_pid_' . $pid]) ? $_SESSION['stepup_mfa_verified_pid_' . $pid] : 'NOT SET'));

    // Enhanced sensitivity check that includes controlled substance detection
    if ($encounter) {
        $requiresMfa = $svc->requiresMfaForEncounter((int)$encounter);
        error_log('StepUpMFA: Encounter ' . $encounter . ' requires MFA? ' . ($requiresMfa ? 'YES' : 'NO'));
        
        if ($requiresMfa) {
            // Set redirect to the correct forms.php URL instead of main screen
            $redirect_url = $GLOBALS['webroot'] . '/interface/patient_file/encounter/forms.php?pid=' . urlencode((string)$pid) . '&encounter=' . urlencode((string)$encounter);
            $_SESSION['stepup_mfa_redirect'] = $redirect_url;
            
            error_log('StepUpMFA: Setting redirect URL = ' . $redirect_url);
            error_log('StepUpMFA: Session ID before redirect = ' . (session_id() ?? 'NOT SET'));
            error_log('StepUpMFA: Session stepup_mfa_redirect after setting = ' . (isset($_SESSION['stepup_mfa_redirect']) ? $_SESSION['stepup_mfa_redirect'] : 'NOT SET'));
            error_log('StepUpMFA: Redirecting to MFA verification page');
            
            $svc->logEvent('MFA_REQUIRED', 'Step-up MFA required during forms.php for sensitive encounter');
            
            // Pass redirect URL as parameter to avoid session issues
            $verify_url = $GLOBALS['webroot'] . '/interface/stepup_mfa_verify.php?pid=' . urlencode((string)$pid) . '&redirect=' . urlencode($redirect_url);
            header('Location: ' . $verify_url);
            error_log('=== StepUpMFA DEBUG END ===');
            exit;
        } else {
            error_log('StepUpMFA: Encounter ' . $encounter . ' does not require MFA');
        }
    } else {
        error_log('StepUpMFA: No encounter ID available');
    }
    
    error_log('=== StepUpMFA DEBUG END ===');
}

oe_stepup_mfa_forms_check();
