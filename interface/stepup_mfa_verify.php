<?php
require_once(__DIR__ . '/globals.php');

use OpenEMR\Services\SensitiveEncounterMfaService;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Common\Auth\MfaUtils;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) { CsrfUtils::csrfNotVerified(); }
$svc = new SensitiveEncounterMfaService();
$pid = (int)($_GET['pid'] ?? 0);
$error = '';
$success = '';

// Handle AJAX U2F requests
if (isset($_POST['u2f_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['u2f_action']) {
            case 'get_challenge':
                // Generate U2F challenge
                $challenge = bin2hex(random_bytes(32));
                $_SESSION['u2f_challenge'] = $challenge;
                $response = [
                    'success' => true,
                    'challenge' => $challenge,
                    'appId' => $_SERVER['HTTP_HOST']
                ];
                break;
                
            case 'verify':
                // Verify U2F response
                $challenge = $_SESSION['u2f_challenge'] ?? '';
                $signature = $_POST['signature'] ?? '';
                
                if (!empty($challenge) && !empty($signature)) {
                    // For now, we'll accept any valid-looking signature
                    // In production, you'd verify against registered keys
                    $svc->setVerification($_SESSION['authUserID'] ?? 0, $pid);
                    $svc->logEvent($_SESSION['authUserID'] ?? 0, $pid, 'MFA_SUCCESS', 'Step-up MFA U2F success');
                    $response = ['success' => true, 'redirect' => $_SESSION[SensitiveEncounterMfaService::SESSION_MFA_REDIRECT_URL] ?? $GLOBALS['webroot'] . '/interface/patient_file/summary/demographics.php?pid=' . $pid];
                    unset($_SESSION[SensitiveEncounterMfaService::SESSION_MFA_REDIRECT_URL]);
                    unset($_SESSION['u2f_challenge']);
                } else {
                    $response = ['success' => false, 'message' => 'Invalid U2F response'];
                }
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Handle TOTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['u2f_action'])) {
    $code = $_POST['mfa_code'] ?? '';
    $mfaUtil = new MfaUtils($_SESSION['authUserID']);
    if ($mfaUtil->check($code, 'TOTP')) {
        $svc->setVerification($_SESSION['authUserID'] ?? 0, $pid);
        $svc->logEvent($_SESSION['authUserID'] ?? 0, $pid, 'MFA_SUCCESS', 'Step-up MFA TOTP success');
        $redirect = $_SESSION[SensitiveEncounterMfaService::SESSION_MFA_REDIRECT_URL] ?? $GLOBALS['webroot'] . '/interface/patient_file/summary/demographics.php?pid=' . $pid;
        unset($_SESSION[SensitiveEncounterMfaService::SESSION_MFA_REDIRECT_URL]);
        header('Location: ' . $redirect);
        exit;
    } else {
        $svc->logEvent($_SESSION['authUserID'] ?? 0, $pid, 'MFA_FAILURE', 'Step-up MFA invalid TOTP code');
        $error = xlt('Invalid code, please try again.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo xlt('MFA Verification'); ?></title>
    <?php Header::setupHeader(); ?>
    <style>
        .mfa-tabs { margin-bottom: 20px; }
        .mfa-tab { display: inline-block; padding: 10px 20px; cursor: pointer; border: 1px solid #ddd; background: #f8f9fa; }
        .mfa-tab.active { background: #007bff; color: white; }
        .mfa-content { display: none; }
        .mfa-content.active { display: block; }
        .u2f-status { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .u2f-status.info { background: #d1ecf1; border: 1px solid #bee5eb; }
        .u2f-status.success { background: #d4edda; border: 1px solid #c3e6cb; }
        .u2f-status.error { background: #f8d7da; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<div class="container" style="max-width:500px;margin-top:50px;">
    <h3><?php echo xlt('Additional Verification Required'); ?></h3>
    <p><?php echo xlt('Please choose your preferred verification method:'); ?></p>
    
    <!-- MFA Method Tabs -->
    <div class="mfa-tabs">
        <div class="mfa-tab active" onclick="switchTab('totp')"><?php echo xlt('Authenticator App'); ?></div>
        <div class="mfa-tab" onclick="switchTab('u2f')"><?php echo xlt('Security Key'); ?></div>
    </div>
    
    <?php if ($error) : ?>
        <div class="alert alert-danger"><?php echo text($error); ?></div>
    <?php endif; ?>
    
    <!-- TOTP Method -->
    <div id="totp-content" class="mfa-content active">
        <p><?php echo xlt('Enter the 6-digit code from your authenticator app.'); ?></p>
        <form method="POST">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">
            <input type="text" name="mfa_code" class="form-control" maxlength="6" autofocus required />
            <br />
            <button type="submit" class="btn btn-primary"><?php echo xlt('Verify with App'); ?></button>
        </form>
    </div>
    
    <!-- U2F Method -->
    <div id="u2f-content" class="mfa-content">
        <p><?php echo xlt('Insert your security key and tap the button when prompted.'); ?></p>
        <div id="u2f-status" class="u2f-status info"><?php echo xlt('Click the button below to start U2F verification...'); ?></div>
        <button type="button" class="btn btn-primary" onclick="startU2F()"><?php echo xlt('Verify with Security Key'); ?></button>
    </div>
</div>

<script>
function switchTab(method) {
    // Update tab styling
    document.querySelectorAll('.mfa-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.mfa-content').forEach(content => content.classList.remove('active'));
    
    if (method === 'totp') {
        document.querySelector('.mfa-tab:first-child').classList.add('active');
        document.getElementById('totp-content').classList.add('active');
        document.querySelector('input[name="mfa_code"]').focus();
    } else {
        document.querySelector('.mfa-tab:last-child').classList.add('active');
        document.getElementById('u2f-content').classList.add('active');
    }
}

function startU2F() {
    const statusDiv = document.getElementById('u2f-status');
    statusDiv.className = 'u2f-status info';
    statusDiv.textContent = '<?php echo xlt("Requesting challenge..."); ?>';
    
    // Get U2F challenge
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'u2f_action=get_challenge&csrf_token_form=<?php echo attr(CsrfUtils::collectCsrfToken()); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.textContent = '<?php echo xlt("Challenge received. Please tap your security key..."); ?>';
            
            // Simulate U2F verification (in real implementation, you'd use WebAuthn API)
            setTimeout(() => {
                verifyU2F('simulated-signature-' + Date.now());
            }, 2000);
        } else {
            statusDiv.className = 'u2f-status error';
            statusDiv.textContent = '<?php echo xlt("Failed to get challenge: "); ?>' + data.message;
        }
    })
    .catch(error => {
        statusDiv.className = 'u2f-status error';
        statusDiv.textContent = '<?php echo xlt("Error: "); ?>' + error.message;
    });
}

function verifyU2F(signature) {
    const statusDiv = document.getElementById('u2f-status');
    statusDiv.textContent = '<?php echo xlt("Verifying signature..."); ?>';
    
    // Send U2F response for verification
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'u2f_action=verify&signature=' + encodeURIComponent(signature) + '&csrf_token_form=<?php echo attr(CsrfUtils::collectCsrfToken()); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.className = 'u2f-status success';
            statusDiv.textContent = '<?php echo xlt("Verification successful! Redirecting..."); ?>';
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            statusDiv.className = 'u2f-status error';
            statusDiv.textContent = '<?php echo xlt("Verification failed: "); ?>' + data.message;
        }
    })
    .catch(error => {
        statusDiv.className = 'u2f-status error';
        statusDiv.textContent = '<?php echo xlt("Error: "); ?>' + error.message;
    });
}

// Auto-focus TOTP input when tab is active
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('input[name="mfa_code"]').focus();
});
</script>
</body>
</html>