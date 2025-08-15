<?php
/**
 * API endpoint for ICD-10 diagnosis search
 * 
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Initialize OpenEMR
$ignoreAuth = false;
require_once dirname(__FILE__) . "/../../../../../interface/globals.php";

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['authUserID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    // Get search parameters
    $search_term = $_GET['search'] ?? '';
    $limit = (int)($_GET['limit'] ?? 20);
    
    if (empty($search_term)) {
        echo json_encode(['success' => false, 'message' => 'Search term is required']);
        exit;
    }
    
    // Prepare wildcard search term
    $wildcard = "%{$search_term}%";
    
    // Search in ICD-10 table (use parameter binding to avoid SQL injection and be database-portable)
    $sql = "SELECT 
                dx_code AS code,
                formatted_dx_code,
                short_desc AS description,
                long_desc,
                active
            FROM icd10_dx_order_code 
            WHERE active = 1 
              AND (
                    dx_code LIKE ? OR
                    formatted_dx_code LIKE ? OR
                    short_desc LIKE ? OR
                    long_desc LIKE ?
                  )
            ORDER BY dx_code ASC 
            LIMIT ?";
    
    $stmt = sqlStatement($sql, [$wildcard, $wildcard, $wildcard, $wildcard, $limit]);
    $results = [];
    while ($row = sqlFetchArray($stmt)) {
        $results[] = [
            'code' => $row['code'],
            'formatted_code' => $row['formatted_dx_code'],
            'description' => $row['description'],
            'long_description' => $row['long_desc'],
            'active' => (bool)$row['active']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    error_log("ICD-10 search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred during ICD-10 search'
    ]);
}
?> 