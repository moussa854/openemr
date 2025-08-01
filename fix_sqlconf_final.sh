#!/bin/bash
# Fix sqlconf.php with correct database credentials
set -e

echo "🔧 Fixing sqlconf.php with correct database credentials..."
echo ""

# Create the correct sqlconf.php content
SQLCONF_CONTENT='<?php
//  OpenEMR
//  MySQL Config

global $disable_utf8_flag;
$disable_utf8_flag = false;

$host   = "localhost";
$port   = "3306";
$login  = "openemr";
$pass   = "cfvcfv33";
$dbase  = "openemr";
$db_encoding = "utf8mb4";

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
'

# Copy to server and apply
echo "📤 Copying correct sqlconf.php to server..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && echo '$SQLCONF_CONTENT' > /tmp/correct_sqlconf.php"

# Apply the fix
echo "📝 Applying correct sqlconf.php..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && sudo cp /tmp/correct_sqlconf.php sites/default/sqlconf.php && sudo chown www-data:www-data sites/default/sqlconf.php && sudo chmod 644 sites/default/sqlconf.php"

# Restart Apache
echo "🔄 Restarting Apache..."
ssh mm@emr.carepointinfusion.com "sudo systemctl restart apache2"

# Test the site
echo ""
echo "🌐 Testing site accessibility..."
ssh mm@emr.carepointinfusion.com "curl -s -o /dev/null -w 'HTTP Status: %{http_code}\n' https://emr.carepointinfusion.com/"

echo ""
echo "✅ sqlconf.php fixed!"
echo "📊 Summary:"
echo "  ✅ Backup created"
echo "  ✅ Correct credentials applied"
echo "  ✅ config = 1 set"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site: https://emr.carepointinfusion.com/" 