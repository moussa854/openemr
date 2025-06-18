<?php

namespace OpenEMR\Tests\Unit\Common\Auth;

use OpenEMR\Common\Auth\MfaUtils;
use OpenEMR\Common\Crypto\CryptoGen; // Used by MfaUtils internally for TOTP
use OpenEMR\Common\Utils\RandomGenUtils;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

// Mock global functions if not already defined (e.g. by AuthUtilsTest)
if (!function_exists('sqlQuery')) {
    function sqlQuery(string $query, array $params = [])
    {
        return \OpenEMR\Tests\Unit\Common\Auth\MfaUtilsTest::$sqlQueryMock($query, $params);
    }
}
if (!function_exists('sqlStatement')) {
    function sqlStatement(string $query, array $params = [])
    {
        // For testing, we might capture these calls if needed for assertion
        \OpenEMR\Tests\Unit\Common\Auth\MfaUtilsTest::$sqlStatementMockLog[] = ['query' => $query, 'params' => $params];
        return true;
    }
}
if (!function_exists('sqlStatementNoLog')) {
    function sqlStatementNoLog(string $query, array $params = [])
    {
        return \OpenEMR\Tests\Unit\Common\Auth\MfaUtilsTest::$sqlStatementNoLogMock($query, $params);
    }
}
if (!function_exists('sqlFetchArray')) {
    function sqlFetchArray($rs)
    {
        return \OpenEMR\Tests\Unit\Common\Auth\MfaUtilsTest::$sqlFetchArrayMock($rs);
    }
}
if (!function_exists('xl')) {
    function xl(string $text)
    {
        return $text;
    }
}
// Mock RandomGenUtils if it's directly called in MfaUtils for device identifier
// For this test, we'll assume RandomGenUtils::produceRandomString works and focus on MfaUtils logic.
// If MfaUtils directly uses `random_bytes` or similar, that's harder to mock without library overrides.
// We will check if `RandomGenUtils::produceRandomString` is called.

class MfaUtilsTest extends TestCase
{
    private $mfaUtils;
    private $userId = 1;

    // Mock delegates and logs
    public static $sqlQueryMock;
    public static $sqlStatementMockLog = [];
    public static $sqlStatementNoLogMock;
    public static $sqlFetchArrayMock;

    /** @var MockObject|MfaUtils */
    private $mockMfaUtilsPartial;


    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_COOKIE = []; // Clear cookies

        // Mock globals
        $GLOBALS['webroot'] = '/openemr';
        $GLOBALS['ADODB_FETCH_MODE'] = 0; // Or whatever your app uses

        // Reset static mock log for statements
        self::$sqlStatementMockLog = [];

        // Default mock behaviors
        self::$sqlQueryMock = function(string $query, array $params = []) {
            if (strpos($query, "SELECT mfa_grace_period FROM users WHERE id = ?") !== false) {
                return ['mfa_grace_period' => 30 * 24 * 60 * 60]; // Default 30 days
            }
            return []; // Default empty result
        };
        self::$sqlStatementNoLogMock = function(string $query, array $params = []) { return true; }; // MfaUtils constructor uses this
        self::$sqlFetchArrayMock = function($rs) { return null; }; // MfaUtils constructor uses this

        // We will use a partial mock for MfaUtils to mock internal checkTOTP/checkU2F
        $this->mockMfaUtilsPartial = $this->getMockBuilder(MfaUtils::class)
            ->setConstructorArgs([$this->userId])
            ->onlyMethods(['checkTOTP', 'checkU2F']) // Add other methods if they are complex and not focus of test
            ->getMock();
    }

    protected function tearDown(): void
    {
        unset($_SESSION, $_POST, $_COOKIE);
    }

    public function testCheckWithTrustDeviceChecked()
    {
        $_POST['trust_mfa_device'] = '1';
        $mfaToken = '123456';

        $this->mockMfaUtilsPartial->method('checkTOTP')->willReturn(true);

        $this->assertTrue($this->mockMfaUtilsPartial->check($mfaToken, MfaUtils::TOTP));

        // Verify database interactions for trusted device
        $this->assertCount(2, self::$sqlStatementMockLog, "Expected 2 SQL statements (DELETE then INSERT)");

        $deleteStatement = self::$sqlStatementMockLog[0];
        $this->assertStringContainsStringIgnoringCase("DELETE FROM login_mfa_trusted_devices WHERE user_id = ?", $deleteStatement['query']);
        $this->assertEquals([$this->userId], $deleteStatement['params']);

        $insertStatement = self::$sqlStatementMockLog[1];
        $this->assertStringContainsStringIgnoringCase("INSERT INTO login_mfa_trusted_devices (user_id, device_identifier, expires_at) VALUES (?, ?, ?)", $insertStatement['query']);
        $this->assertEquals($this->userId, $insertStatement['params'][0]);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{64}$/', $insertStatement['params'][1], "Device identifier should be a 64 char random string");
        $this->assertGreaterThan(time() + (29 * 24 * 60 * 60), strtotime($insertStatement['params'][2]), "Expires_at should be approx 30 days in future");

        // Verify cookie setting (this is harder to directly test without a global function wrapper or more advanced techniques)
        // For now, we trust the setcookie call in MfaUtils was made.
        // To actually test it, one would typically check headers or use a library that helps test HTTP responses.
        // A simple check could be to use xdebug_get_headers() if Xdebug is available and configured for tests,
        // but that's often not the case in CI.
    }

    public function testCheckWithoutTrustDeviceChecked()
    {
        // $_POST['trust_mfa_device'] is NOT set
        $mfaToken = '123456';

        $this->mockMfaUtilsPartial->method('checkTOTP')->willReturn(true);

        $this->assertTrue($this->mockMfaUtilsPartial->check($mfaToken, MfaUtils::TOTP));

        // Verify no database interactions for trusted device
        $this->assertCount(0, self::$sqlStatementMockLog, "Expected 0 SQL statements for trusted device when checkbox is not checked");

        // Verify no cookie was set (again, hard to directly test standard setcookie)
    }
}

[end of tests/Tests/Unit/Common/Auth/MfaUtilsTest.php]
