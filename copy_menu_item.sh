#!/bin/bash
# Copy the exact Step-Up MFA Settings menu item to server
set -e

echo "🔧 Copying Step-Up MFA Settings menu item to server..."
echo ""

# Create the exact menu item content
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

# Copy to server
echo "📤 Copying menu item to server..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && echo '$MENU_ITEM' > /tmp/stepup_mfa_menu_item.txt"

# Find the Config item and insert after it
echo "📝 Finding Config item and inserting menu item..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && CONFIG_LINE=\$(grep -n '\"label\": \"Config\"' interface/main/tabs/menu/menus/standard.json | head -1 | cut -d: -f1) && CONFIG_END=\$(sed -n \"\${CONFIG_LINE},\${CONFIG_LINE}+20p\" interface/main/tabs/menu/menus/standard.json | grep -n '}' | head -1 | cut -d: -f1) && CONFIG_END=\$((CONFIG_LINE + CONFIG_END - 1)) && INSERT_LINE=\$((CONFIG_END + 1)) && awk -v line=\"\$INSERT_LINE\" -v item=\"\$(cat /tmp/stepup_mfa_menu_item.txt)\" 'NR == line {print item} {print}' interface/main/tabs/menu/menus/standard.json > interface/main/tabs/menu/menus/standard.json.tmp && mv interface/main/tabs/menu/menus/standard.json.tmp interface/main/tabs/menu/menus/standard.json"

# Verify JSON syntax
echo ""
echo "✅ JSON syntax check..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && python3 -m json.tool interface/main/tabs/menu/menus/standard.json > /dev/null 2>&1 && echo '✅ JSON is valid' || echo '❌ JSON has errors'"

# Show the result
echo ""
echo "📋 Menu item added:"
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && grep -A 10 -B 2 'Step-Up MFA Settings' interface/main/tabs/menu/menus/standard.json"

# Set permissions and restart
echo ""
echo "🔐 Setting permissions..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && chown www-data:www-data interface/main/tabs/menu/menus/standard.json && chmod 644 interface/main/tabs/menu/menus/standard.json"

echo "🔄 Restarting Apache..."
ssh mm@emr.carepointinfusion.com "systemctl restart apache2"

echo ""
echo "🎉 Step-Up MFA Settings added to Admin menu!"
echo "🌐 Test your site: https://emr.carepointinfusion.com/" 