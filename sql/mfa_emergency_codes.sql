-- MFA Emergency Codes Table
-- This table stores emergency bypass codes for MFA lockout situations

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