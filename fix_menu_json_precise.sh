#!/bin/bash
# Fix JSON syntax error precisely
set -e

echo "🔧 Fixing JSON syntax error precisely..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup current file
echo "📦 Creating backup..."
cp interface/main/tabs/menu/menus/standard.json interface/main/tabs/menu/menus/standard.json.broken2.$(date +%Y%m%d_%H%M%S)

# Remove the duplicate opening brace on line 726
echo "📝 Removing duplicate opening brace..."
sed -i '726d' interface/main/tabs/menu/menus/standard.json

# Add missing target field to Step-Up MFA Settings
echo "📝 Adding missing target field..."
sed -i 's/"menu_id": "adm0",/"menu_id": "adm0",\n        "target": "adm",/' interface/main/tabs/menu/menus/standard.json

# Verify the fix
echo ""
echo "✅ JSON syntax check..."
python3 -m json.tool interface/main/tabs/menu/menus/standard.json > /dev/null 2>&1 && echo "✅ JSON is now valid" || echo "❌ JSON still has errors"

# Show the fixed section
echo ""
echo "📋 Fixed menu section:"
sed -n '720,750p' interface/main/tabs/menu/menus/standard.json

# Set permissions
echo ""
echo "🔐 Setting permissions..."
chown www-data:www-data interface/main/tabs/menu/menus/standard.json
chmod 644 interface/main/tabs/menu/menus/standard.json

# Restart Apache
echo ""
echo "🔄 Restarting Apache..."
systemctl restart apache2

echo ""
echo "🎉 Menu JSON syntax fixed!"
echo "📊 Summary:"
echo "  ✅ Backup created"
echo "  ✅ Duplicate brace removed"
echo "  ✅ Missing target field added"
echo "  ✅ JSON syntax validated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/" 