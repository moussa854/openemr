<?php
// Use OpenEMR database credentials
$host = 'localhost';
$dbname = 'openemr';
$username = 'openemr';
$password = 'cfvcfv33';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

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

try {
    // Get POST data
    $data = $_POST;
    
    // Validate required fields
    if (empty($data['name'])) {
        throw new Exception('Drug name is required');
    }
    
    // Prepare data for insertion
    $name = $data['name'];
    $barcode = $data['barcode'] ?? '';
    $ndc_10 = $data['ndc_10'] ?? '';
    $ndc_11 = $data['ndc_11'] ?? '';
    $is_controlled = isset($data['is_controlled_substance']) ? 1 : 0;
    $form = $data['form'] ?? '';
    $size = $data['size'] ?? '';
    $unit = $data['unit'] ?? '';
    $route = $data['route'] ?? '';
    $quantity = intval($data['quantity'] ?? 0);
    $quantity_unit = $data['quantity_unit'] ?? 'vial';
    $lot_number = $data['lot_number'] ?? '';
    $expiration_date = convertDateToMysql($data['expiration_date'] ?? null);
    
    // Debug: Log the date conversion
    error_log("Add drug - Original expiration_date: '{$data['expiration_date']}', MySQL format: '$expiration_date'");
    
    // Insert into drugs table
    $sql = "INSERT INTO drugs (name, barcode, ndc_10, ndc_11, is_controlled_substance, 
            form, size, unit, route, quantity, quantity_unit, lot_number, expiration_date, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $name, $barcode, $ndc_10, $ndc_11, $is_controlled,
        $form, $size, $unit, $route, $quantity, $quantity_unit, $lot_number, $expiration_date
    ]);
    
    if ($result) {
        $drug_id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Drug added successfully',
            'drug_id' => $drug_id
        ]);
    } else {
        throw new Exception('Failed to add drug');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
