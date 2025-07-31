<?php
require_once(__DIR__ . '/../globals.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\Header;
use OpenEMR\Services\StepupMfaService;

// ACL check – require admin super privileges
if (!AclMain::aclCheckCore('admin', 'super')) {
    die(xlt('Not authorized')); // simple block
}

$service = new StepupMfaService();
$reportData = [];
$error = '';

// Handle date range selection
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// Get compliance data
if ($startDate && $endDate) {
    try {
        if ($patientId) {
            $reportData = $service->getComplianceReport($patientId, $startDate, $endDate);
        } else {
            // Get all sensitive encounters in date range
            $sql = "SELECT fe.encounter, fe.pid, fe.date, fe.reason, fe.sensitivity, fe.pc_catid,
                           pd.fname, pd.lname
                    FROM form_encounter fe 
                    JOIN patient_data pd ON fe.pid = pd.pid
                    WHERE fe.date BETWEEN ? AND ?
                    ORDER BY fe.date DESC";
            
            $result = sqlStatement($sql, [$startDate, $endDate]);
            $reportData = ['sensitive_encounters' => []];
            while ($row = sqlFetchArray($result)) {
                if ($service->isSensitiveEncounter($row['encounter'])) {
                    $reportData['sensitive_encounters'][] = $row;
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error generating report: ' . $e->getMessage();
    }
}

$token = CsrfUtils::collectCsrfToken();
?>
<html>
<head>
    <title><?php echo xlt('Ohio Compliance Report - Step-Up MFA'); ?></title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/interface/themes/style_sky_blue.css">
    <style>
        .compliance-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .report-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .encounter-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .encounter-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .encounter-item:hover {
            background: #f8f9fa;
        }
        .filter-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="body_top">
<div class="container-fluid">
    <div class="compliance-header">
        <h1>📋 Ohio Compliance Report - Step-Up MFA</h1>
        <p>Comprehensive audit report for controlled substance access compliance</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="filter-form">
        <form method="get" class="row">
            <div class="col-md-3">
                <label><?php echo xlt('Start Date'); ?></label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label><?php echo xlt('End Date'); ?></label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label><?php echo xlt('Patient ID (Optional)'); ?></label>
                <input type="number" name="patient_id" value="<?php echo $patientId ?: ''; ?>" class="form-control" placeholder="Leave empty for all patients">
            </div>
            <div class="col-md-3">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary form-control"><?php echo xlt('Generate Report'); ?></button>
            </div>
        </form>
    </div>

    <?php if (!empty($reportData)): ?>
        <div class="stats-grid">
            <div class="stat-box">
                <h3><?php echo count($reportData['sensitive_encounters']); ?></h3>
                <p><?php echo xlt('Sensitive Encounters'); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo $service->getTimeout() / 60; ?></h3>
                <p><?php echo xlt('MFA Timeout (minutes)'); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo $service->isEnabled() ? xlt('Enabled') : xlt('Disabled'); ?></h3>
                <p><?php echo xlt('Step-Up MFA Status'); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo count($service->getSensitiveCategoryIds()); ?></h3>
                <p><?php echo xlt('Sensitive Categories'); ?></p>
            </div>
        </div>

        <div class="report-card">
            <h3>🔍 Sensitive Encounters Detail</h3>
            <div class="encounter-list">
                <?php if (empty($reportData['sensitive_encounters'])): ?>
                    <p class="text-muted"><?php echo xlt('No sensitive encounters found in the selected date range.'); ?></p>
                <?php else: ?>
                    <?php foreach ($reportData['sensitive_encounters'] as $encounter): ?>
                        <div class="encounter-item">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong><?php echo xlt('Date'); ?>:</strong><br>
                                    <?php echo htmlspecialchars($encounter['date']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong><?php echo xlt('Patient'); ?>:</strong><br>
                                    <?php echo htmlspecialchars($encounter['fname'] . ' ' . $encounter['lname']); ?>
                                    (ID: <?php echo (int)$encounter['pid']; ?>)
                                </div>
                                <div class="col-md-3">
                                    <strong><?php echo xlt('Encounter ID'); ?>:</strong><br>
                                    <?php echo (int)$encounter['encounter']; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong><?php echo xlt('Sensitivity'); ?>:</strong><br>
                                    <?php echo htmlspecialchars($encounter['sensitivity'] ?: xlt('Standard')); ?>
                                </div>
                            </div>
                            <?php if (!empty($encounter['reason'])): ?>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <strong><?php echo xlt('Reason'); ?>:</strong> 
                                        <?php echo htmlspecialchars($encounter['reason']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card">
            <h3>⚙️ Configuration Summary</h3>
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo xlt('Sensitive Categories'); ?></h5>
                    <ul>
                        <?php 
                        $catIds = $service->getSensitiveCategoryIds();
                        if (empty($catIds)): ?>
                            <li><?php echo xlt('No categories configured'); ?></li>
                        <?php else: ?>
                            <?php foreach ($catIds as $catId): ?>
                                <?php 
                                $catName = sqlQuery("SELECT pc_catname FROM openemr_postcalendar_categories WHERE pc_catid = ?", [$catId]);
                                ?>
                                <li><?php echo htmlspecialchars($catName['pc_catname'] ?? "Category $catId"); ?> (ID: <?php echo $catId; ?>)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5><?php echo xlt('System Settings'); ?></h5>
                    <ul>
                        <li><?php echo xlt('Step-Up MFA Enabled'); ?>: <?php echo $service->isEnabled() ? xlt('Yes') : xlt('No'); ?></li>
                        <li><?php echo xlt('Controlled Substance Detection'); ?>: <?php echo ($GLOBALS['stepup_mfa_check_controlled_substances'] ?? false) ? xlt('Yes') : xlt('No'); ?></li>
                        <li><?php echo xlt('Ohio Compliance Logging'); ?>: <?php echo ($GLOBALS['stepup_mfa_ohio_compliance_logging'] ?? false) ? xlt('Yes') : xlt('No'); ?></li>
                        <li><?php echo xlt('Grace Period'); ?>: <?php echo $service->getTimeout(); ?> <?php echo xlt('seconds'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="stepup_mfa_settings.php" class="btn btn-secondary"><?php echo xlt('Back to Settings'); ?></a>
        <a href="main_screen.php" class="btn btn-primary"><?php echo xlt('Return to Main Screen'); ?></a>
    </div>
</div>
</body>
</html> 