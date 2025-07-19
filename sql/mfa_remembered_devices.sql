-- MFA Remembered Devices Table
-- This table stores secure tokens for remembering MFA-authenticated devices

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