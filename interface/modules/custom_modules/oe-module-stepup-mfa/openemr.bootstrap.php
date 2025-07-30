<?php
/**
 * Bootstrap file – automatically loaded by OpenEMR when present in a custom module.
 * Hooks into the event system to enforce step-up MFA prior to viewing sensitive encounters.
 */

use OpenEMR\Events\PatientDemographics\ViewEvent;
use OpenEMR\Services\SensitiveEncounterMfaService;

$dispatcher = $GLOBALS['kernel']->getEventDispatcher();
$dispatcher->addListener(ViewEvent::EVENT_HANDLE, function (ViewEvent $event) {
    $svc = new SensitiveEncounterMfaService();
    if (!$svc->isEnabled()) {
        return $event;
    }

    $patientId = $event->getPid();
    if (!$patientId) {
        return $event;
    }

    // If already verified within grace-period, nothing to do.
    if ($svc->hasRecentVerification($patientId)) {
        return $event;
    }

    // Check if request includes an appointment (eid) and if it is sensitive.
        $eventId = $_GET['eid'] ?? null;
    $encId   = $_GET['set_encounterid'] ?? null;
    if ( ($eventId && $svc->isSensitiveAppointment((int)$eventId)) || ($encId && $svc->isSensitiveEncounter((int)$encId, (int)$patientId)) ) {
        // Save redirect URL and send to verification page.
        $_SESSION[SensitiveEncounterMfaService::SESSION_MFA_REDIRECT_URL] = $_SERVER['REQUEST_URI'];
        $svc->logEvent($_SESSION['authUserID'] ?? 0, $patientId, 'MFA_REQUIRED', 'Step-up MFA required for sensitive encounter');
        header('Location: ' . $GLOBALS['webroot'] . '/interface/stepup_mfa_verify.php?pid=' . urlencode($patientId));
        exit;
    }

    return $event;
});