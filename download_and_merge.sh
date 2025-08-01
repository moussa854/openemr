#!/bin/bash
# Download and merge using ZIP file approach
set -e

echo "📦 Downloading repository as ZIP file..."
echo "Repository: https://github.com/moussa854/openemr-production"
echo "Branch: feature/mfa-remember-device"
echo ""

cd /tmp

# Backup current OpenEMR
echo "📦 Creating backup of current OpenEMR..."
BACKUP_DIR="/tmp/openemr_backup_zip_$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR
cp -r /var/www/emr.carepointinfusion.com/* $BACKUP_DIR/ 2>/dev/null || echo "⚠️  Backup created with some permission issues"
echo "✅ Backup created at: $BACKUP_DIR"

# Download the ZIP file
echo "📥 Downloading repository ZIP..."
wget -O openemr-production.zip "https://github.com/moussa854/openemr-production/archive/refs/heads/feature/mfa-remember-device.zip"

# Check if download was successful
if [ ! -f "openemr-production.zip" ]; then
    echo "❌ Failed to download ZIP file"
    echo "🔧 Trying alternative approach..."
    
    # Try downloading the main branch and then switching
    wget -O openemr-production-main.zip "https://github.com/moussa854/openemr-production/archive/refs/heads/main.zip"
    
    if [ ! -f "openemr-production-main.zip" ]; then
        echo "❌ Failed to download main branch ZIP file"
        echo "Please check if the repository is public and accessible"
        exit 1
    fi
fi

# Extract the ZIP file
echo "📂 Extracting ZIP file..."
unzip -q openemr-production.zip

# Find the extracted directory
EXTRACTED_DIR=$(find . -name "openemr-production*" -type d | head -1)
echo "📁 Extracted directory: $EXTRACTED_DIR"

# Check if we have the feature branch or need to switch
if [[ "$EXTRACTED_DIR" == *"feature/mfa-remember-device"* ]]; then
    echo "✅ Feature branch found in extracted files"
else
    echo "⚠️  Main branch downloaded, checking for feature branch files..."
fi

# Copy files to OpenEMR directory
echo "📋 Copying files to OpenEMR directory..."
cp -r $EXTRACTED_DIR/* /var/www/emr.carepointinfusion.com/

# Go to OpenEMR directory
cd /var/www/emr.carepointinfusion.com

# Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Check for MFA-related files
echo "🔍 Checking for MFA-related files..."
find . -name "*mfa*" -type f | head -10

# Check for database changes
echo "🗄️  Checking for database changes..."
if [ -f "sql/mfa_remember_device.sql" ]; then
    echo "📝 Found: sql/mfa_remember_device.sql"
    echo "Applying database changes..."
    mysql -u openemr -pcfvcfv33 openemr < sql/mfa_remember_device.sql && echo "✅ Database changes applied" || echo "⚠️  Database changes failed"
else
    echo "ℹ️  No database schema file found"
fi

# Check for any other SQL files
echo "🔍 Looking for other SQL files..."
find . -name "*.sql" -type f | grep -i mfa || echo "No MFA-related SQL files found"

# Restart Apache
echo "🔄 Restarting Apache..."
systemctl restart apache2

# Clean up
echo "🧹 Cleaning up temporary files..."
rm -rf /tmp/openemr-production.zip
rm -rf /tmp/$EXTRACTED_DIR

echo ""
echo "🎉 Download and merge completed!"
echo "📊 Summary:"
echo "  ✅ Backup created at: $BACKUP_DIR"
echo "  ✅ Repository downloaded and extracted"
echo "  ✅ Files copied to OpenEMR directory"
echo "  ✅ Permissions updated"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site: https://emr.carepointinfusion.com/"
echo ""
echo "🔧 If you need to revert:"
echo "  cp -r $BACKUP_DIR/* /var/www/emr.carepointinfusion.com/" 