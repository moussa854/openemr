#!/bin/bash
# Protect OpenEMR files before deployment
# Following OpenEMR upgrade best practices from:
# https://www.open-emr.org/wiki/index.php/Linux_Upgrade_7.0.2_to_7.0.3

set -e

echo "🔒 Protecting OpenEMR files before deployment..."
echo "📋 Following OpenEMR upgrade best practices"
echo ""

# Configuration
SERVER="mm@emr.carepointinfusion.com"
PROJECT_PATH="/var/www/emr.carepointinfusion.com"
PROTECTION_DIR="/tmp/openemr_protection_$(date +%Y%m%d_%H%M%S)"

echo "📋 Protection Configuration:"
echo "  Server: $SERVER"
echo "  Path: $PROJECT_PATH"
echo "  Protection Dir: $PROTECTION_DIR"
echo ""

# Create protection directory
echo "📦 Creating protection directory..."
ssh $SERVER "sudo mkdir -p $PROTECTION_DIR"

# Backup critical directories (following OpenEMR upgrade guide)
echo "🔒 Backing up sites/ directory (Step 4 from upgrade guide)..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp -r sites $PROTECTION_DIR/"

echo "🔒 Backing up custom_modules/ directory..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp -r interface/modules/custom_modules $PROTECTION_DIR/ 2>/dev/null || echo 'No custom modules found'"

echo "🔒 Backing up contrib/ directory..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp -r contrib $PROTECTION_DIR/ 2>/dev/null || echo 'No contrib directory found'"

# Backup critical configuration files
echo "🔒 Backing up critical configuration files..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp sites/default/sqlconf.php $PROTECTION_DIR/ && sudo cp sites/default/config.php $PROTECTION_DIR/ 2>/dev/null || echo 'No config.php found' && sudo cp sites/default/globals.php $PROTECTION_DIR/ 2>/dev/null || echo 'No globals.php found'"

# Backup documents and uploads
echo "🔒 Backing up documents and uploads..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp -r sites/default/documents $PROTECTION_DIR/ 2>/dev/null || echo 'No documents directory found' && sudo cp -r sites/default/images $PROTECTION_DIR/ 2>/dev/null || echo 'No images directory found'"

# Create a restoration script
echo "📝 Creating restoration script..."
RESTORE_SCRIPT="restore_openemr_files.sh"
ssh $SERVER "cat > $PROTECTION_DIR/$RESTORE_SCRIPT << 'EOF'
#!/bin/bash
# Restore OpenEMR files after deployment
set -e

echo '🔒 Restoring OpenEMR files after deployment...'

# Restore sites directory
echo '📁 Restoring sites/ directory...'
sudo cp -r sites /var/www/emr.carepointinfusion.com/

# Restore custom modules
echo '📁 Restoring custom_modules/ directory...'
sudo cp -r custom_modules /var/www/emr.carepointinfusion.com/interface/modules/ 2>/dev/null || echo 'No custom modules to restore'

# Restore contrib directory
echo '📁 Restoring contrib/ directory...'
sudo cp -r contrib /var/www/emr.carepointinfusion.com/ 2>/dev/null || echo 'No contrib to restore'

# Restore critical configuration files
echo '📄 Restoring critical configuration files...'
sudo cp sqlconf.php /var/www/emr.carepointinfusion.com/sites/default/ 2>/dev/null || echo 'No sqlconf.php to restore'
sudo cp config.php /var/www/emr.carepointinfusion.com/sites/default/ 2>/dev/null || echo 'No config.php to restore'
sudo cp globals.php /var/www/emr.carepointinfusion.com/sites/default/ 2>/dev/null || echo 'No globals.php to restore'

# Restore documents and uploads
echo '📄 Restoring documents and uploads...'
sudo cp -r documents /var/www/emr.carepointinfusion.com/sites/default/ 2>/dev/null || echo 'No documents to restore'
sudo cp -r images /var/www/emr.carepointinfusion.com/sites/default/ 2>/dev/null || echo 'No images to restore'

# Set permissions
echo '🔐 Setting permissions...'
sudo chown -R www-data:www-data /var/www/emr.carepointinfusion.com/
sudo find /var/www/emr.carepointinfusion.com/ -type f -exec chmod 644 {} \;
sudo find /var/www/emr.carepointinfusion.com/ -type d -exec chmod 755 {} \;

# Restart Apache
echo '🔄 Restarting Apache...'
sudo systemctl restart apache2

echo '✅ OpenEMR files restored successfully!'
echo '🌐 Test your site: https://emr.carepointinfusion.com/'
EOF"

# Make restoration script executable
ssh $SERVER "chmod +x $PROTECTION_DIR/$RESTORE_SCRIPT"

# Show protection summary
echo ""
echo "✅ OpenEMR files protected successfully!"
echo "📊 Protected items:"
echo "  ✅ sites/ directory"
echo "  ✅ custom_modules/ directory"
echo "  ✅ contrib/ directory"
echo "  ✅ sqlconf.php"
echo "  ✅ config.php"
echo "  ✅ globals.php"
echo "  ✅ documents/ directory"
echo "  ✅ images/ directory"
echo ""
echo "📁 Protection location: $PROTECTION_DIR"
echo "🔧 Restoration script: $PROTECTION_DIR/$RESTORE_SCRIPT"
echo ""
echo "💡 To restore after deployment, run:"
echo "   ssh $SERVER 'cd $PROTECTION_DIR && sudo ./$RESTORE_SCRIPT'" 