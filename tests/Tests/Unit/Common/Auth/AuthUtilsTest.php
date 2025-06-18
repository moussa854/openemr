<?php

namespace OpenEMR\Tests\Unit\Common\Auth;

use OpenEMR\Common\Auth\AuthUtils;
use OpenEMR\Common\Auth\AuthHash;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Services\UserService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

// Mock global functions
if (!function_exists('sqlQuery')) {
    function sqlQuery(string $query, array $params = [])
    {
        return \OpenEMR\Tests\Unit\Common\Auth\AuthUtilsTest::$sqlQueryMock($query, $params);
    }
}
if (!function_exists('privQuery')) {
    function privQuery(string $query, array $params = [])
    {
        return \OpenEMR\Tests\Unit\Common\Auth\AuthUtilsTest::$privQueryMock($query, $params);
    }
}
if (!function_exists('sqlStatement')) {
    function sqlStatement(string $query, array $params = [])
    {
        // For testing, we might not need to check results of statements
        return true;
    }
}
if (!function_exists('privStatement')) {
    function privStatement(string $query, array $params = [])
    {
        // For testing, we might not need to check results of statements
        return true;
    }
}

if (!function_exists('collectIpAddresses')) {
    function collectIpAddresses()
    {
        return ['ip_string' => '127.0.0.1'];
    }
}
if (!function_exists('xl')) {
    function xl(string $text)
    {
        return $text;
    }
}

class AuthUtilsTest extends TestCase
{
    /** @var AuthUtils */
    private $authUtils;

    /** @var MockObject|EventAuditLogger */
    private $mockEventAuditLogger;

    public static $sqlQueryMock;
    public static $privQueryMock;

    protected function setUp(): void
    {
        // Mock session superglobal
        $_SESSION = [];
        $_COOKIE = [];

        // Mock globals used within AuthUtils and auth.inc.php context
        $GLOBALS['gbl_ldap_enabled'] = 0;
        $GLOBALS['password_max_failed_logins'] = 5;
        $GLOBALS['ip_max_failed_logins'] = 10;
        $GLOBALS['time_reset_password_max_failed_logins'] = 300; // 5 minutes
        $GLOBALS['ip_time_reset_password_max_failed_logins'] = 300; // 5 minutes
        $GLOBALS['password_expiration_days'] = 0; // Disable expiration for simplicity unless testing it
        $GLOBALS['webroot'] = '/openemr'; // Example webroot

        // Mock EventAuditLogger
        $this->mockEventAuditLogger = $this->createMock(EventAuditLogger::class);
        EventAuditLogger::setInstance($this->mockEventAuditLogger); // Assuming a static setter

        // Mock UserService (if direct calls are made, otherwise mock its underlying DB calls)
        // For now, we'll mock the DB calls directly.

        $this->authUtils = new AuthUtils('login');

        // Initialize static mock delegates
        self::$sqlQueryMock = function(string $query, array $params = []) { return []; };
        self::$privQueryMock = function(string $query, array $params = []) { return []; };
    }

    protected function tearDown(): void
    {
        unset($_SESSION);
        unset($_COOKIE);
        EventAuditLogger::setInstance(null); // Clean up singleton
    }

    private function setupUserMocks(string $username, string $passwordHash, array $userInfo, array $userSecureInfo, array $userMfaSettings = [])
    {
        self::$privQueryMock = function (string $query, array $params) use ($username, $userInfo, $userSecureInfo) {
            if (strpos($query, "FROM `users` where BINARY `username` = ?") !== false && $params[0] === $username) {
                return $userInfo;
            }
            if (strpos($query, "FROM `users_secure` WHERE BINARY `username` = ?") !== false && $params[0] === $username) {
                return $userSecureInfo;
            }
            if (strpos($query, "SELECT `password` FROM `users_secure` WHERE BINARY `username` = ?") !== false && $params[0] === $username) {
                return ['password' => $userSecureInfo['password']];
            }
            if (strpos($query, "SELECT `last_update_password` FROM `users_secure` WHERE BINARY `username` = ?") !== false) {
                 return ['last_update_password' => date("Y-m-d H:i:s")]; // Assume not expired
            }
            // Mock for getAuthGroupForUser
            if (strpos($query, "SELECT gr.name FROM groups AS gr INNER JOIN users AS u ON u.id = gr.user") !== false && $params[0] === $username) {
                return ['name' => 'Default']; // Default group
            }
            return [];
        };

        self::$sqlQueryMock = function (string $query, array $params) use ($userMfaSettings, $userInfo) {
            if (strpos($query, "SELECT mfa_required, mfa_grace_period FROM users WHERE id = ?") !== false && $params[0] === $userInfo['id']) {
                return $userMfaSettings;
            }
             if (strpos($query, "SELECT `ip_auto_block_emailed`, `ip_force_block`, `ip_no_prevent_timing_attack`") !== false) {
                return ['ip_force_block' => 0, 'ip_no_prevent_timing_attack' => 0, 'ip_login_fail_counter' => 0]; // IP not blocked
            }
            if (strpos($query, "SELECT `ip_string` FROM `ip_tracking` WHERE `ip_string` = ?") !== false) {
                return ['ip_string' => $params[0]]; // IP exists
            }
            return [];
        };
    }

    public function testConfirmUserPasswordMfaDisabled()
    {
        $username = 'testuser';
        $password = 'password123';
        $passwordHash = (new AuthHash('auth'))->passwordHash($password);

        $userInfo = ['id' => 1, 'authorized' => 1, 'see_auth' => 1, 'active' => 1];
        $userSecureInfo = ['id' => 1, 'password' => $passwordHash, 'login_fail_counter' => 0];
        $userMfaSettings = ['mfa_required' => 'disabled', 'mfa_grace_period' => 2592000]; // 30 days

        $this->setupUserMocks($username, $passwordHash, $userInfo, $userSecureInfo, $userMfaSettings);

        // Mock AclExtended::aclGetGroupTitles to return a valid group count
        $mockAclExtended = $this->getMockBuilder(AclExtended::class)
                                 ->disableOriginalConstructor()
                                 ->getMock();
        // Can't mock static methods directly this way without more complex setups like AspectMock or Runkit.
        // For simplicity, if UserService::getAuthGroupForUser and AclExtended::aclGetGroupTitles are problematic,
        // they might need to be refactored for better testability or their DB queries mocked.
        // For now, assuming their DB queries are caught by the general privQueryMock.
        // If AclExtended::aclGetGroupTitles is static and not easily mockable, ensure its DB query returns > 0.
        // For example, by ensuring the privQueryMock for `gacl_aro_groups_map` returns a row.
        self::$privQueryMock = function (string $query, array $params) use ($username, $userInfo, $userSecureInfo) {
            // ... (previous mocks)
            if (strpos($query, "FROM gacl_aro_map AS am LEFT JOIN gacl_aro_groups_map") !== false) {
                return ['dummy_col' => 'dummy_val']; // Simulate user is in an ACL group
            }
             if (strpos($query, "SELECT `id`, `authorized`, `see_auth`, `active` from `users` where BINARY `username` = ?") !== false && $params[0] === $username) {
                return $userInfo;
            }
            if (strpos($query, "FROM `users_secure` WHERE BINARY `username` = ?") !== false && $params[0] === $username) {
                return $userSecureInfo;
            }
             if (strpos($query, "SELECT `password` FROM `users_secure` WHERE BINARY `username` = ?") !== false && $params[0] === $username) {
                return ['password' => $userSecureInfo['password']];
            }
            if (strpos($query, "SELECT `last_update_password` FROM `users_secure` WHERE BINARY `username` = ?") !== false) {
                 return ['last_update_password' => date("Y-m-d H:i:s")];
            }
            if (strpos($query, "SELECT gr.name FROM groups AS gr INNER JOIN users AS u ON u.id = gr.user") !== false && $params[0] === $username) {
                return ['name' => 'Default'];
            }
            return [];
        };


        $this->assertTrue($this->authUtils->confirmUserPassword($username, $password));
        $this->assertFalse(isset($_SESSION['mfarequired']), "MFA should not be required when disabled.");
        $this->assertEquals($username, $_SESSION['authUser']);
    }

    public function testConfirmUserPasswordMfaTrustedOptionalWithValidDevice()
    {
        $username = 'testuser_trusted_opt';
        $password = 'password123';
        $userId = 2;
        $passwordHash = (new AuthHash('auth'))->passwordHash($password);
        $deviceIdentifier = 'valid_device_id_optional';

        $userInfo = ['id' => $userId, 'authorized' => 1, 'see_auth' => 1, 'active' => 1];
        $userSecureInfo = ['id' => $userId, 'password' => $passwordHash, 'login_fail_counter' => 0];
        $userMfaSettings = ['mfa_required' => 'trusted_device_optional', 'mfa_grace_period' => 2592000];

        $this->setupUserMocks($username, $passwordHash, $userInfo, $userSecureInfo, $userMfaSettings);

        // Override sqlQueryMock for this specific test to return a trusted device
        $originalSqlQueryMock = self::$sqlQueryMock;
        self::$sqlQueryMock = function (string $query, array $params) use ($originalSqlQueryMock, $userId, $deviceIdentifier, $userMfaSettings) {
            if (strpos($query, "SELECT mfa_required, mfa_grace_period FROM users WHERE id = ?") !== false && $params[0] === $userId) {
                return $userMfaSettings;
            }
            if (strpos($query, "SELECT expires_at FROM login_mfa_trusted_devices WHERE user_id = ? AND device_identifier = ?") !== false) {
                if ($params[0] === $userId && $params[1] === $deviceIdentifier) {
                    return ['expires_at' => date('Y-m-d H:i:s', time() + 3600)]; // Expires in 1 hour
                }
            }
            if (strpos($query, "SELECT `ip_auto_block_emailed`, `ip_force_block`, `ip_no_prevent_timing_attack`") !== false) {
                return ['ip_force_block' => 0, 'ip_no_prevent_timing_attack' => 0, 'ip_login_fail_counter' => 0];
            }
             if (strpos($query, "SELECT `ip_string` FROM `ip_tracking` WHERE `ip_string` = ?") !== false) {
                return ['ip_string' => $params[0]];
            }
            return $originalSqlQueryMock($query, $params);
        };

        $_COOKIE['openemr_device_identifier'] = $deviceIdentifier;

        $this->assertTrue($this->authUtils->confirmUserPassword($username, $password));
        $this->assertFalse(isset($_SESSION['mfarequired']), "MFA should be bypassed with a valid trusted device on optional setting.");
        $this->assertTrue(isset($_SESSION['mfa_device_trusted_optional_bypass']), "MFA bypass flag should be set.");
        $this->assertEquals($username, $_SESSION['authUser'], "User should be fully logged in.");

        self::$sqlQueryMock = $originalSqlQueryMock; // Restore original mock
    }

    public function testConfirmUserPasswordMfaTrustedOptionalWithExpiredDevice()
    {
        $username = 'testuser_trusted_expired';
        $password = 'password123';
        $userId = 3;
        $passwordHash = (new AuthHash('auth'))->passwordHash($password);
        $deviceIdentifier = 'expired_device_id_optional';

        $userInfo = ['id' => $userId, 'authorized' => 1, 'see_auth' => 1, 'active' => 1];
        $userSecureInfo = ['id' => $userId, 'password' => $passwordHash, 'login_fail_counter' => 0];
        $userMfaSettings = ['mfa_required' => 'trusted_device_optional', 'mfa_grace_period' => 2592000];

        $this->setupUserMocks($username, $passwordHash, $userInfo, $userSecureInfo, $userMfaSettings);

        $originalSqlQueryMock = self::$sqlQueryMock;
        self::$sqlQueryMock = function (string $query, array $params) use ($originalSqlQueryMock, $userId, $deviceIdentifier, $userMfaSettings) {
            if (strpos($query, "SELECT mfa_required, mfa_grace_period FROM users WHERE id = ?") !== false && $params[0] === $userId) {
                return $userMfaSettings;
            }
            if (strpos($query, "SELECT expires_at FROM login_mfa_trusted_devices WHERE user_id = ? AND device_identifier = ?") !== false) {
                if ($params[0] === $userId && $params[1] === $deviceIdentifier) {
                    return ['expires_at' => date('Y-m-d H:i:s', time() - 3600)]; // Expired 1 hour ago
                }
            }
            if (strpos($query, "SELECT `ip_auto_block_emailed`, `ip_force_block`, `ip_no_prevent_timing_attack`") !== false) {
                return ['ip_force_block' => 0, 'ip_no_prevent_timing_attack' => 0, 'ip_login_fail_counter' => 0];
            }
            if (strpos($query, "SELECT `ip_string` FROM `ip_tracking` WHERE `ip_string` = ?") !== false) {
                return ['ip_string' => $params[0]];
            }
            return $originalSqlQueryMock($query, $params);
        };

        $_COOKIE['openemr_device_identifier'] = $deviceIdentifier;

        $this->assertTrue($this->authUtils->confirmUserPassword($username, $password));
        $this->assertTrue(isset($_SESSION['mfarequired']) && $_SESSION['mfarequired'] === true, "MFA should be required with an expired trusted device.");
        $this->assertEquals($username, $_SESSION['tempAuthUser']); // Should be in temp session for MFA step

        self::$sqlQueryMock = $originalSqlQueryMock;
    }

    public function testConfirmUserPasswordMfaTrustedOptionalWithInvalidDevice()
    {
        $username = 'testuser_trusted_invalid';
        $password = 'password123';
        $userId = 4;
        $passwordHash = (new AuthHash('auth'))->passwordHash($password);
        $deviceIdentifier = 'invalid_device_id_optional';

        $userInfo = ['id' => $userId, 'authorized' => 1, 'see_auth' => 1, 'active' => 1];
        $userSecureInfo = ['id' => $userId, 'password' => $passwordHash, 'login_fail_counter' => 0];
        $userMfaSettings = ['mfa_required' => 'trusted_device_optional', 'mfa_grace_period' => 2592000];

        $this->setupUserMocks($username, $passwordHash, $userInfo, $userSecureInfo, $userMfaSettings);

        $originalSqlQueryMock = self::$sqlQueryMock;
        self::$sqlQueryMock = function (string $query, array $params) use ($originalSqlQueryMock, $userId, $deviceIdentifier, $userMfaSettings) {
            if (strpos($query, "SELECT mfa_required, mfa_grace_period FROM users WHERE id = ?") !== false && $params[0] === $userId) {
                return $userMfaSettings;
            }
            if (strpos($query, "SELECT expires_at FROM login_mfa_trusted_devices WHERE user_id = ? AND device_identifier = ?") !== false) {
                 // Simulate device not found
                if ($params[0] === $userId && $params[1] === $deviceIdentifier) {
                    return null;
                }
            }
            if (strpos($query, "SELECT `ip_auto_block_emailed`, `ip_force_block`, `ip_no_prevent_timing_attack`") !== false) {
                return ['ip_force_block' => 0, 'ip_no_prevent_timing_attack' => 0, 'ip_login_fail_counter' => 0];
            }
            if (strpos($query, "SELECT `ip_string` FROM `ip_tracking` WHERE `ip_string` = ?") !== false) {
                return ['ip_string' => $params[0]];
            }
            return $originalSqlQueryMock($query, $params);
        };

        $_COOKIE['openemr_device_identifier'] = $deviceIdentifier;

        $this->assertTrue($this->authUtils->confirmUserPassword($username, $password));
        $this->assertTrue(isset($_SESSION['mfarequired']) && $_SESSION['mfarequired'] === true, "MFA should be required with an invalid trusted device.");
        $this->assertEquals($username, $_SESSION['tempAuthUser']);

        self::$sqlQueryMock = $originalSqlQueryMock;
    }

    public function testConfirmUserPasswordMfaTrustedOptionalWithoutDeviceCookie()
    {
        $username = 'testuser_trusted_no_cookie';
        $password = 'password123';
        $userId = 5;
        $passwordHash = (new AuthHash('auth'))->passwordHash($password);

        $userInfo = ['id' => $userId, 'authorized' => 1, 'see_auth' => 1, 'active' => 1];
        $userSecureInfo = ['id' => $userId, 'password' => $passwordHash, 'login_fail_counter' => 0];
        $userMfaSettings = ['mfa_required' => 'trusted_device_optional', 'mfa_grace_period' => 2592000];

        $this->setupUserMocks($username, $passwordHash, $userInfo, $userSecureInfo, $userMfaSettings);
        // No cookie is set for this test

        $this->assertTrue($this->authUtils->confirmUserPassword($username, $password));
        $this->assertTrue(isset($_SESSION['mfarequired']) && $_SESSION['mfarequired'] === true, "MFA should be required without a device cookie.");
        $this->assertEquals($username, $_SESSION['tempAuthUser']);
    }

    public function testConfirmUserPasswordMfaAlwaysWithValidDevice()
    {
        $username = 'testuser_mfa_always_valid_device';
        $password = 'password123';
        $userId = 6;
        $passwordHash = (new AuthHash('auth'))->passwordHash($password);
        $deviceIdentifier = 'valid_device_id_always';

        $userInfo = ['id' => $userId, 'authorized' => 1, 'see_auth' => 1, 'active' => 1];
        $userSecureInfo = ['id' => $userId, 'password' => $passwordHash, 'login_fail_counter' => 0];
        $userMfaSettings = ['mfa_required' => 'always', 'mfa_grace_period' => 2592000];

        $this->setupUserMocks($username, $passwordHash, $userInfo, $userSecureInfo, $userMfaSettings);

        $originalSqlQueryMock = self::$sqlQueryMock;
        self::$sqlQueryMock = function (string $query, array $params) use ($originalSqlQueryMock, $userId, $deviceIdentifier, $userMfaSettings) {
            if (strpos($query, "SELECT mfa_required, mfa_grace_period FROM users WHERE id = ?") !== false && $params[0] === $userId) {
                return $userMfaSettings;
            }
            if (strpos($query, "SELECT expires_at FROM login_mfa_trusted_devices WHERE user_id = ? AND device_identifier = ?") !== false) {
                if ($params[0] === $userId && $params[1] === $deviceIdentifier) {
                    return ['expires_at' => date('Y-m-d H:i:s', time() + 3600)]; // Expires in 1 hour
                }
            }
            if (strpos($query, "SELECT `ip_auto_block_emailed`, `ip_force_block`, `ip_no_prevent_timing_attack`") !== false) {
                return ['ip_force_block' => 0, 'ip_no_prevent_timing_attack' => 0, 'ip_login_fail_counter' => 0];
            }
            if (strpos($query, "SELECT `ip_string` FROM `ip_tracking` WHERE `ip_string` = ?") !== false) {
                return ['ip_string' => $params[0]];
            }
            return $originalSqlQueryMock($query, $params);
        };

        $_COOKIE['openemr_device_identifier'] = $deviceIdentifier;

        $this->assertTrue($this->authUtils->confirmUserPassword($username, $password));
        $this->assertTrue(isset($_SESSION['mfarequired']) && $_SESSION['mfarequired'] === true, "MFA should be required even with a valid trusted device when set to 'always'.");
        $this->assertTrue(isset($_SESSION['mfa_device_trusted_always_active']), "MFA trusted device always active flag should be set.");
        $this->assertEquals($username, $_SESSION['tempAuthUser']);
        $this->assertFalse(isset($_SESSION['mfa_device_trusted_optional_bypass']), "MFA optional bypass flag should NOT be set.");
        $this->assertFalse(isset($_SESSION['authUser']), "User should NOT be fully logged in yet.");


        self::$sqlQueryMock = $originalSqlQueryMock;
    }

    public function testConfirmUserPasswordMfaAlwaysWithoutDeviceCookie()
    {
        $username = 'testuser_mfa_always_no_cookie';
        $password = 'password123';
        $userId = 7;
        $passwordHash = (new AuthHash('auth'))->passwordHash($password);

        $userInfo = ['id' => $userId, 'authorized' => 1, 'see_auth' => 1, 'active' => 1];
        $userSecureInfo = ['id' => $userId, 'password' => $passwordHash, 'login_fail_counter' => 0];
        $userMfaSettings = ['mfa_required' => 'always', 'mfa_grace_period' => 2592000];

        $this->setupUserMocks($username, $passwordHash, $userInfo, $userSecureInfo, $userMfaSettings);
        // No cookie is set

        $this->assertTrue($this->authUtils->confirmUserPassword($username, $password));
        $this->assertTrue(isset($_SESSION['mfarequired']) && $_SESSION['mfarequired'] === true, "MFA should be required when set to 'always' and no cookie.");
        $this->assertEquals($username, $_SESSION['tempAuthUser']);
        $this->assertFalse(isset($_SESSION['mfa_device_trusted_always_active']), "MFA trusted device always active flag should NOT be set if no cookie.");
        $this->assertFalse(isset($_SESSION['authUser']), "User should NOT be fully logged in yet.");
    }

    // More tests will follow here for other scenarios
}

[end of tests/Tests/Unit/Common/Auth/AuthUtilsTest.php]
