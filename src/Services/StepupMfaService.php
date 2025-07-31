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

    /** Sensitive medication keywords for text-based detection */
    private const SENSITIVE_MEDICATIONS = [
        'ketamine', 'infusion', 'controlled', 'schedule', 'narcotic',
        'opioid', 'benzodiazepine', 'stimulant', 'sedative', 'anesthetic'
    ];

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
        
        // First check by pc_catid (most reliable)
        $pcCatId = (int)($enc['pc_catid'] ?? 0);
        if ($pcCatId > 0) {
            $sensitiveCategoryIds = self::getSensitiveCategoryIds();
            if (in_array($pcCatId, $sensitiveCategoryIds)) {
                $this->logEvent('SENSITIVE_DETECTED', "Encounter $encounterId is sensitive by pc_catid $pcCatId");
                return true;
            }
        }
        
        // Check by sensitivity level
        $sensitivity = (string)($enc['sensitivity'] ?? '');
        if ($sensitivity === 'high' || $sensitivity === 'restricted') {
            $this->logEvent('SENSITIVE_DETECTED', "Encounter $encounterId is sensitive by sensitivity level: $sensitivity");
            return true;
        }
        
        // Enhanced text-based detection for medications
        $reason = strtolower((string)($enc['reason'] ?? ''));
        if ($reason !== '') {
            // Check for sensitive medication keywords
            foreach (self::SENSITIVE_MEDICATIONS as $keyword) {
                if (strpos($reason, $keyword) !== false) {
                    $this->logEvent('SENSITIVE_DETECTED', "Encounter $encounterId is sensitive by medication keyword: $keyword");
                    return true;
                }
            }
            
            // Check category names
            foreach ($this->getSensitiveCategoryNames() as $name) {
                if (strpos($reason, $name) !== false) {
                    $this->logEvent('SENSITIVE_DETECTED', "Encounter $encounterId is sensitive by category name: $name");
                    return true;
                }
            }
        }
        
        return false;
    }

    /** Check if encounter contains controlled substance prescriptions */
    public function hasControlledSubstances(int $encounterId): bool
    {
        // Check prescriptions table for controlled substances
        $sql = "SELECT p.drug, p.dosage, p.quantity 
                FROM prescriptions p 
                JOIN form_encounter fe ON p.encounter = fe.encounter 
                WHERE fe.encounter = ? 
                AND (p.drug LIKE '%ketamine%' 
                     OR p.drug LIKE '%opioid%' 
                     OR p.drug LIKE '%benzodiazepine%'
                     OR p.drug LIKE '%stimulant%'
                     OR p.drug LIKE '%narcotic%')";
        
        $result = sqlStatement($sql, [$encounterId]);
        while ($row = sqlFetchArray($result)) {
            $this->logEvent('CONTROLLED_SUBSTANCE_DETECTED', 
                "Controlled substance found in encounter $encounterId: " . $row['drug']);
            return true;
        }
        
        return false;
    }

    /** Enhanced sensitivity check that includes medication analysis */
    public function requiresMfaForEncounter(int $encounterId): bool
    {
        // Basic encounter sensitivity check
        if ($this->isSensitiveEncounter($encounterId)) {
            return true;
        }
        
        // Check for controlled substances if enabled
        if ($this->isControlledSubstanceCheckEnabled()) {
            if ($this->hasControlledSubstances($encounterId)) {
                return true;
            }
        }
        
        return false;
    }

    /** Check if controlled substance detection is enabled */
    private function isControlledSubstanceCheckEnabled(): bool
    {
        return (bool)($GLOBALS['stepup_mfa_check_controlled_substances'] ?? false);
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
