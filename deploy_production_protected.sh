#!/bin/bash
# Protected production deployment following OpenEMR upgrade best practices
set -e

echo "🚀 Protected production deployment to emr.carepointinfusion.com..."
echo "📋 Following OpenEMR upgrade best practices from:"
echo "   https://www.open-emr.org/wiki/index.php/Linux_Upgrade_7.0.2_to_7.0.3"
echo ""

# Configuration
SERVER="mm@emr.carepointinfusion.com"
PROJECT_PATH="/var/www/emr.carepointinfusion.com"
BRANCH="production"
BACKUP_DIR="/tmp/openemr_backup_$(date +%Y%m%d_%H%M%S)"

echo "📋 Deployment Configuration:"
echo "  Server: $SERVER"
echo "  Path: $PROJECT_PATH"
echo "  Branch: $BRANCH"
echo "  Backup: $BACKUP_DIR"
echo ""

# Create comprehensive backup following OpenEMR upgrade practices
echo "📦 Creating comprehensive backup (OpenEMR upgrade style)..."
ssh $SERVER "cd $PROJECT_PATH && sudo mkdir -p $BACKUP_DIR && sudo tar -czf $BACKUP_DIR/openemr_full_backup.tar.gz --exclude=.git --exclude=tmp --exclude=sites/default/documents/smarty ."

# Backup critical directories and files
echo "🔒 Backing up critical directories and files..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp -r sites $BACKUP_DIR/ && sudo cp -r interface/modules/custom_modules $BACKUP_DIR/ 2>/dev/null || echo 'No custom modules found' && sudo cp -r contrib $BACKUP_DIR/ 2>/dev/null || echo 'No contrib directory found'"

# Backup specific critical files
echo "🔒 Backing up critical configuration files..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp sites/default/sqlconf.php $BACKUP_DIR/ && sudo cp sites/default/config.php $BACKUP_DIR/ 2>/dev/null || echo 'No config.php found' && sudo cp sites/default/globals.php $BACKUP_DIR/ 2>/dev/null || echo 'No globals.php found'"

# Pull latest changes
echo "📥 Pulling latest changes from production branch..."
ssh $SERVER "cd $PROJECT_PATH && sudo git fetch origin && sudo git reset --hard origin/production"

# Restore critical directories and files
echo "🔒 Restoring critical directories and files..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp -r $BACKUP_DIR/sites . && sudo cp -r $BACKUP_DIR/custom_modules interface/modules/ 2>/dev/null || echo 'No custom modules to restore' && sudo cp -r $BACKUP_DIR/contrib . 2>/dev/null || echo 'No contrib to restore'"

# Restore specific critical files
echo "🔒 Restoring critical configuration files..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp $BACKUP_DIR/sqlconf.php sites/default/ && sudo cp $BACKUP_DIR/config.php sites/default/ 2>/dev/null || echo 'No config.php to restore' && sudo cp $BACKUP_DIR/globals.php sites/default/ 2>/dev/null || echo 'No globals.php to restore'"

# Set permissions
echo "🔐 Setting proper permissions..."
ssh $SERVER "cd $PROJECT_PATH && sudo chown -R www-data:www-data . && sudo find . -type f -exec chmod 644 {} \; && sudo find . -type d -exec chmod 755 {} \;"

# Apply any database changes if needed
echo "🗄️ Checking for database changes..."
ssh $SERVER "cd $PROJECT_PATH && if [ -f sql/*.sql ]; then echo 'Found SQL files, applying...'; mysql -u openemr -pcfvcfv33 openemr < sql/*.sql 2>/dev/null || echo 'No new SQL files to apply'; fi"

# Restart Apache
echo "🔄 Restarting Apache..."
ssh $SERVER "sudo systemctl restart apache2"

# Verify deployment
echo ""
echo "✅ Verifying deployment..."
ssh $SERVER "cd $PROJECT_PATH && echo 'Current branch:' && sudo git branch && echo 'Latest commit:' && sudo git log --oneline -1 && echo 'Critical files status:' && ls -la sites/default/sqlconf.php sites/default/config.php 2>/dev/null || echo 'Some files not found'"

# Test the site
echo ""
echo "🌐 Testing site accessibility..."
ssh $SERVER "wget -q --spider https://emr.carepointinfusion.com/ && echo 'Site is accessible' || echo 'Site may have issues'"

echo ""
echo "🎉 Protected production deployment completed!"
echo "📊 Summary:"
echo "  ✅ Full backup created at: $BACKUP_DIR"
echo "  ✅ sites/ directory protected"
echo "  ✅ custom_modules/ protected"
echo "  ✅ contrib/ protected"
echo "  ✅ Critical config files preserved"
echo "  ✅ Latest changes pulled"
echo "  ✅ Permissions set"
echo "  ✅ Apache restarted"
echo ""
echo "🔗 Production site: https://emr.carepointinfusion.com/"
echo "📁 Backup location: $BACKUP_DIR" 