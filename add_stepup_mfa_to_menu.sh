#!/bin/bash
# Add Step-Up MFA Settings to Admin menu
set -e

echo "🔧 Adding Step-Up MFA Settings to Admin menu..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup the menu file
echo "📦 Creating backup of menu file..."
cp interface/main/tabs/menu/menus/standard.json interface/main/tabs/menu/menus/standard.json.backup.$(date +%Y%m%d_%H%M%S)

# Find the Admin menu section and add Step-Up MFA Settings
echo "📝 Adding Step-Up MFA Settings to Admin menu..."

# Create a temporary file with the new menu item
cat > /tmp/stepup_mfa_menu_item.json << 'EOF'
      {
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
      },
EOF

# Find the position to insert (after the first few items in Admin menu)
# Look for the end of the first few Admin menu items
ADMIN_START=$(grep -n '"label": "Admin"' interface/main/tabs/menu/menus/standard.json | cut -d: -f1)
echo "📍 Admin menu starts at line: $ADMIN_START"

# Find a good insertion point (after the first few items)
INSERT_LINE=$(grep -n '"label": "Clinic"' interface/main/tabs/menu/menus/standard.json | cut -d: -f1)
echo "📍 Inserting at line: $INSERT_LINE"

# Create the updated menu file
echo "📝 Updating menu file..."
sed -i "${INSERT_LINE}i\\$(cat /tmp/stepup_mfa_menu_item.json)" interface/main/tabs/menu/menus/standard.json

# Clean up temp file
rm /tmp/stepup_mfa_menu_item.json

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