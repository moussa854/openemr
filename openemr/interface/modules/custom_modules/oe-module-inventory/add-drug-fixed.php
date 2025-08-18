<?php
// Add Drug API - Fixed Version
// Uses correct database credentials

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Debug: Log received data
error_log("Add drug - Received POST data: " . print_r($_POST, true));

// Collect & validate input
$name = trim($_POST['name'] ?? '');
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Drug name is required']);
    exit;
}

$barcode = trim($_POST['barcode'] ?? '');
$ndc_10 = trim($_POST['ndc_10'] ?? '');
$ndc_11 = trim($_POST['ndc_11'] ?? '');
$size = trim($_POST['size'] ?? '');
$unit = trim($_POST['unit'] ?? '');
$dose = trim($_POST['dose'] ?? '');
$route = trim($_POST['route'] ?? '');
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$quantity_unit = trim($_POST['quantity_unit'] ?? 'vial');
$lot_number = trim($_POST['lot_number'] ?? '');
$expiration_date = trim($_POST['expiration_date'] ?? '');
$form = trim($_POST['form'] ?? '');
$is_controlled = isset($_POST['is_controlled_substance']) ? 1 : 0;
$vial_type = trim($_POST['vial_type'] ?? 'unknown');

// Convert date format from MM/DD/YYYY to YYYY-MM-DD for MySQL
function convertDateToMysql($date) {
    if (empty($date)) {
        return null;
    }
    
    // Try different date formats
    $formats = ['m/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y', 'Y-m-d'];
    
    foreach ($formats as $format) {
        $dateObj = DateTime::createFromFormat($format, $date);
        if ($dateObj !== false) {
            return $dateObj->format('Y-m-d');
        }
    }
    
    // If no format worked, try strtotime
    $timestamp = strtotime($date);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

// Convert expiration date
$mysql_expiration_date = convertDateToMysql($expiration_date);

// Debug: Log processed data
error_log("Add drug - Processed data: name=$name, barcode=$barcode, ndc_10=$ndc_10, ndc_11=$ndc_11");
error_log("Add drug - Original expiration_date: '$expiration_date', MySQL format: '$mysql_expiration_date'");

try {
    // Direct database connection with correct credentials
    $host = 'localhost';
    $dbname = 'openemr';
    $username = 'openemr';
    $password = 'cfvcfv33';
    
    $dsn = "mysql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    
    $insert_sql = "INSERT INTO drugs (
        name, barcode, ndc_10, ndc_11, size, unit, dose, route, 
        quantity, quantity_unit, lot_number, expiration_date, form, 
        is_controlled_substance, vial_type, vial_type_source, status, date_created, last_updated
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', 'active', NOW(), NOW())";

    $stmt = $pdo->prepare($insert_sql);
    $result = $stmt->execute([
        $name,
        $barcode ?: null,
        $ndc_10 ?: null,
        $ndc_11 ?: null,
        $size,
        $unit,
        $dose,
        $route,
        $quantity,
        $quantity_unit,
        $lot_number ?: null,
        $mysql_expiration_date,
        $form,
        $is_controlled,
        $vial_type
    ]);

    // Debug: Log insert result
    error_log("Add drug - SQL executed: " . ($result ? 'SUCCESS' : 'FAILED'));
    error_log("Add drug - New drug ID: " . $pdo->lastInsertId());

    echo json_encode(['success' => true, 'message' => 'Drug added successfully', 'drug_id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    error_log('Add drug error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
