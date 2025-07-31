#!/bin/bash

# Step-Up MFA Development Server Deployment Script
# Target: 192.168.30.116

set -e  # Exit on any error

echo "🚀 Starting Step-Up MFA deployment to development server..."

# Configuration
DEV_SERVER="root@192.168.30.116"
DEV_PATH="/var/www/openemr"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}📋 Deployment Configuration:${NC}"
echo "  Server: $DEV_SERVER"
echo "  Path: $DEV_PATH"

# Step 1: Pull latest changes
echo -e "\n${YELLOW}📥 Pulling latest changes from git...${NC}"
git pull origin feature/stepup-mfa

# Step 2: Create necessary directories
echo -e "\n${YELLOW}📁 Creating necessary directories...${NC}"
ssh $DEV_SERVER "mkdir -p $DEV_PATH/interface/admin"
ssh $DEV_SERVER "mkdir -p $DEV_PATH/src/Services"
ssh $DEV_SERVER "mkdir -p $DEV_PATH/sql"

# Step 3: Deploy files
echo -e "\n${YELLOW}📤 Deploying files to development server...${NC}"

# Core MFA files
echo "  Deploying MFA core files..."
scp interface/stepup_mfa_verify.php $DEV_SERVER:$DEV_PATH/interface/
scp interface/stepup_mfa_forms_interceptor.php $DEV_SERVER:$DEV_PATH/interface/
scp interface/stepup_mfa_success.php $DEV_SERVER:$DEV_PATH/interface/

# Admin files
echo "  Deploying admin files..."
scp interface/admin/stepup_mfa_settings.php $DEV_SERVER:$DEV_PATH/interface/admin/
scp interface/admin/stepup_mfa_compliance_report.php $DEV_SERVER:$DEV_PATH/interface/admin/
scp interface/admin/stepup_mfa_direct.php $DEV_SERVER:$DEV_PATH/interface/admin/

# Service files
echo "  Deploying service files..."
scp src/Services/StepupMfaService.php $DEV_SERVER:$DEV_PATH/src/Services/

# SQL files
echo "  Deploying database schema..."
scp sql/stepup_mfa_verifications.sql $DEV_SERVER:$DEV_PATH/sql/

# Step 4: Apply database changes
echo -e "\n${YELLOW}🗄️  Applying database changes...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && mysql -u root -popenemr openemr < sql/stepup_mfa_verifications.sql"

# Step 5: Set proper permissions
echo -e "\n${YELLOW}🔐 Setting file permissions...${NC}"
ssh $DEV_SERVER "chown -R www-data:www-data $DEV_PATH/interface/stepup_mfa_*.php"
ssh $DEV_SERVER "chown -R www-data:www-data $DEV_PATH/interface/admin/stepup_mfa_*.php"
ssh $DEV_SERVER "chown -R www-data:www-data $DEV_PATH/src/Services/StepupMfaService.php"
ssh $DEV_SERVER "chmod 644 $DEV_PATH/interface/stepup_mfa_*.php"
ssh $DEV_SERVER "chmod 644 $DEV_PATH/interface/admin/stepup_mfa_*.php"
ssh $DEV_SERVER "chmod 644 $DEV_PATH/src/Services/StepupMfaService.php"

# Step 6: Clear caches
echo -e "\n${YELLOW}🧹 Clearing caches...${NC}"
ssh $DEV_SERVER "cd $DEV_PATH && rm -rf tmp/* 2>/dev/null || true"
ssh $DEV_SERVER "cd $DEV_PATH && find . -name '*.cache' -delete 2>/dev/null || true"

# Step 7: Restart services
echo -e "\n${YELLOW}🔄 Restarting services...${NC}"
ssh $DEV_SERVER "systemctl restart apache2"
ssh $DEV_SERVER "systemctl restart mariadb"

# Step 8: Verify deployment
echo -e "\n${YELLOW}✅ Verifying deployment...${NC}"

# Check if files exist
echo "  Checking deployed files..."
ssh $DEV_SERVER "ls -la $DEV_PATH/interface/stepup_mfa_*.php"
ssh $DEV_SERVER "ls -la $DEV_PATH/interface/admin/stepup_mfa_*.php"
ssh $DEV_SERVER "ls -la $DEV_PATH/src/Services/StepupMfaService.php"

# Check database table
echo "  Checking database table..."
ssh $DEV_SERVER "cd $DEV_PATH && mysql -u root -popenemr openemr -e 'DESCRIBE stepup_mfa_verifications;'"

# Check Apache error log
echo "  Checking for errors..."
ssh $DEV_SERVER "tail -5 /var/log/apache2/error.log"

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