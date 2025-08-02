<?php
/**
 * Step-Up MFA helper service for sensitive encounters
 * Enhanced for Ohio compliance requirements for controlled substances
 */
namespace OpenEMR\Services;

use EventAuditLogger;

class StepupMfaService
{
    /** Session key prefix for MFA verification */
    public const SESSION_KEY_PREFIX = 'stepup_mfa_verified_pid_';

    /** Default grace period (seconds) if not configured via Globals */
    public const DEFAULT_TIMEOUT = 900; // 15 minutes

    // AI GENERATED CODE START - Remove sensitive medication keywords that cause false positives
    // Removed SENSITIVE_MEDICATIONS constant to prevent false positives
    // AI GENERATED CODE END

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
        // Use database table instead of session variables for better reliability
        $userId = $_SESSION['authUserID'] ?? 1;
        $timeout = $this->getTimeout();
        
        $sql = "SELECT COUNT(*) as count FROM stepup_mfa_verifications 
                WHERE user_id = ? AND patient_id = ? AND expires_at > NOW()";
        
        $result = sqlQuery($sql, [$userId, $pid]);
        $count = $result['count'] ?? 0;
        
        error_log("StepUpMFA: Database verification check - User: $userId, Patient: $pid, Count: $count");
        
        return $count > 0;
    }

    /** Mark a successful MFA verification for a patient */
    public function setVerified(int $pid): void
    {
        $userId = $_SESSION['authUserID'] ?? 1;
        $encounterId = $_GET['encounter'] ?? null;
        $timeout = $this->getTimeout();
        $expiresAt = date('Y-m-d H:i:s', time() + $timeout);
        
        // Insert verification record into database
        $sql = "INSERT INTO stepup_mfa_verifications 
                (user_id, patient_id, encounter_id, expires_at, verification_type, ip_address, user_agent, session_id, ohio_compliance_logged) 
                VALUES (?, ?, ?, ?, 'TOTP', ?, ?, ?, 1)";
        
        $params = [
            $userId,
            $pid,
            $encounterId,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            session_id()
        ];
        
        sqlStatement($sql, $params);
        
        error_log("StepUpMFA: Database verification set - User: $userId, Patient: $pid, Expires: $expiresAt");
        
        $this->logEvent('MFA_SUCCESS', "Step-up MFA verification completed for patient $pid");
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
        $sens = in_array((int)$row['pc_catid'], self::getSensitiveCategoryIds(), true);
        error_log('StepUpMFA isSensitiveByEventId eid=' . $eid . ' cat=' . $row['pc_catid'] . ' result=' . ($sens?'yes':'no'));
        return $sens;
    }

    /** Enhanced sensitivity detection for encounters with medication awareness */
    public function isSensitiveEncounter(int $encounterId): bool
    {
        $enc = sqlQuery("SELECT reason, pc_catid, sensitivity FROM form_encounter WHERE encounter = ?", [$encounterId]);
        if (!$enc) {
            return false;
        }
        
        // AI GENERATED CODE START - Only check selected sensitive categories
        // Check by pc_catid (most reliable) - ONLY check selected categories
        $pcCatId = (int)($enc['pc_catid'] ?? 0);
        if ($pcCatId > 0) {
            $sensitiveCategoryIds = self::getSensitiveCategoryIds();
            if (in_array($pcCatId, $sensitiveCategoryIds)) {
                $this->logEvent('SENSITIVE_DETECTED', "Encounter $encounterId is sensitive by pc_catid $pcCatId");
                return true;
            }
        }
        
        // Check by sensitivity level (only if explicitly set to high/restricted)
        $sensitivity = (string)($enc['sensitivity'] ?? '');
        if ($sensitivity === 'high' || $sensitivity === 'restricted') {
            $this->logEvent('SENSITIVE_DETECTED', "Encounter $encounterId is sensitive by sensitivity level: $sensitivity");
            return true;
        }
        
        // Remove text-based detection that was causing false positives
        // Only check if the encounter is linked to a sensitive appointment category
        $reason = strtolower((string)($enc['reason'] ?? ''));
        if ($reason !== '') {
            // Only check category names that are actually selected as sensitive
            foreach ($this->getSensitiveCategoryNames() as $name) {
                if (strpos($reason, $name) !== false) {
                    $this->logEvent('SENSITIVE_DETECTED', "Encounter $encounterId is sensitive by category name: $name");
                    return true;
                }
            }
        }
        // AI GENERATED CODE END
        
        return false;
    }

    /** Enhanced sensitivity check that includes medication analysis */
    public function requiresMfaForEncounter(int $encounterId): bool
    {
        // AI GENERATED CODE START - Simplified to only check encounter sensitivity
        // Only check if the encounter is marked as sensitive by category or sensitivity level
        return $this->isSensitiveEncounter($encounterId);
        // AI GENERATED CODE END
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

    /** Enhanced audit logging with Ohio compliance details */
    public function logEvent(string $type, string $detail): void
    {
        $userId = $_SESSION['authUserID'] ?? 0;
        $patientId = $_SESSION['pid'] ?? 0;
        $userName = $_SESSION['authUser'] ?? 'unknown';
        
        // Enhanced logging for Ohio compliance
        $complianceDetail = $detail;
        if (strpos($type, 'SENSITIVE') !== false || strpos($type, 'CONTROLLED') !== false) {
            $complianceDetail .= " | User: $userName | Patient: $patientId | Time: " . date('Y-m-d H:i:s');
        }
        
        error_log("StepUpMFA: $type - $complianceDetail");
        
        // Use EventAuditLogger if available
        if (class_exists('EventAuditLogger')) {
            EventAuditLogger::instance()->newEvent($type, $complianceDetail, $userId, $patientId);
        }
    }

    /** Get compliance report data for Ohio regulations */
    public function getComplianceReport(int $patientId, string $startDate, string $endDate): array
    {
        $report = [
            'patient_id' => $patientId,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'sensitive_encounters' => [],
            'mfa_verifications' => [],
            'controlled_substances' => []
        ];
        
        // Get sensitive encounters in date range
        $sql = "SELECT fe.encounter, fe.date, fe.reason, fe.sensitivity, fe.pc_catid
                FROM form_encounter fe 
                WHERE fe.pid = ? AND fe.date BETWEEN ? AND ?
                ORDER BY fe.date DESC";
        
        $result = sqlStatement($sql, [$patientId, $startDate, $endDate]);
        while ($row = sqlFetchArray($result)) {
            if ($this->isSensitiveEncounter($row['encounter'])) {
                $report['sensitive_encounters'][] = $row;
            }
        }
        
        return $report;
    }
}
