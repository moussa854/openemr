<?php

/**
 * Admin MFA Remembered Devices Management
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2024 Your Name
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// AI GENERATED CODE START

// Set $sessionAllowWrite to true to prevent session concurrency issues during authorization related code
$sessionAllowWrite = true;
require_once('../globals.php');
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\OeUI\OemrUI;
use OpenEMR\Services\MfaRememberDeviceService;

// Check authorization
$thisauth = AclMain::aclCheckCore('admin', 'super');
if (!$thisauth) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("MFA Device Management")]);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$mfaRememberService = new MfaRememberDeviceService();

// Handle actions
if ($action == 'revoke' && isset($_POST['device_id'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $deviceId = (int)$_POST['device_id'];
    $result = sqlQuery("SELECT user_id FROM mfa_remembered_devices WHERE id = ?", [$deviceId]);
    if ($result && $mfaRememberService->revokeDevice($deviceId, $result['user_id'])) {
        $successMsg = xl('Device revoked successfully');
    } else {
        $errorMsg = xl('Failed to revoke device');
    }
}

if ($action == 'revoke_all') {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $userId = (int)$_POST['user_id'];
    $mfaRememberService->invalidateAllUserTokens($userId);
    $successMsg = xl('All devices for user revoked successfully');
}

if ($action == 'cleanup_expired') {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $deletedCount = $mfaRememberService->cleanupExpiredTokens();
    $successMsg = xl('Cleanup completed') . ': ' . $deletedCount . ' ' . xl('expired tokens removed');
}

// Get all remembered devices with user info
$devices = sqlStatement("
    SELECT d.*, u.username, u.fname, u.lname 
    FROM mfa_remembered_devices d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.expires_at > NOW() 
    ORDER BY d.created_at DESC
");

$oemr_ui = new OemrUI();
?>
<html>
<head>
    <title><?php echo xlt('MFA Remembered Devices Management'); ?></title>
    <?php Header::setupHeader(['no_main-theme', 'no-fonts']); ?>
</head>

<body class="body_top">
    <div id="container_div" class="<?php echo $oemr_ui->oeContainer();?>">
        <div class="row">
            <div class="col-sm-12">
                <?php echo $oemr_ui->pageHeading() . "\r\n"; ?>
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
        
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo xlt('System-Wide Remembered Devices'); ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-12">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                                    <input type="hidden" name="action" value="cleanup_expired" />
                                    <button type="submit" class="btn btn-warning">
                                        <?php echo xlt('Cleanup Expired Tokens'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo xlt('User'); ?></th>
                                        <th><?php echo xlt('Device Info'); ?></th>
                                        <th><?php echo xlt('IP Address'); ?></th>
                                        <th><?php echo xlt('Created'); ?></th>
                                        <th><?php echo xlt('Last Used'); ?></th>
                                        <th><?php echo xlt('Expires'); ?></th>
                                        <th><?php echo xlt('Actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $deviceCount = 0;
                                    while ($device = sqlFetchArray($devices)): 
                                        $deviceCount++;
                                    ?>
                                        <tr>
                                            <td>
                                                <?php echo text($device['fname'] . ' ' . $device['lname'] . ' (' . $device['username'] . ')'); ?>
                                            </td>
                                            <td><?php echo text($device['device_info'] ?: xl('Unknown Device')); ?></td>
                                            <td><?php echo text($device['ip_address'] ?: xl('Unknown')); ?></td>
                                            <td><?php echo text(date('Y-m-d H:i:s', strtotime($device['created_at']))); ?></td>
                                            <td><?php echo text($device['last_used'] ? date('Y-m-d H:i:s', strtotime($device['last_used'])) : xl('Never')); ?></td>
                                            <td><?php echo text(date('Y-m-d H:i:s', strtotime($device['expires_at']))); ?></td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                                                    <input type="hidden" name="action" value="revoke" />
                                                    <input type="hidden" name="device_id" value="<?php echo attr($device['id']); ?>" />
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?php echo xla('Are you sure you want to revoke this device?'); ?>')">
                                                        <?php echo xlt('Revoke'); ?>
                                                    </button>
                                                </form>
                                                
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                                                    <input type="hidden" name="action" value="revoke_all" />
                                                    <input type="hidden" name="user_id" value="<?php echo attr($device['user_id']); ?>" />
                                                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('<?php echo xla('Are you sure you want to revoke ALL devices for this user?'); ?>')">
                                                        <?php echo xlt('Revoke All User Devices'); ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if ($deviceCount == 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                <?php echo xlt('No active remembered devices found.'); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-sm-12">
                                <p><strong><?php echo xlt('Total Active Devices'); ?>: <?php echo $deviceCount; ?></strong></p>
                            </div>
                        </div>
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

// AI GENERATED CODE END 