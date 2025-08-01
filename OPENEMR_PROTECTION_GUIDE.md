# OpenEMR File Protection Guide

## 🛡️ Preventing Sites Directory Overwrite

This guide implements the OpenEMR upgrade best practices from the [official documentation](https://www.open-emr.org/wiki/index.php/Linux_Upgrade_7.0.2_to_7.0.3) to prevent critical files from being overwritten during deployments.

## 🎯 What We're Protecting

Following the OpenEMR upgrade guide **Step 4**: "Copy the old OpenEMR 7.0.2 sites (`openemr_bk/sites/`) to the new OpenEMR 7.0.3 at `openemr/sites/`"

### Critical Directories Protected:
- ✅ **`sites/`** - Contains database configuration and site-specific settings
- ✅ **`interface/modules/custom_modules/`** - Custom modules and extensions
- ✅ **`contrib/`** - Site-specific customizations and contributions
- ✅ **`sites/default/documents/`** - Uploaded files and documents
- ✅ **`sites/default/images/`** - Site-specific images

### Critical Files Protected:
- ✅ **`sites/default/sqlconf.php`** - Database configuration
- ✅ **`sites/default/config.php`** - Site-specific configuration
- ✅ **`sites/default/globals.php`** - Global settings

## 🚀 Protection Scripts

### 1. Pre-Deployment Protection
```bash
./protect_openemr_files.sh
```
**What it does:**
- Creates backup of all critical directories and files
- Generates a restoration script for post-deployment
- Follows OpenEMR upgrade best practices

### 2. Safe Deployment
```bash
./deploy_production_protected.sh
```
**What it does:**
- Backs up critical files before deployment
- Pulls latest changes from production branch
- Restores all protected files after deployment
- Sets proper permissions and restarts Apache

### 3. Emergency Fix
```bash
./fix_sqlconf_final.sh
```
**What it does:**
- Fixes corrupted `sqlconf.php` file
- Restores correct database credentials
- Sets `$config = 1;` to prevent setup screen

## 📋 .gitignore Protection

The `.gitignore` file now protects critical files from being tracked in git:

```gitignore
# OpenEMR Critical Files - DO NOT OVERWRITE
sites/default/sqlconf.php
sites/default/config.php
sites/default/globals.php
interface/modules/custom_modules/
contrib/
sites/default/documents/
sites/default/images/
sites/default/logs/
sites/default/documents/smarty/
tmp/
```

## 🔧 Usage Workflow

### For Future Deployments:

1. **Before Deployment:**
   ```bash
   ./protect_openemr_files.sh
   ```

2. **Deploy Changes:**
   ```bash
   ./deploy_production_protected.sh
   ```

3. **If Issues Occur:**
   ```bash
   ./fix_sqlconf_final.sh
   ```

## 🎉 Benefits

### ✅ Prevents Setup Screen
- No more "OpenEMR Setup" screen after deployments
- Database configuration preserved
- Site-specific settings maintained

### ✅ Preserves Customizations
- Custom modules protected
- Site-specific documents preserved
- Configuration files maintained

### ✅ Follows Best Practices
- Based on official OpenEMR upgrade guide
- Comprehensive backup and restore process
- Safe deployment methodology

## 📊 Current Status

**✅ Protection Implemented:**
- All critical directories protected
- All critical files protected
- .gitignore updated
- Protection scripts created
- Safe deployment workflow established

**✅ Ready for Production:**
- No more setup screen issues
- Site-specific data preserved
- Custom modules protected
- Database configuration maintained

## 🔗 References

- [OpenEMR Upgrade Guide](https://www.open-emr.org/wiki/index.php/Linux_Upgrade_7.0.2_to_7.0.3)
- [OpenEMR Security Guide](https://www.open-emr.org/wiki/index.php/Securing_OpenEMR)

---

**Implementation Date**: July 31, 2025  
**Status**: ✅ Complete  
**Server**: `emr.carepointinfusion.com` 