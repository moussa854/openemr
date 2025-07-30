<?php
require_once(__DIR__ . '/globals.php');

use OpenEMR\Services\SensitiveEncounterMfaService;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Common\Auth\MfaUtils;

CsrfUtils::verifyCsrf();
$svc = new SensitiveEncounterMfaService();
$pid = (int)($_GET['pid'] ?? 0);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['mfa_code'] ?? '';
    if (MfaUtils::verifyTotpCode($_SESSION['authUserID'], $code)) {
        $svc->setVerified($pid);
        $redirect = $_SESSION[SensitiveEncounterMfaService::SESSION_MFA_REDIRECT_URL] ?? $GLOBALS['webroot'] . '/interface/patient_file/summary/demographics.php?pid=' . $pid;
        unset($_SESSION[SensitiveEncounterMfaService::SESSION_MFA_REDIRECT_URL]);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = xlt('Invalid code, please try again.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo xlt('MFA Verification'); ?></title>
    <?php Header::setupHeader(); ?>
</head>
<body>
<div class="container" style="max-width:400px;margin-top:50px;">
    <h3><?php echo xlt('Additional Verification Required'); ?></h3>
    <p><?php echo xlt('Please enter the 6-digit code from your authenticator app.'); ?></p>
    <?php if ($error) : ?>
        <div class="alert alert-danger"><?php echo text($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <?php CsrfUtils::generateFormToken(); ?>
        <input type="text" name="mfa_code" class="form-control" maxlength="6" autofocus required />
        <br />
        <button type="submit" class="btn btn-primary"><?php echo xlt('Verify'); ?></button>
    </form>
</div>
</body>
</html>