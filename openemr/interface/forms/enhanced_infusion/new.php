<?php
/**
 * New/Edit script for Enhanced Infusion Form
 * Re-use the single-page UI that lives under oe-module-inventory integration.
 */
require_once(dirname(__FILE__) . '/../../globals.php');

$pid       = $_GET['pid']       ?? 0;
$encounter = $_GET['encounter'] ?? 0;
$form_id   = $_GET['id']        ?? '';

// The integration file relies on pid / encounter / form_id via GET
$_GET['pid']       = $pid;
$_GET['encounter'] = $encounter;
$_GET['form_id']   = $form_id;

// Pull in the actual UI
include_once($GLOBALS['fileroot'] . '/interface/modules/custom_modules/oe-module-inventory/integration/infusion_search_enhanced.php');
?>
