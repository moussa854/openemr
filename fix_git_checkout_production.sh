#!/bin/bash

# Fix Git Checkout for Production
# Handles untracked files that would be overwritten

set -e

echo "🔧 Fixing git checkout conflicts..."

PROD_PATH="/var/www/emr.carepointinfusion.com"
BRANCH="feature/stepup-mfa"
BACKUP_DIR="/tmp/openemr_backup_$(date +%Y%m%d_%H%M%S)"

cd $PROD_PATH

echo "📦 Creating backup of important files..."
mkdir -p $BACKUP_DIR

# Backup important files that might be modified
echo "  Backing up configuration files..."
cp -r interface/admin/ $BACKUP_DIR/ 2>/dev/null || true
cp -r src/Services/ $BACKUP_DIR/ 2>/dev/null || true
cp -r sql/ $BACKUP_DIR/ 2>/dev/null || true
cp *.php $BACKUP_DIR/ 2>/dev/null || true

echo "🧹 Removing untracked files..."
# Remove untracked files (but keep .git directory)
git clean -fd

echo "🔄 Force checking out branch..."
git checkout -f $BRANCH

echo "📥 Pulling latest changes..."
git pull origin $BRANCH

echo "🔐 Setting permissions..."
chown -R www-data:www-data .
find . -name '*.php' -exec chmod 644 {} \;

echo "🧹 Clearing caches..."
rm -rf tmp/* 2>/dev/null || true
find . -name '*.cache' -delete 2>/dev/null || true

echo "🔄 Restarting Apache..."
systemctl restart apache2

echo "✅ Git checkout fixed successfully!"
echo "📦 Backup saved to: $BACKUP_DIR"
echo "Current branch: $(git branch --show-current)"
echo "Latest commit: $(git log --oneline -1)" 