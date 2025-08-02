<?php
namespace OpenEMR\Services;


use OpenEMR\Common\Session\SessionUtil;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Common\Utils\RandomGenUtils;

/**
 * Service responsible for determining when "step-up" MFA is required
 * and for managing the verification session/grace-period.
 */
class SensitiveEncounterMfaService
{
    /** session key prefix */
    public const SESSION_MFA_VERIFIED_PREFIX = 'mfa_verified_for_pid_';

    /** where we temporarily store the original URL we wanted to view */
    public const SESSION_MFA_REDIRECT_URL = 'mfa_redirect_url';

    /** default grace period (seconds) after successful step-up verification */
    private const DEFAULT_MFA_TIMEOUT = 900; // 15 minutes

    /** cache for category names by id */
    private ?array $cachedCatNames = null;

    /**
     * Returns TRUE when this OpenEMR instance has step-up enabled globally.
     */
    public function isEnabled(): bool
    {
        return (bool)($GLOBALS['stepup_mfa_enabled'] ?? false);
    }

    /**
     * Array of pc_catid values that are considered sensitive.
     */
    public function getSensitiveCategoryIds(): array
    {
        $raw = $GLOBALS['stepup_mfa_categories'] ?? '';
        if ($raw === '') {
            return [];
        }
        return array_map('intval', array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Collect id=>name map for sensitive categories.
     */
    private function collectSensitiveCategoryNames(): array
    {
        if ($this->cachedCatNames !== null) {
            return $this->cachedCatNames;
        }
        $ids = $this->getSensitiveCategoryIds();
        if (empty($ids)) {
            $this->cachedCatNames = [];
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $map = [];
        $res = sqlStatement("SELECT pc_catid, pc_catname FROM openemr_postcalendar_categories WHERE pc_catid IN ($placeholders)", $ids);
        while ($r = sqlFetchArray($res)) {
            $map[(int)$r['pc_catid']] = $r['pc_catname'];
        }
        $this->cachedCatNames = $map;
        return $map;
    }

    public function isSensitiveAppointment(int $appointmentId): bool
    {
        $catIds = $this->getSensitiveCategoryIds();
        if (empty($catIds)) {
            return false;
        }
        $row = sqlQuery('SELECT pc_catid FROM openemr_postcalendar_events WHERE pc_eid = ?', [$appointmentId]);
        return $row && in_array((int)$row['pc_catid'], $catIds, true);
    }

    /**
     * Does the encounter (form_encounter) appear to be sensitive based on its reason field.
     */
    public function isSensitiveEncounter(int $encounterId, ?int $patientId = null): bool
    {
        // AI GENERATED CODE START - Simplified to only check selected categories
        $names = array_map('strtolower', $this->collectSensitiveCategoryNames());
        if (empty($names)) {
            return false;
        }
        $row = sqlQuery('SELECT reason, date, pid FROM form_encounter WHERE encounter = ?', [$encounterId]);
        if (!$row) {
            return false;
        }
        $reason = strtolower($row['reason'] ?? '');
        
        // Only check if reason contains selected sensitive category names
        foreach ($names as $name) {
            if ($name !== '' && str_contains($reason, $name)) {
                return true; // matched in reason text
            }
        }
        
        // Check if encounter is linked to a sensitive appointment category
        $fallback = $this->isSensitiveAppointment($encounterId);
        if ($fallback) {
            return true;
        }
        
        // Remove additional fallback checks that cause false positives
        // AI GENERATED CODE END
        
        return false;
    }

    /**
     * Returns TRUE if the logged-in user has verified MFA for this patient within grace period.
     */
    public function hasRecentVerification(int $patientId): bool
    {
        $key = self::SESSION_MFA_VERIFIED_PREFIX . $patientId;
        if (!isset($_SESSION[$key])) {
            return false;
        }
        return (time() - (int)$_SESSION[$key]) < $this->getTimeout();
    }

    /**
     * Mark MFA verification time for this patient.
     */
    public function setVerified(int $patientId): void
    {
        $_SESSION[self::SESSION_MFA_VERIFIED_PREFIX . $patientId] = time();
    }

    /**
     * Step-up MFA grace-period in seconds.
     */
    public function getTimeout(): int
    {
        return (int)($GLOBALS['stepup_mfa_timeout'] ?? self::DEFAULT_MFA_TIMEOUT);
    }

    /**
     * Utility: log audit events for sensitive access.
     */
    public function logEvent(int $userId, int $patientId, string $event, string $comment = ''): void
    {
        EventAuditLogger::instance()->newEvent('security', $event, $userId, $patientId, null, $comment);
    }
}