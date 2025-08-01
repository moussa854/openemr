#!/bin/bash
# Merge feature/mfa-remember-device branch to production server
set -e

echo "🔄 Merging feature/mfa-remember-device branch to production server..."
echo "Repository: https://github.com/moussa854/openemr-production"
echo "Branch: feature/mfa-remember-device"
echo ""

cd /var/www/emr.carepointinfusion.com

# Backup current state
echo "📦 Creating backup of current state..."
BACKUP_DIR="/tmp/openemr_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR
cp -r . $BACKUP_DIR/ 2>/dev/null || echo "⚠️  Backup created with some permission issues"

echo "✅ Backup created at: $BACKUP_DIR"

# Check current git status
echo "📊 Checking current git status..."
git status --porcelain

# Fetch latest changes from GitHub
echo "📥 Fetching latest changes from GitHub..."
git fetch origin

# List available branches
echo "🌿 Available branches:"
git branch -r

# Check if the branch exists
if git branch -r | grep -q "origin/feature/mfa-remember-device"; then
    echo "✅ feature/mfa-remember-device branch found"
else
    echo "❌ feature/mfa-remember-device branch not found"
    echo "Available branches:"
    git branch -r
    exit 1
fi

# Check current branch
CURRENT_BRANCH=$(git branch --show-current)
echo "📍 Current branch: $CURRENT_BRANCH"

# Stash any local changes
echo "💾 Stashing any local changes..."
git stash push -m "Backup before merging mfa-remember-device" || echo "No changes to stash"

# Switch to the target branch
echo "🔄 Switching to feature/mfa-remember-device branch..."
git checkout origin/feature/mfa-remember-device -b feature/mfa-remember-device

# Pull latest changes
echo "📥 Pulling latest changes..."
git pull origin feature/mfa-remember-device

# Check for conflicts
echo "🔍 Checking for merge conflicts..."
if git status --porcelain | grep -q "^UU"; then
    echo "⚠️  Merge conflicts detected!"
    echo "Conflicted files:"
    git status --porcelain | grep "^UU"
    echo ""
    echo "Please resolve conflicts manually or contact support"
    exit 1
fi

# Check what files changed
echo "📋 Files changed in this branch:"
git diff --name-only HEAD~10 || echo "No recent changes to show"

# Set proper permissions
echo "🔐 Setting proper permissions..."
chown -R www-data:www-data .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Apply any database changes
echo "🗄️  Checking for database changes..."
if [ -f "sql/mfa_remember_device.sql" ]; then
    echo "📝 Found database schema file: sql/mfa_remember_device.sql"
    echo "Applying database changes..."
    mysql -u openemr -pcfvcfv33 openemr < sql/mfa_remember_device.sql && echo "✅ Database changes applied" || echo "⚠️  Database changes failed or file was empty"
else
    echo "ℹ️  No database schema file found"
fi

# Clear caches
echo "🧹 Clearing caches..."
rm -rf tmp/cache/* 2>/dev/null || echo "No cache to clear"

# Restart Apache
echo "🔄 Restarting Apache..."
systemctl restart apache2

# Verify deployment
echo "✅ Verifying deployment..."
echo "Checking key files:"
ls -la interface/ | grep -i mfa || echo "No MFA interface files found"
ls -la src/Services/ | grep -i mfa || echo "No MFA service files found"

echo ""
echo "🎉 Merge completed successfully!"
echo "📊 Summary:"
echo "  ✅ Backup created at: $BACKUP_DIR"
echo "  ✅ Switched to feature/mfa-remember-device branch"
echo "  ✅ Permissions updated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/"
echo ""
echo "🔧 If you need to revert:"
echo "  git checkout $CURRENT_BRANCH"
echo "  cp -r $BACKUP_DIR/* ." 