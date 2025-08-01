#!/bin/bash

# Setup Git Repository on Development Server
# Target: 192.168.30.116

set -e  # Exit on any error

echo "🔧 Setting up Git repository on development server..."

# Configuration
DEV_SERVER="root@192.168.30.116"
DEV_PATH="/var/www/openemr"
GITHUB_REPO="https://github.com/moussa854/openemr.git"
BRANCH="feature/stepup-mfa"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}📋 Setup Configuration:${NC}"
echo "  Server: $DEV_SERVER"
echo "  Path: $DEV_PATH"
echo "  Repository: $GITHUB_REPO"
echo "  Branch: $BRANCH"

# Step 1: Backup current files
echo -e "\n${YELLOW}📦 Creating backup of current files...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && tar -czf /tmp/openemr_backup_$(date +%Y%m%d_%H%M%S).tar.gz ."

# Step 2: Initialize git repository
echo -e "\n${YELLOW}🔧 Initializing git repository...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && git init"

# Step 3: Add remote origin
echo -e "\n${YELLOW}🔗 Adding GitHub remote...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && git remote add origin $GITHUB_REPO"

# Step 4: Fetch all branches
echo -e "\n${YELLOW}📥 Fetching all branches from GitHub...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && git fetch origin"

# Step 5: Checkout the feature branch
echo -e "\n${YELLOW}🌿 Checking out $BRANCH branch...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && git checkout -b $BRANCH origin/$BRANCH"

# Step 6: Set up git configuration
echo -e "\n${YELLOW}⚙️  Setting up git configuration...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && git config user.name 'Development Server' && git config user.email 'dev@openemr.local'"

# Step 7: Verify setup
echo -e "\n${YELLOW}✅ Verifying git setup...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && echo 'Current branch:' && git branch --show-current && echo 'Remote branches:' && git branch -r"

# Step 8: Test pull
echo -e "\n${YELLOW}🧪 Testing git pull...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && git pull origin $BRANCH"

# Step 9: Set proper permissions
echo -e "\n${YELLOW}🔐 Setting proper permissions...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && chown -R www-data:www-data . && find . -name '*.php' -exec chmod 644 {} \;"

echo -e "\n${GREEN}🎉 Git repository setup completed successfully!${NC}"
echo -e "${GREEN}📊 Setup Summary:${NC}"
echo "  ✅ Git repository initialized"
echo "  ✅ GitHub remote added"
echo "  ✅ Feature branch checked out"
echo "  ✅ Git configuration set"
echo "  ✅ Permissions updated"

echo -e "\n${YELLOW}🔗 Next Steps:${NC}"
echo "  1. Run: cd /var/www/openemr && ./server_pull.sh"
echo "  2. Test the Step-Up MFA system"
echo "  3. Access: http://192.168.30.116/interface/admin/stepup_mfa_settings.php"

echo -e "\n${GREEN}🚀 Development server is now ready for git operations!${NC}" 