<?php

/**
 * MFA Remembered Devices Management
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

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\OeUI\OemrUI;
use OpenEMR\Services\MfaRememberDeviceService;

// Ensure user is logged in
if (!isset($_SESSION['authUserID'])) {
    header("Location: ../main/main_screen.php");
    exit();
}

$userid = $_SESSION['authUserID'];
$action = $_REQUEST['action'] ?? '';
$mfaRememberService = new MfaRememberDeviceService();

// Handle actions
if ($action == 'revoke' && isset($_POST['device_id'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $deviceId = (int)$_POST['device_id'];
    if ($mfaRememberService->revokeDevice($deviceId, $userid)) {
        $successMsg = xl('Device revoked successfully');
    } else {
        $errorMsg = xl('Failed to revoke device');
    }
}

if ($action == 'revoke_all') {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $mfaRememberService->invalidateAllUserTokens($userid);
    $mfaRememberService->clearRememberCookie();
    $successMsg = xl('All remembered devices revoked successfully');
}

// Get user's remembered devices
$devices = $mfaRememberService->getUserRememberedDevices($userid);

$oemr_ui = new OemrUI();
?>
<html>
<head>
    <title><?php echo xlt('MFA Remembered Devices'); ?></title>
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
                        <h4><?php echo xlt('Remembered Devices'); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($devices)): ?>
                            <p><?php echo xlt('No remembered devices found.'); ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th><?php echo xlt('Device Info'); ?></th>
                                            <th><?php echo xlt('IP Address'); ?></th>
                                            <th><?php echo xlt('Created'); ?></th>
                                            <th><?php echo xlt('Last Used'); ?></th>
                                            <th><?php echo xlt('Expires'); ?></th>
                                            <th><?php echo xlt('Actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($devices as $device): ?>
                                            <tr>
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
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                                    <input type="hidden" name="action" value="revoke_all" />
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('<?php echo xla('Are you sure you want to revoke ALL remembered devices? This will require MFA on all devices.'); ?>')">
                                        <?php echo xlt('Revoke All Devices'); ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-sm-12">
                <a href="mfa_registrations.php" class="btn btn-secondary">
                    <?php echo xlt('Back to MFA Settings'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <?php $oemr_ui->oeBelowContainerDiv(); ?>
</body>
</html>

// AI GENERATED CODE END 