<?php

/**
 * Migration script to add existing infusion_injection forms to the forms table
 * Run this script once to migrate existing data
 */

require_once(__DIR__ . "/../../globals.php");
require_once($GLOBALS["srcdir"] . "/api.inc.php");

echo "Starting migration of existing infusion_injection forms to forms table...\n";

// Get all infusion_injection forms that don't have entries in the forms table
$sql = "SELECT fii.* FROM form_infusion_injection fii 
        LEFT JOIN forms f ON f.formdir = 'infusion_injection' AND f.form_id = fii.id 
        WHERE f.id IS NULL";

$result = sqlStatement($sql);
$count = 0;

while ($row = sqlFetchArray($result)) {
    // Create entry in forms table
    $formsData = [
        'date' => $row['date'] ?? date('Y-m-d H:i:s'),
        'encounter' => $row['encounter'],
        'pid' => $row['pid'],
        'user' => $row['user'],
        'groupname' => $row['groupname'],
        'authorized' => $row['authorized'],
        'activity' => $row['activity'] ?? 1,
        'formdir' => 'infusion_injection',
        'form_id' => $row['id'],
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
    
    $insertResult = sqlInsert($formsSql, array_values($formsData));
    if ($insertResult) {
        $count++;
        echo "Migrated form ID: " . $row['id'] . " for patient: " . $row['pid'] . "\n";
    } else {
        echo "Failed to migrate form ID: " . $row['id'] . "\n";
    }
}

echo "Migration complete. Migrated $count forms.\n"; 