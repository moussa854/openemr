<?php

/**
 * infusion_injection report.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2023 Your Name <your.email@example.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");

use OpenEMR\Common\Csrf\CsrfUtils; // For any potential links or actions, though not strictly needed for read-only display
use OpenEMR\Common\Logging\SystemLogger;

// Get the form ID from the request. This is typically passed when LBF renders the form report.
// The parameter name might vary depending on how LBF calls it, common ones are 'id', 'form_id', 'formid'.
// Let's assume 'id' for now, which is common.
$form_instance_id = $_REQUEST['id'] ?? null;

if (!$form_instance_id || !is_numeric($form_instance_id)) {
    echo "<div>" . xlt('Error: Form ID not provided or invalid.') . "</div>";
    return;
}

// Fetch the form data from the database
// Note: Using prepared statements is crucial if any user input were part of this query,
// but here $form_instance_id is checked to be numeric.
$sql = "SELECT *, DATE_FORMAT(date, '%Y-%m-%d %H:%i') AS formatted_date, " .
       "DATE_FORMAT(order_expiration_date, '%Y-%m-%d') AS formatted_order_expiration_date, " .
       "DATE_FORMAT(administration_start, '%Y-%m-%d %H:%i') AS formatted_administration_start, " .
       "DATE_FORMAT(administration_end, '%Y-%m-%d %H:%i') AS formatted_administration_end " .
       "FROM form_infusion_injection WHERE id = ?";
$formData = sqlQuery($sql, [$form_instance_id]);

if (!$formData) {
    echo "<div>" . xlt('Error: Form data not found for ID') . " " . htmlspecialchars($form_instance_id, ENT_QUOTES, 'UTF-8') . "</div>";
    // Optionally log this
    // (new SystemLogger())->error("Infusion/Injection form report: Data not found for ID " . $form_instance_id);
    return;
}

// Basic HTML output for the report
// This should be a snippet, not a full HTML document.
// The LBF system will embed this into the encounter summary.
?>
<div class="infusion-injection-report">
    <h4><?php echo xlt('Infusion and Injection Treatment Report'); ?></h4>
    <p><strong><?php echo xlt('Date'); ?>:</strong> <?php echo htmlspecialchars($formData['formatted_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

    <h5><?php echo xlt('Assessment'); ?></h5>
    <p><?php echo nl2br(htmlspecialchars($formData['assessment'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>

    <h5><?php echo xlt('IV Access'); ?></h5>
    <ul>
        <li><strong><?php echo xlt('Type'); ?>:</strong> <?php echo htmlspecialchars($formData['iv_access_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
        <li><strong><?php echo xlt('Location'); ?>:</strong> <?php echo htmlspecialchars($formData['iv_access_location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
        <li><strong><?php echo xlt('Blood Return'); ?>:</strong> <?php echo htmlspecialchars($formData['iv_access_blood_return'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
        <?php if (!empty($formData['iv_access_needle_gauge'])): ?>
            <li><strong><?php echo xlt('Needle Gauge'); ?>:</strong> <?php echo htmlspecialchars($formData['iv_access_needle_gauge'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <?php if (!empty($formData['iv_access_attempts'])): ?>
            <li><strong><?php echo xlt('# of Attempts'); ?>:</strong> <?php echo htmlspecialchars($formData['iv_access_attempts'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <?php if (!empty($formData['iv_access_comments'])): ?>
            <li><strong><?php echo xlt('Comments'); ?>:</strong> <?php echo nl2br(htmlspecialchars($formData['iv_access_comments'], ENT_QUOTES, 'UTF-8')); ?></li>
        <?php endif; ?>
    </ul>

    <h5><?php echo xlt('Order'); ?></h5>
    <ul>
        <li><strong><?php echo xlt('Medication'); ?>:</strong> <?php echo htmlspecialchars($formData['order_medication'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
        <li><strong><?php echo xlt('Dose'); ?>:</strong> <?php echo htmlspecialchars($formData['order_dose'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
        <?php if (!empty($formData['order_lot_number'])): ?>
            <li><strong><?php echo xlt('Lot #'); ?>:</strong> <?php echo htmlspecialchars($formData['order_lot_number'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <?php if (!empty($formData['order_ndc'])): ?>
            <li><strong><?php echo xlt('NDC'); ?>:</strong> <?php echo htmlspecialchars($formData['order_ndc'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <?php if (!empty($formData['formatted_order_expiration_date'])): ?>
            <li><strong><?php echo xlt('Expiration Date'); ?>:</strong> <?php echo htmlspecialchars($formData['formatted_order_expiration_date'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <?php if (!empty($formData['order_every_value']) && !empty($formData['order_every_unit'])): ?>
            <li><strong><?php echo xlt('Every'); ?>:</strong> <?php echo htmlspecialchars($formData['order_every_value'], ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($formData['order_every_unit'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <li><strong><?php echo xlt('Servicing Provider'); ?>:</strong> <?php echo htmlspecialchars($formData['order_servicing_provider'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (NPI: <?php echo htmlspecialchars($formData['order_npi'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)</li>
        <?php if (!empty($formData['order_note'])): ?>
            <li><strong><?php echo xlt('Note'); ?>:</strong> <?php echo nl2br(htmlspecialchars($formData['order_note'], ENT_QUOTES, 'UTF-8')); ?></li>
        <?php endif; ?>
    </ul>

    <h5><?php echo xlt('Administration'); ?></h5>
    <ul>
        <?php if (!empty($formData['formatted_administration_start'])): ?>
            <li><strong><?php echo xlt('Start'); ?>:</strong> <?php echo htmlspecialchars($formData['formatted_administration_start'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <?php if (!empty($formData['formatted_administration_end'])): ?>
            <li><strong><?php echo xlt('End'); ?>:</strong> <?php echo htmlspecialchars($formData['formatted_administration_end'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endif; ?>
        <?php if (!empty($formData['administration_note'])): ?>
            <li><strong><?php echo xlt('Note'); ?>:</strong> <?php echo nl2br(htmlspecialchars($formData['administration_note'], ENT_QUOTES, 'UTF-8')); ?></li>
        <?php endif; ?>
    </ul>
</div>
<?php
// It's important that this script does not call formJump() or other redirection/full page rendering functions
// if it's meant to be embedded in an LBF layout.
?>
