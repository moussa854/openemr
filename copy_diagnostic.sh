#!/bin/bash
# Copy diagnostic script to production server

echo "📋 Copying diagnostic script to production server..."

# Copy the diagnostic script to the server
scp diagnose_server.sh mm@emr.carepointinfusion.com:/tmp/

echo "✅ Diagnostic script copied to /tmp/diagnose_server.sh"
echo ""
echo "🔧 Now run these commands on your production server:"
echo "=================================================="
echo ""
echo "1. SSH to your server:"
echo "   ssh mm@emr.carepointinfusion.com"
echo ""
echo "2. Make the script executable:"
echo "   chmod +x /tmp/diagnose_server.sh"
echo ""
echo "3. Run the diagnostic:"
echo "   sudo /tmp/diagnose_server.sh"
echo ""
echo "4. Share the output with me so I can help identify the issue!"
echo ""
echo "🚀 The diagnostic script will check:"
echo "   - Database configuration and connection"
echo "   - Essential OpenEMR files and tables"
echo "   - Apache and PHP configuration"
echo "   - File permissions"
echo "   - Error logs"
echo "" 