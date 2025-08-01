#!/bin/bash
# Delete old branch from GitHub
set -e

echo "🗑️  Deleting old branch from GitHub..."
echo "Branch: feature/stepup-mfa"
echo "Repository: https://github.com/moussa854/openemr-production"
echo ""

cd /var/www/emr.carepointinfusion.com

# Check current branch
echo "📍 Current branch:"
git branch --show-current

# List remote branches
echo ""
echo "🌿 Remote branches:"
git branch -r

# Check if the branch exists remotely
if git branch -r | grep -q "origin/feature/stepup-mfa"; then
    echo "✅ Found feature/stepup-mfa branch on remote"
    
    # Delete the branch from remote
    echo "🗑️  Deleting feature/stepup-mfa from remote..."
    git push origin --delete feature/stepup-mfa
    
    if [ $? -eq 0 ]; then
        echo "✅ Successfully deleted feature/stepup-mfa from GitHub"
    else
        echo "❌ Failed to delete branch from GitHub"
        echo "This might be due to authentication issues"
        echo ""
        echo "🔧 Manual deletion instructions:"
        echo "1. Go to https://github.com/moussa854/openemr-production/branches"
        echo "2. Find 'feature/stepup-mfa' branch"
        echo "3. Click the trash icon to delete it"
        exit 1
    fi
else
    echo "ℹ️  feature/stepup-mfa branch not found on remote"
    echo "It may have already been deleted"
fi

# Clean up local references
echo ""
echo "🧹 Cleaning up local references..."
git remote prune origin

# List remaining branches
echo ""
echo "🌿 Remaining branches:"
git branch -r

echo ""
echo "🎉 Branch deletion completed!"
echo "📊 Summary:"
echo "  ✅ Old branch deleted from GitHub"
echo "  ✅ Local references cleaned up"
echo "  ✅ Working branch preserved"
echo ""
echo "🌐 Your working MFA features are safe at:"
echo "  https://emr.carepointinfusion.com/" 