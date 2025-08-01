#!/bin/bash
# Disable MFA for admin user from database
set -e

echo "🔧 Disabling MFA for admin user..."
echo "User: admin"
echo "Password: bygO1!59R^1L"
echo ""

cd /var/www/emr.carepointinfusion.com

# Database credentials
DB_USER="openemr"
DB_PASS="cfvcfv33"
DB_NAME="openemr"

echo "📊 Checking current MFA status for admin user..."

# Check if user exists and get their ID
USER_ID=$(mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT id FROM users WHERE username = 'admin';" | tail -1)

if [ -z "$USER_ID" ] || [ "$USER_ID" = "id" ]; then
    echo "❌ Admin user not found in database"
    exit 1
fi

echo "✅ Admin user found with ID: $USER_ID"

# Check current MFA settings
echo "📋 Current MFA settings for admin:"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT username, mfa_totp_secret, mfa_u2f_registrations FROM users WHERE username = 'admin';"

echo ""
echo "🔧 Disabling MFA for admin user..."

# Disable MFA by clearing the TOTP secret and U2F registrations
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
UPDATE users 
SET mfa_totp_secret = NULL, 
    mfa_u2f_registrations = NULL 
WHERE username = 'admin';
"

echo "✅ MFA disabled for admin user"

# Verify the change
echo ""
echo "📋 Updated MFA settings for admin:"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT username, mfa_totp_secret, mfa_u2f_registrations FROM users WHERE username = 'admin';"

echo ""
echo "🎉 MFA has been disabled for admin user!"
echo "You can now login without MFA using:"
echo "  Username: admin"
echo "  Password: bygO1!59R^1L"
echo ""
echo "🌐 Login at: https://emr.carepointinfusion.com/interface/login/login.php" 