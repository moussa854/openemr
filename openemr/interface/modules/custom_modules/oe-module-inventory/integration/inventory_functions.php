<?php
/**
 * Enhanced Inventory Management Functions for SDV/MDV Support
 * 
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Initialize OpenEMR
$ignoreAuth = false;
require_once(dirname(__FILE__) . "/../../../../../interface/globals.php");

/**
 * Get vial type for a drug
 * @param int $drugId Drug ID
 * @return string 'single_dose', 'multi_dose', or 'unknown'
 */
function getVialType($drugId) {
    // First check if vial type is explicitly set in drugs table
    $sql = "SELECT vial_type FROM drugs WHERE drug_id = ?";
    $result = sqlQuery($sql, [$drugId]);
    
    if ($result && $result['vial_type'] !== 'unknown') {
        return $result['vial_type'];
    }
    
    // If not set, try to detect based on NDC
    $ndcResult = sqlQuery("SELECT ndc_number, ndc_10, ndc_11 FROM drugs WHERE drug_id = ?", [$drugId]);
    if ($ndcResult) {
        $vialType = detectVialTypeFromNDC($ndcResult['ndc_number'], $ndcResult['ndc_10'], $ndcResult['ndc_11']);
        if ($vialType !== 'unknown') {
            // Update the drugs table with detected vial type
            sqlStatement("UPDATE drugs SET vial_type = ?, vial_type_source = 'ndc_lookup' WHERE drug_id = ?", 
                        [$vialType, $drugId]);
            return $vialType;
        }
    }
    
    // Default to unknown if we can't determine
    return 'unknown';
}

/**
 * Detect vial type from NDC number
 * @param string $ndcNumber NDC number
 * @param string $ndc10 NDC-10
 * @param string $ndc11 NDC-11
 * @return string 'single_dose', 'multi_dose', or 'unknown'
 */
function detectVialTypeFromNDC($ndcNumber, $ndc10, $ndc11) {
    // Check NDC lookup table
    $sql = "SELECT vial_type, confidence_level FROM ndc_vial_type_lookup 
            WHERE ndc_10 = ? OR ndc_11 = ? OR ndc_10 = ? OR ndc_11 = ?";
    $result = sqlQuery($sql, [$ndc10, $ndc11, $ndcNumber, $ndcNumber]);
    
    if ($result && $result['confidence_level'] !== 'low') {
        return $result['vial_type'];
    }
    
    // Heuristic detection based on common patterns
    $ndcToCheck = [$ndcNumber, $ndc10, $ndc11];
    
    foreach ($ndcToCheck as $ndc) {
        if (empty($ndc)) continue;
        
        // Common SDV patterns (based on manufacturer data)
        $sdvPatterns = [
            '60505-6196', // Ertapenem
            '00071-1010-01', // Common SDV pattern
            '00071-1010-03', // Another SDV pattern
        ];
        
        // Common MDV patterns
        $mdvPatterns = [
            '00071-1010-02', // Common MDV pattern
            '00071-1010-04', // Another MDV pattern
        ];
        
        foreach ($sdvPatterns as $pattern) {
            if (strpos($ndc, $pattern) === 0) {
                return 'single_dose';
            }
        }
        
        foreach ($mdvPatterns as $pattern) {
            if (strpos($ndc, $pattern) === 0) {
                return 'multi_dose';
            }
        }
    }
    
    return 'unknown';
}

/**
 * Deduct inventory from infusion form
 * @param int $formId Form ID
 * @param array $medicationData Medication data from form
 * @return array Result with success status and details
 */
function deductInventoryFromInfusionForm($formId, $medicationData) {
    try {
        $drugId = $medicationData['inventory_drug_id'] ?? null;
        $lotNumber = $medicationData['inventory_lot_number'] ?? null;
        $quantityUsed = $medicationData['inventory_quantity_used'] ?? null;
        $wastageQuantity = $medicationData['inventory_wastage_quantity'] ?? null;
        
        if (!$drugId || !$lotNumber) {
            return ['success' => false, 'message' => 'Missing drug ID or lot number'];
        }
        
        $vialType = getVialType($drugId);
        $result = ['success' => true, 'vial_type' => $vialType, 'details' => []];
        
        if ($vialType === 'single_dose') {
            $deductionResult = deductFullVial($drugId, $lotNumber, $quantityUsed, $wastageQuantity, $formId, $medicationData);
        } else if ($vialType === 'multi_dose') {
            $deductionResult = deductPartialVial($drugId, $lotNumber, $quantityUsed, $wastageQuantity, $formId, $medicationData);
        } else {
            // Unknown type - treat as SDV for safety
            $deductionResult = deductFullVial($drugId, $lotNumber, $quantityUsed, $wastageQuantity, $formId, $medicationData);
        }
        
        $result['details'] = $deductionResult;
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in deductInventoryFromInfusionForm: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error deducting inventory: ' . $e->getMessage()];
    }
}

/**
 * Deduct full vial (for SDV or unknown vial types)
 * @param int $drugId Drug ID
 * @param string $lotNumber Lot number
 * @param float $quantityUsed Quantity used
 * @param float $wastageQuantity Wastage quantity
 * @param int $formId Form ID
 * @param array $medicationData Medication data
 * @return array Result details
 */
function deductFullVial($drugId, $lotNumber, $quantityUsed, $wastageQuantity, $formId, $medicationData) {
    // Get inventory information
    $sql = "SELECT inventory_id, on_hand, partial_usage_remaining, partial_usage_unit 
            FROM drug_inventory 
            WHERE drug_id = ? AND lot_number = ? AND destroy_date IS NULL";
    $inventory = sqlQuery($sql, [$drugId, $lotNumber]);
    
    if (!$inventory) {
        throw new Exception("Inventory not found for drug ID $drugId, lot $lotNumber");
    }
    
    $inventoryId = $inventory['inventory_id'];
    $currentOnHand = $inventory['on_hand'];
    $partialRemaining = $inventory['partial_usage_remaining'];
    
    // For SDV, we always deduct 1 vial regardless of amount used
    $vialsToDeduct = 1;
    
    // Check if we have enough inventory
    if ($currentOnHand < $vialsToDeduct) {
        throw new Exception("Insufficient inventory. Need $vialsToDeduct vial(s), have $currentOnHand");
    }
    
    // Update inventory
    $newOnHand = $currentOnHand - $vialsToDeduct;
    sqlStatement("UPDATE drug_inventory SET on_hand = ? WHERE inventory_id = ?", 
                [$newOnHand, $inventoryId]);
    
    // Clear any partial usage (SDV cannot be partially used)
    if ($partialRemaining !== null) {
        sqlStatement("UPDATE drug_inventory SET 
                     partial_usage_remaining = NULL, 
                     partial_usage_unit = NULL, 
                     partial_usage_opened_date = NULL 
                     WHERE inventory_id = ?", [$inventoryId]);
    }
    
    // Record usage history
    $usageType = ($wastageQuantity > 0) ? 'wastage' : 'full_vial';
    $totalQuantity = $quantityUsed + $wastageQuantity;
    
    recordVialUsage($drugId, $inventoryId, $formId, $medicationData['pid'] ?? null, 
                   $medicationData['encounter'] ?? null, $usageType, $totalQuantity, 
                   $medicationData['order_strength'] ?? 'mg', null);
    
    return [
        'vials_deducted' => $vialsToDeduct,
        'quantity_used' => $quantityUsed,
        'wastage_quantity' => $wastageQuantity,
        'new_on_hand' => $newOnHand,
        'usage_type' => $usageType
    ];
}

/**
 * Deduct partial vial (for MDV)
 * @param int $drugId Drug ID
 * @param string $lotNumber Lot number
 * @param float $quantityUsed Quantity used
 * @param float $wastageQuantity Wastage quantity
 * @param int $formId Form ID
 * @param array $medicationData Medication data
 * @return array Result details
 */
function deductPartialVial($drugId, $lotNumber, $quantityUsed, $wastageQuantity, $formId, $medicationData) {
    // Get inventory information
    $sql = "SELECT inventory_id, on_hand, partial_usage_remaining, partial_usage_unit, 
                   partial_usage_opened_date, partial_usage_expires_after_hours
            FROM drug_inventory 
            WHERE drug_id = ? AND lot_number = ? AND destroy_date IS NULL";
    $inventory = sqlQuery($sql, [$drugId, $lotNumber]);
    
    if (!$inventory) {
        throw new Exception("Inventory not found for drug ID $drugId, lot $lotNumber");
    }
    
    $inventoryId = $inventory['inventory_id'];
    $currentOnHand = $inventory['on_hand'];
    $partialRemaining = $inventory['partial_usage_remaining'];
    $partialUnit = $inventory['partial_usage_unit'];
    $openedDate = $inventory['partial_usage_opened_date'];
    $expiresAfterHours = $inventory['partial_usage_expires_after_hours'];
    
    $totalQuantity = $quantityUsed + $wastageQuantity;
    $unit = $medicationData['order_strength'] ?? 'mg';
    
    // Check if we have a partially used vial that's still valid
    $usePartialVial = false;
    if ($partialRemaining !== null && $partialUnit === $unit) {
        // Check if the opened vial is still within expiration time
        if ($openedDate && $expiresAfterHours) {
            $expirationTime = strtotime($openedDate) + ($expiresAfterHours * 3600);
            if (time() <= $expirationTime) {
                $usePartialVial = true;
            }
        } else {
            // No expiration time set, assume it's still valid
            $usePartialVial = true;
        }
    }
    
    if ($usePartialVial && $partialRemaining >= $totalQuantity) {
        // Use the partially opened vial
        $newPartialRemaining = $partialRemaining - $totalQuantity;
        
        sqlStatement("UPDATE drug_inventory SET 
                     partial_usage_remaining = ? 
                     WHERE inventory_id = ?", 
                     [$newPartialRemaining, $inventoryId]);
        
        $usageType = ($wastageQuantity > 0) ? 'wastage' : 'partial_vial';
        
        recordVialUsage($drugId, $inventoryId, $formId, $medicationData['pid'] ?? null, 
                       $medicationData['encounter'] ?? null, $usageType, $totalQuantity, 
                       $unit, $newPartialRemaining);
        
        return [
            'vials_deducted' => 0,
            'quantity_used' => $quantityUsed,
            'wastage_quantity' => $wastageQuantity,
            'new_partial_remaining' => $newPartialRemaining,
            'usage_type' => $usageType,
            'used_partial_vial' => true
        ];
        
    } else {
        // Need to open a new vial
        if ($currentOnHand < 1) {
            throw new Exception("Insufficient inventory. Need at least 1 vial, have $currentOnHand");
        }
        
        // Calculate remaining amount in the new vial
        $vialSize = getVialSize($drugId, $unit);
        $remainingAfterUse = $vialSize - $totalQuantity;
        
        // Update inventory
        $newOnHand = $currentOnHand - 1;
        $openedDate = date('Y-m-d H:i:s');
        
        sqlStatement("UPDATE drug_inventory SET 
                     on_hand = ?,
                     partial_usage_remaining = ?,
                     partial_usage_unit = ?,
                     partial_usage_opened_date = ?
                     WHERE inventory_id = ?", 
                     [$newOnHand, $remainingAfterUse, $unit, $openedDate, $inventoryId]);
        
        $usageType = ($wastageQuantity > 0) ? 'wastage' : 'partial_vial';
        
        recordVialUsage($drugId, $inventoryId, $formId, $medicationData['pid'] ?? null, 
                       $medicationData['encounter'] ?? null, $usageType, $totalQuantity, 
                       $unit, $remainingAfterUse);
        
        return [
            'vials_deducted' => 1,
            'quantity_used' => $quantityUsed,
            'wastage_quantity' => $wastageQuantity,
            'new_on_hand' => $newOnHand,
            'new_partial_remaining' => $remainingAfterUse,
            'usage_type' => $usageType,
            'opened_new_vial' => true
        ];
    }
}

/**
 * Get vial size for a drug
 * @param int $drugId Drug ID
 * @param string $unit Unit (mg, ml, etc.)
 * @return float Vial size
 */
function getVialSize($drugId, $unit) {
    // Get drug information
    $sql = "SELECT size, unit FROM drugs WHERE drug_id = ?";
    $drug = sqlQuery($sql, [$drugId]);
    
    if ($drug) {
        // Convert to the requested unit if possible
        // This is a simplified version - in practice, you'd need unit conversion logic
        return $drug['size'];
    }
    
    // Default vial size if not specified
    return 1000; // Default to 1000 units
}

/**
 * Record vial usage in history table
 * @param int $drugId Drug ID
 * @param int $inventoryId Inventory ID
 * @param int $formId Form ID
 * @param int $patientId Patient ID
 * @param int $encounterId Encounter ID
 * @param string $usageType Usage type
 * @param float $quantityUsed Quantity used
 * @param string $quantityUnit Quantity unit
 * @param float $remainingAfterUsage Remaining after usage
 */
function recordVialUsage($drugId, $inventoryId, $formId, $patientId, $encounterId, 
                        $usageType, $quantityUsed, $quantityUnit, $remainingAfterUsage) {
    $sql = "INSERT INTO vial_usage_history 
            (drug_id, inventory_id, form_id, patient_id, encounter_id, 
             usage_type, quantity_used, quantity_unit, remaining_after_usage, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    sqlStatement($sql, [
        $drugId, $inventoryId, $formId, $patientId, $encounterId,
        $usageType, $quantityUsed, $quantityUnit, $remainingAfterUsage,
        $_SESSION['authUserID'] ?? 0
    ]);
}

/**
 * Get inventory status for a drug
 * @param int $drugId Drug ID
 * @param string $lotNumber Lot number
 * @return array Inventory status
 */
function getInventoryStatus($drugId, $lotNumber) {
    $sql = "SELECT di.*, d.name, d.vial_type, d.size, d.unit
            FROM drug_inventory di
            JOIN drugs d ON di.drug_id = d.drug_id
            WHERE di.drug_id = ? AND di.lot_number = ? AND di.destroy_date IS NULL";
    
    $result = sqlQuery($sql, [$drugId, $lotNumber]);
    
    if (!$result) {
        return null;
    }
    
    $status = [
        'drug_name' => $result['name'],
        'vial_type' => $result['vial_type'],
        'on_hand' => $result['on_hand'],
        'partial_remaining' => $result['partial_usage_remaining'],
        'partial_unit' => $result['partial_usage_unit'],
        'opened_date' => $result['partial_usage_opened_date'],
        'expires_after_hours' => $result['partial_usage_expires_after_hours'],
        'vial_size' => $result['size'],
        'vial_unit' => $result['unit']
    ];
    
    // Check if partial usage is expired
    if ($status['opened_date'] && $status['expires_after_hours']) {
        $expirationTime = strtotime($status['opened_date']) + ($status['expires_after_hours'] * 3600);
        $status['partial_expired'] = (time() > $expirationTime);
    } else {
        $status['partial_expired'] = false;
    }
    
    return $status;
}
?>
