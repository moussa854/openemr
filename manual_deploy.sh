#!/bin/bash

# Manual Step-Up MFA Deployment Script
# Run this directly on the development server (192.168.30.116)

set -e  # Exit on any error

echo "🚀 Manual Step-Up MFA deployment..."

# Configuration
DEV_PATH="/var/www/openemr"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}📋 Deployment Configuration:${NC}"
echo "  Path: $DEV_PATH"

# Step 1: Pull latest changes
echo -e "\n${YELLOW}📥 Pulling latest changes from git...${NC}"
cd $DEV_PATH
git fetch origin
git checkout feature/stepup-mfa
git pull origin feature/stepup-mfa

# Step 2: Apply database changes
echo -e "\n${YELLOW}🗄️  Applying database changes...${NC}"
mysql -u root -popenemr openemr < sql/stepup_mfa_verifications.sql

# Step 3: Set proper permissions
echo -e "\n${YELLOW}🔐 Setting file permissions...${NC}"
chown -R www-data:www-data interface/stepup_mfa_*.php
chown -R www-data:www-data interface/admin/stepup_mfa_*.php
chown -R www-data:www-data src/Services/StepupMfaService.php
chmod 644 interface/stepup_mfa_*.php
chmod 644 interface/admin/stepup_mfa_*.php
chmod 644 src/Services/StepupMfaService.php

# Step 4: Clear caches
echo -e "\n${YELLOW}🧹 Clearing caches...${NC}"
rm -rf tmp/* 2>/dev/null || true
find . -name '*.cache' -delete 2>/dev/null || true

# Step 5: Restart services
echo -e "\n${YELLOW}🔄 Restarting services...${NC}"
systemctl restart apache2
systemctl restart mariadb

# Step 6: Verify deployment
echo -e "\n${YELLOW}✅ Verifying deployment...${NC}"

# Check if files exist
echo "  Checking deployed files..."
ls -la interface/stepup_mfa_*.php
ls -la interface/admin/stepup_mfa_*.php
ls -la src/Services/StepupMfaService.php

# Check database table
echo "  Checking database table..."
mysql -u root -popenemr openemr -e 'DESCRIBE stepup_mfa_verifications;'

# Check Apache error log
echo "  Checking for errors..."
tail -5 /var/log/apache2/error.log

echo -e "\n${GREEN}🎉 Deployment completed successfully!${NC}"
echo -e "${GREEN}📊 Deployment Summary:${NC}"
echo "  ✅ Files deployed to $DEV_PATH"
echo "  ✅ Database schema applied"
echo "  ✅ Permissions set correctly"
echo "  ✅ Services restarted"

echo -e "\n${YELLOW}🔗 Access URLs:${NC}"
echo "  MFA Settings: http://192.168.30.116/interface/admin/stepup_mfa_settings.php"
echo "  Compliance Report: http://192.168.30.116/interface/admin/stepup_mfa_compliance_report.php"
echo "  MFA Verification: http://192.168.30.116/interface/stepup_mfa_verify.php"

echo -e "\n${GREEN}🚀 Step-Up MFA system is now live on the development server!${NC}" 