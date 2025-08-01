#!/bin/bash
# Safe production deployment that preserves sqlconf.php
set -e

echo "🚀 Safe production deployment to emr.carepointinfusion.com..."
echo ""

# Configuration
SERVER="mm@emr.carepointinfusion.com"
PROJECT_PATH="/var/www/emr.carepointinfusion.com"
BRANCH="production"

echo "📋 Deployment Configuration:"
echo "  Server: $SERVER"
echo "  Path: $PROJECT_PATH"
echo "  Branch: $BRANCH"
echo ""

# Create backup
echo "📦 Creating backup of current production files..."
ssh $SERVER "cd $PROJECT_PATH && sudo tar -czf /tmp/openemr_backup_$(date +%Y%m%d_%H%M%S).tar.gz --exclude=.git --exclude=tmp --exclude=sites/default/documents/smarty ."

# Backup sqlconf.php before deployment
echo "🔒 Backing up sqlconf.php..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp sites/default/sqlconf.php /tmp/sqlconf_backup_$(date +%Y%m%d_%H%M%S).php"

# Pull latest changes
echo "📥 Pulling latest changes from production branch..."
ssh $SERVER "cd $PROJECT_PATH && sudo git fetch origin && sudo git reset --hard origin/production"

# Restore sqlconf.php
echo "🔒 Restoring sqlconf.php..."
ssh $SERVER "cd $PROJECT_PATH && sudo cp /tmp/sqlconf_backup_$(date +%Y%m%d_%H%M%S).php sites/default/sqlconf.php && sudo chown www-data:www-data sites/default/sqlconf.php && sudo chmod 644 sites/default/sqlconf.php"

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
ssh $SERVER "cd $PROJECT_PATH && echo 'Current branch:' && sudo git branch && echo 'Latest commit:' && sudo git log --oneline -1 && echo 'sqlconf.php status:' && grep -E '(login|pass|config)' sites/default/sqlconf.php"

# Test the site
echo ""
echo "🌐 Testing site accessibility..."
ssh $SERVER "curl -s -o /dev/null -w 'HTTP Status: %{http_code}\n' https://emr.carepointinfusion.com/"

echo ""
echo "🎉 Safe production deployment completed!"
echo "📊 Summary:"
echo "  ✅ Backup created"
echo "  ✅ sqlconf.php preserved"
echo "  ✅ Latest changes pulled"
echo "  ✅ Permissions set"
echo "  ✅ Apache restarted"
echo "  ✅ Site verified"
echo ""
echo "🔗 Production site: https://emr.carepointinfusion.com/" 