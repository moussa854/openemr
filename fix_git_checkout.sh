#!/bin/bash

# Fix Git Checkout Conflict
# Run this on the development server

set -e  # Exit on any error

echo "🔧 Fixing git checkout conflict..."

# Configuration
DEV_PATH="/var/www/openemr"
BACKUP_DIR="/tmp/openemr_backup_$(date +%Y%m%d_%H%M%S)"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}📋 Fix Configuration:${NC}"
echo "  Path: $DEV_PATH"
echo "  Backup: $BACKUP_DIR"

# Step 1: Create backup
echo -e "\n${YELLOW}📦 Creating backup of current files...${NC}"
mkdir -p $BACKUP_DIR
cd $DEV_PATH
cp -r * $BACKUP_DIR/ 2>/dev/null || true

# Step 2: Remove untracked files (except important ones)
echo -e "\n${YELLOW}🧹 Cleaning untracked files...${NC}"
cd $DEV_PATH

# Keep important files
mkdir -p /tmp/important_files
cp -r interface/ /tmp/important_files/ 2>/dev/null || true
cp -r src/ /tmp/important_files/ 2>/dev/null || true
cp -r sql/ /tmp/important_files/ 2>/dev/null || true

# Remove all files except .git
find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} \; 2>/dev/null || true

# Step 3: Force checkout
echo -e "\n${YELLOW}🔄 Force checking out feature branch...${NC}"
git checkout -f feature/stepup-mfa

# Step 4: Restore important files if they don't exist
echo -e "\n${YELLOW}📥 Restoring important files...${NC}"
if [ ! -d "interface" ]; then
    cp -r /tmp/important_files/interface/ . 2>/dev/null || true
fi
if [ ! -d "src" ]; then
    cp -r /tmp/important_files/src/ . 2>/dev/null || true
fi
if [ ! -d "sql" ]; then
    cp -r /tmp/important_files/sql/ . 2>/dev/null || true
fi

# Step 5: Set permissions
echo -e "\n${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data .
find . -name '*.php' -exec chmod 644 {} \;

# Step 6: Verify
echo -e "\n${YELLOW}✅ Verifying setup...${NC}"
echo "Current branch: $(git branch --show-current)"
echo "Git status:"
git status --short

echo -e "\n${GREEN}🎉 Git checkout fixed successfully!${NC}"
echo -e "${GREEN}📊 Fix Summary:${NC}"
echo "  ✅ Backup created in $BACKUP_DIR"
echo "  ✅ Untracked files cleaned"
echo "  ✅ Feature branch checked out"
echo "  ✅ Important files restored"
echo "  ✅ Permissions set"

echo -e "\n${YELLOW}🔗 Next Steps:${NC}"
echo "  1. Run: ./server_pull.sh"
echo "  2. Test the Step-Up MFA system"
echo "  3. Access: http://192.168.30.116/interface/admin/stepup_mfa_settings.php"

echo -e "\n${GREEN}🚀 Development server is now ready!${NC}" 