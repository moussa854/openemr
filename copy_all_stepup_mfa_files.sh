#!/bin/bash
# Copy all Step-Up MFA files to server
set -e

echo "🔧 Copying all Step-Up MFA files to server..."
echo ""

# Create backup
echo "📦 Creating backup of current files..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && sudo tar -czf /tmp/stepup_mfa_backup_$(date +%Y%m%d_%H%M%S).tar.gz interface/admin/stepup_mfa_*.php interface/stepup_mfa_*.php src/Services/StepupMfaService.php sql/stepup_mfa_*.sql 2>/dev/null || true"

# Copy interface files
echo "📤 Copying interface files..."
scp interface/stepup_mfa_verify.php mm@emr.carepointinfusion.com:/tmp/
scp interface/stepup_mfa_success.php mm@emr.carepointinfusion.com:/tmp/
scp interface/stepup_mfa_forms_interceptor.php mm@emr.carepointinfusion.com:/tmp/

# Copy admin files
echo "📤 Copying admin files..."
scp interface/admin/stepup_mfa_direct.php mm@emr.carepointinfusion.com:/tmp/
scp interface/admin/stepup_mfa_compliance_report.php mm@emr.carepointinfusion.com:/tmp/

# Copy service file
echo "📤 Copying service file..."
scp src/Services/StepupMfaService.php mm@emr.carepointinfusion.com:/tmp/

# Copy SQL files
echo "📤 Copying SQL files..."
scp sql/stepup_mfa_verifications.sql mm@emr.carepointinfusion.com:/tmp/
scp sql/stepup_mfa_globals.sql mm@emr.carepointinfusion.com:/tmp/

# Move files to correct locations on server
echo "📁 Moving files to correct locations..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && sudo cp /tmp/stepup_mfa_verify.php interface/ && sudo cp /tmp/stepup_mfa_success.php interface/ && sudo cp /tmp/stepup_mfa_forms_interceptor.php interface/ && sudo cp /tmp/stepup_mfa_direct.php interface/admin/ && sudo cp /tmp/stepup_mfa_compliance_report.php interface/admin/ && sudo cp /tmp/StepupMfaService.php src/Services/ && sudo cp /tmp/stepup_mfa_verifications.sql sql/ && sudo cp /tmp/stepup_mfa_globals.sql sql/"

# Set permissions
echo "🔐 Setting permissions..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && sudo chown www-data:www-data interface/stepup_mfa_*.php interface/admin/stepup_mfa_*.php src/Services/StepupMfaService.php sql/stepup_mfa_*.sql && sudo chmod 644 interface/stepup_mfa_*.php interface/admin/stepup_mfa_*.php src/Services/StepupMfaService.php sql/stepup_mfa_*.sql"

# Apply database changes
echo "🗄️ Applying database changes..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && mysql -u root -popenemr openemr < sql/stepup_mfa_verifications.sql && mysql -u root -popenemr openemr < sql/stepup_mfa_globals.sql"

# Restart Apache
echo "🔄 Restarting Apache..."
ssh mm@emr.carepointinfusion.com "sudo systemctl restart apache2"

# Verify files
echo ""
echo "✅ Verifying files..."
ssh mm@emr.carepointinfusion.com "cd /var/www/emr.carepointinfusion.com && echo 'Interface files:' && ls -la interface/stepup_mfa_*.php && echo 'Admin files:' && ls -la interface/admin/stepup_mfa_*.php && echo 'Service file:' && ls -la src/Services/StepupMfaService.php && echo 'SQL files:' && ls -la sql/stepup_mfa_*.sql"

# Test the page
echo ""
echo "🌐 Testing Step-Up MFA Settings page..."
ssh mm@emr.carepointinfusion.com "curl -s -o /dev/null -w '%{http_code}' https://emr.carepointinfusion.com/interface/admin/stepup_mfa_settings.php"

echo ""
echo "🎉 All Step-Up MFA files copied and configured!"
echo "📊 Summary:"
echo "  ✅ All interface files copied"
echo "  ✅ All admin files copied"
echo "  ✅ Service file copied"
echo "  ✅ SQL files copied and applied"
echo "  ✅ Permissions set"
echo "  ✅ Apache restarted"
echo ""
echo "🌐 Test your site now:"
echo "  https://emr.carepointinfusion.com/interface/admin/stepup_mfa_settings.php" 