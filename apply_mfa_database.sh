#!/bin/bash
# Apply MFA remember device database changes
set -e

echo "🗄️  Applying MFA remember device database changes..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Check if the SQL file exists
if [ -f "sql/install-mfa-remembered-devices-complete.sql" ]; then
    echo "📝 Found database file: sql/install-mfa-remembered-devices-complete.sql"
    echo "Applying database changes..."
    
    # Apply the SQL file
    mysql -u openemr -pcfvcfv33 openemr < sql/install-mfa-remembered-devices-complete.sql
    
    if [ $? -eq 0 ]; then
        echo "✅ Database changes applied successfully"
    else
        echo "❌ Database changes failed"
        exit 1
    fi
else
    echo "❌ Database file not found"
    exit 1
fi

# Check for new tables
echo ""
echo "📊 Checking for new MFA tables..."
mysql -u openemr -pcfvcfv33 openemr -e "SHOW TABLES LIKE '%mfa%';"
mysql -u openemr -pcfvcfv33 openemr -e "SHOW TABLES LIKE '%remember%';"

# Check table structure
echo ""
echo "📋 Checking table structure..."
mysql -u openemr -pcfvcfv33 openemr -e "DESCRIBE mfa_remembered_devices;" 2>/dev/null || echo "Table mfa_remembered_devices not found"

echo ""
echo "🎉 Database changes completed!"
echo "🌐 Test your MFA remember device feature now:"
echo "  https://emr.carepointinfusion.com/" 