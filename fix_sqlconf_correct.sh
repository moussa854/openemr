#!/bin/bash
# Fix sqlconf.php with correct database credentials
set -e

echo "🔧 Fixing sqlconf.php with correct database credentials..."
echo "Using credentials provided by user:"
echo "  Login: openemr"
echo "  Password: cfvcfv33"
echo "  Database: openemr"
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup current file
echo "📦 Creating backup of current sqlconf.php..."
cp sites/default/sqlconf.php sites/default/sqlconf.php.backup.$(date +%Y%m%d_%H%M%S)

# Create new sqlconf.php with correct credentials
echo "📝 Creating new sqlconf.php with correct credentials..."
cat > sites/default/sqlconf.php << 'EOF'
<?php
//  OpenEMR
//  MySQL Config
global $disable_utf8_flag;
$disable_utf8_flag = false;
$host   = 'localhost';
$port   = '3306';
$login  = 'openemr';
$pass   = 'cfvcfv33';
$dbase  = 'openemr';
$db_encoding = 'utf8mb4';
$sqlconf = array();
global $sqlconf;
$sqlconf["host"]= $host;
$sqlconf["port"] = $port;
$sqlconf["login"] = $login;
$sqlconf["pass"] = $pass;
$sqlconf["dbase"] = $dbase;
$sqlconf["db_encoding"] = $db_encoding;
//////////////////////////
//////////////////////////
//////////////////////////
//////DO NOT TOUCH THIS///
$config = 0; /////////////
//////////////////////////
//////////////////////////
//////////////////////////
EOF

# Set proper permissions
echo "🔐 Setting proper permissions..."
chown www-data:www-data sites/default/sqlconf.php
chmod 644 sites/default/sqlconf.php

# Test the configuration
echo "🧪 Testing configuration..."
if php -l sites/default/sqlconf.php >/dev/null 2>&1; then
    echo "  ✅ sqlconf.php is valid PHP"
else
    echo "  ❌ sqlconf.php has PHP errors"
    exit 1
fi

# Test database connection with new credentials
echo "🔗 Testing database connection with new credentials..."
if mysql -u openemr -pcfvcfv33 openemr -e "SELECT 1;" >/dev/null 2>&1; then
    echo "  ✅ Database connection successful"
else
    echo "  ❌ Database connection failed"
    echo "  Restoring backup..."
    cp sites/default/sqlconf.php.backup.* sites/default/sqlconf.php
    exit 1
fi

# Test OpenEMR tables
echo "📊 Testing OpenEMR tables..."
tables=("users" "patient_data" "form_encounter")
for table in "${tables[@]}"; do
    if mysql -u openemr -pcfvcfv33 openemr -e "SHOW TABLES LIKE '$table';" | grep -q "$table"; then
        echo "  ✅ Table '$table' exists"
    else
        echo "  ❌ Table '$table' missing"
    fi
done

# Count users
user_count=$(mysql -u openemr -pcfvcfv33 openemr -e "SELECT COUNT(*) as count FROM users;" | tail -1)
echo "  📈 Users in database: $user_count"

# Restart Apache
echo "🔄 Restarting Apache..."
systemctl restart apache2

echo ""
echo "🎉 sqlconf.php fixed successfully!"
echo "📊 Summary:"
echo "  ✅ Backup created with timestamp"
echo "  ✅ New sqlconf.php created with correct credentials"
echo "  ✅ Database connection verified"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/"
echo "  https://emr.carepointinfusion.com/interface/login/login.php"
echo ""
echo "🚀 Your OpenEMR should now be working!" 