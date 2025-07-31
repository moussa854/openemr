<?php
/**
 * Step-Up MFA verification page.
 * Enhanced for Ohio compliance requirements for controlled substances.
 */
require_once(__DIR__ . '/globals.php');

// Basic access logging
error_log("StepUpMFA: Verification page accessed - Method: " . $_SERVER['REQUEST_METHOD']);

// Debug session information
error_log("StepUpMFA: Session ID at start = " . (session_id() ?? 'NOT SET'));
error_log("StepUpMFA: Session stepup_mfa_redirect at start = " . (isset($_SESSION['stepup_mfa_redirect']) ? $_SESSION['stepup_mfa_redirect'] : 'NOT SET'));

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Auth\MfaUtils;
use OpenEMR\Services\StepupMfaService;

$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$service = new StepupMfaService();
$error = '';
$patientName = '';

// Get redirect URL from parameter or session
$redirect_url = $_GET['redirect'] ?? $_SESSION['stepup_mfa_redirect'] ?? ($GLOBALS['webroot'] . '/interface/main/main_screen.php');

// Debug logging for redirect URL
error_log('StepUpMFA: Redirect URL from parameter = ' . ($_GET['redirect'] ?? 'NOT SET'));
error_log('StepUpMFA: Redirect URL from session = ' . ($_SESSION['stepup_mfa_redirect'] ?? 'NOT SET'));
error_log('StepUpMFA: Final redirect URL = ' . $redirect_url);

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
        // Try to get user ID from the current session
        $userId = (int)($_SESSION['authUserID'] ?? 1);
    }
    
    $mfa = new MfaUtils($userId);
    
    // Debug logging
    error_log("StepUpMFA: User ID: " . $userId);
    error_log("StepUpMFA: Code entered: " . $code);
    error_log("StepUpMFA: MFA types: " . implode(',', $mfa->getType() ?? []));
    error_log("StepUpMFA: MFA required: " . ($mfa->isMfaRequired() ? 'yes' : 'no'));
    
    // Check if MFA is enabled for this user using the proper method
    if (!$mfa->isMfaRequired()) {
        $error = 'MFA is not enabled for your account. Please contact your administrator to enable MFA.';
        $service->logEvent('MFA_FAILURE', 'MFA not enabled for user');
    } else {
        $result = $mfa->check($code, 'TOTP');
        error_log("StepUpMFA: MFA check result: " . ($result ? 'true' : 'false'));
        
        if ($result) {
            $service->setVerified($pid);
            $service->logEvent('MFA_SUCCESS', 'Step-Up MFA success');
            
            // Debug logging for session verification
            error_log('StepUpMFA: Setting session verification for PID ' . $pid);
            error_log('StepUpMFA: Session verification key after setVerified = ' . (isset($_SESSION['stepup_mfa_verified_pid_' . $pid]) ? $_SESSION['stepup_mfa_verified_pid_' . $pid] : 'NOT SET'));
            error_log('StepUpMFA: Current time = ' . time());
            
            // Set verification flag and show success page with JavaScript redirect
            $_SESSION['stepup_mfa_verified'] = true;
            unset($_SESSION['stepup_mfa_redirect']); // Clear redirect from session
            
            // Debug logging for redirect
            error_log('=== StepUpMFA VERIFY DEBUG ===');
            error_log('StepUpMFA: MFA verification successful for user ID ' . $userId);
            error_log('StepUpMFA: Redirect URL = ' . $redirect_url);
            error_log('StepUpMFA: Session stepup_mfa_verified = ' . (isset($_SESSION['stepup_mfa_verified']) ? 'SET' : 'NOT SET'));
            error_log('StepUpMFA: Session stepup_mfa_redirect = ' . (isset($_SESSION['stepup_mfa_redirect']) ? 'SET' : 'NOT SET'));
            error_log('=== StepUpMFA VERIFY DEBUG END ===');
            
            // Output success page directly to avoid CSRF issues
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>MFA Verification Successful</title>
                <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/interface/themes/style_sky_blue.css">
                <style>
                    .success-container {
                        max-width: 500px;
                        margin: 100px auto;
                        padding: 40px;
                        text-align: center;
                        background: white;
                        border-radius: 8px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    .success-icon {
                        font-size: 4em;
                        color: #28a745;
                        margin-bottom: 20px;
                    }
                    .redirect-info {
                        background-color: #f8f9fa;
                        padding: 15px;
                        border-radius: 4px;
                        margin: 20px 0;
                    }
                    .btn-primary {
                        background-color: #007bff;
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 4px;
                        display: inline-block;
                        margin-top: 15px;
                    }
                    .btn-primary:hover {
                        background-color: #0056b3;
                    }
                    .btn-secondary {
                        background-color: #6c757d;
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 4px;
                        display: inline-block;
                        margin-top: 10px;
                        margin-left: 10px;
                    }
                    .btn-secondary:hover {
                        background-color: #545b62;
                    }
                </style>
            </head>
            <body class="body_top">
                <div class="success-container">
                    <div class="success-icon">✅</div>
                    <h2 style="color: #28a745;">MFA Verification Successful</h2>
                    
                    <div class="redirect-info">
                        <p><strong>Ohio Compliance:</strong> Your multi-factor authentication has been verified successfully.</p>
                        <p>You can now access sensitive encounters for the next 15 minutes.</p>
                    </div>
                    
                    <p>Choose where to go next:</p>
                    <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn-primary">Return to Encounter</a>
                    <a href="<?php echo $GLOBALS['webroot']; ?>/interface/main/main_screen.php" class="btn-secondary">Go to Main Screen</a>
                    
                    <p style="margin-top: 20px; font-size: 0.9em; color: #666;">
                        <em>You can also use your browser's back button to return to your previous page.</em>
                    </p>
                </div>
            </body>
            </html>
            <?php
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
