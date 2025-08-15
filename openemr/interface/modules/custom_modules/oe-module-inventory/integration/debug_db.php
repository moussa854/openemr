<?php
/**
 * Database debugging script for Enhanced Infusion Form
 * Shows recent form entries and their associated data
 */
require_once("../../../../globals.php");

echo "<h2>Enhanced Infusion Form Debug - Recent Entries</h2>";

// Show recent forms table entries
echo "<h3>Recent Forms Table Entries</h3>";
$forms = sqlStatement("SELECT * FROM forms WHERE formdir = 'enhanced_infusion' ORDER BY id DESC LIMIT 10");
echo "<table border='1'><tr><th>forms.id</th><th>form_id</th><th>pid</th><th>encounter</th><th>form_name</th><th>date</th></tr>";
while ($form = sqlFetchArray($forms)) {
    echo "<tr>";
    echo "<td>" . $form['id'] . "</td>";
    echo "<td>" . $form['form_id'] . "</td>";
    echo "<td>" . $form['pid'] . "</td>";
    echo "<td>" . $form['encounter'] . "</td>";
    echo "<td>" . $form['form_name'] . "</td>";
    echo "<td>" . $form['date'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show recent primary injection data
echo "<h3>Recent Primary Injection Data</h3>";
$injections = sqlStatement("SELECT * FROM form_enhanced_infusion_injection ORDER BY id DESC LIMIT 10");
echo "<table border='1'><tr><th>id</th><th>pid</th><th>encounter</th><th>order_medication</th><th>order_dose</th><th>order_strength</th><th>date</th></tr>";
while ($inj = sqlFetchArray($injections)) {
    echo "<tr>";
    echo "<td>" . $inj['id'] . "</td>";
    echo "<td>" . $inj['pid'] . "</td>";
    echo "<td>" . $inj['encounter'] . "</td>";
    echo "<td>" . $inj['order_medication'] . "</td>";
    echo "<td>" . $inj['order_dose'] . "</td>";
    echo "<td>" . $inj['order_strength'] . "</td>";
    echo "<td>" . $inj['date'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show recent secondary medications
echo "<h3>Recent Secondary Medications</h3>";
$secondaries = sqlStatement("SELECT * FROM form_enhanced_infusion_medications ORDER BY id DESC LIMIT 10");
echo "<table border='1'><tr><th>id</th><th>form_id</th><th>medication_order</th><th>order_medication</th><th>order_dose</th><th>order_strength</th><th>created_date</th></tr>";
while ($sec = sqlFetchArray($secondaries)) {
    echo "<tr>";
    echo "<td>" . $sec['id'] . "</td>";
    echo "<td>" . $sec['form_id'] . "</td>";
    echo "<td>" . $sec['medication_order'] . "</td>";
    echo "<td>" . $sec['order_medication'] . "</td>";
    echo "<td>" . $sec['order_dose'] . "</td>";
    echo "<td>" . $sec['order_strength'] . "</td>";
    echo "<td>" . $sec['created_date'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Cross-reference check
echo "<h3>Cross-Reference Check</h3>";
$crossRef = sqlStatement("
    SELECT 
        f.id as forms_id,
        f.form_id,
        f.pid,
        f.encounter,
        i.order_medication,
        i.order_dose,
        COUNT(m.id) as secondary_count
    FROM forms f
    LEFT JOIN form_enhanced_infusion_injection i ON f.form_id = i.id
    LEFT JOIN form_enhanced_infusion_medications m ON f.form_id = m.form_id
    WHERE f.formdir = 'enhanced_infusion'
    GROUP BY f.id
    ORDER BY f.id DESC
    LIMIT 10
");
echo "<table border='1'><tr><th>forms.id</th><th>form_id</th><th>pid</th><th>encounter</th><th>primary_med</th><th>primary_dose</th><th>secondary_count</th></tr>";
while ($cross = sqlFetchArray($crossRef)) {
    echo "<tr>";
    echo "<td>" . $cross['forms_id'] . "</td>";
    echo "<td>" . $cross['form_id'] . "</td>";
    echo "<td>" . $cross['pid'] . "</td>";
    echo "<td>" . $cross['encounter'] . "</td>";
    echo "<td>" . $cross['order_medication'] . "</td>";
    echo "<td>" . $cross['order_dose'] . "</td>";
    echo "<td>" . $cross['secondary_count'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
