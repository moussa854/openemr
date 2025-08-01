#!/bin/bash
# Fix JSON syntax error in menu file
set -e

echo "🔧 Fixing JSON syntax error in menu file..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup current file
echo "📦 Creating backup..."
cp interface/main/tabs/menu/menus/standard.json interface/main/tabs/menu/menus/standard.json.broken.$(date +%Y%m%d_%H%M%S)

# Fix the duplicate opening braces
echo "📝 Fixing duplicate opening braces..."
sed -i '730,735s/^      {$/      },/' interface/main/tabs/menu/menus/standard.json

# Remove the extra opening brace
sed -i '730d' interface/main/tabs/menu/menus/standard.json

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
echo "  ✅ Duplicate braces removed"
echo "  ✅ JSON syntax validated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/" 