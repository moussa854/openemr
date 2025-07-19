<?php

/**
 * MfaRememberDeviceService.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2024 Your Name
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// AI GENERATED CODE START

namespace OpenEMR\Services;

use OpenEMR\Common\Crypto\CryptoGen;

/**
 * Service for handling MFA device remembering functionality
 */
class MfaRememberDeviceService
{
    const COOKIE_NAME = 'mfa_remember';
    const DEFAULT_EXPIRY_DAYS = 30;
    const SELECTOR_LENGTH = 32;
    const VALIDATOR_LENGTH = 64;

    /**
     * Get the configured remember duration from globals
     *
     * @return int Number of days
     */
    public function getRememberDuration()
    {
        return (int)($GLOBALS['mfa_remember_duration'] ?? self::DEFAULT_EXPIRY_DAYS);
    }

    /**
     * Check if remember device is enabled globally
     *
     * @return bool
     */
    public function isRememberEnabled()
    {
        return !empty($GLOBALS['mfa_remember_enable']);
    }

    /**
     * Get the maximum devices per user setting
     *
     * @return int
     */
    public function getMaxDevicesPerUser()
    {
        return (int)($GLOBALS['mfa_max_devices_per_user'] ?? 5);
    }

    /**
     * Get the remember policy setting
     *
     * @return int
     */
    public function getRememberPolicy()
    {
        return (int)($GLOBALS['mfa_remember_policy'] ?? 0);
    }

    /**
     * Generate a new remember device token for a user
     *
     * @param int $userId The user ID
     * @param int $expiryDays Number of days the token should be valid (default 30)
     * @return array Array containing selector, validator, and cookie value
     */
    public function generateRememberToken($userId, $expiryDays = null)
    {
        // Use global setting if not specified
        if ($expiryDays === null) {
            $expiryDays = $this->getRememberDuration();
        }

        // Check device limit
        $maxDevices = $this->getMaxDevicesPerUser();
        if ($maxDevices > 0) {
            $currentCount = $this->getUserRememberedDevicesCount($userId);
            if ($currentCount >= $maxDevices) {
                // Remove oldest device
                $this->removeOldestDevice($userId);
            }
        }

        // Generate cryptographically secure random tokens
        $selector = bin2hex(random_bytes(self::SELECTOR_LENGTH / 2));
        $validator = bin2hex(random_bytes(self::VALIDATOR_LENGTH / 2));
        $validatorHash = hash('sha256', $validator);

        // Calculate expiry date
        $expiryDate = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

        // Get device and IP information
        $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Store the token in the database
        $sql = "INSERT INTO mfa_remembered_devices 
                (user_id, selector, validator_hash, device_info, ip_address, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        \sqlStatement($sql, [
            $userId,
            $selector,
            $validatorHash,
            $deviceInfo,
            $ipAddress,
            $expiryDate
        ]);

        // Return the data needed for the cookie
        return [
            'selector' => $selector,
            'validator' => $validator,
            'cookie_value' => $selector . ':' . $validator,
            'expiry' => time() + (86400 * $expiryDays)
        ];
    }

    /**
     * Validate a remember device token from a cookie
     *
     * @param string $cookieValue The cookie value (selector:validator)
     * @param int $userId The user ID to validate against
     * @return array|false Array with token data if valid, false otherwise
     */
    public function validateRememberToken($cookieValue, $userId)
    {
        if (empty($cookieValue)) {
            return false;
        }

        $parts = explode(':', $cookieValue, 2);
        if (count($parts) !== 2) {
            return false;
        }

        list($selector, $validator) = $parts;

        // Look up the token in the database
        $sql = "SELECT * FROM mfa_remembered_devices 
                WHERE selector = ? AND user_id = ? AND expires_at > NOW()";
        $result = \sqlQuery($sql, [$selector, $userId]);

        if (!$result) {
            return false;
        }

        // Validate the token hash
        $validatorHash = hash('sha256', $validator);
        if (!hash_equals($result['validator_hash'], $validatorHash)) {
            // Token mismatch - potential theft, invalidate all tokens for this user
            $this->invalidateAllUserTokens($userId);
            return false;
        }

        // Update last used timestamp
        \sqlStatement(
            "UPDATE mfa_remembered_devices SET last_used = NOW() WHERE id = ?",
            [$result['id']]
        );

        return $result;
    }

    /**
     * Set the remember device cookie
     *
     * @param string $cookieValue The cookie value to set
     * @param int $expiry The cookie expiry timestamp
     * @return void
     */
    public function setRememberCookie($cookieValue, $expiry)
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $httponly = true;
        $samesite = 'Strict';

        setcookie(
            self::COOKIE_NAME,
            $cookieValue,
            [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]
        );
    }

    /**
     * Clear the remember device cookie
     *
     * @return void
     */
    public function clearRememberCookie()
    {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }

    /**
     * Invalidate all remember tokens for a user
     *
     * @param int $userId The user ID
     * @return void
     */
    public function invalidateAllUserTokens($userId)
    {
        \sqlStatement(
            "DELETE FROM mfa_remembered_devices WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Clean up expired tokens (should be called by a cron job)
     *
     * @return int Number of tokens deleted
     */
    public function cleanupExpiredTokens()
    {
        $result = \sqlStatement(
            "DELETE FROM mfa_remembered_devices WHERE expires_at < NOW()"
        );
        return \generic_sql_affected_rows();
    }

    /**
     * Get remembered devices for a user
     *
     * @param int $userId The user ID
     * @return array Array of remembered devices
     */
    public function getUserRememberedDevices($userId)
    {
        $sql = "SELECT id, device_info, ip_address, created_at, last_used, expires_at 
                FROM mfa_remembered_devices 
                WHERE user_id = ? AND expires_at > NOW() 
                ORDER BY created_at DESC";
        
        $result = \sqlStatement($sql, [$userId]);
        $devices = [];
        
        while ($row = \sqlFetchArray($result)) {
            $devices[] = $row;
        }
        
        return $devices;
    }

    /**
     * Get count of remembered devices for a user
     *
     * @param int $userId The user ID
     * @return int Number of devices
     */
    public function getUserRememberedDevicesCount($userId)
    {
        $result = \sqlQuery(
            "SELECT COUNT(*) as count FROM mfa_remembered_devices WHERE user_id = ? AND expires_at > NOW()",
            [$userId]
        );
        return (int)($result['count'] ?? 0);
    }

    /**
     * Remove the oldest remembered device for a user
     *
     * @param int $userId The user ID
     * @return bool True if successful, false otherwise
     */
    public function removeOldestDevice($userId)
    {
        $result = \sqlStatement(
            "DELETE FROM mfa_remembered_devices WHERE id = ? AND user_id = ? AND id = (
                SELECT id FROM (
                    SELECT id FROM mfa_remembered_devices 
                    WHERE user_id = ? 
                    ORDER BY created_at ASC 
                    LIMIT 1
                ) as oldest
            )",
            [$userId, $userId, $userId]
        );
        return \generic_sql_affected_rows() > 0;
    }

    /**
     * Revoke a specific remembered device
     *
     * @param int $deviceId The device ID to revoke
     * @param int $userId The user ID (for security)
     * @return bool True if successful, false otherwise
     */
    public function revokeDevice($deviceId, $userId)
    {
        $result = \sqlStatement(
            "DELETE FROM mfa_remembered_devices WHERE id = ? AND user_id = ?",
            [$deviceId, $userId]
        );
        return \generic_sql_affected_rows() > 0;
    }

    /**
     * Check if user is required to use remember device based on policy
     *
     * @param int $userId The user ID
     * @return bool True if required
     */
    public function isRememberRequired($userId)
    {
        $policy = $this->getRememberPolicy();
        
        if ($policy == 0) {
            return false; // Optional
        }
        
        if ($policy == 1) {
            return true; // Required for all
        }
        
        if ($policy == 2) {
                    // Required for clinical staff only
        $userInfo = \sqlQuery(
            "SELECT authorized FROM users WHERE id = ?",
            [$userId]
        );
        return !empty($userInfo['authorized']);
        }
        
        return false;
    }
}

// AI GENERATED CODE END 