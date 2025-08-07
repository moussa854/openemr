<?php
// Inventory Module Reports
// Provides comprehensive reporting capabilities

require_once '/var/www/emr.carepointinfusion.com/interface/globals.php';
require_once "/var/www/emr.carepointinfusion.com/library/formatting_DateToYYYYMMDD_js.js.php";

// Get current user ID
$user_id = $_SESSION['authUserID'] ?? 1;

// Database connection
$sqlconf = $GLOBALS['sqlconf'];
$dsn = "mysql:host={$sqlconf['host']};dbname={$sqlconf['dbase']}";
$pdo = new PDO($dsn, $sqlconf['login'], $sqlconf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$report_type = $_GET['type'] ?? 'overview';
$start_date = $_GET["start_date"] ?? oeFormatShortDate(date("Y-m-01")); // First day of current month
$end_date = $_GET["end_date"] ?? oeFormatShortDate(date("Y-m-d")); // Today

// Add time to end_date to include the full day
$end_date_with_time = $end_date . ' 23:59:59';

$reports = [];

if ($report_type === 'wastage') {
    // Wastage Report
    $stmt = $pdo->prepare("
        SELECT w.*, d.name as drug_name, d.is_controlled_substance
        FROM drug_wastage w
        JOIN drugs d ON w.drug_id = d.drug_id
        WHERE w.created_date BETWEEN ? AND ?
        ORDER BY w.created_date DESC
    ");
    $stmt->execute([$start_date, $end_date_with_time]);
    $reports['wastage'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Wastage summary
    $stmt = $pdo->prepare("
        SELECT 
            d.name,
            SUM(w.quantity_wasted) as total_wasted,
            COUNT(*) as wastage_count,
            d.is_controlled_substance
        FROM drug_wastage w
        JOIN drugs d ON w.drug_id = d.drug_id
        WHERE w.created_date BETWEEN ? AND ?
        GROUP BY w.drug_id
        ORDER BY total_wasted DESC
    ");
    $stmt->execute([$start_date, $end_date_with_time]);
    $reports['wastage_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($report_type === 'adjustments') {
    // Adjustments Report
    $stmt = $pdo->prepare("
        SELECT a.*, d.name as drug_name, d.is_controlled_substance
        FROM drug_adjustments a
        JOIN drugs d ON a.drug_id = d.drug_id
        WHERE a.created_date BETWEEN ? AND ?
        ORDER BY a.created_date DESC
    ");
    $stmt->execute([$start_date, $end_date_with_time]);
    $reports['adjustments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($report_type === 'stock_levels') {
    // Stock Levels Report
    $stmt = $pdo->query("
        SELECT 
            drug_id,
            name,
            quantity,
            quantity_unit,
            is_controlled_substance,
            expiration_date,
            CASE 
                WHEN quantity = 0 THEN 'Out of Stock'
                WHEN quantity < 10 THEN 'Low Stock'
                WHEN quantity < 50 THEN 'Moderate Stock'
                ELSE 'Good Stock'
            END as stock_status
        FROM drugs
        ORDER BY quantity ASC, name ASC
    ");
    $reports['stock_levels'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($report_type === 'expiring') {
    // Expiring Items Report
    $stmt = $pdo->query("
        SELECT 
            drug_id,
            name,
            quantity,
            quantity_unit,
            expiration_date,
            DATEDIFF(expiration_date, CURDATE()) as days_until_expiry
        FROM drugs
        WHERE expiration_date IS NOT NULL 
        AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        ORDER BY expiration_date ASC
    ");
    $reports['expiring'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($report_type === 'overview') {
    // Overview Report - Summary of key metrics
    $overview = [];
    
    // Total drugs
    $stmt = $pdo->query("SELECT COUNT(*) as total_drugs FROM drugs");
    $overview['total_drugs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_drugs'];
    
    // Out of stock items
    $stmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM drugs WHERE quantity = 0");
    $overview['out_of_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock'];
    
    // Low stock items (quantity < 10)
    $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM drugs WHERE quantity < 10 AND quantity > 0");
    $overview['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'];
    
    // Controlled substances
    $stmt = $pdo->query("SELECT COUNT(*) as controlled_substances FROM drugs WHERE is_controlled_substance = 1");
    $overview['controlled_substances'] = $stmt->fetch(PDO::FETCH_ASSOC)['controlled_substances'];
    
    // Expiring soon (within 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) as expiring_soon FROM drugs WHERE expiration_date IS NOT NULL AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $overview['expiring_soon'] = $stmt->fetch(PDO::FETCH_ASSOC)['expiring_soon'];
    
    // Total wastage in date range
    $stmt = $pdo->prepare("SELECT COUNT(*) as wastage_count, SUM(quantity_wasted) as total_wasted FROM drug_wastage WHERE created_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date_with_time]);
    $wastage_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $overview['wastage_count'] = $wastage_data['wastage_count'] ?? 0;
    $overview['total_wasted'] = $wastage_data['total_wasted'] ?? 0;
    
    // Total adjustments in date range
    $stmt = $pdo->prepare("SELECT COUNT(*) as adjustment_count FROM drug_adjustments WHERE created_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date_with_time]);
    $overview['adjustment_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['adjustment_count'];
    
    $reports['overview'] = $overview;
}

if ($report_type === 'audit_trail') {
    // Audit Trail Report
    $stmt = $pdo->prepare("
        SELECT 
            'wastage' as action_type,
            w.created_date,
            d.name as drug_name,
            w.quantity_wasted as quantity,
            w.reason_code,
            w.reason_description,
            w.user_id,
            w.user_name,
            w.lot_number,
            w.expiration_date
        FROM drug_wastage w
        JOIN drugs d ON w.drug_id = d.drug_id
        WHERE w.created_date BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'adjustment' as action_type,
            a.created_date,
            d.name as drug_name,
            a.quantity_adjusted as quantity,
            a.reason_code,
            a.justification as reason_description,
        a.user_id,
            a.user_name,
            NULL as lot_number,
            NULL as expiration_date
        FROM drug_adjustments a
        JOIN drugs d ON a.drug_id = d.drug_id
        WHERE a.created_date BETWEEN ? AND ?
        ORDER BY created_date DESC
    ");
    $stmt->execute([$start_date, $end_date_with_time, $start_date, $end_date_with_time]);
    $reports['audit_trail'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Reports</title>
    <link rel="stylesheet" href="library/css/inventory-module.css">
    <style>
        .reports-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .report-filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-group {
            display: inline-block;
            margin-right: 20px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .generate-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
        }
        .generate-btn:hover {
            background: #229954;
        }
        .report-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .report-table th,
        .report-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .report-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status-out { color: #e74c3c; }
        .status-low { color: #f39c12; }
        .status-moderate { color: #f1c40f; }
        .status-good { color: #27ae60; }
        .export-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .export-btn:hover {
            background: #2980b9;
        }
    </style>
<script>
// Date conversion functions
document.addEventListener("DOMContentLoaded", function() {
    const forms = document.querySelectorAll("form");
    forms.forEach(function(form) {
        form.addEventListener("submit", function(e) {
            const startDateInput = form.querySelector("input[name="start_date"]");
            const endDateInput = form.querySelector("input[name="end_date"]");
            if (startDateInput && startDateInput.value) {
                startDateInput.value = DateToYYYYMMDD_js(startDateInput.value);
            }
            if (endDateInput && endDateInput.value) {
                endDateInput.value = DateToYYYYMMDD_js(endDateInput.value);
            }
        });
    });
});
</script>
</head>
<body>
    <div class="reports-container">
        <h1>Inventory Reports</h1>
        
        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="index.php" class="nav-link">Main</a>
            <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="reports.php" class="nav-link active">📈 Reports</a>
            <a href="alerts.php" class="nav-link">🚨 Alerts</a>
        </div>
        
        <!-- Report Filters -->
        <div class="report-filters">
            <form method="GET">
                <div class="filter-group">
                    <label for="type">Report Type:</label>
                    <select name="type" id="type">
                        <option value="overview" <?= $report_type === 'overview' ? 'selected' : '' ?>>Overview</option>
                        <option value="wastage" <?= $report_type === 'wastage' ? 'selected' : '' ?>>Wastage Analysis</option>
                        <option value="adjustments" <?= $report_type === 'adjustments' ? 'selected' : '' ?>>Inventory Adjustments</option>
                        <option value="stock_levels" <?= $report_type === 'stock_levels' ? 'selected' : '' ?>>Stock Levels</option>
                        <option value="expiring" <?= $report_type === 'expiring' ? 'selected' : '' ?>>Expiring Items</option>
                        <option value="audit_trail" <?= $report_type === 'audit_trail' ? 'selected' : '' ?>>Audit Trail</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="start_date">Start Date:</label>
                    <input type="text" name="start_date" id="start_date" value="<?= $start_date ?>">
                </div>
                
                <div class="filter-group">
                    <label for="end_date">End Date:</label>
                    <input type="text" name="end_date" id="end_date" value="<?= $end_date ?>">
                </div>
                
                <button type="submit" class="generate-btn">Generate Report</button>
            </form>
        </div>

        <!-- Report Content -->
        <?php if ($report_type === 'overview' && isset($reports['overview'])): ?>
        <div class="report-section">
            <h2>Inventory Overview (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)</h2>
            <button class="export-btn" onclick="exportToCSV('overview')">Export CSV</button>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3 style="color: #3498db; margin: 0 0 10px 0;">📦 Total Drugs</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #2c3e50;"><?= $reports['overview']['total_drugs'] ?></div>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3 style="color: #e74c3c; margin: 0 0 10px 0;">🚨 Out of Stock</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #2c3e50;"><?= $reports['overview']['out_of_stock'] ?></div>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3 style="color: #f39c12; margin: 0 0 10px 0;">⚠️ Low Stock</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #2c3e50;"><?= $reports['overview']['low_stock'] ?></div>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3 style="color: #9b59b6; margin: 0 0 10px 0;">🔒 Controlled Substances</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #2c3e50;"><?= $reports['overview']['controlled_substances'] ?></div>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3 style="color: #e67e22; margin: 0 0 10px 0;">⏰ Expiring Soon</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #2c3e50;"><?= $reports['overview']['expiring_soon'] ?></div>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3 style="color: #27ae60; margin: 0 0 10px 0;">📊 Activity Summary</h3>
                    <div style="font-size: 1.2em; color: #2c3e50;">
                        <div>Wastage: <?= $reports['overview']['wastage_count'] ?> records</div>
                        <div>Adjustments: <?= $reports['overview']['adjustment_count'] ?> records</div>
                        <div>Total Wasted: <?= $reports['overview']['total_wasted'] ?> units</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'wastage' && isset($reports['wastage'])): ?>
        <div class="report-section">
            <h2>Wastage Analysis (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)</h2>
            <button class="export-btn" onclick="exportToCSV('wastage')">Export CSV</button>
            
            <h3>Wastage Summary</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Total Wasted</th>
                        <th>Wastage Count</th>
                        <th>Controlled Substance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports['wastage_summary'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= $item['total_wasted'] ?></td>
                        <td><?= $item['wastage_count'] ?></td>
                        <td><?= $item['is_controlled_substance'] ? 'Yes' : 'No' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h3>Detailed Wastage Records</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Drug Name</th>
                        <th>Quantity Wasted</th>
                        <th>Reason</th>
                        <th>Description</th>
                        <th>User</th>
                        <th>Lot Number</th>
                        <th>Expiration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports['wastage'] as $item): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($item['created_date'])) ?></td>
                        <td><?= htmlspecialchars($item['drug_name']) ?></td>
                        <td><?= $item['quantity_wasted'] ?></td>
                        <td><?= $item['reason_code'] ?></td>
                        <td><?= htmlspecialchars($item['reason_description']) ?></td>
                        <td><?= htmlspecialchars($item['user_name'] ?? 'Unknown User') ?></td>
                        <td><?= $item['lot_number'] ?: 'N/A' ?></td>
                        <td><?= $item['expiration_date'] ? date('M j, Y', strtotime($item['expiration_date'])) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'adjustments' && isset($reports['adjustments'])): ?>
        <div class="report-section">
            <h2>Inventory Adjustments Report (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)</h2>
            <button class="export-btn" onclick="exportToCSV('adjustments')">Export CSV</button>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Drug Name</th>
                        <th>Adjustment Type</th>
                        <th>Quantity Adjusted</th>
                        <th>Previous Quantity</th>
                        <th>New Quantity</th>
                        <th>Reason Code</th>
                        <th>Justification</th>
                        <th>User</th>
                        <th>Controlled Substance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports['adjustments'] as $item): ?>
                    <tr>
                        <td><?= date('M j, Y H:i', strtotime($item['created_date'])) ?></td>
                        <td><?= htmlspecialchars($item['drug_name']) ?></td>
                        <td><?= ucfirst($item['adjustment_type']) ?></td>
                        <td><?= $item['quantity_adjusted'] ?></td>
                        <td><?= $item['previous_quantity'] ?></td>
                        <td><?= $item['new_quantity'] ?></td>
                        <td><?= $item['reason_code'] ?></td>
                        <td><?= htmlspecialchars($item['justification']) ?></td>
                        <td><?= htmlspecialchars($item['user_name'] ?? 'Unknown User') ?></td>
                        <td><?= $item['is_controlled_substance'] ? 'Yes' : 'No' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'stock_levels' && isset($reports['stock_levels'])): ?>
        <div class="report-section">
            <h2>Stock Levels Report</h2>
            <button class="export-btn" onclick="exportToCSV('stock_levels')">Export CSV</button>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Current Stock</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Expiration Date</th>
                        <th>Controlled Substance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports['stock_levels'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= $item['quantity_unit'] ?></td>
                        <td class="status-<?= strtolower(str_replace(' ', '-', $item['stock_status'])) ?>">
                            <?= $item['stock_status'] ?>
                        </td>
                        <td><?= $item['expiration_date'] ? date('M j, Y', strtotime($item['expiration_date'])) : 'N/A' ?></td>
                        <td><?= $item['is_controlled_substance'] ? 'Yes' : 'No' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'expiring' && isset($reports['expiring'])): ?>
        <div class="report-section">
            <h2>Expiring Items Report</h2>
            <button class="export-btn" onclick="exportToCSV('expiring')">Export CSV</button>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Current Stock</th>
                        <th>Unit</th>
                        <th>Expiration Date</th>
                        <th>Days Until Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports['expiring'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= $item['quantity_unit'] ?></td>
                        <td><?= date('M j, Y', strtotime($item['expiration_date'])) ?></td>
                        <td><?= $item['days_until_expiry'] ?> days</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'audit_trail' && isset($reports['audit_trail'])): ?>
        <div class="report-section">
            <h2>Audit Trail Report (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)</h2>
            <button class="export-btn" onclick="exportToCSV('audit_trail')">Export CSV</button>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Drug Name</th>
                        <th>Quantity</th>
                        <th>Reason Code</th>
                        <th>Description</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports['audit_trail'] as $item): ?>
                    <tr>
                        <td><?= date('M j, Y H:i', strtotime($item['created_date'])) ?></td>
                        <td><?= ucfirst($item['action_type']) ?></td>
                        <td><?= htmlspecialchars($item['drug_name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= $item['reason_code'] ?></td>
                        <td><?= htmlspecialchars($item['reason_description']) ?></td>
                        <td><?= htmlspecialchars($item['user_name'] ?? 'Unknown User') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function exportToCSV(reportType) {
            // Implementation for CSV export
            alert('CSV export functionality would be implemented here');
        }
    </script>
</body>
</html> 