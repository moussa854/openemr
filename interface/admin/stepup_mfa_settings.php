<?php
require_once(__DIR__ . '/../globals.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Acl\AclMain;

// ACL check – require admin super privileges
if (!AclMain::aclCheckCore('admin', 'users')) {
    die(xlt('Not authorized')); // simple block
}

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        CsrfUtils::csrfNotVerified();
    }
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_enabled'", [isset($_POST['enabled']) ? '1' : '0']);
    $catsCsv = isset($_POST['categories']) ? implode(',', array_map('intval', $_POST['categories'])) : '';
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_categories'", [$catsCsv]);
    $timeout = (int)($_POST['timeout'] ?? 900);
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_timeout'", [(string)$timeout]);
    
    // New controlled substance detection setting
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_check_controlled_substances'", 
        [isset($_POST['check_controlled_substances']) ? '1' : '0']);
    
    // Ohio compliance logging setting
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_ohio_compliance_logging'", 
        [isset($_POST['ohio_compliance_logging']) ? '1' : '0']);
    
    echo "<script>alert('" . addslashes(xlt('Settings saved')) . "'); window.location.href='stepup_mfa_settings.php';</script>";
    exit;
}

// fetch current values
$enabledVal = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_enabled'")['gl_value'] ?? 0);
$catsVal = (string)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_categories'")['gl_value'] ?? '');
$timeoutVal = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_timeout'")['gl_value'] ?? 900);
$checkControlledSubstances = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_check_controlled_substances'")['gl_value'] ?? 0);
$ohioComplianceLogging = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_ohio_compliance_logging'")['gl_value'] ?? 0);
$selectedCats = array_filter(array_map('intval', explode(',', $catsVal)));

// category list
$catRes = sqlStatement('SELECT pc_catid, pc_catname FROM openemr_postcalendar_categories ORDER BY pc_catname');
$categories = [];
while ($r = sqlFetchArray($catRes)) {
    $categories[] = $r;
}

$token = CsrfUtils::collectCsrfToken();
?>
<html>
<head>
    <title><?php echo xlt('Step-Up MFA Settings'); ?></title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/interface/themes/style_sky_blue.css">
</head>
<body class="body_top">
<div class="container mt-3">
    <h2><?php echo xlt('Step-Up MFA Settings'); ?></h2>
    <p class="text-muted"><?php echo xlt('Enhanced for Ohio compliance requirements for controlled substances'); ?></p>
    
    <form method="post" class="">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($token); ?>"/>
        
        <div class="card mb-3">
            <div class="card-header">
                <h4><?php echo xlt('Basic Settings'); ?></h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label><input type="checkbox" name="enabled" value="1" <?php echo $enabledVal ? 'checked' : ''; ?>/> 
                    <?php echo xlt('Enable Step-Up MFA'); ?></label>
                </div>
                
                <div class="form-group">
                    <label><?php echo xlt('Sensitive Appointment Categories'); ?></label><br>
                    <select name="categories[]" multiple size="8" style="min-width:250px;">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['pc_catid']; ?>" <?php echo in_array((int)$cat['pc_catid'], $selectedCats, true) ? 'selected' : ''; ?>>
                                <?php echo text($cat['pc_catname']); ?> (<?php echo (int)$cat['pc_catid']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted"><?php echo xlt('Select categories that require MFA verification (e.g., Ketamine Infusion)'); ?></small>
                </div>
                
                <div class="form-group">
                    <label><?php echo xlt('MFA Grace Period (seconds)'); ?></label>
                    <input type="number" name="timeout" value="<?php echo (int)$timeoutVal; ?>" class="form-control" style="max-width:120px;">
                    <small class="form-text text-muted"><?php echo xlt('How long to remember MFA verification per patient (default: 900 = 15 minutes)'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h4><?php echo xlt('Ohio Compliance Features'); ?></h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label><input type="checkbox" name="check_controlled_substances" value="1" <?php echo $checkControlledSubstances ? 'checked' : ''; ?>/> 
                    <?php echo xlt('Enable Controlled Substance Detection'); ?></label>
                    <small class="form-text text-muted"><?php echo xlt('Automatically detect encounters with controlled substances (ketamine, opioids, benzodiazepines, etc.)'); ?></small>
                </div>
                
                <div class="form-group">
                    <label><input type="checkbox" name="ohio_compliance_logging" value="1" <?php echo $ohioComplianceLogging ? 'checked' : ''; ?>/> 
                    <?php echo xlt('Enhanced Ohio Compliance Logging'); ?></label>
                    <small class="form-text text-muted"><?php echo xlt('Log detailed information for Ohio Board of Pharmacy compliance requirements'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info">
            <h5><?php echo xlt('Ohio Compliance Information'); ?></h5>
            <p><?php echo xlt('This feature helps comply with Ohio Board of Pharmacy regulations for controlled substances:'); ?></p>
            <ul>
                <li><?php echo xlt('Requires MFA for access to encounters with controlled substances'); ?></li>
                <li><?php echo xlt('Logs all access attempts for audit purposes'); ?></li>
                <li><?php echo xlt('Supports both category-based and medication-based detection'); ?></li>
                <li><?php echo xlt('Maintains session-based verification with configurable timeout'); ?></li>
            </ul>
        </div>
        
        <br>
        <button type="submit" class="btn btn-primary"><?php echo xlt('Save Settings'); ?></button>
    </form>
    
    <div class="text-center mt-4">
        <a href="stepup_mfa_compliance_report.php" class="btn btn-info"><?php echo xlt('View Compliance Report'); ?></a>
        <a href="main_screen.php" class="btn btn-secondary"><?php echo xlt('Return to Main Screen'); ?></a>
    </div>
</div>
</body>
</html>
