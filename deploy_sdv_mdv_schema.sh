#!/bin/bash
# Deploy SDV/MDV Database Schema Changes

echo "Deploying SDV/MDV Database Schema Changes..."

# Connect to the database and run the schema changes
mysql -u openemr -pcfvcfv33 openemr < /tmp/enhanced_inventory_schema.sql

if [ $? -eq 0 ]; then
    echo "✅ Database schema changes applied successfully!"
    echo ""
    echo "📊 Schema Changes Applied:"
    echo "  - Added vial_type column to drugs table"
    echo "  - Added partial usage tracking to drug_inventory table"
    echo "  - Created ndc_vial_type_lookup table"
    echo "  - Created vial_usage_history table"
    echo "  - Added necessary indexes"
    echo "  - Pre-populated common vial types"
    echo ""
    echo "🎯 Next Steps:"
    echo "  1. Test the inventory module with new vial type features"
    echo "  2. Configure existing drugs with proper vial types"
    echo "  3. Test infusion form integration with inventory tracking"
    echo ""
else
    echo "❌ Error applying database schema changes!"
    echo "Please check the MySQL error log for details."
    exit 1
fi
