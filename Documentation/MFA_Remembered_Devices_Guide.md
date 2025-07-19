# MFA Remembered Devices Feature Guide

<!-- AI GENERATED CODE START -->

## Overview

The MFA Remembered Devices feature allows OpenEMR users to remember their device for a specified period (default 30 days) after successful MFA authentication. This reduces the friction of repeated MFA prompts while maintaining security through secure token-based authentication.

## Features

### Core Functionality
- **Secure Token System**: Uses the Selector-Validator Token Model with cryptographic security
- **Configurable Duration**: Administrators can set remember duration (14-30 days recommended)
- **Device Limits**: Configurable maximum devices per user
- **Policy Enforcement**: Optional, mandatory for all users, or mandatory for clinical staff only
- **Automatic Cleanup**: Expired tokens are automatically removed

### Administrative Controls
- **Global Configuration**: Settings in Administration > Globals > Security
- **Device Management**: View and revoke remembered devices system-wide
- **Emergency Bypass**: Emergency system for locked-out users
- **Audit Logging**: All actions are logged for compliance

## Installation

### 1. Database Setup
Run the complete installation script:
```sql
mysql -u openemr_user -p openemr < sql/install-mfa-remembered-devices-complete.sql
```

### 2. Configure Settings
Navigate to **Administration > Globals > Security** and configure:

- **Enable MFA Remember Device**: Enable/disable the feature globally
- **MFA Remember Duration (Days)**: Set duration (default: 30 days)
- **MFA Remember Policy**: 
  - Optional - User Choice (default)
  - Mandatory for All Users
  - Mandatory for Clinical Staff Only
- **Maximum Remembered Devices Per User**: Set device limit (default: 5)

### 3. Set Up Cleanup Cron Job
Add to your crontab:
```bash
0 2 * * * /path/to/openemr/bin/cleanup-mfa-remembered-devices.php
```

## User Experience

### For End Users
1. **Login with MFA**: Users complete normal MFA authentication
2. **Remember Device Option**: If enabled, users see "Remember this device" checkbox
3. **Trusted Device**: For the configured duration, users skip MFA on subsequent logins
4. **Device Management**: Users can view and revoke their remembered devices

### For Administrators
1. **Global Management**: View all remembered devices system-wide
2. **Emergency Access**: Generate bypass codes for locked-out users
3. **Policy Enforcement**: Configure who must use the feature
4. **Audit Trail**: Monitor all remember device activities

## Security Features

### Token Security
- **Cryptographic Tokens**: Uses cryptographically secure random tokens
- **Selector-Validator Model**: Prevents token theft and replay attacks
- **Secure Cookies**: HttpOnly, Secure, SameSite flags
- **Token Rotation**: New token generated on each use

### Administrative Security
- **Emergency Bypass**: Requires administrator password verification
- **Audit Logging**: All actions logged with user and timestamp
- **Time-Limited Codes**: Emergency codes expire after 24 hours
- **CSRF Protection**: All forms protected against CSRF attacks

## Configuration Options

### Global Settings (Administration > Globals > Security)

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `mfa_remember_enable` | Boolean | 1 | Enable/disable the feature globally |
| `mfa_remember_duration` | Number | 30 | Days to remember devices |
| `mfa_remember_policy` | Select | 0 | Policy enforcement level |
| `mfa_max_devices_per_user` | Number | 5 | Maximum devices per user (0=unlimited) |

### Policy Options
- **0 - Optional**: Users choose whether to remember devices
- **1 - Mandatory for All**: All users must remember devices
- **2 - Mandatory for Clinical**: Only clinical staff must remember devices

## Administrative Tools

### Device Management
**Location**: Administration > Globals > Security > "Manage MFA Remembered Devices"

Features:
- View all active remembered devices
- Revoke individual devices
- Revoke all devices for a specific user
- Cleanup expired tokens
- Device count statistics

### Emergency Bypass
**Location**: Administration > Globals > Security > "Emergency MFA Bypass"

Features:
- Generate emergency bypass codes for locked users
- Verify and use bypass codes
- Disable MFA for users in emergency situations
- Full audit logging of all actions

## Troubleshooting

### Common Issues

#### User Can't Remember Device
1. Check if feature is enabled globally
2. Verify user has MFA enabled
3. Check device limit settings
4. Review browser cookie settings

#### Emergency Bypass Not Working
1. Verify administrator password
2. Check if bypass code is expired (24 hours)
3. Ensure user has MFA enabled
4. Check audit logs for errors

#### Performance Issues
1. Run cleanup script to remove expired tokens
2. Check database indexes are created
3. Monitor device count per user
4. Review cleanup cron job

### Log Files
- **Audit Log**: Check for MFA-related events
- **Error Log**: Look for authentication errors
- **Database Log**: Monitor for slow queries

## Compliance Considerations

### HIPAA Compliance
- All actions are logged for audit trails
- Secure token storage with cryptographic hashing
- Time-limited access with automatic expiration
- Administrative oversight and control

### Security Best Practices
- Regular cleanup of expired tokens
- Monitoring of device usage patterns
- Emergency procedures for lockouts
- Policy enforcement based on user roles

## API Reference

### MfaRememberDeviceService Methods

```php
// Check if remember device is enabled
$service->isRememberEnabled()

// Get configured duration
$service->getRememberDuration()

// Get maximum devices per user
$service->getMaxDevicesPerUser()

// Check if user is required to remember device
$service->isRememberRequired($userId)

// Generate remember token
$service->generateRememberToken($userId, $expiryDays)

// Verify remember token
$service->verifyRememberToken($cookieValue)

// Get user's remembered devices
$service->getUserRememberedDevices($userId)

// Revoke a device
$service->revokeDevice($deviceId, $userId)

// Cleanup expired tokens
$service->cleanupExpiredTokens()
```

## Migration Guide

### From Previous Versions
If upgrading from a previous version without this feature:

1. Run the installation SQL script
2. Configure global settings
3. Test with a small group of users
4. Monitor logs for any issues
5. Roll out to all users

### Database Schema Changes
- New table: `mfa_remembered_devices`
- New table: `mfa_emergency_codes`
- New global settings in `globals` table
- New indexes for performance

## Support

For technical support:
1. Check the audit logs for error details
2. Verify all database tables are created
3. Test with a fresh user account
4. Review browser console for JavaScript errors
5. Contact OpenEMR community for assistance

## Future Enhancements

Potential future improvements:
- Device fingerprinting for better security
- Geographic restrictions for remembered devices
- Integration with mobile device management
- Advanced analytics and reporting
- Integration with SSO systems

<!-- AI GENERATED CODE END --> 