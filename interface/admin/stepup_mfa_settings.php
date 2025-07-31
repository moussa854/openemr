<?php
require_once(__DIR__ . '/../globals.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\Header;

// ACL check – require admin super privileges
if (!AclMain::aclCheckCore('admin', 'super')) {
    die(xlt('Not authorized')); // simple block
}

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        CsrfUtils::csrfNotVerified();
    }
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_enabled'", [isset($_POST['enabled']) ? '1' : '0']);
    $catsCsv = isset($_POST['categories']) ? implode(',', array_map('intval', $_POST['categories'])) : '';
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_categories'", [$catsCsv]);
    $timeout = (int)($_POST['timeout'] ?? 900);
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_timeout'", [(string)$timeout]);
    
    // New controlled substance detection setting
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_check_controlled_substances'", 
        [isset($_POST['check_controlled_substances']) ? '1' : '0']);
    
    // Ohio compliance logging setting
    sqlStatement("UPDATE globals SET gl_value = ? WHERE gl_name = 'stepup_mfa_ohio_compliance_logging'", 
        [isset($_POST['ohio_compliance_logging']) ? '1' : '0']);
    
    echo "<script>alert('" . addslashes(xlt('Settings saved')) . "'); window.location.href='stepup_mfa_settings.php';</script>";
    exit;
}

// fetch current values
$enabledVal = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_enabled'")['gl_value'] ?? 0);
$catsVal = (string)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_categories'")['gl_value'] ?? '');
$timeoutVal = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_timeout'")['gl_value'] ?? 900);
$checkControlledSubstances = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_check_controlled_substances'")['gl_value'] ?? 0);
$ohioComplianceLogging = (int)(sqlQuery("SELECT gl_value FROM globals WHERE gl_name = 'stepup_mfa_ohio_compliance_logging'")['gl_value'] ?? 0);
$selectedCats = array_filter(array_map('intval', explode(',', $catsVal)));

// category list
$catRes = sqlStatement('SELECT pc_catid, pc_catname FROM openemr_postcalendar_categories ORDER BY pc_catname');
$categories = [];
while ($r = sqlFetchArray($catRes)) {
    $categories[] = $r;
}

$token = CsrfUtils::collectCsrfToken();

// Setup header
Header::setupHeader(['common', 'opener']);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Step-Up MFA Settings'); ?></title>
    <style>
        .section-header {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px 20px;
            margin: 25px 0 20px 0;
            font-weight: bold;
            color: #495057;
            border-radius: 0 5px 5px 0;
        }
        .form-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .compliance-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 25px;
            margin: 25px 0;
        }
        .compliance-info h5 {
            color: #1976d2;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .compliance-info ul {
            margin-bottom: 0;
        }
        .compliance-info li {
            margin-bottom: 10px;
            color: #424242;
        }
        .btn-action {
            margin: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.1);
        }
        .help-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 8px;
            line-height: 1.4;
        }
        .status-indicator {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-enabled {
            background-color: #28a745;
        }
        .status-disabled {
            background-color: #dc3545;
        }
        .container-fluid {
            padding: 20px;
        }
        .row {
            margin: 0;
        }
        .col-12, .col-lg-10, .col-xl-8 {
            padding: 0 15px;
        }
        @media (min-width: 992px) {
            .col-lg-10 {
                padding: 0 30px;
            }
        }
        @media (min-width: 1200px) {
            .col-xl-8 {
                padding: 0 40px;
            }
        }
    </style>
</head>
<body class="body_top">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10 col-xl-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">
                        <i class="fa fa-shield-alt text-primary"></i>
                        <?php echo xlt('Step-Up MFA Settings'); ?>
                    </h2>
                    <div>
                        <span class="status-indicator <?php echo $enabledVal ? 'status-enabled' : 'status-disabled'; ?>"></span>
                        <span class="text-muted">
                            <?php echo $enabledVal ? xlt('Enabled') : xlt('Disabled'); ?>
                        </span>
                    </div>
                </div>
                
                <p class="text-muted mb-4">
                    <i class="fa fa-info-circle"></i>
                    <?php echo xlt('Enhanced for Ohio compliance requirements for controlled substances'); ?>
                </p>
                
                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token_form" value="<?php echo attr($token); ?>"/>
                    
                    <!-- Basic Settings Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="fa fa-cogs"></i>
                            <?php echo xlt('Basic Settings'); ?>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="enabled" value="1" id="enabled" <?php echo $enabledVal ? 'checked' : ''; ?>/>
                                <label for="enabled" class="mb-0">
                                    <strong><?php echo xlt('Enable Step-Up MFA'); ?></strong>
                                </label>
                            </div>
                            <div class="help-text">
                                <?php echo xlt('Enable multi-factor authentication for sensitive encounters'); ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="categories">
                                <i class="fa fa-calendar-check"></i>
                                <?php echo xlt('Sensitive Appointment Categories'); ?>
                            </label>
                            <select name="categories[]" id="categories" multiple size="8" class="form-control" style="min-width:300px;">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo (int)$cat['pc_catid']; ?>" 
                                            <?php echo in_array((int)$cat['pc_catid'], $selectedCats, true) ? 'selected' : ''; ?>>
                                        <?php echo text($cat['pc_catname']); ?> (<?php echo (int)$cat['pc_catid']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">
                                <?php echo xlt('Select categories that require MFA verification (e.g., Ketamine Infusion)'); ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="timeout">
                                <i class="fa fa-clock"></i>
                                <?php echo xlt('MFA Grace Period (seconds)'); ?>
                            </label>
                            <input type="number" name="timeout" id="timeout" value="<?php echo (int)$timeoutVal; ?>" 
                                   class="form-control" style="max-width:150px;" min="60" max="3600">
                            <div class="help-text">
                                <?php echo xlt('How long to remember MFA verification per patient (default: 900 = 15 minutes)'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ohio Compliance Features Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="fa fa-gavel"></i>
                            <?php echo xlt('Ohio Compliance Features'); ?>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="check_controlled_substances" value="1" id="check_controlled_substances" 
                                       <?php echo $checkControlledSubstances ? 'checked' : ''; ?>/>
                                <label for="check_controlled_substances" class="mb-0">
                                    <strong><?php echo xlt('Enable Controlled Substance Detection'); ?></strong>
                                </label>
                            </div>
                            <div class="help-text">
                                <?php echo xlt('Automatically detect encounters with controlled substances (ketamine, opioids, benzodiazepines, etc.)'); ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="ohio_compliance_logging" value="1" id="ohio_compliance_logging" 
                                       <?php echo $ohioComplianceLogging ? 'checked' : ''; ?>/>
                                <label for="ohio_compliance_logging" class="mb-0">
                                    <strong><?php echo xlt('Enhanced Ohio Compliance Logging'); ?></strong>
                                </label>
                            </div>
                            <div class="help-text">
                                <?php echo xlt('Log detailed information for Ohio Board of Pharmacy compliance requirements'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ohio Compliance Information -->
                    <div class="compliance-info">
                        <h5>
                            <i class="fa fa-info-circle"></i>
                            <?php echo xlt('Ohio Compliance Information'); ?>
                        </h5>
                        <p class="mb-3">
                            <?php echo xlt('This feature helps comply with Ohio Board of Pharmacy regulations for controlled substances:'); ?>
                        </p>
                        <ul>
                            <li><i class="fa fa-check-circle text-success"></i> <?php echo xlt('Requires MFA for access to encounters with controlled substances'); ?></li>
                            <li><i class="fa fa-check-circle text-success"></i> <?php echo xlt('Logs all access attempts for audit purposes'); ?></li>
                            <li><i class="fa fa-check-circle text-success"></i> <?php echo xlt('Supports both category-based and medication-based detection'); ?></li>
                            <li><i class="fa fa-check-circle text-success"></i> <?php echo xlt('Maintains session-based verification with configurable timeout'); ?></li>
                        </ul>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-center align-items-center mt-4 mb-4">
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-action">
                                <i class="fa fa-save"></i>
                                <?php echo xlt('Save Settings'); ?>
                            </button>
                            <a href="stepup_mfa_compliance_report.php" class="btn btn-info btn-action">
                                <i class="fa fa-chart-bar"></i>
                                <?php echo xlt('View Compliance Report'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Update status indicator when checkbox changes
        document.getElementById('enabled').addEventListener('change', function() {
            var indicator = document.querySelector('.status-indicator');
            var statusText = document.querySelector('.status-indicator + span');
            
            if (this.checked) {
                indicator.classList.remove('status-disabled');
                indicator.classList.add('status-enabled');
                statusText.textContent = '<?php echo xlt('Enabled'); ?>';
            } else {
                indicator.classList.remove('status-enabled');
                indicator.classList.add('status-disabled');
                statusText.textContent = '<?php echo xlt('Disabled'); ?>';
            }
        });
    </script>
</body>
</html>
