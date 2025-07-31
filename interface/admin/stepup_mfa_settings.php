<?php
require_once(__DIR__ . '/../globals.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Acl\AclMain;

// ACL check – require admin super privileges
if (!AclMain::aclCheckCore('admin', 'users')) {
    die(xlt('Not authorized')); // simple block
}

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        CsrfUtils::csrfNotVerified();
    }
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_enabled'", [isset($_POST['enabled']) ? '1' : '0']);
    $catsCsv = isset($_POST['categories']) ? implode(',', array_map('intval', $_POST['categories'])) : '';
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_categories'", [$catsCsv]);
    $timeout = (int)($_POST['timeout'] ?? 900);
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_timeout'", [(string)$timeout]);
    echo "<script>alert('" . addslashes(xlt('Settings saved')) . "'); window.location.href='stepup_mfa_settings.php';</script>";
    exit;
}

// fetch current values
$enabledVal = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_enabled'")['gl_value'] ?? 0);
$catsVal = (string)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_categories'")['gl_value'] ?? '');
$timeoutVal = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_timeout'")['gl_value'] ?? 900);
$selectedCats = array_filter(array_map('intval', explode(',', $catsVal)));

// category list
$catRes = sqlStatement('SELECT pc_catid, pc_catname FROM openemr_postcalendar_categories ORDER BY pc_catname');
$categories = [];
while ($r = sqlFetchArray($catRes)) {
    $categories[] = $r;
}

$token = CsrfUtils::collectCsrfToken();
?>
<html>
<head>
    <title><?php echo xlt('Step-Up MFA Settings'); ?></title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/interface/themes/style_sky_blue.css">
</head>
<body class="body_top">
<div class="container mt-3">
    <h2><?php echo xlt('Step-Up MFA Settings'); ?></h2>
    <form method="post" class="">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($token); ?>"/>
        <div class="form-group">
            <label><input type="checkbox" name="enabled" value="1" <?php echo $enabledVal ? 'checked' : ''; ?>/> <?php echo xlt('Enable Step-Up MFA'); ?></label>
        </div>
        <div class="form-group">
            <label><?php echo xlt('Sensitive Appointment Categories'); ?></label><br>
            <select name="categories[]" multiple size="8" style="min-width:250px;">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo (int)$cat['pc_catid']; ?>" <?php echo in_array((int)$cat['pc_catid'], $selectedCats, true) ? 'selected' : ''; ?>>
                        <?php echo text($cat['pc_catname']); ?> (<?php echo (int)$cat['pc_catid']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo xlt('MFA Grace Period (seconds)'); ?></label>
            <input type="number" name="timeout" value="<?php echo (int)$timeoutVal; ?>" class="form-control" style="max-width:120px;">
        </div>
        <br>
        <button type="submit" class="btn btn-primary"><?php echo xlt('Save'); ?></button>
    </form>
</div>
</body>
</html>
