-- Complete Installation Script for MFA Remembered Devices Feature
-- This script creates all necessary database tables and indexes

-- AI GENERATED CODE START

-- 1. Create the main remembered devices table
DROP TABLE IF EXISTS `mfa_remembered_devices`;
CREATE TABLE `mfa_remembered_devices` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `selector` varchar(255) NOT NULL,
  `validator_hash` varchar(255) NOT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `last_used` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB COMMENT='Stores secure tokens for remembering MFA-authenticated devices';

-- 2. Create the emergency codes table
DROP TABLE IF EXISTS `mfa_emergency_codes`;
CREATE TABLE `mfa_emergency_codes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_by` (`created_by`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB COMMENT='Stores emergency bypass codes for MFA lockout situations';

-- 3. Insert default global settings (if they don't exist)
INSERT IGNORE INTO globals (gl_name, gl_index, gl_value) VALUES
('mfa_remember_enable', 0, '1'),
('mfa_remember_duration', 0, '30'),
('mfa_remember_policy', 0, '0'),
('mfa_max_devices_per_user', 0, '5');

-- 4. Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_mfa_remembered_devices_user_expiry ON mfa_remembered_devices(user_id, expires_at);
CREATE INDEX IF NOT EXISTS idx_mfa_emergency_codes_user_expiry ON mfa_emergency_codes(user_id, expires_at);

-- 5. Add foreign key constraints (optional - for referential integrity)
-- ALTER TABLE mfa_remembered_devices ADD CONSTRAINT fk_mfa_remembered_devices_user 
--   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
-- ALTER TABLE mfa_emergency_codes ADD CONSTRAINT fk_mfa_emergency_codes_user 
--   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
-- ALTER TABLE mfa_emergency_codes ADD CONSTRAINT fk_mfa_emergency_codes_created_by 
--   FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE;

-- Installation complete!
-- The MFA Remembered Devices feature is now ready to use.
-- 
-- Next steps:
-- 1. Configure the settings in Administration > Globals > Security
-- 2. Test the feature with a user account
-- 3. Set up the cleanup cron job: 0 2 * * * /path/to/openemr/bin/cleanup-mfa-remembered-devices.php

-- AI GENERATED CODE END 