<?php
/**
 * Step-Up MFA verification page.
 */
require_once(__DIR__ . '/globals.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Auth\MfaUtils;
use OpenEMR\Services\StepupMfaService;

$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$service = new StepupMfaService();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        CsrfUtils::csrfNotVerified();
    }
    $code = trim($_POST['mfa_code'] ?? '');
    $mfa = new MfaUtils($_SESSION['authUserID'] ?? 0);
    if ($mfa->check($code, 'TOTP')) {
        $service->setVerified($pid);
        $service->logEvent('MFA_SUCCESS', 'Step-Up MFA success');
        $dest = $_SESSION['stepup_mfa_redirect'] ?? ($GLOBALS['webroot'] . '/interface/main/main_screen.php');
        unset($_SESSION['stepup_mfa_redirect']);
        header('Location: ' . $dest);
        exit;
    } else {
        $service->logEvent('MFA_FAILURE', 'Invalid Step-Up MFA code');
        $error = 'Invalid code. Please try again.';
    }
}

$token = CsrfUtils::collectCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Multi-Factor Verification</title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/interface/themes/style_sky_blue.css">
</head>
<body class="body_top">
    <div style="max-width:400px;margin:60px auto;padding:20px;border:1px solid #ccc;border-radius:4px;text-align:center;">
        <h2>Additional Verification Required</h2>
        <p>This action requires multi-factor authentication.</p>
        <?php if ($error): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token_form" value="<?php echo htmlspecialchars($token); ?>">
            <input type="text" name="mfa_code" pattern="[0-9]{6}" inputmode="numeric" required autofocus style="font-size:1.5em;text-align:center;">
            <br><br>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>
    </div>
</body>
</html>
