<?php
require_once(__DIR__ . "/../../../globals.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Management</title>
    <link rel="stylesheet" href="library/css/inventory-module.css">
    <style>
        /* Vial Type Styling */
        .vial-type-sdv {
            color: #dc3545;
            font-weight: bold;
            background-color: #f8d7da;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #dc3545;
        }
        
        .vial-type-mdv {
            color: #28a745;
            font-weight: bold;
            background-color: #d4edda;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #28a745;
        }
        
        .vial-type-unknown {
            color: #6c757d;
            font-weight: bold;
            background-color: #e2e3e5;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #6c757d;
        }
        
        .vial-type-section {
            margin: 10px 0;
        }
        
        .vial-type-section label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .vial-type-section small {
            color: #6c757d;
            font-style: italic;
            display: block;
            margin-top: 5px;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Temporarily disabled datetimepicker due to xlj() function error -->
    <!-- <script src="../../../../library/js/xl/jquery-datetimepicker-2-5-4.js.php"></script> -->
    <!-- <script src="../../../../library/js/xl/jquery-datetimepicker-2-5-4-translated.js"></script> -->
    <script src="library/js/barcode-auto-populate.js"></script>
    <script src="/library/formatting_DateToYYYYMMDD_js.js.php"></script>
<script>
// Date conversion functions
document.addEventListener("DOMContentLoaded", function() {
    const forms = document.querySelectorAll("form");
    forms.forEach(function(form) {
        form.addEventListener("submit", function(e) {
            const expirationInputs = form.querySelectorAll('input[name="expiration_date"]');
            expirationInputs.forEach(function(input) {
                if (input && input.value) {
                    input.value = DateToYYYYMMDD_js(input.value);
                }
            });
        });
    });
    
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
});
</script>
</head>
<body>
    <div class="inventory-dashboard">
        <h1>Inventory Management</h1>
        
        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="index.php" class="nav-link active">Main</a>
            <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="reports.php" class="nav-link">📈 Reports</a>
            <a href="alerts.php" class="nav-link">🚨 Alerts</a>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <input type="text" id="search-input" placeholder="Search by name, barcode, or NDC...">
            <button onclick="searchDrugs()">Search</button>
            <button onclick="searchAllDrugs()" class="btn-secondary">Search All</button>
        </div>

        <!-- Add Drug Section -->
        <div class="add-drug-section">
            <h2>Add New Drug</h2>
            <form id="add-drug-form">
                <input type="text" name="name" placeholder="Drug Name" required>
                <input type="text" name="barcode" placeholder="Barcode">
                <input type="text" name="ndc_10" placeholder="NDC-10 (0000-0000-00)">
                <input type="text" name="ndc_11" placeholder="NDC-11 (00000-0000-00)">
                <input type="text" name="size" placeholder="Strength/Size">
                <select name="unit" required>
                    <option value="">Select Unit</option>
                    <option value="mg">mg (milligrams)</option>
                    <option value="mcg">mcg (micrograms)</option>
                    <option value="g">g (grams)</option>
                    <option value="ml">mL (milliliters)</option>
                    <option value="L">L (liters)</option>
                    <option value="units">units</option>
                    <option value="mEq">mEq (milliequivalents)</option>
                    <option value="IU">IU (International Units)</option>
                    <option value="puffs">puffs</option>
                    <option value="tablets">tablets</option>
                    <option value="capsules">capsules</option>
                    <option value="suppositories">suppositories</option>
                    <option value="patches">patches</option>
                    <option value="vials">vials</option>
                    <option value="ampules">ampules</option>
                    <option value="other">Other</option>
                </select>
                <input type="text" name="dose" placeholder="Dose (e.g., 5 mg)">
                <input type="text" name="route" placeholder="Route (oral, topical, etc.)">
                <input type="text" name="form" placeholder="Form (tablet, injection, etc.)">
                
                <!-- Vial Type Section - Only visible for vials -->
                <div class="vial-type-section" id="vial-type-section" style="display: none;">
                    <label>Vial Type:</label>
                    <select name="vial_type" id="vial-type-select">
                        <option value="">Select Vial Type</option>
                        <option value="single_dose">Single Dose Vial (SDV)</option>
                        <option value="multi_dose">Multi-Dose Vial (MDV)</option>
                        <option value="unknown">Unknown</option>
                    </select>
                    <small>SDV: Used once, discard remainder. MDV: Can be reused if properly stored.</small>
                </div>
                
                <!-- Quantity Section -->
                <div class="quantity-section">
                    <input type="number" name="quantity" placeholder="Quantity" min="0">
                    <select name="quantity_unit">
                        <option value="vial">Vial(s)</option>
                        <option value="tablet">Tablet(s)</option>
                        <option value="capsule">Capsule(s)</option>
                        <option value="bottle">Bottle(s)</option>
                        <option value="tube">Tube(s)</option>
                        <option value="pack">Pack(s)</option>
                    </select>
                </div>
                
                <!-- Lot Number and Expiration -->
                <div class="lot-expiration-section">
                    <input type="text" name="lot_number" placeholder="Lot Number">
                    <input type="text" name="expiration_date" placeholder="Expiration Date" class="datepicker" readonly>
                </div>
                
                <label>
                    <input type="checkbox" name="is_controlled_substance"> Controlled Substance
                </label>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit">Add Drug</button>
                    <button type="button" onclick="clearForm()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Clear Form</button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div id="search-results"></div>
        
        <!-- Edit Modal -->
        <div id="edit-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Drug</h2>
                <form id="edit-drug-form">
                    <input type="hidden" name="drug_id" id="edit-drug-id">
                    <input type="text" name="name" id="edit-name" placeholder="Drug Name" required>
                    <input type="text" name="barcode" id="edit-barcode" placeholder="Barcode">
                    <input type="text" name="ndc_10" id="edit-ndc-10" placeholder="NDC-10 (0000-0000-00)">
                    <input type="text" name="ndc_11" id="edit-ndc-11" placeholder="NDC-11 (00000-0000-00)">
                    <input type="text" name="size" id="edit-size" placeholder="Strength/Size">
                    <select name="unit" id="edit-unit" required>
                        <option value="">Select Unit</option>
                        <option value="mg">mg (milligrams)</option>
                        <option value="mcg">mcg (micrograms)</option>
                        <option value="g">g (grams)</option>
                        <option value="ml">mL (milliliters)</option>
                        <option value="L">L (liters)</option>
                        <option value="units">units</option>
                        <option value="mEq">mEq (milliequivalents)</option>
                        <option value="IU">IU (International Units)</option>
                        <option value="puffs">puffs</option>
                        <option value="tablets">tablets</option>
                        <option value="capsules">capsules</option>
                        <option value="suppositories">suppositories</option>
                        <option value="patches">patches</option>
                        <option value="vials">vials</option>
                        <option value="ampules">ampules</option>
                        <option value="other">Other</option>
                    </select>
                    <input type="text" name="dose" id="edit-dose" placeholder="Dose (e.g., 5 grams)">
                    <input type="text" name="route" id="edit-route" placeholder="Route (oral, topical, etc.)">
                    
                    <!-- Vial Type Section - Only visible for vials -->
                    <div class="vial-type-section" id="edit-vial-type-section" style="display: none;">
                        <label>Vial Type:</label>
                        <select name="vial_type" id="edit-vial-type">
                            <option value="">Select Vial Type</option>
                            <option value="single_dose">Single Dose Vial (SDV)</option>
                            <option value="multi_dose">Multi-Dose Vial (MDV)</option>
                            <option value="unknown">Unknown</option>
                        </select>
                        <small>SDV: Used once, discard remainder. MDV: Can be reused if properly stored.</small>
                    </div>
                    
                    <!-- Quantity Section -->
                    <div class="quantity-section">
                        <input type="number" name="quantity" id="edit-quantity" placeholder="Quantity" min="0">
                        <select name="quantity_unit" id="edit-quantity-unit">
                            <option value="vial">Vial(s)</option>
                            <option value="tablet">Tablet(s)</option>
                            <option value="capsule">Capsule(s)</option>
                            <option value="bottle">Bottle(s)</option>
                            <option value="tube">Tube(s)</option>
                            <option value="pack">Pack(s)</option>
                        </select>
                    </div>
                    
                    <!-- Lot Number and Expiration -->
                    <div class="lot-expiration-section">
                        <input type="text" name="lot_number" id="edit-lot-number" placeholder="Lot Number">
                        <input type="text" name="expiration_date" id="edit-expiration-date" placeholder="Expiration Date" class="datepicker" readonly>
                    </div>
                    
                    <!-- Form Selection -->
                    <select name="form" id="edit-form" required>
                        <option value="">Select Form</option>
                        <option value="tablet">Tablet</option>
                        <option value="capsule">Capsule</option>
                        <option value="liquid">Liquid</option>
                        <option value="injection">Injection</option>
                        <option value="cream">Cream</option>
                        <option value="ointment">Ointment</option>
                        <option value="vial">Vial</option>
                    </select>
                    
                    <label>
                        <input type="checkbox" name="is_controlled_substance" id="edit-controlled"> Controlled Substance
                    </label>
                    <button type="submit">Update Drug</button>
                </form>
            </div>
        </div>
        
        <!-- Wastage Modal -->
        <div id="wastage-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Record Wastage</h2>
                <form id="wastage-form">
                    <input type="hidden" name="drug_id" id="wastage-drug-id">
                    <input type="hidden" name="drug_name" id="wastage-drug-name">
                    <input type="hidden" name="is_controlled" id="wastage-is-controlled">
                    
                    <div class="form-group">
                        <label>Drug: <span id="wastage-drug-display"></span></label>
                    </div>
                    
                    <!-- Controlled Substance Warning -->
                    <div id="wastage-controlled-warning" class="controlled-warning" style="display: none;">
                        ⚠️ CONTROLLED SUBSTANCE - MFA Required
                    </div>
                    
                    <div class="form-group">
                        <label>Lot Number:</label>
                        <input type="text" name="lot_number" id="wastage-lot-number" placeholder="Lot Number">
                    </div>
                    
                    <div class="form-group">
                        <label>Expiration Date:</label>
                        <input type="text" name="expiration_date" id="wastage-expiration-date">
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity Wasted:</label>
                        <input type="number" name="quantity_wasted" id="wastage-quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason Code:</label>
                        <select name="reason_code" id="wastage-reason-code" required>
                            <option value="">Select Reason</option>
                            <option value="EXP">Expired</option>
                            <option value="DAM">Damaged</option>
                            <option value="SPI">Spilled</option>
                            <option value="RET">Returned</option>
                            <option value="REC">Recalled</option>
                            <option value="OTH">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason Description:</label>
                        <textarea name="reason_description" id="wastage-reason-description" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Witness ID:</label>
                        <input type="number" name="witness_id" id="wastage-witness-id" placeholder="Witness User ID">
                    </div>
                    
                    <div class="form-group">
                        <label>Destruction Method:</label>
                        <select name="destruction_method" id="wastage-destruction-method">
                            <option value="">Select Method</option>
                            <option value="INCINERATION">Incineration</option>
                            <option value="CHEMICAL">Chemical Destruction</option>
                            <option value="RETURN">Return to Supplier</option>
                            <option value="DISPOSAL">Disposal Service</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes:</label>
                        <textarea name="notes" id="wastage-notes" rows="3"></textarea>
                    </div>
                    
                    <!-- MFA Section for Controlled Substances -->
                    <div id="wastage-mfa-section" class="form-group" style="display: none;">
                        <label>MFA Token (Required for Controlled Substances):</label>
                        <input type="text" name="mfa_token" id="wastage-mfa-token" placeholder="Enter your MFA token" value="">
                        <input type="hidden" name="mfa_type" value="TOTP">
                        <small>Enter your 6-digit TOTP code from your authenticator app (Google Authenticator, Authy, etc.)</small>
                    </div>
                    
                    <button type="submit">Record Wastage</button>
                </form>
            </div>
        </div>
        
        <!-- Adjustment Modal -->
        <div id="adjustment-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Record Adjustment</h2>
                <form id="adjustment-form">
                    <input type="hidden" name="drug_id" id="adjustment-drug-id">
                    <input type="hidden" name="drug_name" id="adjustment-drug-name">
                    <input type="hidden" name="is_controlled" id="adjustment-is-controlled">
                    
                    <div class="form-group">
                        <label>Drug: <span id="adjustment-drug-display"></span></label>
                    </div>
                    
                    <!-- Controlled Substance Warning -->
                    <div id="adjustment-controlled-warning" class="controlled-warning" style="display: none;">
                        ⚠️ CONTROLLED SUBSTANCE - MFA Required
                    </div>
                    
                    <div class="form-group">
                        <label>Adjustment Type:</label>
                        <select name="adjustment_type" id="adjustment-type" required>
                            <option value="">Select Type</option>
                            <option value="add">Add Quantity</option>
                            <option value="subtract">Subtract Quantity</option>
                            <option value="correction">Correction</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity Adjusted:</label>
                        <input type="number" name="quantity_adjusted" id="adjustment-quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason Code:</label>
                        <select name="reason_code" id="adjustment-reason-code" required>
                            <option value="">Select Reason</option>
                            <option value="REC">Received</option>
                            <option value="COR">Correction</option>
                            <option value="TRF">Transfer</option>
                            <option value="INV">Inventory Count</option>
                            <option value="OTH">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Justification (Required):</label>
                        <textarea name="justification" id="adjustment-justification" rows="3" required placeholder="Explain why this adjustment is necessary..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes:</label>
                        <textarea name="notes" id="adjustment-notes" rows="3"></textarea>
                    </div>
                    
                    <!-- MFA Section for Controlled Substances -->
                    <div id="adjustment-mfa-section" class="form-group" style="display: none;">
                        <label>MFA Token (Required for Controlled Substances):</label>
                        <input type="text" name="mfa_token" id="adjustment-mfa-token" placeholder="Enter your MFA token" value="">
                        <input type="hidden" name="mfa_type" value="TOTP">
                        <small>Enter your 6-digit TOTP code from your authenticator app (Google Authenticator, Authy, etc.)</small>
                    </div>
                    
                    <button type="submit">Record Adjustment</button>
                </form>
            </div>
        </div>
        
        <!-- Remove Drug Modal -->
        <div id="remove-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Remove from Inventory</h2>
                <form id="remove-form">
                    <input type="hidden" name="drug_id" id="remove-drug-id">
                    <input type="hidden" name="drug_name" id="remove-drug-name">
                    <input type="hidden" name="is_controlled" id="remove-is-controlled">
                    
                    <div class="form-group">
                        <label>Drug: <span id="remove-drug-display"></span></label>
                    </div>
                    

                    
                    <div class="form-group">
                        <label>Removal Reason:</label>
                        <select name="reason_code" id="remove-reason-code" required>
                            <option value="">Select Reason</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="remove-notes" style="display: block; margin-bottom: 5px; font-weight: 500;">Notes (Required):</label>
                        <textarea name="notes" id="remove-notes" rows="3" required placeholder="Explain why this drug is being removed from inventory..." autocomplete="off" style="width: 100%; margin-top: 0;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="remove-confirmation" required>
                            I understand this will hide the item from active inventory but keep all records for audit purposes
                        </label>
                    </div>
                    
                    <!-- MFA Section for Controlled Substances -->
                    <div id="remove-mfa-section" class="form-group" style="display: none;">
                        <label>MFA Token:</label>
                        <input type="text" name="mfa_token" id="remove-mfa-token" placeholder="Enter your MFA token" value="">
                        <input type="hidden" name="mfa_type" value="TOTP">
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Remove from Inventory</button>
                        <button type="button" onclick="$('#remove-modal').hide()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Store the current drug data for editing
        var currentDrugData = {};
        
        $(document).ready(function() {
            // Show/hide vial type section based on quantity unit selection
            function toggleVialTypeSection() {
                var quantityUnit = $('select[name="quantity_unit"]').val();
                var vialTypeSection = $('#vial-type-section');
                var vialTypeSelect = $('#vial-type-select');
                
                if (quantityUnit === 'vial') {
                    vialTypeSection.show();
                    vialTypeSelect.attr('required', true);
                } else {
                    vialTypeSection.hide();
                    vialTypeSelect.attr('required', false);
                    vialTypeSelect.val('unknown'); // Set to unknown for non-vials
                }
            }
            
            // Show/hide edit vial type section based on quantity unit selection
            function toggleEditVialTypeSection() {
                var quantityUnit = $('#edit-quantity-unit').val();
                var vialTypeSection = $('#edit-vial-type-section');
                var vialTypeSelect = $('#edit-vial-type');
                
                if (quantityUnit === 'vial') {
                    vialTypeSection.show();
                    vialTypeSelect.attr('required', true);
                } else {
                    vialTypeSection.hide();
                    vialTypeSelect.attr('required', false);
                    vialTypeSelect.val('unknown'); // Set to unknown for non-vials
                }
            }
            
            // Bind events for quantity unit changes
            $('select[name="quantity_unit"]').on('change', toggleVialTypeSection);
            $('#edit-quantity-unit').on('change', toggleEditVialTypeSection);
            
            // Initial check on page load
            toggleVialTypeSection();
            
            // Handle form submission
            $('#add-drug-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                $.ajax({
                    url: 'add-drug-fixed.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('Drug added successfully!');
                            $('#add-drug-form')[0].reset();
                            searchAllDrugs(); // Refresh the list
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Add drug error:', xhr.responseText);
                        alert('Error adding drug. Please check the console for details.');
                    }
                });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }

            // Handle edit form submission
            $('#edit-drug-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                $.ajax({
                    url: 'update-drug-fixed.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('Drug updated successfully!');
                            $('#edit-modal').hide();
                            searchAllDrugs(); // Refresh the list
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Update error:', xhr.responseText);
                        alert('Error updating drug. Please check the console for details.');
                    }
                });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }

            // Handle wastage form submission
            $('#wastage-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                // Debug: Log form data
                console.log('Submitting wastage form...');
                for (var pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                $.ajax({
                    url: 'record-wastage.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log('Wastage response:', response);
                        if (response.success) {
                            alert('Wastage recorded successfully!');
                            $('#wastage-modal').hide();
                            searchAllDrugs(); // Refresh the list
                        } else {
                            if (response.mfa_required) {
                                if (response.mfa_setup_required) {
                                    alert('MFA setup required for controlled substances. Please configure MFA in your user settings first.');
                                } else {
                                    alert('MFA token required for controlled substance wastage. Please enter your MFA token.');
                                    $('#wastage-mfa-section').show();
                                    if (response.mfa_types && response.mfa_types.length > 0) {
                                        console.log('Available MFA types:', response.mfa_types);
                                    }
                                }
                            } else if (response.mfa_verified === false) {
                                alert('Invalid MFA token: ' + response.message);
                                $('#wastage-mfa-token').val('').focus();
                            } else {
                                alert('Error: ' + response.message);
                                if (response.debug_info) {
                                    console.log('Debug info:', response.debug_info);
                                }
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Wastage error:', xhr.responseText);
                        alert('Error recording wastage. Please check the console for details.\n\nError: ' + xhr.responseText);
                    }
                });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }

            // Handle adjustment form submission
            $('#adjustment-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                // Debug: Log form data
                console.log('Submitting adjustment form...');
                for (var pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                $.ajax({
                    url: 'record-adjustment.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log('Adjustment response:', response);
                        if (response.success) {
                            alert('Adjustment recorded successfully!');
                            $('#adjustment-modal').hide();
                            searchAllDrugs(); // Refresh the list
                        } else {
                            if (response.mfa_required) {
                                if (response.mfa_setup_required) {
                                    alert('MFA setup required for controlled substances. Please configure MFA in your user settings first.');
                                } else {
                                    alert('MFA token required for controlled substance adjustment. Please enter your MFA token.');
                                    $('#adjustment-mfa-section').show();
                                    if (response.mfa_types && response.mfa_types.length > 0) {
                                        console.log('Available MFA types:', response.mfa_types);
                                    }
                                }
                            } else if (response.mfa_verified === false) {
                                alert('Invalid MFA token: ' + response.message);
                                $('#adjustment-mfa-token').val('').focus();
                            } else {
                                alert('Error: ' + response.message);
                                if (response.debug_info) {
                                    console.log('Debug info:', response.debug_info);
                                }
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Adjustment error:', xhr.responseText);
                        alert('Error recording adjustment. Please check the console for details.\n\nError: ' + xhr.responseText);
                    }
                });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }

            // Handle search
            $('#search-input').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchDrugs();
                }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }

            // Modal close functionality
            $('.close').click(function() {
                $('.modal').hide();
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }

            $(window).click(function(e) {
                if ($(e.target).hasClass('modal')) {
                    $('.modal').hide();
                }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
        });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }

        function searchDrugs() {
            var query = $('#search-input').val();
            
            $.ajax({
                url: 'search.php',
                type: 'GET',
                data: { q: query },
                success: function(response) {
                    displaySearchResults(response);
                },
                error: function(xhr, status, error) {
                    $('#search-results').html('<p>Error searching drugs.</p>');
                }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
        }

        function searchAllDrugs() {
            console.log('Starting searchAllDrugs...');
            $.ajax({
                url: 'search_all_fixed.php?t=' + Date.now(),
                type: 'GET',
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    console.log('Search all response:', response);
                    if (Array.isArray(response)) {
                        displayAllDrugs(response);
                    } else {
                        console.error('Expected array but got:', typeof response, response);
                        $('#search-results').html('<p>Error: Invalid response format</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Search all error status:', status);
                    console.error('Search all error:', error);
                    console.error('Search all response text:', xhr.responseText);
                    console.error('Search all response status:', xhr.status);
                    $('#search-results').html('<p>Error loading all drugs. Status: ' + xhr.status + '</p>');
                }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
        }

        function displaySearchResults(results) {
            var container = $('#search-results');
            
            if (results.length === 0) {
                container.html('<p>No drugs found.</p>');
                return;
            }

            var html = '<h3>Search Results</h3><div class="drug-list">';
            
            results.forEach(function(drug) {
                html += createDrugCard(drug);
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            
            html += '</div>';
            container.html(html);
        }

        function displayAllDrugs(results) {
            var container = $('#search-results');
            
            console.log('DisplayAllDrugs called with:', results);
            console.log('Type:', typeof results);
            console.log('Is Array:', Array.isArray(results));
            
            if (!Array.isArray(results)) {
                console.error('Results is not an array:', results);
                container.html('<p>Error: Invalid data format</p>');
                return;
            }
            
            if (results.length === 0) {
                container.html('<p>No drugs found.</p>');
                return;
            }

            var html = '<h3>All Inventory (' + results.length + ' items)</h3><div class="drug-list">';
            
            results.forEach(function(drug) {
                html += createDrugCard(drug);
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            
            html += '</div>';
            container.html(html);
        }

        function createDrugCard(drug) {
            // Only show vial type for actual vials
            var vialTypeHtml = '';
            if (drug.quantity_unit === 'vial') {
                var vialTypeText = '';
                var vialTypeClass = '';
                if (drug.vial_type === 'single_dose') {
                    vialTypeText = '⚡ Single Dose Vial (SDV)';
                    vialTypeClass = 'vial-type-sdv';
                } else if (drug.vial_type === 'multi_dose') {
                    vialTypeText = '🔄 Multi-Dose Vial (MDV)';
                    vialTypeClass = 'vial-type-mdv';
                } else {
                    vialTypeText = '❓ Unknown Vial Type';
                    vialTypeClass = 'vial-type-unknown';
                }
                vialTypeHtml = '<p class="' + vialTypeClass + '"><strong>' + vialTypeText + '</strong></p>';
            }
            
            return '<div class="drug-item ' + (drug.is_controlled_substance ? 'controlled' : '') + '" data-drug=\'' + JSON.stringify(drug) + '\'>' +
                '<h4>' + drug.name + '</h4>' +
                '<p>Barcode: ' + (drug.barcode || 'N/A') + '</p>' +
                '<p>NDC-10: ' + (drug.ndc_10 || 'N/A') + '</p>' +
                '<p>NDC-11: ' + (drug.ndc_11 || 'N/A') + '</p>' +
                '<p>Form: ' + (drug.form || 'N/A') + '</p>' +
                '<p>Size: ' + (drug.size || 'N/A') + ' ' + (drug.unit || '') + '</p>' +
                '<p>Dose: ' + (drug.dose || 'N/A') + '</p>' +
                '<p><strong>Quantity: ' + (drug.quantity || 0) + ' ' + (drug.quantity_unit || 'vial') + '</strong></p>' +
                '<p>Lot Number: ' + (drug.lot_number || 'N/A') + '</p>' +
                '<p>Expiration: ' + (drug.expiration_date || 'N/A') + '</p>' +
                vialTypeHtml +
                '<p>Controlled: ' + (drug.is_controlled_substance ? 'Yes' : 'No') + '</p>' +
                '<div class="action-buttons">' +
                '<button onclick="editDrug(' + drug.drug_id + ')" class="btn-edit">Edit</button>' +
                '<button onclick="recordWastage(' + drug.drug_id + ')" class="btn-wastage">Wastage</button>' +
                '<button onclick="recordAdjustment(' + drug.drug_id + ')" class="btn-adjustment">Adjust</button>' +
                '<button onclick="removeDrug(' + drug.drug_id + ')" class="btn-remove">Remove</button>' +
                '</div>' +
                '</div>';
        }

        function editDrug(drugId) {
            // Find the drug card and get the stored data
            var drugCard = $('.drug-item').filter(function() {
                return $(this).find('button').attr('onclick').includes(drugId);
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            
            if (drugCard.length === 0) {
                alert('Drug data not found. Please search again.');
                return;
            }
            
            // Get the drug data from the data attribute
            var drugData = JSON.parse(drugCard.attr('data-drug'));
            
            // Populate the edit form with the actual drug data
            $('#edit-drug-id').val(drugData.drug_id);
            $('#edit-name').val(drugData.name);
            $('#edit-barcode').val(drugData.barcode || '');
            $('#edit-ndc-10').val(drugData.ndc_10 || '');
            $('#edit-ndc-11').val(drugData.ndc_11 || '');
            $('#edit-form').val(drugData.form || '');
            $('#edit-size').val(drugData.size || '');
            $('#edit-unit').val(drugData.unit || '');
            $('#edit-dose').val(drugData.dose || '');
            $('#edit-route').val(drugData.route || '');
            $('#edit-quantity').val(drugData.quantity || 0);
            $('#edit-vial-type').val(drugData.vial_type || 'unknown');
            $('#edit-quantity-unit').val(drugData.quantity_unit || 'vial');
            $('#edit-lot-number').val(drugData.lot_number || '');
            $('#edit-expiration-date').val(drugData.expiration_date || '');
            $('#edit-controlled').prop('checked', drugData.is_controlled_substance == 1);
            
            // Toggle vial type section based on current quantity unit
            toggleEditVialTypeSection();
            
            // Show the modal
            $('#edit-modal').show();
        }

        function recordWastage(drugId) {
            // Find the drug card and get the stored data
            var drugCard = $('.drug-item').filter(function() {
                return $(this).find('button').attr('onclick').includes(drugId);
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            
            if (drugCard.length === 0) {
                alert('Drug data not found. Please search again.');
                return;
            }
            
            // Get the drug data from the data attribute
            var drugData = JSON.parse(drugCard.attr('data-drug'));
            
            // Populate the wastage form
            $('#wastage-drug-id').val(drugData.drug_id);
            $('#wastage-drug-name').val(drugData.name);
            $('#wastage-drug-display').text(drugData.name);
            $('#wastage-is-controlled').val(drugData.is_controlled_substance ? '1' : '0');
            $('#wastage-lot-number').val(drugData.lot_number || '');
            $('#wastage-expiration-date').val(drugData.expiration_date || '');
            $('#wastage-quantity').attr('max', drugData.quantity);
            
            // Show/hide controlled substance warnings and MFA sections
            if (drugData.is_controlled_substance) {
                $('#wastage-controlled-warning').show();
                $('#wastage-mfa-section').show();
                $('#wastage-mfa-token').val(''); // Clear any previous token
            } else {
                $('#wastage-controlled-warning').hide();
                $('#wastage-mfa-section').hide();
            }
            
            // Show the modal
            $('#wastage-modal').show();
        }

        function recordAdjustment(drugId) {
            // Find the drug card and get the stored data
            var drugCard = $('.drug-item').filter(function() {
                return $(this).find('button').attr('onclick').includes(drugId);
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            
            if (drugCard.length === 0) {
                alert('Drug data not found. Please search again.');
                return;
            }
            
            // Get the drug data from the data attribute
            var drugData = JSON.parse(drugCard.attr('data-drug'));
            
            // Populate the adjustment form
            $('#adjustment-drug-id').val(drugData.drug_id);
            $('#adjustment-drug-name').val(drugData.name);
            $('#adjustment-drug-display').text(drugData.name);
            $('#adjustment-is-controlled').val(drugData.is_controlled_substance ? '1' : '0');
            
            // Show/hide controlled substance warnings and MFA sections
            if (drugData.is_controlled_substance) {
                $('#adjustment-controlled-warning').show();
                $('#adjustment-mfa-section').show();
                $('#adjustment-mfa-token').val(''); // Clear any previous token
            } else {
                $('#adjustment-controlled-warning').hide();
                $('#adjustment-mfa-section').hide();
            }
            
            // Show the modal
            $('#adjustment-modal').show();
        }

        function clearForm() {
            // Clear all form fields
            $('#add-drug-form')[0].reset();
            
            // Clear auto-populated visual indicators
            if (window.barcodeAutoPopulate) {
                window.barcodeAutoPopulate.clearAutoPopulatedFields();
            }
            
            // Remove any barcode messages
            $('.barcode-message').remove();
            
            // Focus on drug name field
            $('input[name="name"]').focus();
        }

        function removeDrug(drugId) {
            // Find the drug card and get the stored data
            var drugCard = $('.drug-item').filter(function() {
                return $(this).find('button').attr('onclick').includes(drugId);
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
            
            if (drugCard.length === 0) {
                alert('Drug data not found. Please search again.');
                return;
            }
            
            // Get the drug data from the data attribute
            var drugData = JSON.parse(drugCard.attr('data-drug'));
            
            // Populate the remove form
            $('#remove-drug-id').val(drugData.drug_id);
            $('#remove-drug-name').val(drugData.name);
            $('#remove-drug-display').text(drugData.name);
            $('#remove-is-controlled').val(drugData.is_controlled_substance ? '1' : '0');
            
            // Load removal reasons
            loadRemovalReasons();
            
            // Show/hide MFA section for controlled substances
            if (drugData.is_controlled_substance) {
                $('#remove-mfa-section').show();
                $('#remove-mfa-token').val(''); // Clear any previous token
            } else {
                $('#remove-mfa-section').hide();
            }
            
            // Clear all form fields to prevent browser auto-fill
            $('#remove-notes').val('');
            $('#remove-reason-code').val('');
            $('#remove-confirmation').prop('checked', false);
            $('#remove-mfa-token').val('');
            
            // Show the modal
            $('#remove-modal').show();
        }

        function loadRemovalReasons() {
            $.ajax({
                url: 'get-removal-reasons.php',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        var select = $('#remove-reason-code');
                        select.empty();
                        select.append('<option value="">Select Reason</option>');
                        
                        response.reasons.forEach(function(reason) {
                            select.append('<option value="' + reason.reason_code + '">' + reason.reason_description + '</option>');
                        });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
                    }
                },
                error: function() {
                    console.error('Failed to load removal reasons');
                }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
        }

        // Handle remove form submission
        $('#remove-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            
            $.ajax({
                url: 'remove-drug.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Drug successfully removed from inventory: ' + response.drug_name);
                        $('#remove-modal').hide();
                        searchAllDrugs(); // Refresh the list
                    } else {
                        if (response.mfa_required) {
                            alert('MFA token required for controlled substance removal. Please enter your MFA token.');
                            $('#remove-mfa-section').show();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Remove error:', xhr.responseText);
                    alert('Error removing drug. Please check the console for details.\n\nError: ' + xhr.responseText);
                }
            });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
        });
    // Initialize date pickers - disabled to prevent errors
    // if (typeof datetimepickerTranslated !== "undefined") {
    //     datetimepickerTranslated(".datepicker", {
    //         timepicker: false,
    //         showSeconds: false,
    //         formatInput: false,
    //         minDate: "-1970/01/01"
    //     });
    // }
    </script>
</body>
</html> 