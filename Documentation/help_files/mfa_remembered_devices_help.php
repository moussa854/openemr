<?php
/**
 * MFA Remembered Devices Help
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2024 Your Name
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// AI GENERATED CODE START

require_once("../../interface/globals.php");
require_once("$srcdir/options.inc.php");
?>

<div class="row">
    <div class="col-sm-12">
        <div>
            <h2><?php echo xlt("MFA Remembered Devices"); ?></h2>
            
            <p><?php echo xlt("The MFA Remembered Devices feature allows users to remember their device for a specified period (default 30 days) after successful MFA authentication."); ?></p>
            
            <h3><?php echo xlt("How It Works"); ?></h3>
            <ul>
                <li><?php echo xlt("When a user successfully completes MFA authentication, they can check 'Remember this device for 30 days'"); ?></li>
                <li><?php echo xlt("A secure token is generated and stored in the database"); ?></li>
                <li><?php echo xlt("A secure cookie is set on the user's device"); ?></li>
                <li><?php echo xlt("On subsequent logins, if the token is valid and not expired, MFA is bypassed"); ?></li>
            </ul>
            
            <h3><?php echo xlt("Security Features"); ?></h3>
            <ul>
                <li><?php echo xlt("Uses cryptographically secure random tokens"); ?></li>
                <li><?php echo xlt("Tokens are hashed before storage in the database"); ?></li>
                <li><?php echo xlt("Automatic token rotation on each use"); ?></li>
                <li><?php echo xlt("Secure cookie settings (HttpOnly, Secure, SameSite)"); ?></li>
                <li><?php echo xlt("Automatic cleanup of expired tokens"); ?></li>
            </ul>
            
            <h3><?php echo xlt("Managing Remembered Devices"); ?></h3>
            <p><?php echo xlt("Users can manage their remembered devices through:"); ?></p>
            <ul>
                <li><?php echo xlt("Administration > Users > MFA Settings > Manage Remembered Devices"); ?></li>
                <li><?php echo xlt("View all remembered devices with device info and IP address"); ?></li>
                <li><?php echo xlt("Revoke individual devices or all devices at once"); ?></li>
            </ul>
            
            <h3><?php echo xlt("Installation"); ?></h3>
            <ol>
                <li><?php echo xlt("Run the SQL installation script: sql/install-mfa-remembered-devices.sql"); ?></li>
                <li><?php echo xlt("Set up a cron job to clean up expired tokens:"); ?>
                    <code>0 2 * * * /path/to/openemr/bin/cleanup-mfa-remembered-devices.php</code>
                </li>
            </ol>
        </div>
    </div>
</div>

// AI GENERATED CODE END 