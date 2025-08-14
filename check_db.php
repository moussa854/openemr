<?php
require_once 'interface/globals.php';

$conn = new PDO("mysql:host=$host;dbname=$dbase", $login, $pass);

echo "Checking form_enhanced_infusion_injection table:\n";
$stmt = $conn->query("SELECT id, pid, encounter, date, assessment, order_medication FROM form_enhanced_infusion_injection WHERE id = 1436789");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($result);

echo "\nChecking forms table:\n";
$stmt2 = $conn->query("SELECT * FROM forms WHERE id = 1436789 AND form_name = 'enhanced_infusion'");
$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
print_r($result2);

echo "\nChecking all forms for this encounter:\n";
$stmt3 = $conn->query("SELECT * FROM forms WHERE encounter = 903 AND form_name = 'enhanced_infusion'");
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
