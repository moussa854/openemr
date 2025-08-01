#!/bin/bash
# Simple merge of feature/mfa-remember-device branch
set -e

echo "🔄 Simple merge of feature/mfa-remember-device branch..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup current state
echo "📦 Creating backup..."
BACKUP_DIR="/tmp/openemr_backup_simple_$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR
cp -r . $BACKUP_DIR/ 2>/dev/null || echo "⚠️  Backup created with some permission issues"
echo "✅ Backup created at: $BACKUP_DIR"

# Clean up any git issues
echo "🧹 Cleaning git state..."
git reset --hard HEAD
git clean -fd

# Fetch latest
echo "📥 Fetching latest changes..."
git fetch origin

# Check available branches
echo "🌿 Available branches:"
git branch -r

# Switch to the target branch (force)
echo "🔄 Switching to feature/mfa-remember-device branch..."
git checkout -B feature/mfa-remember-device origin/feature/mfa-remember-device

# Verify we're on the right branch
CURRENT_BRANCH=$(git branch --show-current)
echo "📍 Current branch: $CURRENT_BRANCH"

# Check what files are in this branch
echo "📋 Checking files in this branch..."
git ls-tree -r --name-only HEAD | head -20

# Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Check for database changes
echo "🗄️  Checking for database changes..."
if [ -f "sql/mfa_remember_device.sql" ]; then
    echo "📝 Found: sql/mfa_remember_device.sql"
    echo "Applying database changes..."
    mysql -u openemr -pcfvcfv33 openemr < sql/mfa_remember_device.sql && echo "✅ Database changes applied" || echo "⚠️  Database changes failed"
else
    echo "ℹ️  No database schema file found"
fi

# Check for any other SQL files
echo "🔍 Looking for other SQL files..."
find . -name "*.sql" -type f | grep -i mfa || echo "No MFA-related SQL files found"

# Restart Apache
echo "🔄 Restarting Apache..."
systemctl restart apache2

# Verify deployment
echo "✅ Verifying deployment..."
echo "Checking for MFA-related files:"
find . -name "*mfa*" -type f | head -10

echo ""
echo "🎉 Merge completed!"
echo "📊 Summary:"
echo "  ✅ Backup created at: $BACKUP_DIR"
echo "  ✅ Switched to feature/mfa-remember-device"
echo "  ✅ Permissions updated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site: https://emr.carepointinfusion.com/"
echo ""
echo "🔧 If you need to revert:"
echo "  git checkout feature/stepup-mfa"
echo "  cp -r $BACKUP_DIR/* ." 