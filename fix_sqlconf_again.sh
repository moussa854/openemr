#!/bin/bash
# Fix sqlconf.php again after branch merge
set -e

echo "🔧 Fixing sqlconf.php after branch merge..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup current sqlconf.php
echo "📦 Creating backup of current sqlconf.php..."
cp sites/default/sqlconf.php sites/default/sqlconf.php.backup.$(date +%Y%m%d_%H%M%S)

# Create correct sqlconf.php
echo "📝 Creating correct sqlconf.php..."
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
$config = 1; /////////////
//////////////////////////
//////////////////////////
//////////////////////////
EOF

# Set permissions
echo "🔐 Setting permissions..."
chown www-data:www-data sites/default/sqlconf.php
chmod 644 sites/default/sqlconf.php

# Test database connection
echo "🔗 Testing database connection..."
if mysql -u openemr -pcfvcfv33 openemr -e "SELECT 1;" >/dev/null 2>&1; then
    echo "  ✅ Database connection successful"
else
    echo "  ❌ Database connection failed"
    exit 1
fi

# Restart Apache
echo "🔄 Restarting Apache..."
systemctl restart apache2

echo ""
echo "🎉 sqlconf.php fixed successfully!"
echo "📊 Summary:"
echo "  ✅ Backup created"
echo "  ✅ sqlconf.php recreated with correct credentials"
echo "  ✅ Database connection verified"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/"
echo ""
echo "🚀 Your OpenEMR should now be working!" 