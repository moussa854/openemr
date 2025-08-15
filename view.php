<?php
/**
 * View (and edit) entry for Enhanced Infusion Form inside encounter UI.
 * Maps the generic forms.id value that OpenEMR passes (`id`) to the
 * pid / encounter / form_id values required by the React-style UI.
 */
require_once(dirname(__FILE__) . '/../../globals.php');

// The id provided by OpenEMR forms system (row in `forms` table)
$formsRowId = intval($_GET['id'] ?? 0);

// Look up pid / encounter and the real data-table id (form_id)
$map = sqlQuery("SELECT pid, encounter, form_id FROM forms WHERE id = ?", [$formsRowId]);

$pid       = $map['pid']       ?? ($_GET['pid'] ?? 0);
$encounter = $map['encounter'] ?? ($_GET['encounter'] ?? 0);
$form_id   = $map['form_id']   ?? $formsRowId;

// Pass parameters expected by the main UI script
$_GET['pid']       = $pid;
$_GET['encounter'] = $encounter;
$_GET['id']        = $form_id;      // Use the actual form_id for data loading
$_GET['forms_id']  = $formsRowId;   // Keep original for save logic

require_once(dirname(__FILE__) . '/new.php');
?>
