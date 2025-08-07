<?php
// Security Audit System for Inventory Module
// Provides comprehensive logging and compliance reporting

require_once '/var/www/emr.carepointinfusion.com/interface/globals.php';
require_once "/var/www/emr.carepointinfusion.com/library/formatting_DateToYYYYMMDD_js.js.php";

// Get current user ID
$user_id = $_SESSION['authUserID'] ?? 1;

// Database connection
$sqlconf = $GLOBALS['sqlconf'];
$dsn = "mysql:host={$sqlconf['host']};dbname={$sqlconf['dbase']}";
$pdo = new PDO($dsn, $sqlconf['login'], $sqlconf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

class SecurityAudit {
    private $pdo;
    private $user_id;
    
    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }
    
    /**
     * Log a security event
     */
    public function logEvent($action, $details = [], $severity = 'info') {
        $sql = "INSERT INTO security_audit_log (
            user_id, action, details, severity, ip_address, user_agent, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $this->user_id,
            $action,
            json_encode($details),
            $severity,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    /**
     * Log controlled substance access
     */
    public function logControlledSubstanceAccess($drug_id, $action, $mfa_verified = false) {
        $this->logEvent('controlled_substance_access', [
            'drug_id' => $drug_id,
            'action' => $action,
            'mfa_verified' => $mfa_verified,
            'session_id' => session_id()
        ], 'high');
    }
    
    /**
     * Log wastage events
     */
    public function logWastage($drug_id, $quantity, $reason, $is_controlled = false) {
        $this->logEvent('drug_wastage', [
            'drug_id' => $drug_id,
            'quantity' => $quantity,
            'reason' => $reason,
            'is_controlled' => $is_controlled
        ], $is_controlled ? 'high' : 'medium');
    }
    
    /**
     * Log inventory adjustments
     */
    public function logAdjustment($drug_id, $previous_qty, $new_qty, $reason, $is_controlled = false) {
        $this->logEvent('inventory_adjustment', [
            'drug_id' => $drug_id,
            'previous_quantity' => $previous_qty,
            'new_quantity' => $new_qty,
            'adjustment' => $new_qty - $previous_qty,
            'reason' => $reason,
            'is_controlled' => $is_controlled
        ], $is_controlled ? 'high' : 'medium');
    }
    
    /**
     * Log MFA events
     */
    public function logMFAEvent($action, $success, $details = []) {
        $this->logEvent('mfa_event', [
            'action' => $action,
            'success' => $success,
            'details' => $details
        ], 'high');
    }
    
    /**
     * Get audit trail for compliance reporting
     */
    public function getAuditTrail($start_date = null, $end_date = null, $user_id = null, $action = null) {
        $conditions = [];
        $params = [];
        
        if ($start_date) {
            $conditions[] = "created_at >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $conditions[] = "created_at <= ?";
            $params[] = $end_date;
        }
        
        if ($user_id) {
            $conditions[] = "user_id = ?";
            $params[] = $user_id;
        }
        
        if ($action) {
            $conditions[] = "action = ?";
            $params[] = $action;
        }
        
        $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "SELECT 
                    sal.*,
                    u.username,
                    u.fname,
                    u.lname
                FROM security_audit_log sal
                LEFT JOIN users u ON sal.user_id = u.id
                $where_clause
                ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get controlled substance activity report
     */
    public function getControlledSubstanceReport($start_date = null, $end_date = null) {
        $conditions = ["action = 'controlled_substance_access'"];
        $params = [];
        
        if ($start_date) {
            $conditions[] = "created_at >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $conditions[] = "created_at <= ?";
            $params[] = $end_date;
        }
        
        $sql = "SELECT 
                    sal.*,
                    u.username,
                    u.fname,
                    u.lname,
                    d.name as drug_name,
                    d.is_controlled_substance
                FROM security_audit_log sal
                LEFT JOIN users u ON sal.user_id = u.id
                LEFT JOIN drugs d ON JSON_EXTRACT(sal.details, '$.drug_id') = d.drug_id
                WHERE " . implode(" AND ", $conditions) . "
                ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get security statistics
     */
    public function getSecurityStats($days = 30) {
        $sql = "SELECT 
                    action,
                    severity,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM security_audit_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action, severity, DATE(created_at)
                ORDER BY date DESC, count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check for suspicious activity
     */
    public function detectSuspiciousActivity($user_id = null) {
        $conditions = [];
        $params = [];
        
        if ($user_id) {
            $conditions[] = "user_id = ?";
            $params[] = $user_id;
        }
        
        $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Check for multiple failed MFA attempts
        $sql = "SELECT 
                    user_id,
                    COUNT(*) as failed_attempts
                FROM security_audit_log
                $where_clause
                AND action = 'mfa_event'
                AND JSON_EXTRACT(details, '$.success') = false
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY user_id
                HAVING failed_attempts >= 5";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $mfa_failures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check for unusual wastage patterns
        $sql = "SELECT 
                    user_id,
                    COUNT(*) as wastage_count,
                    SUM(CAST(JSON_EXTRACT(details, '$.quantity') AS UNSIGNED)) as total_wastage
                FROM security_audit_log
                $where_clause
                AND action = 'drug_wastage'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY user_id
                HAVING wastage_count > 10 OR total_wastage > 100";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $unusual_wastage = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'mfa_failures' => $mfa_failures,
            'unusual_wastage' => $unusual_wastage
        ];
    }
}

// Initialize security audit
$securityAudit = new SecurityAudit($pdo, $user_id);

// Handle audit report requests
$report_type = $_GET['type'] ?? 'overview';
$start_date = $_GET["start_date"] ?? YYYYMMDDToDate(date("Y-m-01"));
$end_date = $_GET["end_date"] ?? YYYYMMDDToDate(date("Y-m-d"));

$audit_data = [];

if ($report_type === 'controlled_substances') {
    $audit_data = $securityAudit->getControlledSubstanceReport($start_date, $end_date);
} elseif ($report_type === 'suspicious_activity') {
    $audit_data = $securityAudit->detectSuspiciousActivity();
} elseif ($report_type === 'security_stats') {
    $audit_data = $securityAudit->getSecurityStats(30);
} else {
    $audit_data = $securityAudit->getAuditTrail($start_date, $end_date);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Security Audit Report</title>
    <link rel="stylesheet" href="library/css/inventory-module.css">
    <style>
        .audit-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .audit-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .audit-table th,
        .audit-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .audit-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .severity-high { color: #e74c3c; font-weight: bold; }
        .severity-medium { color: #f39c12; font-weight: bold; }
        .severity-low { color: #27ae60; }
        .suspicious-activity {
            background: #fdf2f2;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            margin: 10px 0;
        }
    </style>
<script>
// Date conversion functions
document.addEventListener("DOMContentLoaded", function() {
    const forms = document.querySelectorAll("form");
    forms.forEach(function(form) {
        form.addEventListener("submit", function(e) {
            const startDateInput = form.querySelector("input[name="start_date"]");
            const endDateInput = form.querySelector("input[name="end_date"]");
            if (startDateInput && startDateInput.value) {
                startDateInput.value = DateToYYYYMMDD_js(startDateInput.value);
            }
            if (endDateInput && endDateInput.value) {
                endDateInput.value = DateToYYYYMMDD_js(endDateInput.value);
            }
        });
    });
});
</script>
</head>
<body>
    <div class="audit-container">
        <h1>Security Audit Report</h1>
        
        <!-- Report Filters -->
        <div class="audit-section">
            <form method="GET">
                <div style="display: flex; gap: 20px; align-items: end;">
                    <div>
                        <label for="type">Report Type:</label>
                        <select name="type" id="type">
                            <option value="overview" <?= $report_type === 'overview' ? 'selected' : '' ?>>Overview</option>
                            <option value="controlled_substances" <?= $report_type === 'controlled_substances' ? 'selected' : '' ?>>Controlled Substances</option>
                            <option value="suspicious_activity" <?= $report_type === 'suspicious_activity' ? 'selected' : '' ?>>Suspicious Activity</option>
                            <option value="security_stats" <?= $report_type === 'security_stats' ? 'selected' : '' ?>>Security Statistics</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="start_date">Start Date:</label>
                        <input type="text" name="start_date" value="<?= $start_date ?>">
                    </div>
                    
                    <div>
                        <label for="end_date">End Date:</label>
                        <input type="text" name="end_date" value="<?= $end_date ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>

        <!-- Report Content -->
        <?php if ($report_type === 'controlled_substances'): ?>
        <div class="audit-section">
            <h2>Controlled Substance Activity Report</h2>
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Drug</th>
                        <th>Action</th>
                        <th>MFA Verified</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_data as $event): ?>
                    <tr>
                        <td><?= date('M j, Y H:i:s', strtotime($event['created_at'])) ?></td>
                        <td><?= htmlspecialchars($event['fname'] . ' ' . $event['lname']) ?></td>
                        <td><?= htmlspecialchars($event['drug_name'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($event['action']) ?></td>
                        <td><?= json_decode($event['details'], true)['mfa_verified'] ? 'Yes' : 'No' ?></td>
                        <td><?= htmlspecialchars($event['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'suspicious_activity'): ?>
        <div class="audit-section">
            <h2>Suspicious Activity Detection</h2>
            
            <?php if (!empty($audit_data['mfa_failures'])): ?>
            <div class="suspicious-activity">
                <h3>🚨 Multiple MFA Failures</h3>
                <p>The following users have had multiple failed MFA attempts in the last hour:</p>
                <ul>
                    <?php foreach ($audit_data['mfa_failures'] as $failure): ?>
                    <li>User ID: <?= $failure['user_id'] ?> - <?= $failure['failed_attempts'] ?> failed attempts</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($audit_data['unusual_wastage'])): ?>
            <div class="suspicious-activity">
                <h3>⚠️ Unusual Wastage Patterns</h3>
                <p>The following users have unusual wastage patterns in the last 24 hours:</p>
                <ul>
                    <?php foreach ($audit_data['unusual_wastage'] as $wastage): ?>
                    <li>User ID: <?= $wastage['user_id'] ?> - <?= $wastage['wastage_count'] ?> wastage events, <?= $wastage['total_wastage'] ?> total units</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (empty($audit_data['mfa_failures']) && empty($audit_data['unusual_wastage'])): ?>
            <p>✅ No suspicious activity detected in the current time period.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'security_stats'): ?>
        <div class="audit-section">
            <h2>Security Statistics (Last 30 Days)</h2>
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Severity</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_data as $stat): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($stat['date'])) ?></td>
                        <td><?= htmlspecialchars($stat['action']) ?></td>
                        <td class="severity-<?= $stat['severity'] ?>"><?= ucfirst($stat['severity']) ?></td>
                        <td><?= $stat['count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 