#!/usr/bin/env php
<?php

/**
 * Cleanup script for expired MFA remembered device tokens
 *
 * This script should be run via cron job to clean up expired tokens.
 * Example cron job: 0 2 * * * /path/to/openemr/bin/cleanup-mfa-remembered-devices.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2024 Your Name
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// AI GENERATED CODE START

// Include OpenEMR globals
require_once(dirname(__FILE__) . '/../interface/globals.php');
require_once($GLOBALS['srcdir'] . '/src/Services/MfaRememberDeviceService.php');

use OpenEMR\Services\MfaRememberDeviceService;

// Initialize the service
$mfaRememberService = new MfaRememberDeviceService();

// Clean up expired tokens
$deletedCount = $mfaRememberService->cleanupExpiredTokens();

// Log the cleanup
if ($deletedCount > 0) {
    error_log("OpenEMR MFA Cleanup: Deleted {$deletedCount} expired remembered device tokens");
} else {
    error_log("OpenEMR MFA Cleanup: No expired tokens found");
}

echo "Cleanup completed. Deleted {$deletedCount} expired tokens.\n";

// AI GENERATED CODE END 