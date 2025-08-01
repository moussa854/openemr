#!/bin/bash

# Fix sqlconf.php with correct database credentials
# Run this on the production server

set -e

echo "🔧 Fixing sqlconf.php with correct database credentials..."

cd /var/www/emr.carepointinfusion.com

# Backup current file
echo "📦 Creating backup of current sqlconf.php..."
cp sites/default/sqlconf.php sites/default/sqlconf.php.backup

# Create new sqlconf.php with correct credentials
echo "📝 Creating new sqlconf.php..."
cat > sites/default/sqlconf.php << 'EOF'
<?php
//  OpenEMR
//  MySQL Config

global $disable_utf8_flag;
$disable_utf8_flag = false;

$host   = 'localhost';
$port   = '3306';
$login  = 'root';
$pass   = 'openemr';
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
if mysql -u root -popenemr openemr -e "SELECT 1;" >/dev/null 2>&1; then
    echo "  ✅ Database connection successful"
else
    echo "  ❌ Database connection failed"
    echo "  Restoring backup..."
    cp sites/default/sqlconf.php.backup sites/default/sqlconf.php
    exit 1
fi

# Restart Apache
echo "🔄 Restarting Apache..."
systemctl restart apache2

echo ""
echo "🎉 sqlconf.php fixed successfully!"
echo "📊 Summary:"
echo "  ✅ Backup created: sites/default/sqlconf.php.backup"
echo "  ✅ New sqlconf.php created with correct credentials"
echo "  ✅ Database connection verified"
echo "  ✅ Apache restarted"

echo ""
echo "🔗 Test your site now:"
echo "  https://emr.carepointinfusion.com/"
echo "  https://emr.carepointinfusion.com/interface/login/login.php"

echo ""
echo "🚀 Your OpenEMR should now be working!" 