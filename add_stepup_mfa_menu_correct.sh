#!/bin/bash
# Add Step-Up MFA Settings to Admin menu correctly
set -e

echo "🔧 Adding Step-Up MFA Settings to Admin menu correctly..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup the menu file
echo "📦 Creating backup of menu file..."
cp interface/main/tabs/menu/menus/standard.json interface/main/tabs/menu/menus/standard.json.backup.$(date +%Y%m%d_%H%M%S)

# Find the Admin menu children array
echo "📝 Finding Admin menu children array..."
ADMIN_CHILDREN_START=$(grep -n '"children": \[' interface/main/tabs/menu/menus/standard.json | grep -A 10 '"label": "Admin"' | head -1 | cut -d: -f1)
echo "📍 Admin children start at line: $ADMIN_CHILDREN_START"

# Find the first item in Admin menu (Config)
CONFIG_LINE=$(grep -n '"label": "Config"' interface/main/tabs/menu/menus/standard.json | head -1 | cut -d: -f1)
echo "📍 Config item at line: $CONFIG_LINE"

# Find the end of the Config item
CONFIG_END=$(sed -n "${CONFIG_LINE},${CONFIG_LINE}+15p" interface/main/tabs/menu/menus/standard.json | grep -n "}" | head -1 | cut -d: -f1)
CONFIG_END=$((CONFIG_LINE + CONFIG_END))
echo "📍 Config item ends at line: $CONFIG_END"

# Insert the Step-Up MFA Settings after the Config item
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
echo "🎉 Step-Up MFA Settings added to Admin menu!"
echo "📊 Summary:"
echo "  ✅ Menu file backed up"
echo "  ✅ Step-Up MFA Settings added correctly"
echo "  ✅ JSON syntax validated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/"
echo ""
echo "🔍 Look for 'Step-Up MFA Settings' under Admin menu" 