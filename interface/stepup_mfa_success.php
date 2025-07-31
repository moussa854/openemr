<?php
/**
 * Step-Up MFA success page - handles redirect without CSRF issues
 */
require_once(__DIR__ . '/globals.php');

// Check if user is verified
if (!isset($_SESSION['stepup_mfa_verified']) || empty($_SESSION['stepup_mfa_verified'])) {
    header('Location: ' . $GLOBALS['webroot'] . '/interface/main/main_screen.php');
    exit;
}

// Clear the verification flag
unset($_SESSION['stepup_mfa_verified']);

// Get the original redirect URL or default to main screen
$redirect_url = $_SESSION['stepup_mfa_redirect'] ?? ($GLOBALS['webroot'] . '/interface/main/main_screen.php');
unset($_SESSION['stepup_mfa_redirect']);

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
    </style>
</head>
<body class="body_top">
    <div class="success-container">
        <div class="success-icon">✅</div>
        <h2 style="color: #28a745;">MFA Verification Successful</h2>
        
        <div class="redirect-info">
            <p><strong>Ohio Compliance:</strong> Your multi-factor authentication has been verified successfully.</p>
            <p>You will be redirected to the main screen in <span id="countdown">3</span> seconds...</p>
        </div>
        
        <p>If you are not redirected automatically, <a href="<?php echo htmlspecialchars($redirect_url); ?>">click here</a>.</p>
    </div>
    
    <script>
        // Countdown and redirect
        let countdown = 3;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '<?php echo htmlspecialchars($redirect_url); ?>';
            }
        }, 1000);
    </script>
</body>
</html> 