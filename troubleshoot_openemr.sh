#!/bin/bash
# Comprehensive OpenEMR Troubleshooting Script
set -e

echo "🔍 OpenEMR Troubleshooting Script"
echo "================================="

cd /var/www/emr.carepointinfusion.com

# Check current sqlconf.php
echo "📋 Current sqlconf.php contents:"
cat sites/default/sqlconf.php

echo ""
echo "🔗 Testing database connection..."
mysql -u openemr -pcfvcfv33 openemr -e "SELECT 1;" && echo "✅ Database connection OK" || echo "❌ Database connection failed"

echo ""
echo "📊 Checking essential tables..."
tables=("users" "patient_data" "form_encounter" "gacl_aro")
for table in "${tables[@]}"; do
    mysql -u openemr -pcfvcfv33 openemr -e "SHOW TABLES LIKE '$table';" | grep -q "$table" && echo "✅ $table exists" || echo "❌ $table missing"
done

echo ""
echo "🔧 Fixing sqlconf.php with correct config..."
cat > sites/default/sqlconf.php << 'EOF'
<?php
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
$config = 1;
EOF

chown www-data:www-data sites/default/sqlconf.php
chmod 644 sites/default/sqlconf.php

echo "✅ sqlconf.php fixed with config=1"

echo ""
echo "🔄 Restarting Apache..."
systemctl restart apache2

echo ""
echo "🌐 Test your site now: https://emr.carepointinfusion.com/" 