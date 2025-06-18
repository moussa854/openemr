<?php

/**
 * MFA Settings Management UI
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Your Name <you@example.com>
 * @copyright Copyright (c) 2023 Your Name <you@example.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\UI\OemrUI;

// Ensure user is logged in
if (!isset($_SESSION['authUserID']) || empty($_SESSION['authUserID'])) {
    // Redirect to login or show error
    header("Location: " . $GLOBALS['webroot'] . "/interface/login/login.php");
    exit;
}

$current_user_id = $_SESSION['authUserID'];
$success_message = '';
$error_message = '';

// Handle MFA settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mfa_settings'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        $error_message = xl('CSRF token validation failed. Please try again.');
    } else {
        $mfa_requirement_level = $_POST['mfa_requirement_level'] ?? 'disabled';
        $mfa_grace_period_days = filter_input(INPUT_POST, 'mfa_grace_period_days', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        $allowed_mfa_levels = ['disabled', 'trusted_device_optional', 'always'];
        if (!in_array($mfa_requirement_level, $allowed_mfa_levels)) {
            $error_message .= xl('Invalid MFA requirement level selected.') . "<br>";
            $mfa_requirement_level = 'disabled'; // default to safe value
        }

        if ($mfa_grace_period_days === false || $mfa_grace_period_days === null) {
            $error_message .= xl('Invalid grace period. Please enter a number between 1 and 365.') . "<br>";
            // Fetch current grace period to avoid overwriting with bad data, or use a default
            $user_data_for_grace = sqlQuery("SELECT mfa_grace_period FROM users WHERE id = ?", [$current_user_id]);
            $mfa_grace_period_seconds = $user_data_for_grace['mfa_grace_period'] ?? (30 * 24 * 60 * 60);
        } else {
            $mfa_grace_period_seconds = $mfa_grace_period_days * 24 * 60 * 60;
        }

        if (empty($error_message)) {
            $stmt = $GLOBALS['dbh']->prepare("UPDATE users SET mfa_required = ?, mfa_grace_period = ? WHERE id = ?");
            if ($stmt->execute([$mfa_requirement_level, $mfa_grace_period_seconds, $current_user_id])) {
                $success_message = xl('MFA settings saved successfully.');
                // Update session variables if they exist, or re-evaluate on next login
                if (isset($_SESSION['mfa_required'])) {
                    $_SESSION['mfa_required'] = $mfa_requirement_level;
                }
            } else {
                $error_message = xl('Failed to save MFA settings.');
            }
        }
    }
}

// Handle device revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_device'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        $error_message = xl('CSRF token validation failed. Please try again.');
    } else {
        $device_identifier_to_revoke = $_POST['device_identifier_to_revoke'] ?? '';
        if (!empty($device_identifier_to_revoke)) {
            $stmt = $GLOBALS['dbh']->prepare("DELETE FROM login_mfa_trusted_devices WHERE user_id = ? AND device_identifier = ?");
            if ($stmt->execute([$current_user_id, $device_identifier_to_revoke])) {
                $success_message = xl('Trusted device revoked successfully.');
                // Also remove the cookie if it matches the revoked device
                if (isset($_COOKIE['openemr_device_identifier']) && $_COOKIE['openemr_device_identifier'] === $device_identifier_to_revoke) {
                    $cookieParams = [
                        'expires' => time() - 3600, // Expire in the past
                        'path' => $GLOBALS['webroot'] . '/',
                        'secure' => isset($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ];
                    setcookie('openemr_device_identifier', '', $cookieParams);
                }
            } else {
                $error_message = xl('Failed to revoke trusted device.');
            }
        } else {
            $error_message = xl('No device identifier provided for revocation.');
        }
    }
}


// Fetch current user settings
$user_settings = sqlQuery("SELECT mfa_required, mfa_grace_period FROM users WHERE id = ?", [$current_user_id]);
$current_mfa_level = $user_settings['mfa_required'] ?? 'disabled';
$current_grace_period_seconds = $user_settings['mfa_grace_period'] ?? (30 * 24 * 60 * 60);
$current_grace_period_days = $current_grace_period_seconds / (24 * 60 * 60);

// Fetch trusted devices
$trusted_devices = sqlStatement("SELECT device_identifier, expires_at FROM login_mfa_trusted_devices WHERE user_id = ? ORDER BY expires_at DESC", [$current_user_id]);

$ui = new OemrUI(xlt('MFA Settings'), PAGE_MODE_USERGROUP, [ 'user' => $_SESSION['authUser'] ]);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('MFA Settings'); ?></title>
    <?php $ui->standardHeaders(); ?>
</head>
<body class="body_top">

<?php $ui->top(); ?>

<div class="container">
    <h3><?php echo xlt('Multi-Factor Authentication Settings'); ?></h3>

    <?php if ($success_message): ?>
        <div class="alert alert-success" role="alert"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="mfa_settings.php" name="mfaSettingsForm">
        <?php echo csrf_token_form(); ?>
        <div class="form-group">
            <label for="mfa_requirement_level"><?php echo xlt('MFA Requirement'); ?></label>
            <select name="mfa_requirement_level" id="mfa_requirement_level" class="form-control">
                <option value="disabled" <?php echo ($current_mfa_level === 'disabled') ? 'selected' : ''; ?>><?php echo xlt('Disabled'); ?></option>
                <option value="trusted_device_optional" <?php echo ($current_mfa_level === 'trusted_device_optional') ? 'selected' : ''; ?>><?php echo xlt('Trusted Device Optional'); ?></option>
                <option value="always" <?php echo ($current_mfa_level === 'always') ? 'selected' : ''; ?>><?php echo xlt('Always (Require MFA for every login)'); ?></option>
            </select>
        </div>

        <div class="form-group">
            <label for="mfa_grace_period_days"><?php echo xlt('Trusted Device Grace Period (days)'); ?></label>
            <input type="number" name="mfa_grace_period_days" id="mfa_grace_period_days" class="form-control"
                   value="<?php echo htmlspecialchars((string)$current_grace_period_days, ENT_QUOTES); ?>" min="1" max="365" required>
            <small class="form-text text-muted"><?php echo xlt('Number of days a device will be trusted after successful MFA (1-365).'); ?></small>
        </div>

        <button type="submit" name="save_mfa_settings" class="btn btn-primary"><?php echo xlt('Save MFA Settings'); ?></button>
    </form>

    <hr>

    <h4><?php echo xlt('Trusted Devices'); ?></h4>
    <?php if (sqlNumRows($trusted_devices) > 0): ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo xlt('Device Identifier'); ?></th>
                    <th><?php echo xlt('Expires At'); ?></th>
                    <th><?php echo xlt('Action'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($device = sqlFetchArray($trusted_devices)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($device['device_identifier'], ENT_QUOTES); ?></td>
                        <td><?php echo htmlspecialchars(oeFormatShortDate($device['expires_at']) . " " . oeFormatShortTime($device['expires_at']), ENT_QUOTES); ?></td>
                        <td>
                            <form method="POST" action="mfa_settings.php" style="display: inline;" onsubmit="return confirm('<?php echo xlj('Are you sure you want to revoke this device?'); ?>');">
                                <?php echo csrf_token_form(); ?>
                                <input type="hidden" name="device_identifier_to_revoke" value="<?php echo htmlspecialchars($device['device_identifier'], ENT_QUOTES); ?>">
                                <button type="submit" name="revoke_device" class="btn btn-danger btn-sm"><?php echo xlt('Revoke'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><?php echo xlt('You have no trusted devices saved.'); ?></p>
    <?php endif; ?>

</div>

<?php $ui->bottom(); ?>
</body>
</html>
