<?php
require_once 'interface/globals.php';

$conn = new PDO("mysql:host=$host;dbname=$dbase", $login, $pass);

echo "Checking for infusion forms in forms table:\n";
$stmt = $conn->query("SELECT DISTINCT form_name FROM forms WHERE form_name LIKE '%infusion%'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\nChecking all forms for encounter 903:\n";
$stmt2 = $conn->query("SELECT * FROM forms WHERE encounter = 903");
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\nChecking form_enhanced_infusion_injection table:\n";
$stmt3 = $conn->query("SELECT id, pid, encounter, date FROM form_enhanced_infusion_injection WHERE encounter = 903");
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
