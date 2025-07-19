<?php

/**
 * Emergency MFA Bypass System
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2024 Your Name
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Set $sessionAllowWrite to true to prevent session concurrency issues during authorization related code
$sessionAllowWrite = true;
require_once('../globals.php');
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Core\Header;
use OpenEMR\OeUI\OemrUI;
use OpenEMR\Services\MfaRememberDeviceService;

// Check authorization - only super admins
$thisauth = AclMain::aclCheckCore('admin', 'super');
if (!$thisauth) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Emergency MFA Bypass")]);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$mfaRememberService = new MfaRememberDeviceService();

// Handle emergency bypass generation
if ($action == 'generate_bypass' && isset($_POST['user_id'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $userId = (int)$_POST['user_id'];
    $adminPassword = $_POST['admin_password'] ?? '';
    
    // Verify admin password
    $adminUser = sqlQuery("SELECT password FROM users WHERE id = ?", [$_SESSION['authUserID']]);
    if (!password_verify($adminPassword, $adminUser['password'])) {
        $errorMsg = xl('Invalid administrator password');
    } else {
        // Generate one-time bypass code
        $bypassCode = sprintf('%08d', mt_rand(10000000, 99999999));
        $bypassHash = password_hash($bypassCode, PASSWORD_DEFAULT);
        
        // Store bypass code (valid for 24 hours)
        sqlStatement(
            "INSERT INTO mfa_emergency_codes (user_id, code_hash, created_by, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL 1 DAY)",
            [$userId, $bypassHash, $_SESSION['authUserID']]
        );
        
        // Log the emergency action
        EventAuditLogger::instance()->newEvent(
            'security',
            $_SESSION['authUserID'],
            $_SESSION['authUser'],
            $_SESSION['authUser'],
            'Emergency MFA bypass generated for user ID: ' . $userId,
            'Emergency MFA Bypass'
        );
        
        $successMsg = xl('Emergency bypass code generated successfully');
        $bypassCodeDisplay = $bypassCode;
    }
}

// Handle bypass code verification
if ($action == 'verify_bypass' && isset($_POST['bypass_code'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $bypassCode = $_POST['bypass_code'];
    $userId = (int)$_POST['user_id'];
    
    // Check if bypass code exists and is valid
    $result = sqlQuery(
        "SELECT * FROM mfa_emergency_codes WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
        [$userId]
    );
    
    if ($result && password_verify($bypassCode, $result['code_hash'])) {
        // Clear all MFA registrations for this user
        sqlStatement("DELETE FROM login_mfa_registrations WHERE user_id = ?", [$userId]);
        
        // Clear all remembered devices
        $mfaRememberService->invalidateAllUserTokens($userId);
        
        // Delete the used bypass code
        sqlStatement("DELETE FROM mfa_emergency_codes WHERE id = ?", [$result['id']]);
        
        // Log the bypass usage
        EventAuditLogger::instance()->newEvent(
            'security',
            $_SESSION['authUserID'],
            $_SESSION['authUser'],
            $_SESSION['authUser'],
            'Emergency MFA bypass used for user ID: ' . $userId,
            'Emergency MFA Bypass Used'
        );
        
        $successMsg = xl('MFA has been disabled for this user. They can now log in without MFA and set up new MFA if needed.');
    } else {
        $errorMsg = xl('Invalid or expired bypass code');
    }
}

// Get users with MFA enabled
$usersWithMfa = sqlStatement("
    SELECT DISTINCT u.id, u.username, u.fname, u.lname, u.active
    FROM users u 
    JOIN login_mfa_registrations mfa ON u.id = mfa.user_id 
    WHERE u.active = 1
    ORDER BY u.lname, u.fname
");

$oemr_ui = new OemrUI();
?>
<html>
<head>
    <title><?php echo xlt('Emergency MFA Bypass'); ?></title>
    <?php Header::setupHeader(['no_main-theme', 'no-fonts']); ?>
</head>

<body class="body_top">
    <div id="container_div" class="<?php echo $oemr_ui->oeContainer();?>">
        <div class="row">
            <div class="col-sm-12">
                <?php echo $oemr_ui->pageHeading() . "\r\n"; ?>
            </div>
        </div>
        
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-warning">
                    <strong><?php echo xlt('Warning'); ?>:</strong> 
                    <?php echo xlt('This tool should only be used in emergency situations when a user is locked out of their account due to MFA issues.'); ?>
                </div>
            </div>
        </div>
        
        <?php if (isset($successMsg)): ?>
            <div class="row">
                <div class="col-sm-12">
                    <div class="alert alert-success"><?php echo text($successMsg); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="row">
                <div class="col-sm-12">
                    <div class="alert alert-danger"><?php echo text($errorMsg); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($bypassCodeDisplay)): ?>
            <div class="row">
                <div class="col-sm-12">
                    <div class="alert alert-info">
                        <strong><?php echo xlt('Emergency Bypass Code'); ?>:</strong> 
                        <span style="font-size: 1.2em; font-weight: bold; font-family: monospace;"><?php echo text($bypassCodeDisplay); ?></span>
                        <br><br>
                        <?php echo xlt('This code is valid for 24 hours. Provide this code to the user so they can bypass MFA during their next login.'); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-sm-6">
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo xlt('Generate Emergency Bypass Code'); ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                            <input type="hidden" name="action" value="generate_bypass" />
                            
                            <div class="form-group">
                                <label for="user_id"><?php echo xlt('Select User'); ?></label>
                                <select name="user_id" id="user_id" class="form-control" required>
                                    <option value=""><?php echo xlt('-- Select User --'); ?></option>
                                    <?php while ($user = sqlFetchArray($usersWithMfa)): ?>
                                        <option value="<?php echo attr($user['id']); ?>">
                                            <?php echo text($user['fname'] . ' ' . $user['lname'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_password"><?php echo xlt('Your Administrator Password'); ?></label>
                                <input type="password" name="admin_password" id="admin_password" class="form-control" required>
                                <small class="form-text text-muted">
                                    <?php echo xlt('Enter your administrator password to confirm this emergency action.'); ?>
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-warning" onclick="return confirm('<?php echo xla('Are you sure you want to generate an emergency bypass code? This action will be logged.'); ?>')">
                                <?php echo xlt('Generate Emergency Bypass Code'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-sm-6">
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo xlt('Verify and Use Bypass Code'); ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                            <input type="hidden" name="action" value="verify_bypass" />
                            
                            <div class="form-group">
                                <label for="verify_user_id"><?php echo xlt('Select User'); ?></label>
                                <select name="user_id" id="verify_user_id" class="form-control" required>
                                    <option value=""><?php echo xlt('-- Select User --'); ?></option>
                                    <?php 
                                    sqlDataSeek($usersWithMfa, 0);
                                    while ($user = sqlFetchArray($usersWithMfa)): 
                                    ?>
                                        <option value="<?php echo attr($user['id']); ?>">
                                            <?php echo text($user['fname'] . ' ' . $user['lname'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="bypass_code"><?php echo xlt('Bypass Code'); ?></label>
                                <input type="text" name="bypass_code" id="bypass_code" class="form-control" maxlength="8" pattern="[0-9]{8}" required>
                                <small class="form-text text-muted">
                                    <?php echo xlt('Enter the 8-digit bypass code provided to the user.'); ?>
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-danger" onclick="return confirm('<?php echo xla('Are you sure you want to disable MFA for this user? This action will be logged.'); ?>')">
                                <?php echo xlt('Disable MFA for User'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-sm-12">
                <a href="edit_globals.php" class="btn btn-secondary">
                    <?php echo xlt('Back to Configuration'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <?php $oemr_ui->oeBelowContainerDiv(); ?>
</body>
</html> 