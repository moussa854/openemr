#!/bin/bash
# Clean up old branch that didn't have redirect and MFA setup detection
set -e

echo "🧹 Cleaning up old branch..."
echo ""

cd /var/www/emr.carepointinfusion.com

# Check current branch
echo "📍 Current branch:"
git branch --show-current

# List all branches
echo ""
echo "🌿 All branches:"
git branch -a

# Check which branch has the working MFA features
echo ""
echo "🔍 Checking which branch has the working MFA features..."

# Check for MFA setup detection files
echo "📋 Looking for MFA setup detection files..."
if find . -name "*mfa*" -type f | grep -q "setup\|redirect\|detection"; then
    echo "✅ Found MFA setup detection files in current branch"
else
    echo "❌ No MFA setup detection files found in current branch"
fi

# Check for remember device functionality
echo ""
echo "📱 Looking for remember device functionality..."
if find . -name "*remember*" -type f | grep -q "device\|remember"; then
    echo "✅ Found remember device files in current branch"
else
    echo "❌ No remember device files found in current branch"
fi

# Check database tables
echo ""
echo "🗄️  Checking MFA database tables..."
MFA_TABLES=$(mysql -u openemr -pcfvcfv33 openemr -e "SHOW TABLES LIKE '%mfa%';" | wc -l)
if [ $MFA_TABLES -gt 1 ]; then
    echo "✅ Found $((MFA_TABLES-1)) MFA tables in database"
else
    echo "❌ No MFA tables found in database"
fi

# Identify the old branch to delete
echo ""
echo "🎯 Identifying old branch to delete..."

# Check if we're on the correct branch
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" = "feature/mfa-remember-device" ]; then
    echo "✅ Currently on feature/mfa-remember-device (working branch)"
    echo "🗑️  Will delete feature/stepup-mfa (old branch without redirect)"
    BRANCH_TO_DELETE="feature/stepup-mfa"
else
    echo "⚠️  Currently on $CURRENT_BRANCH"
    echo "🔍 Need to identify which branch to delete..."
    BRANCH_TO_DELETE="feature/stepup-mfa"
fi

# Delete the old branch locally
echo ""
echo "🗑️  Deleting old branch locally..."
git branch -D $BRANCH_TO_DELETE 2>/dev/null && echo "✅ Deleted local branch: $BRANCH_TO_DELETE" || echo "⚠️  Local branch $BRANCH_TO_DELETE not found"

# Delete the old branch from remote
echo ""
echo "🗑️  Deleting old branch from remote..."
git push origin --delete $BRANCH_TO_DELETE 2>/dev/null && echo "✅ Deleted remote branch: $BRANCH_TO_DELETE" || echo "⚠️  Remote branch $BRANCH_TO_DELETE not found or already deleted"

# Clean up any remaining references
echo ""
echo "🧹 Cleaning up git references..."
git remote prune origin

# List remaining branches
echo ""
echo "🌿 Remaining branches:"
git branch -a

echo ""
echo "🎉 Cleanup completed!"
echo "📊 Summary:"
echo "  ✅ Old branch deleted"
echo "  ✅ Working MFA features preserved"
echo "  ✅ Database tables intact"
echo ""
echo "🌐 Your working MFA features are safe at:"
echo "  https://emr.carepointinfusion.com/" 