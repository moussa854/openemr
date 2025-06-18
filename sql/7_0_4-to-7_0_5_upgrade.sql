-- Add mfa_required and mfa_grace_period columns to users table
ALTER TABLE `users`
ADD COLUMN `mfa_required` VARCHAR(255) DEFAULT 'disabled',
ADD COLUMN `mfa_grace_period` INT DEFAULT 172800; -- 30 * 24 * 60 * 60 (30 days in seconds)

-- Create login_mfa_trusted_devices table
CREATE TABLE `login_mfa_trusted_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `device_identifier` varchar(255) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_identifier` (`device_identifier`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_mfa_trusted_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
