<?php
/**
 * Enhanced Infusion Form with Multiple Secondary/PRN Medications Support
 * 
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Moussa El-hallak
 * @copyright Copyright (c) 2024 Moussa El-hallak
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// This is a patch to add multiple secondary medications to the existing form
// Insert this section after the administration section and before the signatures section

?>

<!-- Multiple Secondary/PRN Medications Section -->
<div class="form-section">
    <h3 class="section-title"><i class="fa fa-plus-circle"></i> Secondary / PRN Medications (Up to 4)</h3>
    
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i> Add up to 4 secondary or PRN medications for this infusion.
    </div>

    <!-- Secondary Medications Container -->
    <div id="secondary-medications-container">
        <!-- Secondary Medication 1 -->
        <div class="secondary-medication-block" id="secondary-med-1" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
            <div class="row">
                <div class="col-md-10">
                    <h5 class="text-primary"><i class="fa fa-medkit"></i> Secondary Medication #1</h5>
                </div>
                <div class="col-md-2 text-right">
                    <button type="button" class="btn btn-sm btn-danger" onclick="clearSecondaryMedication(1)" title="Clear this medication">
                        <i class="fa fa-trash"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Inventory Search Section -->
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="secondary_drug_search_1" class="control-label">Search Inventory:</label>
                        <input type="text" id="secondary_drug_search_1" class="form-control" 
                               placeholder="Type to search for medications in inventory..." autocomplete="off">
                        <div id="secondary_search_results_1" class="search-results-dropdown" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <!-- Selected Drug Display -->
            <div id="secondary_selected_drug_1" class="selected-drug-display" style="display: none; background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Drug:</strong><br>
                        <span id="secondary_drug_name_display_1"></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Available:</strong><br>
                        <span id="secondary_drug_quantity_display_1"></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Form:</strong><br>
                        <span id="secondary_drug_form_display_1"></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong><br>
                        <span id="secondary_drug_status_display_1"></span>
                    </div>
                </div>
                <input type="hidden" id="secondary_selected_drug_id_1" name="secondary_inventory_drug_id_1">
                <input type="hidden" id="secondary_selected_drug_lot_1" name="secondary_inventory_lot_number_1">
            </div>

            <!-- Medication Details -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_medication_1" class="control-label">Medication Name:</label>
                        <input type="text" name="secondary_medication_1" id="secondary_medication_1" class="form-control" 
                               placeholder="Medication name">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_dose_1" class="control-label">Dose:</label>
                        <input type="text" name="secondary_dose_1" id="secondary_dose_1" class="form-control" 
                               placeholder="Dose and unit">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_strength_1" class="control-label">Strength:</label>
                        <input type="text" name="secondary_strength_1" id="secondary_strength_1" class="form-control" 
                               placeholder="Medication strength">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_route_1" class="control-label">Route:</label>
                        <input type="text" name="secondary_route_1" id="secondary_route_1" class="form-control" 
                               placeholder="Route of administration">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_lot_number_1" class="control-label">Lot Number:</label>
                        <input type="text" name="secondary_lot_number_1" id="secondary_lot_number_1" class="form-control" 
                               placeholder="Lot number">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_expiration_date_1" class="control-label">Expiration Date:</label>
                        <input type="date" name="secondary_expiration_date_1" id="secondary_expiration_date_1" class="form-control">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_ndc_1" class="control-label">NDC:</label>
                        <input type="text" name="secondary_ndc_1" id="secondary_ndc_1" class="form-control" 
                               placeholder="NDC code">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="secondary_frequency_1" class="control-label">Frequency:</label>
                        <select name="secondary_frequency_1" id="secondary_frequency_1" class="form-control">
                            <option value="">Select frequency</option>
                            <option value="once">Once</option>
                            <option value="prn">PRN (as needed)</option>
                            <option value="q4h">Every 4 hours</option>
                            <option value="q6h">Every 6 hours</option>
                            <option value="q8h">Every 8 hours</option>
                            <option value="q12h">Every 12 hours</option>
                            <option value="daily">Daily</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Administration Details -->
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="secondary_admin_start_1" class="control-label">Administration Start:</label>
                        <input type="datetime-local" name="secondary_admin_start_1" id="secondary_admin_start_1" class="form-control">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="secondary_admin_end_1" class="control-label">Administration End:</label>
                        <input type="datetime-local" name="secondary_admin_end_1" id="secondary_admin_end_1" class="form-control">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="secondary_admin_notes_1" class="control-label">Administration Notes:</label>
                        <textarea name="secondary_admin_notes_1" id="secondary_admin_notes_1" class="form-control" rows="2" 
                                  placeholder="Notes about this medication administration"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional medication blocks will be generated by JavaScript -->
    </div>

    <!-- Add Medication Button -->
    <div class="row">
        <div class="col-md-12 text-center">
            <button type="button" id="add-secondary-med-btn" class="btn btn-success" onclick="addSecondaryMedication()">
                <i class="fa fa-plus"></i> Add Another Secondary Medication
            </button>
            <small class="text-muted d-block">You can add up to 4 secondary medications</small>
        </div>
    </div>
</div>

<script>
// JavaScript for multiple secondary medications functionality
var secondaryMedCount = 1;
var maxSecondaryMeds = 4;

function addSecondaryMedication() {
    if (secondaryMedCount >= maxSecondaryMeds) {
        alert('You can only add up to ' + maxSecondaryMeds + ' secondary medications.');
        return;
    }

    secondaryMedCount++;
    
    // Clone the first medication block
    const template = document.getElementById('secondary-med-1');
    const newBlock = template.cloneNode(true);
    
    // Update IDs and names for the new block
    newBlock.id = 'secondary-med-' + secondaryMedCount;
    updateSecondaryMedicationBlock(newBlock, secondaryMedCount);
    
    // Insert before the add button
    const container = document.getElementById('secondary-medications-container');
    container.appendChild(newBlock);
    
    // Update button visibility
    updateAddButtonVisibility();
    
    // Initialize search for the new block
    initializeSecondarySearch(secondaryMedCount);
}

function updateSecondaryMedicationBlock(block, index) {
    // Update the header
    const header = block.querySelector('h5');
    header.innerHTML = '<i class="fa fa-medkit"></i> Secondary Medication #' + index;
    
    // Update clear button
    const clearBtn = block.querySelector('button[onclick*="clearSecondaryMedication"]');
    clearBtn.setAttribute('onclick', 'clearSecondaryMedication(' + index + ')');
    
    // Update all input IDs and names
    const inputs = block.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        const oldId = input.id;
        const oldName = input.name;
        
        if (oldId) {
            input.id = oldId.replace('_1', '_' + index);
        }
        if (oldName) {
            input.name = oldName.replace('_1', '_' + index);
        }
        
        // Clear values for new blocks
        if (index > 1) {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
            }
        }
    });
    
    // Update labels
    const labels = block.querySelectorAll('label');
    labels.forEach(label => {
        const forAttr = label.getAttribute('for');
        if (forAttr) {
            label.setAttribute('for', forAttr.replace('_1', '_' + index));
        }
    });
    
    // Update display elements
    const displayElements = block.querySelectorAll('[id*="_display_"], [id*="_results_"]');
    displayElements.forEach(element => {
        element.id = element.id.replace('_1', '_' + index);
    });
}

function clearSecondaryMedication(index) {
    if (index === 1 && secondaryMedCount === 1) {
        // Just clear the first block instead of removing it
        const block = document.getElementById('secondary-med-1');
        const inputs = block.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        
        // Hide the selected drug display
        const selectedDrugDisplay = document.getElementById('secondary_selected_drug_1');
        if (selectedDrugDisplay) {
            selectedDrugDisplay.style.display = 'none';
        }
    } else {
        // Remove the block
        const block = document.getElementById('secondary-med-' + index);
        if (block) {
            block.remove();
            // Renumber remaining blocks
            renumberSecondaryMedications();
        }
    }
    
    updateAddButtonVisibility();
}

function renumberSecondaryMedications() {
    const blocks = document.querySelectorAll('.secondary-medication-block');
    secondaryMedCount = 0;
    
    blocks.forEach((block, index) => {
        const newIndex = index + 1;
        secondaryMedCount = newIndex;
        
        // Update block ID
        block.id = 'secondary-med-' + newIndex;
        
        // Update the block content
        updateSecondaryMedicationBlock(block, newIndex);
    });
}

function updateAddButtonVisibility() {
    const addBtn = document.getElementById('add-secondary-med-btn');
    if (secondaryMedCount >= maxSecondaryMeds) {
        addBtn.style.display = 'none';
    } else {
        addBtn.style.display = 'inline-block';
    }
}

function initializeSecondarySearch(index) {
    const searchInput = document.getElementById('secondary_drug_search_' + index);
    const resultsDiv = document.getElementById('secondary_search_results_' + index);
    
    if (!searchInput || !resultsDiv) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSecondarySearch(query, index);
        }, 300);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
}

function performSecondarySearch(query, index) {
    fetch('search_inventory.php?site=default&q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            displaySecondarySearchResults(data, index);
        })
        .catch(error => {
            console.error('Secondary search error:', error);
        });
}

function displaySecondarySearchResults(results, index) {
    const resultsDiv = document.getElementById('secondary_search_results_' + index);
    
    if (!results || results.length === 0) {
        resultsDiv.innerHTML = '<div class="search-result-item">No medications found</div>';
    } else {
        resultsDiv.innerHTML = results.map(item => 
            `<div class="search-result-item" onclick="selectSecondaryInventoryItem(${index}, ${item.id}, '${item.name.replace(/'/g, "\\'")}', '${item.quantity}', '${item.form}', '${item.lot_number.replace(/'/g, "\\'")}', '${item.status}')">
                <strong>${item.name}</strong><br>
                <small>Qty: ${item.quantity} | Form: ${item.form} | Lot: ${item.lot_number}</small>
            </div>`
        ).join('');
    }
    
    resultsDiv.style.display = 'block';
}

function selectSecondaryInventoryItem(index, drugId, drugName, quantity, form, lotNumber, status) {
    // Hide search results
    const resultsDiv = document.getElementById('secondary_search_results_' + index);
    resultsDiv.style.display = 'none';
    
    // Clear search input
    const searchInput = document.getElementById('secondary_drug_search_' + index);
    searchInput.value = '';
    
    // Update hidden fields
    document.getElementById('secondary_selected_drug_id_' + index).value = drugId;
    document.getElementById('secondary_selected_drug_lot_' + index).value = lotNumber;
    
    // Update display
    document.getElementById('secondary_drug_name_display_' + index).textContent = drugName;
    document.getElementById('secondary_drug_quantity_display_' + index).textContent = quantity;
    document.getElementById('secondary_drug_form_display_' + index).textContent = form;
    document.getElementById('secondary_drug_status_display_' + index).textContent = status;
    
    // Show the selected drug display
    document.getElementById('secondary_selected_drug_' + index).style.display = 'block';
    
    // Auto-fill medication name
    document.getElementById('secondary_medication_' + index).value = drugName;
    document.getElementById('secondary_lot_number_' + index).value = lotNumber;
}

// Initialize search for the first medication block
document.addEventListener('DOMContentLoaded', function() {
    initializeSecondarySearch(1);
});
</script>

<style>
.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.search-result-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-result-item:hover {
    background-color: #f8f9fa;
}

.search-result-item:last-child {
    border-bottom: none;
}

.selected-drug-display {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.secondary-medication-block {
    transition: all 0.3s ease;
}

.secondary-medication-block:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<?php
// End of multiple secondary medications section
?>
