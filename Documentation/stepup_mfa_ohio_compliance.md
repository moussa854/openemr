# Enhanced Step-Up MFA for Ohio Compliance

## Overview

This enhanced Step-Up MFA (Multi-Factor Authentication) feature provides comprehensive protection for sensitive encounters, particularly those involving controlled substances like Ketamine Infusion, in compliance with Ohio Board of Pharmacy regulations.

## Key Features

### 🔐 Enhanced Security
- **Step-Up MFA**: Requires additional authentication when accessing sensitive encounters
- **Controlled Substance Detection**: Automatically detects encounters with controlled substances
- **Session-Based Verification**: Configurable grace period per patient
- **Comprehensive Logging**: Detailed audit trails for compliance

### 📋 Ohio Compliance
- **Ohio Board of Pharmacy Compliance**: Meets regulatory requirements for controlled substances
- **Enhanced Audit Logging**: Detailed logging for regulatory audits
- **Controlled Substance Detection**: Automatic detection of encounters with controlled substances
- **Compliance Reporting**: Built-in reporting for regulatory compliance

## Installation

### 1. Database Setup
```bash
cd openemr # project root
git checkout feature/stepup-mfa
mysql openemr < sql/stepup_mfa_globals.sql
```

### 2. File Deployment
Sync/rsync the branch to your server. No database changes beyond the `globals` table entries.

## Configuration

### Admin Settings
Navigate to **Administration → Step-Up MFA Settings**

#### Basic Settings
- **Enable Step-Up MFA**: Master switch for the feature
- **Sensitive Appointment Categories**: Select categories requiring MFA (e.g., Ketamine Infusion)
- **MFA Grace Period**: How long to remember verification per patient (default: 900 seconds = 15 minutes)

#### Ohio Compliance Features
- **Enable Controlled Substance Detection**: Automatically detect encounters with controlled substances
- **Enhanced Ohio Compliance Logging**: Detailed logging for regulatory compliance

### Global Settings
The following settings are stored in the `globals` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `stepup_mfa_enabled` | `0` | Master switch |
| `stepup_mfa_categories` | `''` | Comma-separated list of sensitive category IDs |
| `stepup_mfa_timeout` | `900` | Grace period in seconds |
| `stepup_mfa_check_controlled_substances` | `0` | Enable controlled substance detection |
| `stepup_mfa_ohio_compliance_logging` | `0` | Enable enhanced compliance logging |

## Workflow

### 1. User Access Flow
1. User logs in normally (standard MFA at login still applies if enabled)
2. User clicks on an encounter with a sensitive category (e.g., Ketamine Infusion)
3. System detects sensitivity through:
   - Category-based detection
   - Sensitivity level detection
   - Medication keyword detection
   - Controlled substance detection
4. If MFA verification is required and not recently completed:
   - Redirect to verification page
   - User enters 6-digit TOTP code
   - On success: access granted and session marked as verified
   - On failure: error message and retry

### 2. Detection Methods

#### Category-Based Detection
- Checks if encounter category is in the sensitive categories list
- Most reliable method
- Configured via admin settings

#### Sensitivity Level Detection
- Checks encounter sensitivity field
- Triggers for 'high' or 'restricted' sensitivity levels

#### Medication Keyword Detection
- Scans encounter reason field for sensitive medication keywords:
  - ketamine, infusion, controlled, schedule, narcotic
  - opioid, benzodiazepine, stimulant, sedative, anesthetic

#### Controlled Substance Detection
- Scans prescriptions table for controlled substances
- Detects medications containing: ketamine, opioid, benzodiazepine, stimulant, narcotic

## Ohio Compliance Features

### Regulatory Compliance
This feature helps comply with Ohio Board of Pharmacy regulations:

1. **Access Control**: Requires MFA for sensitive encounters
2. **Audit Logging**: Comprehensive logging of all access attempts
3. **Session Management**: Configurable timeout periods
4. **Controlled Substance Protection**: Special handling for controlled substances

### Compliance Reporting
Access **Administration → Step-Up MFA Settings → View Compliance Report** for:
- Sensitive encounter statistics
- Access attempt logs
- Configuration summary
- Date range filtering
- Patient-specific reports

## Security Features

### Session Management
- Verification stored in PHP session (not persisted)
- Configurable timeout per patient
- Automatic cleanup on session expiration

### Audit Logging
Events logged via `EventAuditLogger`:
- `MFA_REQUIRED`: Redirect triggered
- `MFA_SUCCESS`: Successful verification
- `MFA_FAILURE`: Invalid code entered
- `SENSITIVE_DETECTED`: Sensitive encounter detected
- `CONTROLLED_SUBSTANCE_DETECTED`: Controlled substance found

### Enhanced Logging
When Ohio compliance logging is enabled:
- User identification
- Patient identification
- Timestamp information
- Detailed context for regulatory audits

## Technical Implementation

### Core Components

#### StepupMfaService
- Main business logic class
- Sensitivity detection methods
- Session management
- Audit logging

#### Forms Interceptor
- Hooks into encounter forms
- Checks sensitivity before allowing access
- Redirects to verification when needed

#### Verification Page
- TOTP code entry interface
- Ohio compliance information
- Enhanced user experience

#### Admin Settings
- Configuration interface
- Category selection
- Compliance feature toggles

### Database Schema
No new tables required. Uses existing:
- `globals` table for configuration
- `form_encounter` for encounter data
- `prescriptions` for medication detection
- `openemr_postcalendar_categories` for category information

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Always redirected after entering code | Check server clock accuracy and timeout settings |
| MFA not working | Verify user has TOTP enabled in MFA Management |
| No sensitive encounters detected | Check category configuration and sensitivity settings |
| Controlled substances not detected | Enable controlled substance detection in settings |

### Debug Logging
Enable debug logging by checking error logs for entries starting with "StepUpMFA:"

### Configuration Verification
1. Check `stepup_mfa_enabled` global setting
2. Verify sensitive categories are configured
3. Ensure users have MFA enabled
4. Check timeout settings

## Future Enhancements

### Planned Features
- **U2F/WebAuthn Support**: Security key authentication
- **Per-Category Timeouts**: Different timeouts for different categories
- **Provider Exemptions**: Allow certain providers to bypass MFA
- **Advanced Reporting**: Export compliance reports
- **Integration APIs**: REST API for external systems

### Compliance Enhancements
- **DEA Integration**: Direct DEA compliance reporting
- **State-Specific Rules**: Support for other state regulations
- **Automated Auditing**: Automated compliance checking
- **Real-time Monitoring**: Live compliance dashboard

## Support

For technical support or compliance questions:
1. Check the troubleshooting section above
2. Review error logs for "StepUpMFA" entries
3. Verify configuration in admin settings
4. Test with a known sensitive encounter

## License

This feature is part of OpenEMR and is licensed under the GNU General Public License v3.

---

**Note**: This feature is designed to help with Ohio compliance but should be reviewed by legal counsel to ensure full compliance with all applicable regulations. 