#!/bin/bash
# Add Step-Up MFA Settings menu item simply
set -e

echo "🔧 Adding Step-Up MFA Settings menu item..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup the menu file
echo "📦 Creating backup..."
cp interface/main/tabs/menu/menus/standard.json interface/main/tabs/menu/menus/standard.json.backup.$(date +%Y%m%d_%H%M%S)

# Find the line number of the Config item
echo "📝 Finding Config item..."
CONFIG_LINE=$(grep -n '"label": "Config"' interface/main/tabs/menu/menus/standard.json | head -1 | cut -d: -f1)
echo "📍 Config item at line: $CONFIG_LINE"

# Find the end of the Config item (the closing brace)
echo "📝 Finding end of Config item..."
CONFIG_END=$(sed -n "${CONFIG_LINE},${CONFIG_LINE}+20p" interface/main/tabs/menu/menus/standard.json | grep -n "}" | head -1 | cut -d: -f1)
CONFIG_END=$((CONFIG_LINE + CONFIG_END - 1))
echo "📍 Config item ends at line: $CONFIG_END"

# Insert after the Config item
INSERT_LINE=$((CONFIG_END + 1))
echo "📍 Inserting at line: $INSERT_LINE"

# Create the menu item
MENU_ITEM='      {
        "label": "Step-Up MFA Settings",
        "menu_id": "adm0",
        "target": "adm",
        "url": "/interface/admin/stepup_mfa_settings.php",
        "children": [],
        "requirement": 0,
        "acl_req": [
          "admin",
          "users"
        ]
      },'

# Insert the menu item
echo "📝 Inserting Step-Up MFA Settings menu item..."
awk -v line="$INSERT_LINE" -v item="$MENU_ITEM" '
NR == line {print item}
{print}
' interface/main/tabs/menu/menus/standard.json > interface/main/tabs/menu/menus/standard.json.tmp

mv interface/main/tabs/menu/menus/standard.json.tmp interface/main/tabs/menu/menus/standard.json

# Verify JSON syntax
echo ""
echo "✅ JSON syntax check..."
python3 -m json.tool interface/main/tabs/menu/menus/standard.json > /dev/null 2>&1 && echo "✅ JSON is valid" || echo "❌ JSON has errors"

# Show the result
echo ""
echo "📋 Menu item added:"
grep -A 10 -B 2 "Step-Up MFA Settings" interface/main/tabs/menu/menus/standard.json

# Set permissions and restart
echo ""
echo "🔐 Setting permissions..."
chown www-data:www-data interface/main/tabs/menu/menus/standard.json
chmod 644 interface/main/tabs/menu/menus/standard.json

echo "🔄 Restarting Apache..."
systemctl restart apache2

echo ""
echo "🎉 Step-Up MFA Settings added to Admin menu!"
echo "🌐 Test your site: https://emr.carepointinfusion.com/" 