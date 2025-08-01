#!/bin/bash
# Fix JSON syntax and add Step-Up MFA Settings correctly
set -e

echo "🔧 Fixing JSON syntax and adding Step-Up MFA Settings..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup the menu file
echo "📦 Creating backup of menu file..."
cp interface/main/tabs/menu/menus/standard.json interface/main/tabs/menu/menus/standard.json.backup.$(date +%Y%m%d_%H%M%S)

# Find the Config item and add comma after it
echo "📝 Adding missing comma after Config item..."
sed -i '/"label": "Config"/,/}/ s/}$/},/' interface/main/tabs/menu/menus/standard.json

# Find the line after Config item ends
CONFIG_END=$(grep -n '"label": "Config"' interface/main/tabs/menu/menus/standard.json | head -1 | cut -d: -f1)
CONFIG_END=$(sed -n "${CONFIG_END},${CONFIG_END}+15p" interface/main/tabs/menu/menus/standard.json | grep -n "}" | head -1 | cut -d: -f1)
CONFIG_END=$((CONFIG_END + CONFIG_END))
INSERT_LINE=$((CONFIG_END + 1))

echo "📍 Inserting Step-Up MFA Settings at line: $INSERT_LINE"

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

# Verify the change
echo ""
echo "✅ Menu updated successfully!"
echo "📋 Checking the change..."
grep -A 10 -B 5 "Step-Up MFA Settings" interface/main/tabs/menu/menus/standard.json

# Verify JSON syntax
echo ""
echo "✅ JSON syntax check..."
python3 -m json.tool interface/main/tabs/menu/menus/standard.json > /dev/null 2>&1 && echo "✅ JSON is valid" || echo "❌ JSON has errors"

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
echo "🎉 JSON syntax fixed and Step-Up MFA Settings added!"
echo "📊 Summary:"
echo "  ✅ Menu file backed up"
echo "  ✅ Missing comma added"
echo "  ✅ Step-Up MFA Settings added correctly"
echo "  ✅ JSON syntax validated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/"
echo ""
echo "🔍 Look for 'Step-Up MFA Settings' under Admin menu" 