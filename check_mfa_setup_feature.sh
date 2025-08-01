#!/bin/bash
# Check MFA setup detection feature
set -e

echo "🔍 Checking MFA setup detection feature..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Check current branch
echo "📍 Current branch:"
git branch --show-current

# Check for MFA-related files
echo ""
echo "📋 MFA-related files found:"
find . -name "*mfa*" -type f | head -20

# Check for remember device feature
echo ""
echo "🔍 Looking for remember device feature:"
find . -name "*remember*" -type f | head -10

# Check interface files
echo ""
echo "🌐 Interface files with MFA:"
find interface/ -name "*mfa*" -type f | head -10

# Check services
echo ""
echo "⚙️  MFA services:"
find src/Services/ -name "*mfa*" -type f | head -10

# Check for setup detection logic
echo ""
echo "🔍 Looking for MFA setup detection:"
grep -r "mfa.*enabled\|mfa.*setup\|mfa.*configure" interface/ src/ 2>/dev/null | head -10 || echo "No setup detection found"

# Check for redirect logic
echo ""
echo "🔄 Looking for redirect logic:"
grep -r "redirect.*mfa\|mfa.*redirect" interface/ src/ 2>/dev/null | head -10 || echo "No redirect logic found"

# Check database for MFA tables
echo ""
echo "🗄️  MFA database tables:"
mysql -u openemr -pcfvcfv33 openemr -e "SHOW TABLES LIKE '%mfa%';" 2>/dev/null || echo "No MFA tables found"

# Check for remember device table
echo ""
echo "📱 Remember device table:"
mysql -u openemr -pcfvcfv33 openemr -e "SHOW TABLES LIKE '%remember%';" 2>/dev/null || echo "No remember device table found"

echo ""
echo "🎯 Summary:"
echo "The feature/mfa-remember-device branch should contain:"
echo "  - MFA setup detection logic"
echo "  - Automatic redirect to MFA setup"
echo "  - Remember device functionality"
echo "  - Database tables for remembered devices"
echo ""
echo "🌐 Test the feature by:"
echo "  1. Login as a user without MFA enabled"
echo "  2. Try to access sensitive features"
echo "  3. Check if you get redirected to MFA setup" 