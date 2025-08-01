#!/bin/bash
# Comprehensive OpenEMR Server Diagnosis Script
# Run this on the production server to identify the installation page issue

set -e

echo "🔍 OpenEMR Server Diagnosis Script"
echo "=================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    if [ "$status" = "OK" ]; then
        echo -e "${GREEN}✅ $message${NC}"
    elif [ "$status" = "WARN" ]; then
        echo -e "${YELLOW}⚠️  $message${NC}"
    elif [ "$status" = "ERROR" ]; then
        echo -e "${RED}❌ $message${NC}"
    else
        echo -e "${BLUE}ℹ️  $message${NC}"
    fi
}

# Change to OpenEMR directory
cd /var/www/emr.carepointinfusion.com

echo "📋 System Information:"
echo "======================"
echo "Current directory: $(pwd)"
echo "Current user: $(whoami)"
echo "Date: $(date)"
echo ""

echo "🗄️ Database Configuration Check:"
echo "================================"

# Check if sqlconf.php exists
if [ -f "sites/default/sqlconf.php" ]; then
    print_status "OK" "sqlconf.php file exists"
    
    # Check database credentials
    echo "Database credentials from sqlconf.php:"
    grep -E "(login|pass|host|dbase)" sites/default/sqlconf.php | head -4
    
    # Test PHP syntax
    if php -l sites/default/sqlconf.php >/dev/null 2>&1; then
        print_status "OK" "sqlconf.php has valid PHP syntax"
    else
        print_status "ERROR" "sqlconf.php has PHP syntax errors"
    fi
else
    print_status "ERROR" "sqlconf.php file is missing!"
fi

echo ""

echo "🔗 Database Connection Test:"
echo "============================"

# Test database connection
if mysql -u root -popenemr openemr -e "SELECT 1;" >/dev/null 2>&1; then
    print_status "OK" "Database connection successful"
    
    # Check essential tables
    echo "Checking essential OpenEMR tables:"
    tables=("users" "patient_data" "form_encounter" "gacl_aro" "gacl_aro_groups" "gacl_groups_aro_map")
    
    for table in "${tables[@]}"; do
        if mysql -u root -popenemr openemr -e "SHOW TABLES LIKE '$table';" | grep -q "$table"; then
            print_status "OK" "Table '$table' exists"
        else
            print_status "ERROR" "Table '$table' is missing!"
        fi
    done
    
    # Check MFA table
    if mysql -u root -popenemr openemr -e "SHOW TABLES LIKE 'stepup_mfa_verifications';" | grep -q "stepup_mfa_verifications"; then
        print_status "OK" "MFA table exists"
    else
        print_status "WARN" "MFA table is missing"
    fi
    
    # Count users
    user_count=$(mysql -u root -popenemr openemr -e "SELECT COUNT(*) as count FROM users;" | tail -1)
    echo "Number of users in database: $user_count"
    
else
    print_status "ERROR" "Database connection failed!"
    echo "Trying to identify the issue..."
    mysql -u root -popenemr openemr -e "SELECT 1;" 2>&1 || true
fi

echo ""

echo "🌐 Web Server Check:"
echo "==================="

# Check Apache status
if systemctl is-active --quiet apache2; then
    print_status "OK" "Apache is running"
else
    print_status "ERROR" "Apache is not running!"
fi

# Check Apache configuration
if apache2ctl configtest >/dev/null 2>&1; then
    print_status "OK" "Apache configuration is valid"
else
    print_status "ERROR" "Apache configuration has errors"
    apache2ctl configtest 2>&1 || true
fi

echo ""

echo "📁 File System Check:"
echo "===================="

# Check essential files
essential_files=("index.php" "interface/login/login.php" "sites/default/sqlconf.php")

for file in "${essential_files[@]}"; do
    if [ -f "$file" ]; then
        print_status "OK" "File '$file' exists"
    else
        print_status "ERROR" "File '$file' is missing!"
    fi
done

# Check permissions
echo "File permissions:"
ls -la index.php 2>/dev/null || echo "index.php not found"
ls -la sites/default/sqlconf.php 2>/dev/null || echo "sqlconf.php not found"

echo ""

echo "🐘 PHP Configuration:"
echo "===================="

# Check PHP version
php_version=$(php -v | head -1 | cut -d' ' -f2)
echo "PHP version: $php_version"

# Check required PHP modules
required_modules=("mysqli" "session" "json")

for module in "${required_modules[@]}"; do
    if php -m | grep -q "^$module$"; then
        print_status "OK" "PHP module '$module' is loaded"
    else
        print_status "ERROR" "PHP module '$module' is missing!"
    fi
done

echo ""

echo "📄 Recent Error Logs:"
echo "====================="

# Check Apache error logs
echo "Recent Apache errors:"
tail -10 /var/log/apache2/error.log 2>/dev/null || echo "No Apache error log found"

echo ""

echo "🧪 OpenEMR Configuration Test:"
echo "=============================="

# Create a test PHP file to check OpenEMR configuration
cat > test_openemr.php << 'EOF'
<?php
// Test OpenEMR configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing OpenEMR configuration...\n";

// Test if we can load sqlconf.php
if (file_exists('sites/default/sqlconf.php')) {
    echo "✅ sqlconf.php exists\n";
    require_once('sites/default/sqlconf.php');
    echo "✅ sqlconf.php loaded successfully\n";
    echo "Database: " . $sqlconf["dbase"] . "\n";
    echo "Host: " . $sqlconf["host"] . "\n";
    echo "User: " . $sqlconf["login"] . "\n";
} else {
    echo "❌ sqlconf.php not found\n";
    exit(1);
}

// Test database connection
try {
    $conn = mysqli_connect($sqlconf["host"], $sqlconf["login"], $sqlconf["pass"], $sqlconf["dbase"]);
    if ($conn) {
        echo "✅ Database connection successful\n";
        
        // Test essential tables
        $tables = ["users", "patient_data", "form_encounter"];
        foreach ($tables as $table) {
            $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($result) > 0) {
                echo "✅ Table '$table' exists\n";
            } else {
                echo "❌ Table '$table' missing\n";
            }
        }
        
        // Check if this looks like a fresh install
        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
        $row = mysqli_fetch_assoc($result);
        echo "Users in database: " . $row['count'] . "\n";
        
        if ($row['count'] == 0) {
            echo "⚠️  No users found - this might be a fresh install\n";
        }
        
        mysqli_close($conn);
    } else {
        echo "❌ Database connection failed: " . mysqli_connect_error() . "\n";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Test if globals.php exists
if (file_exists('library/globals.php')) {
    echo "✅ globals.php exists\n";
} else {
    echo "❌ globals.php missing\n";
}

echo "Test completed.\n";
?>
EOF

# Run the test
php test_openemr.php

echo ""

echo "🌐 Web Access Test:"
echo "=================="

# Test web access
echo "Testing web access to OpenEMR..."
if command -v curl >/dev/null 2>&1; then
    echo "Testing main page:"
    curl -I https://emr.carepointinfusion.com/ 2>/dev/null | head -5 || echo "Failed to access main page"
    
    echo "Testing login page:"
    curl -I https://emr.carepointinfusion.com/interface/login/login.php 2>/dev/null | head -5 || echo "Failed to access login page"
else
    echo "curl not available, skipping web tests"
fi

echo ""

echo "📊 Summary:"
echo "==========="
echo "If you see the installation page, the most likely causes are:"
echo "1. Database connection issues (check sqlconf.php credentials)"
echo "2. Missing essential database tables"
echo "3. File permission problems"
echo "4. PHP configuration issues"
echo ""

echo "🔧 Recommended Actions:"
echo "======================"
echo "1. If database connection failed: Check sqlconf.php credentials"
echo "2. If tables are missing: Run OpenEMR installation"
echo "3. If files are missing: Restore from backup or reinstall"
echo "4. If permissions are wrong: Run: chown -R www-data:www-data /var/www/emr.carepointinfusion.com"
echo ""

echo "🎯 Next Steps:"
echo "=============="
echo "1. Review the output above for ERROR messages"
echo "2. Fix any identified issues"
echo "3. Restart Apache: systemctl restart apache2"
echo "4. Test the website again"
echo ""

# Clean up test file
rm -f test_openemr.php

echo "✅ Diagnosis complete!" 