#!/bin/bash

# Local Production Pull Script
# Run this directly on the production server (emr.carepointinfusion.com)

set -e  # Exit on any error

echo "🔄 Pulling latest changes from GitHub..."

# Configuration
PROD_PATH="/var/www/emr.carepointinfusion.com"
BRANCH="feature/stepup-mfa"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}📋 Local Production Pull Configuration:${NC}"
echo "  Path: $PROD_PATH"
echo "  Branch: $BRANCH"

# Step 1: Check current status
echo -e "\n${YELLOW}📊 Checking current status...${NC}"
cd $PROD_PATH
git status --short

# Step 2: Fetch latest changes
echo -e "\n${YELLOW}📥 Fetching latest changes from GitHub...${NC}"
git fetch origin

# Step 3: Check what branch we're on
echo -e "\n${YELLOW}🌿 Checking current branch...${NC}"
git branch --show-current

# Step 4: Switch to correct branch if needed
echo -e "\n${YELLOW}🔄 Switching to $BRANCH branch...${NC}"
git checkout $BRANCH

# Step 5: Pull latest changes
echo -e "\n${YELLOW}📥 Pulling latest changes...${NC}"
git pull origin $BRANCH

# Step 6: Show what changed
echo -e "\n${YELLOW}📋 Recent changes...${NC}"
git log --oneline -5

# Step 7: Check for any conflicts or issues
echo -e "\n${YELLOW}🔍 Checking for conflicts...${NC}"
git status

# Step 8: Set proper permissions for any new files
echo -e "\n${YELLOW}🔐 Setting permissions for new files...${NC}"
find . -name 'stepup_mfa_*.php' -exec chown www-data:www-data {} \;
find . -name 'stepup_mfa_*.php' -exec chmod 644 {} \;

# Step 9: Clear caches
echo -e "\n${YELLOW}🧹 Clearing caches...${NC}"
rm -rf tmp/* 2>/dev/null || true
find . -name '*.cache' -delete 2>/dev/null || true

# Step 10: Restart services if needed
echo -e "\n${YELLOW}🔄 Restarting services...${NC}"
systemctl restart apache2

# Step 11: Verify deployment
echo -e "\n${YELLOW}✅ Verifying deployment...${NC}"

# Check if key files exist
echo "  Checking key files..."
ls -la interface/stepup_mfa_*.php
ls -la interface/admin/stepup_mfa_*.php
ls -la src/Services/StepupMfaService.php

# Check database table
echo "  Checking database table..."
mysql -u root -popenemr openemr -e 'DESCRIBE stepup_mfa_verifications;'

# Check for errors
echo "  Checking for errors..."
tail -3 /var/log/apache2/error.log

echo -e "\n${GREEN}🎉 Pull from GitHub completed successfully!${NC}"
echo -e "${GREEN}📊 Pull Summary:${NC}"
echo "  ✅ Latest changes pulled from GitHub"
echo "  ✅ Branch switched to $BRANCH"
echo "  ✅ Permissions updated"
echo "  ✅ Services restarted"
echo "  ✅ Caches cleared"

echo -e "\n${BLUE}📋 Current Status:${NC}"
echo "Current branch: $(git branch --show-current)"
echo "Latest commit: $(git log --oneline -1)"

echo -e "\n${YELLOW}🔗 Access URLs:${NC}"
echo "  MFA Settings: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_settings.php"
echo "  Compliance Report: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_compliance_report.php"
echo "  MFA Verification: https://emr.carepointinfusion.com/interface/stepup_mfa_verify.php"

echo -e "\n${GREEN}🚀 Production server is now up to date with GitHub!${NC}" 