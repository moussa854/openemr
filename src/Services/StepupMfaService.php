<?php
/**
 * Step-Up MFA helper service
 */
namespace OpenEMR\Services;

use EventAuditLogger;

class StepupMfaService
{
    /** Session key prefix for MFA verification */
    public const SESSION_KEY_PREFIX = 'stepup_mfa_verified_pid_';

    /** Default grace period (seconds) if not configured via Globals */
    public const DEFAULT_TIMEOUT = 900; // 15 minutes

    /** Return true/false if the feature toggle is on */
    public static function isEnabled(): bool
    {
        return (bool)($GLOBALS['stepup_mfa_enabled'] ?? false);
    }

    /** Return array<int> of category IDs that require MFA */
    public static function getSensitiveCategoryIds(): array
    {
        $raw = (string)($GLOBALS['stepup_mfa_categories'] ?? '');
        if ($raw === '') {
            return [];
        }
        return array_map('intval', array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }

    /** Grace period in seconds before requiring a new MFA challenge */
    public static function getTimeout(): int
    {
        return isset($GLOBALS['stepup_mfa_timeout']) && is_numeric($GLOBALS['stepup_mfa_timeout'])
            ? (int)$GLOBALS['stepup_mfa_timeout']
            : self::DEFAULT_TIMEOUT;
    }

    /** Check if current user session has a recent verification for this patient */
    public function hasRecentVerification(int $pid): bool
    {
        $key = self::SESSION_KEY_PREFIX . $pid;
        if (!isset($_SESSION[$key])) {
            return false;
        }
        return (time() - $_SESSION[$key]) < self::getTimeout();
    }

    /** Mark a successful MFA verification for a patient */
    public function setVerified(int $pid): void
    {
        $_SESSION[self::SESSION_KEY_PREFIX . $pid] = time();
    }

    /** Determine if a calendar event ID belongs to a sensitive category */
    public function isSensitiveByEventId(int $eid): bool
    {
        if (!$eid) {
            return false;
        }
        $row = sqlQuery("SELECT pc_catid FROM openemr_postcalendar_events WHERE pc_eid = ?", [$eid]);
        if (!$row) {
            return false;
        }
        return in_array((int)$row['pc_catid'], self::getSensitiveCategoryIds(), true);
    }

    /** Determine sensitivity by encounter (fallback using reason field text match) */
    public function isSensitiveEncounter(int $encounterId): bool
    {
        $enc = sqlQuery("SELECT reason FROM form_encounter WHERE encounter = ?", [$encounterId]);
        if (!$enc) {
            return false;
        }
        $reason = strtolower((string)($enc['reason'] ?? ''));
        if ($reason === '') {
            return false;
        }
        foreach ($this->getSensitiveCategoryNames() as $name) {
            if (strpos($reason, $name) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Collect lowercase category names for text-matching */
    private function getSensitiveCategoryNames(): array
    {
        $ids = self::getSensitiveCategoryIds();
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $res = sqlStatement("SELECT pc_catname FROM openemr_postcalendar_categories WHERE pc_catid IN ($placeholders)", $ids);
        $out = [];
        while ($r = sqlFetchArray($res)) {
            $out[] = strtolower($r['pc_catname']);
        }
        return $out;
    }

    /** Convenience audit helper */
    public function logEvent(string $type, string $detail): void
    {
        // Minimal wrapper – adjust fields per EventAuditLogger API in target OpenEMR version
        if (class_exists('EventAuditLogger')) {
            EventAuditLogger::instance()->newEvent($type, $detail, $_SESSION['authUserID'] ?? 0, $_SESSION['pid'] ?? 0);
        }
    }
}
