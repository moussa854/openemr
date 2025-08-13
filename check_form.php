<?php
require_once 'interface/globals.php';

$conn = new PDO("mysql:host=$host;dbname=$dbase", $login, $pass);
$stmt = $conn->query("SELECT id, pid, encounter, date FROM form_enhanced_infusion_injection WHERE id = 1434956");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Form data:\n";
print_r($result);

// Also check the forms table
$stmt2 = $conn->query("SELECT * FROM forms WHERE form_name = 'enhanced_infusion' AND id = 1434956");
$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "\nForms table data:\n";
print_r($result2);
?>
