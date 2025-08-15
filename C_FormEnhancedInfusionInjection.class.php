<?php

/**
 * enhanced_infusion_injection C_FormEnhancedInfusionInjection.class.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once($GLOBALS['fileroot'] . "/library/forms.inc.php");
require_once($GLOBALS['fileroot'] . "/library/patient.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\ListService;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\FormService;
use OpenEMR\Core\Header;
use OpenEMR\Services\VitalsService;
use OpenEMR\Services\AllergyIntoleranceService;

/**
 * Enhanced Infusion and Injection Form Controller
 * This controller handles the enhanced infusion form and redirects to our custom interface
 */
class C_FormEnhancedInfusionInjection extends Controller
{
    public function __construct($template_mod = "")
    {
        parent::__construct();
        $this->template_mod = $template_mod;
        $this->template_dir = dirname(__FILE__) . "/templates/";
        $this->assign("FORM_ACTION", $GLOBALS['webroot']);
        $this->assign("DONT_SAVE_LINK", $GLOBALS['form_exit_url']);
        $this->assign("STYLE", $GLOBALS['style']);
    }

    public function default_action()
    {
        // Get patient and encounter information
        $pid = $_GET['pid'] ?? $_SESSION['pid'] ?? 0;
        $encounter = $_GET['encounter'] ?? $_SESSION['encounter'] ?? 0;
        $form_id = $_GET['id'] ?? 0;

        // Validate required parameters
        if (!$pid || !$encounter) {
            echo "<div style='padding: 20px; text-align: center;'>";
            echo "<h3>Enhanced Infusion and Injection Form</h3>";
            echo "<p style='color: #dc3545; font-weight: bold;'>Patient ID and Encounter ID are required</p>";
            echo "<p>Please ensure you are in an active patient encounter before accessing this form.</p>";
            echo "<button onclick='window.close()' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;'>Close</button>";
            echo "</div>";
            return;
        }

        // Check if this is an auto-open request
        $is_auto_open = !isset($_GET['manual']) && !isset($_GET['id']);
        
        if ($is_auto_open) {
            // This is an auto-open request - redirect to encounter summary instead
            $encounter_url = $GLOBALS['webroot'] . "/interface/patient_file/encounter/encounter_top.php?set_encounter=" . urlencode($encounter);
            echo "<script>window.location.href = '" . $encounter_url . "';</script>";
            return;
        }

        // Check if a form already exists for this encounter
        $existing_form_sql = "SELECT form_id FROM `forms` WHERE formdir = 'enhanced_infusion_injection' AND pid = ? AND encounter = ? AND deleted = 0 LIMIT 1";
        $existing_form = sqlQuery($existing_form_sql, array($pid, $encounter));

        if (!empty($existing_form['form_id'])) {
            // Form already exists - redirect to view the existing form
            $redirect_url = $GLOBALS['webroot'] . "/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php?id=" . urlencode($existing_form['form_id']) . "&pid=" . urlencode($pid) . "&encounter=" . urlencode($encounter);
            echo "<script>window.location.href = '" . $redirect_url . "';</script>";
            return;
        }

        // No existing form - redirect to our enhanced infusion form for new form
        $redirect_url = $GLOBALS['webroot'] . "/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php?pid=" . urlencode($pid) . "&encounter=" . urlencode($encounter);
        echo "<script>window.location.href = '" . $redirect_url . "';</script>";
    }

    public function default_action_process()
    {
        // This method is called when the form is submitted
        // Since we're redirecting to our custom form, this shouldn't be called directly
        // But we'll handle it gracefully
        
        if (empty($_POST['process']) || $_POST['process'] != "true") {
            return;
        }

        // Redirect to our custom save handler
        $redirect_url = $GLOBALS['webroot'] . "/interface/modules/custom_modules/oe-module-inventory/integration/save_enhanced.php";
        echo "<script>window.location.href = '" . $redirect_url . "';</script>";
    }

    public function view_action($form_id)
    {
        // This method is called when viewing an existing form
        $pid = $_GET['pid'] ?? $_SESSION['pid'] ?? 0;
        $encounter = $_GET['encounter'] ?? $_SESSION['encounter'] ?? 0;
        
        if (!$form_id || !$pid || !$encounter) {
            echo "<div style='padding: 20px; text-align: center;'>";
            echo "<h3>Enhanced Infusion and Injection Form</h3>";
            echo "<p style='color: #dc3545;'>Form ID, Patient ID and Encounter ID are required.</p>";
            echo "<button onclick='window.close()' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;'>Close</button>";
            echo "</div>";
            return;
        }

        // Redirect to our custom form with the form ID
        $redirect_url = $GLOBALS['webroot'] . "/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php?id=" . urlencode($form_id) . "&pid=" . urlencode($pid) . "&encounter=" . urlencode($encounter);
        echo "<script>window.location.href = '" . $redirect_url . "';</script>";
    }

    public function new_action($form_id = "")
    {
        // This method is called when creating a new form
        $pid = $_GET['pid'] ?? $_SESSION['pid'] ?? 0;
        $encounter = $_GET['encounter'] ?? $_SESSION['encounter'] ?? 0;
        
        if (!$pid || !$encounter) {
            echo "<div style='padding: 20px; text-align: center;'>";
            echo "<h3>Enhanced Infusion and Injection Form</h3>";
            echo "<p style='color: #dc3545; font-weight: bold;'>Patient ID and Encounter ID are required</p>";
            echo "<p>Please ensure you are in an active patient encounter before accessing this form.</p>";
            echo "<button onclick='window.close()' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;'>Close</button>";
            echo "</div>";
            return;
        }

        // Redirect to our custom form for new form
        $redirect_url = $GLOBALS['webroot'] . "/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php?pid=" . urlencode($pid) . "&encounter=" . urlencode($encounter);
        echo "<script>window.location.href = '" . $redirect_url . "';</script>";
    }
} 