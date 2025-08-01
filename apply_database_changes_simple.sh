#!/bin/bash

# Apply Database Changes for Step-Up MFA (Simplified)
# Run this on the production server

set -e

echo "🗄️  Applying database changes for Step-Up MFA..."

# Configuration
DB_NAME="openemr"
DB_USER="root"
DB_PASS="openemr"
SQL_FILE="sql/stepup_mfa_verifications.sql"

cd /var/www/emr.carepointinfusion.com

echo "📋 Database Configuration:"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo "  SQL File: $SQL_FILE"

# Step 1: Check if SQL file exists
echo "📄 Checking SQL file..."
if [ -f "$SQL_FILE" ]; then
    echo "  ✅ SQL file found: $SQL_FILE"
else
    echo "  ❌ SQL file not found: $SQL_FILE"
    exit 1
fi

# Step 2: Test database connection
echo "🔗 Testing database connection..."
if mysql -u $DB_USER -p$DB_PASS -e "USE $DB_NAME; SELECT 1;" >/dev/null 2>&1; then
    echo "  ✅ Database connection successful"
else
    echo "  ❌ Database connection failed"
    echo "  Please check your database credentials"
    exit 1
fi

# Step 3: Check if table already exists
echo "🔍 Checking if table exists..."
if mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;" >/dev/null 2>&1; then
    echo "  ✅ Table already exists"
    echo "  📊 Table structure:"
    mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;"
else
    echo "  📝 Table does not exist, creating..."
    
    # Step 4: Apply SQL file
    echo "📥 Applying SQL file..."
    if mysql -u $DB_USER -p$DB_PASS $DB_NAME < $SQL_FILE; then
        echo "  ✅ SQL file applied successfully"
    else
        echo "  ❌ Failed to apply SQL file"
        exit 1
    fi
fi

# Step 5: Verify table creation
echo "✅ Verifying table creation..."
if mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;" >/dev/null 2>&1; then
    echo "  ✅ Table verified successfully"
    echo "  📊 Final table structure:"
    mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;"
    
    # Check table count
    COUNT=$(mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT COUNT(*) FROM stepup_mfa_verifications;" -s -N)
    echo "  📈 Records in table: $COUNT"
else
    echo "  ❌ Table verification failed"
    exit 1
fi

# Step 6: Check OpenEMR configuration
echo "🔧 Checking OpenEMR configuration..."
if [ -f "sites/default/sqlconf.php" ]; then
    echo "  ✅ OpenEMR configuration file exists"
else
    echo "  ⚠️  OpenEMR configuration file missing"
fi

# Step 7: Check Apache status
echo "🌐 Checking Apache status..."
if systemctl is-active --quiet apache2; then
    echo "  ✅ Apache is running"
else
    echo "  ⚠️  Apache is not running"
    echo "  🔄 Starting Apache..."
    systemctl start apache2
fi

echo ""
echo "🎉 Database changes applied successfully!"
echo "📊 Summary:"
echo "  ✅ Database connection verified"
echo "  ✅ Table created/verified"
echo "  ✅ Apache status checked"

echo ""
echo "🔗 Test URLs:"
echo "  Main: https://emr.carepointinfusion.com/"
echo "  MFA Settings: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_settings.php"
echo "  Compliance Report: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_compliance_report.php"

echo ""
echo "🚀 Your OpenEMR should now be working!"
echo "💡 If you still see the installation page, try:"
echo "   1. Clear your browser cache"
echo "   2. Try a different browser"
echo "   3. Check if there are any PHP errors in /var/log/apache2/error.log" 