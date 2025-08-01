#!/bin/bash
# Add Step-Up MFA Settings to Admin menu (fixed version)
set -e

echo "🔧 Adding Step-Up MFA Settings to Admin menu..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup the menu file
echo "📦 Creating backup of menu file..."
cp interface/main/tabs/menu/menus/standard.json interface/main/tabs/menu/menus/standard.json.backup.$(date +%Y%m%d_%H%M%S)

# Find the Admin menu section and add Step-Up MFA Settings
echo "📝 Adding Step-Up MFA Settings to Admin menu..."

# Find the line number where we want to insert (after "Config" item)
CONFIG_LINE=$(grep -n '"label": "Config"' interface/main/tabs/menu/menus/standard.json | cut -d: -f1)
echo "📍 Config item at line: $CONFIG_LINE"

# Find the end of the Config item (look for the closing brace)
INSERT_LINE=$(sed -n "${CONFIG_LINE},${CONFIG_LINE}+10p" interface/main/tabs/menu/menus/standard.json | grep -n "}" | head -1 | cut -d: -f1)
INSERT_LINE=$((CONFIG_LINE + INSERT_LINE))
echo "📍 Inserting at line: $INSERT_LINE"

# Create the menu item content
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
echo "📝 Inserting menu item..."
awk -v line="$INSERT_LINE" -v item="$MENU_ITEM" '
NR == line {print item}
{print}
' interface/main/tabs/menu/menus/standard.json > interface/main/tabs/menu/menus/standard.json.tmp

mv interface/main/tabs/menu/menus/standard.json.tmp interface/main/tabs/menu/menus/standard.json

# Verify the change
echo ""
echo "✅ Menu updated successfully!"
echo "📋 Checking the change..."
grep -A 5 -B 5 "Step-Up MFA Settings" interface/main/tabs/menu/menus/standard.json

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
echo "  ✅ Step-Up MFA Settings added to Admin menu"
echo "  ✅ Permissions updated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/"
echo ""
echo "🔍 Look for 'Step-Up MFA Settings' under Admin menu" 