#!/bin/bash

# Apply Database Changes for Step-Up MFA
# Run this on the production server

set -e

echo "🗄️  Applying database changes for Step-Up MFA..."

# Configuration
DB_NAME="openemr"
DB_USER="root"
DB_PASS="openemr"
SQL_FILE="sql/stepup_mfa_verifications.sql"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

cd /var/www/emr.carepointinfusion.com

echo -e "${GREEN}📋 Database Configuration:${NC}"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo "  SQL File: $SQL_FILE"

# Step 1: Check if SQL file exists
echo -e "\n${YELLOW}📄 Checking SQL file...${NC}"
if [ -f "$SQL_FILE" ]; then
    echo "  ✅ SQL file found: $SQL_FILE"
else
    echo -e "${RED}  ❌ SQL file not found: $SQL_FILE${NC}"
    exit 1
fi

# Step 2: Test database connection
echo -e "\n${YELLOW}🔗 Testing database connection...${NC}"
if mysql -u $DB_USER -p$DB_PASS -e "USE $DB_NAME; SELECT 1;" >/dev/null 2>&1; then
    echo "  ✅ Database connection successful"
else
    echo -e "${RED}  ❌ Database connection failed${NC}"
    echo "  Please check your database credentials"
    exit 1
fi

# Step 3: Check if table already exists
echo -e "\n${YELLOW}🔍 Checking if table exists...${NC}"
if mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;" >/dev/null 2>&1; then
    echo "  ✅ Table already exists"
    echo "  📊 Table structure:"
    mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;"
else
    echo "  📝 Table does not exist, creating..."
    
    # Step 4: Apply SQL file
    echo -e "\n${YELLOW}📥 Applying SQL file...${NC}"
    if mysql -u $DB_USER -p$DB_PASS $DB_NAME < $SQL_FILE; then
        echo "  ✅ SQL file applied successfully"
    else
        echo -e "${RED}  ❌ Failed to apply SQL file${NC}"
        exit 1
    fi
fi

# Step 5: Verify table creation
echo -e "\n${YELLOW}✅ Verifying table creation...${NC}"
if mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;" >/dev/null 2>&1; then
    echo "  ✅ Table verified successfully"
    echo "  📊 Final table structure:"
    mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE stepup_mfa_verifications;"
    
    # Check table count
    COUNT=$(mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT COUNT(*) FROM stepup_mfa_verifications;" -s -N)
    echo "  📈 Records in table: $COUNT"
else
    echo -e "${RED}  ❌ Table verification failed${NC}"
    exit 1
fi

# Step 6: Test OpenEMR access
echo -e "\n${YELLOW}🌐 Testing OpenEMR access...${NC}"
echo "  🔗 Testing main page..."
if curl -s -o /dev/null -w "%{http_code}" https://emr.carepointinfusion.com/ | grep -q "200\|302"; then
    echo "  ✅ Main page accessible"
else
    echo "  ⚠️  Main page may have issues"
fi

echo -e "\n${GREEN}🎉 Database changes applied successfully!${NC}"
echo -e "${GREEN}📊 Summary:${NC}"
echo "  ✅ Database connection verified"
echo "  ✅ Table created/verified"
echo "  ✅ OpenEMR should now be accessible"

echo -e "\n${YELLOW}🔗 Test URLs:${NC}"
echo "  Main: https://emr.carepointinfusion.com/"
echo "  MFA Settings: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_settings.php"
echo "  Compliance Report: https://emr.carepointinfusion.com/interface/admin/stepup_mfa_compliance_report.php"

echo -e "\n${GREEN}🚀 Your OpenEMR should now be working!${NC}" 