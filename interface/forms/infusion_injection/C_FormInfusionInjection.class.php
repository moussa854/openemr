<?php

/**
 * infusion_injection C_FormInfusionInjection.class.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Your Name <your.email@example.com>
 * @copyright Copyright (c) 2023 Your Name <your.email@example.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once($GLOBALS['fileroot'] . "/library/forms.inc.php");
require_once($GLOBALS['fileroot'] . "/library/patient.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\ListService;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Services\PatientService; // Added for fetching patient data if needed for allergies
use OpenEMR\Services\FormService; // Added for interacting with other forms if needed
use OpenEMR\Core\Header; // Added for Header::setupHeader()
use OpenEMR\Services\VitalsService; // For fetching latest vitals
use OpenEMR\Services\AllergyIntoleranceService;

// TODO: Create a FormInfusionInjection class for data handling (e.g., using ORM or a simple data object)
// For now, we'll use an associative array or a stdClass object to hold form data.
// require_once($GLOBALS['srcdir'] . "/common/models/FormInfusionInjection.php");

class C_FormInfusionInjection
{
    /**
     * @var stdClass|array // TODO: Replace with actual data model class instance
     */
    public $infusion_injection_data;

    var $template_dir;
    var $form_id;
    var $template_mod;
    var $context;

    public function __construct($template_mod = "general", $context = '')
    {
        $this->template_mod = $template_mod;
        // Corrected template directory path
        $this->template_dir = __DIR__ . "/templates/";
        $this->context = $context;
        // TODO: Initialize $this->infusion_injection_data, possibly by fetching from DB if $this->form_id is set
        $this->infusion_injection_data = new stdClass(); // Placeholder
    }

    public function setFormId($form_id)
    {
        $this->form_id = $form_id;
        if (!empty($form_id) && is_numeric($form_id) && isset($GLOBALS['pid'])) {
            $sql = "SELECT * FROM form_infusion_injection WHERE id = ? AND pid = ?";
            $result = sqlQuery($sql, [$form_id, $GLOBALS['pid']]);
            if ($result) {
                $this->infusion_injection_data = (object) $result;
            } else {
                // Initialize empty data if not found for the given id and pid
                $this->infusion_injection_data = new stdClass();
                (new SystemLogger())->info("Infusion/Injection form: No data found for form_id " . $form_id . " and pid " . $GLOBALS['pid'] . " in setFormId. Initializing empty data.");
            }
        } else {
            // Initialize empty data if form_id is not valid or pid is not set
            $this->infusion_injection_data = new stdClass();
            if (empty($form_id) || !is_numeric($form_id)) {
                 error_log("Infusion/Injection form: setFormId called with invalid or empty form_id. Initializing empty data.");
            }
            if (!isset($GLOBALS['pid'])) {
                 error_log("Infusion/Injection form: setFormId called without global pid set. Initializing empty data.");
            }
        }
    }

    public function default_action()
    {
        error_log("FormInfusionInjection loaded - PID: " . ($GLOBALS['pid'] ?? 'N/A') . ", Form ID: " . ($this->form_id ?? 'N/A'));
        error_log("Template path: " . $this->template_dir);

        try {
            $listService = new ListService();

            // Ensure infusion_injection_data is an object, especially if setFormId wasn't called (e.g. new form)
            if (!is_object($this->infusion_injection_data)) {
                $this->infusion_injection_data = new stdClass();
            }

            /*
             * Prefill vital signs from latest Vitals form (if any) when creating a NEW infusion/injection form.
             */
            $latestVitals = [];
            if (empty($this->form_id) && !empty($GLOBALS['pid'])) {
                try {
                    $vitalsService = new VitalsService();
                    $vitalsHistory = $vitalsService->getVitalsHistoryForPatient($GLOBALS['pid'], null);
                    if (!empty($vitalsHistory)) {
                        // first record is most recent because service orders DESC by date
                        $latest = $vitalsHistory[0] ?? [];
                        $latestVitals = [
                            'bp_systolic' => $latest['bps'] ?? '',
                            'bp_diastolic' => $latest['bpd'] ?? '',
                            'pulse' => $latest['pulse'] ?? '',
                            'temperature_f' => $latest['temperature'] ?? '',
                            'oxygen_saturation' => $latest['oxygen_saturation'] ?? '',
                        ];
                    }
                } catch (\Exception $ex) {
                    error_log("Infusion/Injection form: Failed fetching latest vitals - " . $ex->getMessage());
                }
            }

            // Ensure custom saved values appear in dropdowns
            $ivTypeRecords = $listService->getOptionsByListName('iv_access_type');
            $ivTypeOptions = ['' => xl('- Unassigned -')];
            foreach ($ivTypeRecords as $rec) {
                $ivTypeOptions[$rec['option_id']] = text($rec['title']);
            }
            $currentType = $this->infusion_injection_data->iv_access_type ?? '';
            if ($currentType !== '' && !isset($ivTypeOptions[$currentType])) {
                $ivTypeOptions[$currentType] = $currentType;
            }

            $locationOptionsArr = $this->getProcedureBodySiteOptions($listService);
            $currentLoc = $this->infusion_injection_data->iv_access_location ?? '';
            $locKeys = array_column($locationOptionsArr, 'value');
            if ($currentLoc !== '' && !in_array($currentLoc, $locKeys)) {
                $locationOptionsArr[] = ['value' => $currentLoc, 'label' => $currentLoc];
            }

            // Define form fields
            $formFields = [
                'Assessment' => [
                    ['type' => 'textarea', 'label' => xl('Assessment'), 'name' => 'assessment', 'value' => $this->infusion_injection_data->assessment ?? '']
                ],
                'Allergies' => [
                    ['type' => 'html', 'html' => $this->buildAllergyHtml($GLOBALS['pid'] ?? null)]
                ],
                'Vital Signs' => [
                    ['type' => 'text', 'label' => xl('BP Systolic'), 'name' => 'bp_systolic', 'value' => $this->infusion_injection_data->bp_systolic ?? $latestVitals['bp_systolic'] ?? '', 'units' => 'mmHg'],
                    ['type' => 'text', 'label' => xl('BP Diastolic'), 'name' => 'bp_diastolic', 'value' => $this->infusion_injection_data->bp_diastolic ?? $latestVitals['bp_diastolic'] ?? '', 'units' => 'mmHg'],
                    ['type' => 'text', 'label' => xl('Pulse'), 'name' => 'pulse', 'value' => $this->infusion_injection_data->pulse ?? $latestVitals['pulse'] ?? '', 'units' => 'per min'],
                    ['type' => 'text', 'label' => xl('Temperature F'), 'name' => 'temperature_f', 'value' => $this->infusion_injection_data->temperature_f ?? $latestVitals['temperature_f'] ?? '', 'units' => '°F'],
                    ['type' => 'text', 'label' => xl('Oxygen Saturation %'), 'name' => 'oxygen_saturation', 'value' => $this->infusion_injection_data->oxygen_saturation ?? $latestVitals['oxygen_saturation'] ?? '', 'units' => '%'],
                ],
                'IV Access' => [
                    ['type' => 'select_add_guard', 'label' => xl('Type of IV access'), 'name' => 'iv_access_type', 'options' => $this->getDropdownOptions($ivTypeOptions), 'value' => (!empty($_POST['iv_access_type_new']) ? $_POST['iv_access_type_new'] : ($this->infusion_injection_data->iv_access_type ?? ''))],
                    ['type' => 'select_add_guard', 'label' => xl('Location'), 'name' => 'iv_access_location', 'options' => $locationOptionsArr, 'value' => (!empty($_POST['iv_access_location_new']) ? $_POST['iv_access_location_new'] : ($_POST['iv_access_location'] ?? $this->infusion_injection_data->iv_access_location ?? null))],
                    ['type' => 'select', 'label' => xl('Blood Return'), 'name' => 'iv_access_blood_return', 'options' => $this->getDropdownOptions(['' => xl('- Unassigned -'), 'Yes' => xl('Yes'), 'No' => xl('No')]), 'value' => $this->infusion_injection_data->iv_access_blood_return ?? ''],
                    ['type' => 'text', 'label' => xl('Needle Gauge'), 'name' => 'iv_access_needle_gauge', 'value' => $this->infusion_injection_data->iv_access_needle_gauge ?? ''],
                    ['type' => 'text', 'label' => xl('# of attempts'), 'name' => 'iv_access_attempts', 'value' => $this->infusion_injection_data->iv_access_attempts ?? ''],
                    ['type' => 'textarea', 'label' => xl('Comments'), 'name' => 'iv_access_comments', 'value' => $this->infusion_injection_data->iv_access_comments ?? ''],
                ],
                'Order' => [
                    ['type' => 'select', 'label' => xl('Medication'), 'name' => 'order_medication', 'options' => $this->getMedicationOptions($listService), 'value' => $this->infusion_injection_data->order_medication ?? ''],
                    ['type' => 'text', 'label' => xl('Dose'), 'name' => 'order_dose', 'value' => $this->infusion_injection_data->order_dose ?? ''],
                    ['type' => 'text', 'label' => xl('Lot #'), 'name' => 'order_lot_number', 'value' => $this->infusion_injection_data->order_lot_number ?? ''],
                    ['type' => 'text', 'label' => xl('NDC'), 'name' => 'order_ndc', 'value' => $this->infusion_injection_data->order_ndc ?? ''],
                    ['type' => 'date', 'label' => xl('Expiration Date'), 'name' => 'order_expiration_date', 'value' => $this->infusion_injection_data->order_expiration_date ?? ''],
                    ['type' => 'number_unit_select', 'label' => xl('Every'), 'name_num' => 'order_every_value', 'name_unit' => 'order_every_unit',
                     'num_options' => $this->getNumericRangeOptions(1, 30, xl('- Unassigned -')), 'unit_options' => $this->getDropdownOptions(['' => xl('- Unassigned -'), 'days' => xl('Days'), 'weeks' => xl('Weeks'), 'months' => xl('Months'), 'years' => xl('Years')]),
                     'value_num' => $this->infusion_injection_data->order_every_value ?? '', 'value_unit' => $this->infusion_injection_data->order_every_unit ?? ''],
                    ['type' => 'text', 'label' => xl('Servicing Provider'), 'name' => 'order_servicing_provider', 'value' => $this->infusion_injection_data->order_servicing_provider ?? 'Moussa El-hallak, M.D.'],
                    ['type' => 'text', 'label' => xl('NPI'), 'name' => 'order_npi', 'value' => $this->infusion_injection_data->order_npi ?? '1831381524'],
                    ['type' => 'textarea', 'label' => xl('Note'), 'name' => 'order_note', 'value' => $this->infusion_injection_data->order_note ?? ''],
                ],
                'Administration' => [
                    ['type' => 'datetime', 'label' => xl('Start'), 'name' => 'administration_start', 'value' => $this->infusion_injection_data->administration_start ?? ''],
                    ['type' => 'datetime', 'label' => xl('End'), 'name' => 'administration_end', 'value' => $this->infusion_injection_data->administration_end ?? ''],
                    ['type' => 'textarea', 'label' => xl('Note'), 'name' => 'administration_note', 'value' => $this->infusion_injection_data->administration_note ?? ''],
                ],
            ];

            $data = [
                'FORM_ACTION' => $GLOBALS['web_root'],
                'DONT_SAVE_LINK' => $GLOBALS['form_exit_url'],
                'STYLE' => $GLOBALS['style'],
                'CSRF_TOKEN_FORM' => CsrfUtils::collectCsrfToken(),
                'VIEW' => true,
                'has_id' => $this->form_id,
                'formSections' => $formFields,
                'infusion_injection_data' => $this->infusion_injection_data,
                'patient_id' => $GLOBALS['pid'] ?? null,
            ];

            // Include OpenEMR datetime picker assets (plugin + translated helper)
            Header::setupHeader(['datetime-picker', 'datetime-picker-translated']);

            $twig = (new TwigContainer($this->template_dir, $GLOBALS['kernel']))->getTwig();
            return $twig->render("infusion_injection_form.html.twig", $data);

        } catch (\Exception $e) {
            $errorMessage = "Error loading form: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            error_log("Form rendering error in C_FormInfusionInjection::default_action(): " . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
            return "<div class='alert alert-danger'>{$errorMessage}</div>";
        }
    }

    private function getDropdownOptions(array $options)
    {
        // Ensure options are sorted alphabetically by label (A -> Z) except that blank/Unassigned stays first
        $unassigned = [];
        if (isset($options[''])) {
            $unassigned[''] = $options[''];
            unset($options['']);
        }
        // Sort remaining options by label
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $options = $unassigned + $options; // merge keeping Unassigned first if exists

        $formattedOptions = [];
        foreach ($options as $value => $label) {
            $formattedOptions[] = ['value' => $value, 'label' => $label];
        }
        return $formattedOptions;
    }

    private function getNumericRangeOptions(int $start, int $end, string $unassignedLabel = '- Unassigned -')
    {
        $options = [['value' => '', 'label' => $unassignedLabel]];
        for ($i = $start; $i <= $end; $i++) {
            $options[] = ['value' => (string)$i, 'label' => (string)$i];
        }
        return $options;
    }

    private function getProcedureBodySiteOptions(ListService $listService)
    {
        // Use list_options list for iv_access_location instead of procedure body site? keep old function for other use
        $options = $listService->getOptionsByListName('iv_access_location');
        $active = [];
        foreach ($options as $option) {
            if (!$option['inactive']) {
                $active[$option['option_id']] = text($option['title']);
            }
        }
        return $this->getDropdownOptions(['' => xl('- Unassigned -')] + $active);
    }

    /**
     * Ensure a list id is registered in list_options master list (list_id='lists').
     */
    private function ensureListExists(string $list_id): void
    {
        $row = sqlQuery("SELECT 1 FROM list_options WHERE list_id = 'lists' AND option_id = ?", [$list_id]);
        if ($row) {return;}
        $seqRow = sqlQuery("SELECT MAX(seq) AS maxseq FROM list_options WHERE list_id = 'lists'", []);
        $nextSeq = ($seqRow && $seqRow['maxseq'] !== null) ? ((int)$seqRow['maxseq'] + 1) : 10;
        sqlStatement("INSERT INTO list_options (list_id, option_id, title, seq, is_default, option_value, activity) VALUES ('lists', ?, ?, ?, 0, ?, 1)", [$list_id, $list_id, $nextSeq, $list_id]);
    }

    /**
     * Ensure a list option exists (creates list row if needed).
     */
    private function ensureListOptionExists(string $list_id, string $value): void
    {
        if ($value === '') {return;}
        // Ensure parent list exists first
        $this->ensureListExists($list_id);
        $row = sqlQuery("SELECT 1 FROM list_options WHERE list_id = ? AND option_id = ?", [$list_id, $value]);
        if ($row) {return;}
        // determine next seq
        $seqRow = sqlQuery("SELECT MAX(seq) AS maxseq FROM list_options WHERE list_id = ?", [$list_id]);
        $nextSeq = ($seqRow && $seqRow['maxseq'] !== null) ? ((int)$seqRow['maxseq'] + 1) : 10;
        // insert
        sqlStatement("INSERT INTO list_options (list_id, option_id, title, seq, is_default, option_value, activity) VALUES (?,?,?,?,0,?,1)", [$list_id, $value, $value, $nextSeq, $value]);
    }

    private function getMedicationOptions(ListService $listService)
    {
        // This is a placeholder. Fetching and formatting RXCUI medications
        // will likely be more complex and might involve a dedicated service or helper.
        // For now, returns a dummy list.
        // TODO: Implement proper RXCUI medication list fetching.
        $options = [
            '' => xl('- Unassigned -'),
            'rxnorm:197381' => xl('Acetaminophen 325 MG Oral Tablet'), // Example
            'rxnorm:8640' => xl('Penicillin V Potassium 250 MG Oral Tablet'), // Example
        ];
        return $this->getDropdownOptions($options);
    }

    /**
     * Build HTML listing of active allergies for the patient (uses lists table).
     */
    private function buildAllergyHtml(?int $pid): string
    {
        if (empty($pid)) {
            return '<p>' . xl('No patient selected.') . '</p>';
        }

        $rows = sqlStatement("SELECT title,reaction FROM lists WHERE pid = ? AND type = 'allergy' AND (enddate IS NULL OR enddate = '' OR enddate = '0000-00-00') AND activity = 1", [$pid]);
        $items = [];
        while ($row = sqlFetchArray($rows)) {
            $label = text($row['title']);
            $reaction = trim($row['reaction']);
            if ($reaction !== '') {
                $label .= ' (' . text($reaction) . ')';
            }
            $items[] = $label;
        }

        if (empty($items)) {
            return '<p>' . xl('No known allergies recorded.') . '</p>';
        }

        $html = '<ul class="list-unstyled mb-0">';
        foreach ($items as $item) {
            $html .= '<li>&#8226; ' . $item . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    public function default_action_process()
    {
        if (empty($_POST['process']) || $_POST['process'] != "true") {
            return; // Or handle error appropriately
        }

        // TODO: Add comprehensive validation and sanitization for all fields.
        // For now, we'll do basic retrieval.

        $this->infusion_injection_data = new stdClass(); // Using stdClass for now
        $this->populate_object($this->infusion_injection_data); // Populates from $_POST and session

        // Prepare data for SQL query
        $formData = [
            'pid' => $this->infusion_injection_data->pid,
            'encounter' => $this->infusion_injection_data->encounter,
            'user' => $this->infusion_injection_data->user,
            'groupname' => $this->infusion_injection_data->groupname,
            'authorized' => $this->infusion_injection_data->authorized,
            'activity' => 1, // Typically 1 for active form
            'date' => date('Y-m-d H:i:s'), // Current datetime for new/updated form
            'assessment' => $_POST['assessment'] ?? null,
            'iv_access_type' => (!empty($_POST['iv_access_type_new']) ? $_POST['iv_access_type_new'] : ($_POST['iv_access_type'] ?? null)),
            'iv_access_location' => (!empty($_POST['iv_access_location_new']) ? $_POST['iv_access_location_new'] : ($_POST['iv_access_location'] ?? $this->infusion_injection_data->iv_access_location ?? null)),
            'iv_access_blood_return' => $_POST['iv_access_blood_return'] ?? null,
            'iv_access_needle_gauge' => $_POST['iv_access_needle_gauge'] ?? null,
            'iv_access_attempts' => $_POST['iv_access_attempts'] ?? null,
            'iv_access_comments' => $_POST['iv_access_comments'] ?? null,
            'order_medication' => $_POST['order_medication'] ?? null,
            'order_dose' => $_POST['order_dose'] ?? null,
            'order_lot_number' => $_POST['order_lot_number'] ?? null,
            'order_ndc' => $_POST['order_ndc'] ?? null,
            'order_expiration_date' => empty($_POST['order_expiration_date']) ? null : $_POST['order_expiration_date'],
            'order_every_value' => $_POST['order_every_value'] ?? null,
            'order_every_unit' => $_POST['order_every_unit'] ?? null,
            'order_servicing_provider' => $_POST['order_servicing_provider'] ?? 'Moussa El-hallak, M.D.',
            'order_npi' => $_POST['order_npi'] ?? '1831381524',
            'order_note' => $_POST['order_note'] ?? null,
            // Vital sign fields also stored in infusion table for quick reference
            'bp_systolic' => $_POST['bp_systolic'] ?? null,
            'bp_diastolic' => $_POST['bp_diastolic'] ?? null,
            'pulse' => $_POST['pulse'] ?? null,
            'temperature_f' => $_POST['temperature_f'] ?? null,
            'oxygen_saturation' => $_POST['oxygen_saturation'] ?? null,
            'administration_start' => empty($_POST['administration_start']) ? null : $_POST['administration_start'],
            'administration_end' => empty($_POST['administration_end']) ? null : $_POST['administration_end'],
            'administration_note' => $_POST['administration_note'] ?? null,
        ];

        if (empty($_POST['id'])) { // New form
            // Correct way to generate UUID
            $uuidRegistry = new UuidRegistry(['table_name' => 'form_infusion_injection']);
            $formData['uuid'] = $uuidRegistry->createUuid();
            $sql = "INSERT INTO form_infusion_injection SET ";
            $first = true;
            foreach ($formData as $key => $value) {
                if (!$first) {
                    $sql .= ", ";
                }
                $sql .= "`$key` = ?";
                $first = false;
            }
            // sqlInsert() handles both the INSERT and returns the new ID
            $this->form_id = sqlInsert($sql, array_values($formData));
            $_POST['id'] = $this->form_id; // Set it for formJump or other post-save actions

            // Create entry in the forms table for encounter display
            $formsData = [
                'date' => date('Y-m-d H:i:s'),
                'encounter' => $this->infusion_injection_data->encounter,
                'pid' => $this->infusion_injection_data->pid,
                'user' => $this->infusion_injection_data->user,
                'groupname' => $this->infusion_injection_data->groupname,
                'authorized' => $this->infusion_injection_data->authorized,
                'formdir' => 'infusion_injection',
                'form_id' => $this->form_id,
                'form_name' => 'Infusion and Injection Treatment Form'
            ];
            
            $formsSql = "INSERT INTO forms SET ";
            $first = true;
            foreach ($formsData as $key => $value) {
                if (!$first) {
                    $formsSql .= ", ";
                }
                $formsSql .= "`$key` = ?";
                $first = false;
            }
            sqlInsert($formsSql, array_values($formsData));

        } else { // Existing form
            $this->form_id = $_POST['id'];
            // Ensure date is updated on edit if that's the desired behavior
            // $formData['date'] = date('Y-m-d H:i:s');
            unset($formData['pid']); // Usually PID doesn't change on edit
            unset($formData['encounter']); // Encounter might also be fixed

            $sql = "UPDATE form_infusion_injection SET ";
            $first = true;
            $updateValues = [];
            foreach ($formData as $key => $value) {
                // Skip keys that shouldn't be updated directly or are part of WHERE
                if ($key === 'uuid' && empty($value)) { // Don't update UUID if it wasn't fetched/set
                    continue;
                }
                if (!$first) {
                    $sql .= ", ";
                }
                $sql .= "`$key` = ?";
                $updateValues[] = $value;
                $first = false;
            }
            $sql .= " WHERE `id` = ? AND `pid` = ?";
            $updateValues[] = $this->form_id;
            $updateValues[] = $GLOBALS['pid']; // Ensure user is updating their own patient's form
            $result = sqlStatement($sql, $updateValues);
            
            // Update the forms table entry
            $formsUpdateSql = "UPDATE forms SET date = ? WHERE formdir = 'infusion_injection' AND form_id = ? AND pid = ?";
            sqlStatement($formsUpdateSql, [date('Y-m-d H:i:s'), $this->form_id, $GLOBALS['pid']]);
        }

        if ($result === false) {
            // TODO: Handle SQL error (e.g., log it, display a message to the user)
            (new SystemLogger())->error("Failed to save form_infusion_injection data for form ID: " . $this->form_id . " and PID: " . $GLOBALS['pid']);
            // Potentially redirect back with an error message
            return;
        }

        /*
         * ---------------------------------------------------------------------
         * Save / Update VITAL SIGNS to form_vitals table for this encounter.
         * ---------------------------------------------------------------------
         */
        try {
            $vitalInputs = [
                'bps' => $_POST['bp_systolic'] ?? null,
                'bpd' => $_POST['bp_diastolic'] ?? null,
                'pulse' => $_POST['pulse'] ?? null,
                'temperature' => $_POST['temperature_f'] ?? null,
                'oxygen_saturation' => $_POST['oxygen_saturation'] ?? null,
            ];

            // Only proceed if at least one vital sign has been entered
            $hasVitals = false;
            foreach ($vitalInputs as $v) {
                if ($v !== null && $v !== '') {
                    $hasVitals = true;
                    break;
                }
            }

            if ($hasVitals && !empty($this->infusion_injection_data->pid) && !empty($this->infusion_injection_data->encounter)) {
                $vitalsService = new VitalsService();

                // Check for existing vitals record for this patient + encounter
                $existingVitals = $vitalsService->getVitalsForPatientEncounter($this->infusion_injection_data->pid, $this->infusion_injection_data->encounter);
                $record = [];
                if (!empty($existingVitals) && is_array($existingVitals)) {
                    // Update first record (assumes list ordered by date desc)
                    $record = $existingVitals[0];
                    $record['id'] = $record['form_id'] ?? $record['id'] ?? null; // ensure id present for update
                }

                // Merge vital inputs
                foreach ($vitalInputs as $k => $v) {
                    if ($v !== null && $v !== '') {
                        $record[$k] = $v;
                    }
                }

                // Required base fields
                $record['pid'] = $this->infusion_injection_data->pid;
                $record['eid'] = $this->infusion_injection_data->encounter;
                $record['authorized'] = $_SESSION['userauthorized'] ?? 0;
                $record['user'] = $_SESSION['authUser'] ?? null;
                $record['groupname'] = $_SESSION['authProvider'] ?? null;
                if (empty($record['date'])) {
                    $record['date'] = date('Y-m-d H:i:s');
                }

                // Persist via service
                $vitalsService->saveVitalsArray($record);
            }
        } catch (\Exception $ex) {
            // Log but do not block infusion form save
            (new SystemLogger())->error("Infusion/Injection form: Failed to save vitals data - " . $ex->getMessage());
        }

        // TODO: Save allergy data (this will likely involve OpenEMR's allergy service/functions)
        // Example:
        // $patientService = new PatientService();
        // $patientService->addOrUpdateAllergy($GLOBALS['pid'], $allergy_type, $allergen, $reaction, ...);

        // TODO: Save vital signs data to form_vitals
        // This will likely involve creating a new VitalsService or FormVitals object, populating it, and saving.
        // Example:
        // $vitalsData = new FormVitals(); // Assuming FormVitals is the data model for vitals
        // $vitalsData->set_pid($GLOBALS['pid']);
        // $vitalsData->set_encounter($this->infusion_injection_data->encounter);
        // $vitalsData->set_user($this->infusion_injection_data->user);
        // ... set other vital fields from $_POST['bp_systolic'], etc.
        // $vitalsService = new VitalsService(); // Or however vitals are saved
        // $vitalsService->saveVitalsForm($vitalsData);

        // After determining new values, ensure they exist in list_options
        if (!empty($_POST['iv_access_type_new'])) {
            $this->ensureListOptionExists('iv_access_type', $_POST['iv_access_type_new']);
        }
        if (!empty($_POST['iv_access_location_new'])) {
            $this->ensureListOptionExists('iv_access_location', $_POST['iv_access_location_new']);
        }

        // The formJump() function (called in save.php) will handle redirecting.
        return;
    }

    /**
     * Populates an object (expected to be stdClass or a custom data model)
     * with data from $_POST and session globals.
     *
     * @param stdClass $obj The object to populate.
     */
    public function populate_object(&$obj)
    {
        if (!is_object($obj)) {
            // This should ideally throw an error or log, but for now, we'll ensure it's an object.
            $obj = new stdClass();
        }

        // General properties from $_POST - a more robust solution would map specific fields
        // or use setters if $obj were a defined class.
        foreach ($_POST as $key => $value) {
            if (property_exists($obj, $key) || is_a($obj, 'stdClass')) {
                 // Sanitize string input using htmlspecialchars to prevent XSS
                if (is_string($value)) {
                    $obj->$key = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                } else {
                    // For non-string values, assign directly or handle as appropriate
                    // This might need more specific handling based on expected data types
                    $obj->$key = $value;
                }
            }
        }

        // Core properties from globals/session
        $obj->pid = $GLOBALS['pid'];
        $obj->encounter = $GLOBALS['encounter'] < 1 ? date("Ymd") : $GLOBALS['encounter'];
        $obj->user = $_SESSION['authUser'] ?? null;
        $obj->groupname = $_SESSION['authProvider'] ?? null;
        $obj->authorized = $_SESSION['userauthorized'] ?? 0;

        // If it's an existing form, ensure 'id' is populated
        if (!empty($_POST['id'])) {
            $obj->id = $_POST['id'];
        }
    }

    // Removed populate_session_user_information as its logic is merged into populate_object
    // private function populate_session_user_information(&$obj) // TODO: Change type hint if FormInfusionInjection is different
    // {
    // // TODO: Ensure $obj has these setter methods
    // // $obj->set_groupname($_SESSION['authProvider']);
    // // $obj->set_user($_SESSION['authUser']);
    // } // This was an extra closing brace causing the parse error.
}
