<?php
/**
 * Step-Up MFA verification page.
 * Enhanced for Ohio compliance requirements for controlled substances.
 */
require_once(__DIR__ . '/globals.php');

// Basic access logging
error_log("StepUpMFA: Verification page accessed - Method: " . $_SERVER['REQUEST_METHOD']);

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Auth\MfaUtils;
use OpenEMR\Services\StepupMfaService;

$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$service = new StepupMfaService();
$error = '';
$patientName = '';

// Get patient name for display
if ($pid) {
    $patientResult = sqlQuery("SELECT fname, lname FROM patient_data WHERE pid = ?", [$pid]);
    if ($patientResult) {
        $patientName = $patientResult['fname'] . ' ' . $patientResult['lname'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        CsrfUtils::csrfNotVerified();
    }
    $code = trim($_POST['mfa_code'] ?? '');
    
    // Get the correct user ID - try different session variables
    $userId = null;
    if (isset($_SESSION['authUserID']) && is_numeric($_SESSION['authUserID'])) {
        $userId = (int)$_SESSION['authUserID'];
    } elseif (isset($_SESSION['authUserID']) && is_array($_SESSION['authUserID'])) {
        $userId = (int)($_SESSION['authUserID']['id'] ?? 0);
    } elseif (isset($_SESSION['userauthorized'])) {
        $userId = (int)$_SESSION['userauthorized'];
    } else {
        $userId = 1; // Default to user ID 1 for testing
    }
    
    $mfa = new MfaUtils($userId);
    
    // Debug logging
    error_log("StepUpMFA: User ID: " . $userId);
    error_log("StepUpMFA: Code entered: " . $code);
    error_log("StepUpMFA: MFA enabled: " . (empty($mfa->var1TOTP) ? 'no' : 'yes'));
    error_log("StepUpMFA: var1TOTP length: " . (empty($mfa->var1TOTP) ? '0' : strlen($mfa->var1TOTP)));
    error_log("StepUpMFA: MFA types: " . implode(',', $mfa->types ?? []));
    
    // Check if MFA is enabled for this user
    if (empty($mfa->var1TOTP)) {
        $error = 'MFA is not enabled for your account. Please contact your administrator to enable MFA.';
        $service->logEvent('MFA_FAILURE', 'MFA not enabled for user');
    } else {
        $result = $mfa->check($code, 'TOTP');
        error_log("StepUpMFA: MFA check result: " . ($result ? 'true' : 'false'));
        
        if ($result) {
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
}

$token = CsrfUtils::collectCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Multi-Factor Verification - Ohio Compliance</title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/interface/themes/style_sky_blue.css">
    <style>
        .compliance-info {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .patient-info {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .mfa-form {
            max-width: 400px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: white;
        }
        .mfa-input {
            font-size: 1.5em;
            text-align: center;
            width: 100%;
            padding: 15px;
            border: 2px solid #007bff;
            border-radius: 4px;
            margin: 20px 0;
        }
        .btn-verify {
            width: 100%;
            padding: 12px;
            font-size: 1.1em;
        }
    </style>
</head>
<body class="body_top">
    <div class="mfa-form">
        <h2 style="text-align: center; color: #007bff;">🔐 Additional Verification Required</h2>
        
        <?php if ($patientName): ?>
        <div class="patient-info">
            <strong>Patient:</strong> <?php echo htmlspecialchars($patientName); ?>
        </div>
        <?php endif; ?>
        
        <div class="compliance-info">
            <h5>📋 Ohio Compliance Notice</h5>
            <p>This action requires multi-factor authentication in compliance with Ohio Board of Pharmacy regulations for controlled substances.</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Access to sensitive encounters requires additional verification</li>
                <li>All access attempts are logged for audit purposes</li>
                <li>This verification is valid for 15 minutes per patient</li>
            </ul>
        </div>
        
        <?php if ($error): ?>
            <div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0;">
                <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <input type="hidden" name="csrf_token_form" value="<?php echo htmlspecialchars($token); ?>">
            
            <div style="text-align: center; margin: 20px 0;">
                <label for="mfa_code" style="font-weight: bold; display: block; margin-bottom: 10px;">
                    Enter your 6-digit authentication code:
                </label>
                <input type="text" 
                       id="mfa_code"
                       name="mfa_code" 
                       pattern="[0-9]{6}" 
                       inputmode="numeric" 
                       required 
                       autofocus 
                       class="mfa-input"
                       placeholder="000000"
                       maxlength="6">
            </div>
            
            <button type="submit" class="btn btn-primary btn-verify">
                🔐 Verify Access
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #666;">
            <p>This verification helps ensure compliance with Ohio controlled substance regulations.</p>
        </div>
    </div>
    
    <script>
        // Auto-format the MFA input
        document.getElementById('mfa_code').addEventListener('input', function(e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
    </script>
</body>
</html>
