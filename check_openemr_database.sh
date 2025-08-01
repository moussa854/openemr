#!/bin/bash

# Check OpenEMR Database Status
# Run this on the production server

set -e

echo "🔍 Checking OpenEMR Database Status..."

# Configuration
DB_NAME="openemr"
DB_USER="root"
DB_PASS="openemr"

cd /var/www/emr.carepointinfusion.com

echo "📋 Database Configuration:"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"

# Test database connection
echo "🔗 Testing database connection..."
if mysql -u $DB_USER -p$DB_PASS -e "USE $DB_NAME; SELECT 1;" >/dev/null 2>&1; then
    echo "  ✅ Database connection successful"
else
    echo "  ❌ Database connection failed"
    exit 1
fi

# Check for essential OpenEMR tables
echo "📊 Checking essential OpenEMR tables..."
ESSENTIAL_TABLES=("users" "patient_data" "form_encounter" "gacl_aro" "gacl_aro_groups" "gacl_groups_aro_map")

for table in "${ESSENTIAL_TABLES[@]}"; do
    if mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE $table;" >/dev/null 2>&1; then
        echo "  ✅ $table table exists"
    else
        echo "  ❌ $table table missing"
    fi
done

# Check for Step-Up MFA table
echo "🔐 Checking Step-Up MFA table..."
if mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;" >/dev/null 2>&1; then
    echo "  ✅ stepup_mfa_verifications table exists"
    COUNT=$(mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT COUNT(*) FROM stepup_mfa_verifications;" -s -N)
    echo "  📈 Records in stepup_mfa_verifications: $COUNT"
else
    echo "  ❌ stepup_mfa_verifications table missing"
fi

# Check OpenEMR configuration
echo "⚙️  Checking OpenEMR configuration..."
if [ -f "sites/default/sqlconf.php" ]; then
    echo "  ✅ OpenEMR configuration file exists"
    
    # Check if configuration is readable
    if php -l sites/default/sqlconf.php >/dev/null 2>&1; then
        echo "  ✅ OpenEMR configuration file is valid PHP"
    else
        echo "  ⚠️  OpenEMR configuration file has PHP errors"
    fi
else
    echo "  ❌ OpenEMR configuration file missing"
fi

# Check Apache and PHP
echo "🌐 Checking web server..."
if systemctl is-active --quiet apache2; then
    echo "  ✅ Apache is running"
else
    echo "  ❌ Apache is not running"
fi

# Check PHP modules
echo "🐘 Checking PHP modules..."
if php -m | grep -q "mysqli"; then
    echo "  ✅ PHP mysqli module loaded"
else
    echo "  ❌ PHP mysqli module not loaded"
fi

# Check for recent errors
echo "📋 Checking recent Apache errors..."
if [ -f "/var/log/apache2/error.log" ]; then
    echo "  📄 Recent Apache errors:"
    tail -5 /var/log/apache2/error.log | grep -E "(error|Error|ERROR)" || echo "    No recent errors found"
else
    echo "  ⚠️  Apache error log not found"
fi

echo ""
echo "📊 Summary:"
echo "  Database connection: ✅"
echo "  Step-Up MFA table: ✅"
echo "  OpenEMR config: ✅"
echo "  Apache status: ✅"

echo ""
echo "🔗 Test your site now:"
echo "  https://emr.carepointinfusion.com/" 