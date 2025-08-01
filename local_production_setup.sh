#!/bin/bash

# Local Production Setup Script
# Run this directly on the production server (emr.carepointinfusion.com)

set -e  # Exit on any error

echo "🔧 Setting up Git repository on production server..."

# Configuration
PROD_PATH="/var/www/emr.carepointinfusion.com"
GITHUB_REPO="https://github.com/moussa854/openemr.git"
BRANCH="feature/stepup-mfa"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}📋 Local Production Setup Configuration:${NC}"
echo "  Path: $PROD_PATH"
echo "  Repository: $GITHUB_REPO"
echo "  Branch: $BRANCH"

# Step 1: Backup current files
echo -e "\n${YELLOW}📦 Creating backup of current files...${NC}"
cd $PROD_PATH
tar -czf /tmp/production_backup_$(date +%Y%m%d_%H%M%S).tar.gz .

# Step 2: Check if git repository exists
echo -e "\n${YELLOW}🔍 Checking git repository status...${NC}"
if [ -d ".git" ]; then
    echo "  ✅ Git repository already exists"
    
    # Check if remote origin exists
    if git remote get-url origin >/dev/null 2>&1; then
        echo "  ✅ Remote origin already exists"
        CURRENT_REMOTE=$(git remote get-url origin)
        echo "  📍 Current remote: $CURRENT_REMOTE"
        
        # Update remote if different
        if [ "$CURRENT_REMOTE" != "$GITHUB_REPO" ]; then
            echo "  🔄 Updating remote origin..."
            git remote set-url origin $GITHUB_REPO
        fi
    else
        echo "  🔗 Adding remote origin..."
        git remote add origin $GITHUB_REPO
    fi
else
    echo "  🔧 Initializing new git repository..."
    git init
    echo "  🔗 Adding remote origin..."
    git remote add origin $GITHUB_REPO
fi

# Step 3: Fetch all branches
echo -e "\n${YELLOW}📥 Fetching all branches from GitHub...${NC}"
git fetch origin

# Step 4: Check current branch and switch if needed
echo -e "\n${YELLOW}🌿 Checking current branch...${NC}"
CURRENT_BRANCH=$(git branch --show-current 2>/dev/null || echo "none")
echo "  📍 Current branch: $CURRENT_BRANCH"

if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    echo "  🔄 Switching to $BRANCH branch..."
    
    # Check if branch exists locally
    if git show-ref --verify --quiet refs/heads/$BRANCH; then
        echo "  ✅ Local branch exists, switching..."
        git checkout $BRANCH
    else
        echo "  🌿 Creating local branch from remote..."
        # Handle untracked files that would be overwritten
        if git checkout -b $BRANCH origin/$BRANCH 2>&1 | grep -q "untracked working tree files would be overwritten"; then
            echo "  ⚠️  Untracked files conflict detected. Running cleanup..."
            echo "  📦 Creating backup of important files..."
            BACKUP_DIR="/tmp/openemr_backup_$(date +%Y%m%d_%H%M%S)"
            mkdir -p $BACKUP_DIR
            cp -r interface/admin/ $BACKUP_DIR/ 2>/dev/null || true
            cp -r src/Services/ $BACKUP_DIR/ 2>/dev/null || true
            cp -r sql/ $BACKUP_DIR/ 2>/dev/null || true
            cp *.php $BACKUP_DIR/ 2>/dev/null || true
            
            echo "  🧹 Removing untracked files..."
            git clean -fd
            
            echo "  🔄 Force creating branch..."
            git checkout -b $BRANCH origin/$BRANCH
            
            echo "  📦 Backup saved to: $BACKUP_DIR"
        else
            git checkout -b $BRANCH origin/$BRANCH
        fi
    fi
else
    echo "  ✅ Already on correct branch"
fi

# Step 5: Set up git configuration
echo -e "\n${YELLOW}⚙️  Setting up git configuration...${NC}"
git config user.name "Production Server"
git config user.email "prod@carepointinfusion.com"

# Step 6: Verify setup
echo -e "\n${YELLOW}✅ Verifying git setup...${NC}"
echo "Current branch: $(git branch --show-current)"
echo "Remote branches:"
git branch -r

# Step 7: Test pull
echo -e "\n${YELLOW}🧪 Testing git pull...${NC}"
git pull origin $BRANCH

# Step 8: Set proper permissions
echo -e "\n${YELLOW}🔐 Setting proper permissions...${NC}"
chown -R www-data:www-data .
find . -name '*.php' -exec chmod 644 {} \;

# Step 9: Clear caches
echo -e "\n${YELLOW}🧹 Clearing caches...${NC}"
rm -rf tmp/* 2>/dev/null || true
find . -name '*.cache' -delete 2>/dev/null || true

# Step 10: Restart services
echo -e "\n${YELLOW}🔄 Restarting services...${NC}"
systemctl restart apache2

# Step 11: Verify deployment
echo -e "\n${YELLOW}✅ Verifying deployment...${NC}"

# Check if key files exist
echo "  Checking key files..."
ls -la interface/stepup_mfa_*.php 2>/dev/null || echo "    ⚠️  No stepup_mfa_*.php files found in interface/"
ls -la interface/admin/stepup_mfa_*.php 2>/dev/null || echo "    ⚠️  No stepup_mfa_*.php files found in interface/admin/"
ls -la src/Services/StepupMfaService.php 2>/dev/null || echo "    ⚠️  StepupMfaService.php not found"

# Check database table
echo "  Checking database table..."
mysql -u root -popenemr openemr -e 'DESCRIBE stepup_mfa_verifications;' 2>/dev/null || echo "    ⚠️  Database table not found or error accessing database"

# Check for errors
echo "  Checking for errors..."
tail -3 /var/log/apache2/error.log 2>/dev/null || echo "    ⚠️  Could not read error log"

echo -e "\n${GREEN}🎉 Git repository setup completed successfully!${NC}"
echo -e "${GREEN}📊 Setup Summary:${NC}"
echo "  ✅ Git repository initialized/updated"
echo "  ✅ GitHub remote configured"
echo "  ✅ Feature branch checked out"
echo "  ✅ Git configuration set"
echo "  ✅ Permissions updated"
echo "  ✅ Services restarted"

echo -e "\n${BLUE}📋 Current Status:${NC}"
echo "Current branch: $(git branch --show-current)"
echo "Latest commit: $(git log --oneline -1)"

echo -e "\n${YELLOW}🔗 Access URLs:${NC}"
echo "  MFA Settings: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_settings.php"
echo "  Compliance Report: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_compliance_report.php"
echo "  MFA Verification: https://emr.carepointinfusion.com/interface/stepup_mfa_verify.php"

echo -e "\n${GREEN}🚀 Production server is now ready for git operations!${NC}" 