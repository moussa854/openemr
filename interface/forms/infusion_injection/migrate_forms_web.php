<?php

/**
 * Web-accessible migration script to add existing infusion_injection forms to the forms table
 * Access this through your browser: /interface/forms/infusion_injection/migrate_forms_web.php
 */

require_once(__DIR__ . "/../../globals.php");
require_once($GLOBALS["srcdir"] . "/api.inc.php");

use OpenEMR\Common\Acl\AclMain;

// Check if user is authorized
if (!AclMain::aclCheckCore('admin', 'super')) {
    die("Access denied. Admin privileges required.");
}

echo "<html><head><title>Infusion Injection Form Migration</title></head><body>";
echo "<h2>Infusion Injection Form Migration</h2>";

if (isset($_POST['run_migration'])) {
    echo "<p>Starting migration of existing infusion_injection forms to forms table...</p>";
    
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
            echo "<p>✓ Migrated form ID: " . htmlspecialchars($row['id']) . " for patient: " . htmlspecialchars($row['pid']) . "</p>";
        } else {
            echo "<p>✗ Failed to migrate form ID: " . htmlspecialchars($row['id']) . "</p>";
        }
    }
    
    echo "<p><strong>Migration complete. Migrated $count forms.</strong></p>";
    echo "<p><a href='migrate_forms_web.php'>Back to migration page</a></p>";
    
} else {
    // Check how many forms need migration
    $sql = "SELECT COUNT(*) as count FROM form_infusion_injection fii 
            LEFT JOIN forms f ON f.formdir = 'infusion_injection' AND f.form_id = fii.id 
            WHERE f.id IS NULL";
    
    $result = sqlQuery($sql);
    $needsMigration = $result['count'];
    
    echo "<p>Found $needsMigration infusion_injection forms that need to be migrated to the forms table.</p>";
    
    if ($needsMigration > 0) {
        echo "<form method='post'>";
        echo "<input type='submit' name='run_migration' value='Run Migration' style='background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer;'>";
        echo "</form>";
    } else {
        echo "<p>All forms are already migrated!</p>";
    }
    
    echo "<p><a href='../../patient_file/encounter/forms.php'>Back to Encounter Forms</a></p>";
}

echo "</body></html>"; 