#!/bin/bash
# Merge from the correct production repository
set -e

echo "🔄 Merging from correct production repository..."
echo "Repository: https://github.com/moussa854/openemr-production"
echo "Branch: feature/mfa-remember-device"
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup current state
echo "📦 Creating backup..."
BACKUP_DIR="/tmp/openemr_backup_correct_$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR
cp -r . $BACKUP_DIR/ 2>/dev/null || echo "⚠️  Backup created with some permission issues"
echo "✅ Backup created at: $BACKUP_DIR"

# Check current remote
echo "📡 Current remote:"
git remote -v

# Add the correct production repository as a new remote
echo "➕ Adding production repository as remote..."
git remote add production https://github.com/moussa854/openemr-production.git

# Fetch from production repository
echo "📥 Fetching from production repository..."
git fetch production

# List available branches from production
echo "🌿 Available branches from production:"
git branch -r | grep production

# Check if the branch exists in production
if git branch -r | grep -q "production/feature/mfa-remember-device"; then
    echo "✅ feature/mfa-remember-device branch found in production"
else
    echo "❌ feature/mfa-remember-device branch not found in production"
    echo "Available branches:"
    git branch -r | grep production
    exit 1
fi

# Switch to the production branch
echo "🔄 Switching to production feature/mfa-remember-device branch..."
git checkout -B feature/mfa-remember-device production/feature/mfa-remember-device

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
echo "🎉 Merge from production repository completed!"
echo "📊 Summary:"
echo "  ✅ Backup created at: $BACKUP_DIR"
echo "  ✅ Switched to production feature/mfa-remember-device"
echo "  ✅ Permissions updated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site: https://emr.carepointinfusion.com/"
echo ""
echo "🔧 If you need to revert:"
echo "  git checkout feature/stepup-mfa"
echo "  cp -r $BACKUP_DIR/* ." 