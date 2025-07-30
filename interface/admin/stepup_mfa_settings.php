<?php
/**
 * Step-Up MFA – Admin Settings
 *
 * Allows administrators to enable/disable the feature, choose sensitive
 * appointment categories, and set the verification grace-period.
 */

require_once dirname(__DIR__, 1) . "/globals.php";
require_once $GLOBALS['srcdir'] . "/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Services\SensitiveEncounterMfaService;

if (!acl_check('admin', 'super')) {
    die(xlt('Not authorized.'));
}

$svc = new SensitiveEncounterMfaService();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CsrfUtils::verifyCsrf();

    $enabled = isset($_POST['enabled']) ? '1' : '0';
    $cats    = isset($_POST['categories']) ? implode(',', array_map('intval', $_POST['categories'])) : '';
    $timeout = (int)($_POST['timeout'] ?? $svc->getTimeout());
    if ($timeout < 60) {
        $timeout = 60;
    }

    sqlStatement('REPLACE INTO globals (gl_name, gl_value, gl_category) VALUES (?,?,"Security")', ['stepup_mfa_enabled', $enabled]);
    sqlStatement('REPLACE INTO globals (gl_name, gl_value, gl_category) VALUES (?,?,"Security")', ['stepup_mfa_categories', $cats]);
    sqlStatement('REPLACE INTO globals (gl_name, gl_value, gl_category) VALUES (?,?,"Security")', ['stepup_mfa_timeout', $timeout]);

    // reload in-memory globals
    $GLOBALS['stepup_mfa_enabled']   = $enabled;
    $GLOBALS['stepup_mfa_categories'] = $cats;
    $GLOBALS['stepup_mfa_timeout']   = $timeout;

    echo "<div class='alert alert-success'>" . xlt('Settings saved') . "</div>";
}

// Fetch categories for select list
$catRows = sqlStatement('SELECT pc_catid, pc_catname FROM openemr_postcalendar_categories ORDER BY pc_catname')->fetchAll(PDO::FETCH_ASSOC);
$selectedCatIds = $svc->getSensitiveCategoryIds();

$enabledVal = $svc->isEnabled();
$timeoutVal = $svc->getTimeout();

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Step-Up MFA Settings'); ?></title>
    <?php Header::setupHeader(); ?>
</head>
<body>
<div class="container mt-4">
    <h2><?php echo xlt('Step-Up MFA Settings'); ?></h2>
    <form method="POST" class="mt-3">
        <?php CsrfUtils::generateFormToken(); ?>
        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" id="enabled" name="enabled" value="1" <?php if ($enabledVal) echo 'checked'; ?>>
            <label class="form-check-label" for="enabled"><?php echo xlt('Enable Step-Up MFA'); ?></label>
        </div>

        <div class="form-group">
            <label for="categories"><?php echo xlt('Sensitive Appointment Categories'); ?></label>
            <select multiple size="10" class="form-control" id="categories" name="categories[]">
                <?php foreach ($catRows as $row) : ?>
                    <option value="<?php echo attr($row['pc_catid']); ?>" <?php echo in_array((int)$row['pc_catid'], $selectedCatIds) ? 'selected' : ''; ?>>
                        <?php echo text($row['pc_catname']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted"><?php echo xlt('Hold Ctrl or Cmd to select multiple.'); ?></small>
        </div>

        <div class="form-group">
            <label for="timeout"><?php echo xlt('Grace Period (seconds)'); ?></label>
            <input type="number" class="form-control" id="timeout" name="timeout" min="60" value="<?php echo attr($timeoutVal); ?>">
        </div>

        <button type="submit" class="btn btn-primary"><?php echo xlt('Save'); ?></button>
    </form>
</div>
</body>
</html>