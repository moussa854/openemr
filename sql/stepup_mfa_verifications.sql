-- Step-Up MFA Verifications Table
-- This table tracks MFA verifications for sensitive encounters
-- Enhanced for Ohio compliance requirements

CREATE TABLE IF NOT EXISTS `stepup_mfa_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User who performed the verification',
  `patient_id` int(11) NOT NULL COMMENT 'Patient for whom verification was performed',
  `encounter_id` int(11) DEFAULT NULL COMMENT 'Specific encounter if applicable',
  `verification_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When verification was completed',
  `expires_at` timestamp NOT NULL COMMENT 'When verification expires',
  `verification_type` enum('TOTP','U2F') NOT NULL DEFAULT 'TOTP' COMMENT 'Type of verification used',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of verification',
  `user_agent` text DEFAULT NULL COMMENT 'User agent string',
  `session_id` varchar(255) DEFAULT NULL COMMENT 'Session ID for tracking',
  `ohio_compliance_logged` tinyint(1) DEFAULT 0 COMMENT 'Whether Ohio compliance was logged',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_patient` (`user_id`, `patient_id`),
  KEY `idx_encounter` (`encounter_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_verification_time` (`verification_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Step-Up MFA verifications for sensitive encounters';

-- Insert sample data for testing (optional)
-- INSERT INTO stepup_mfa_verifications (user_id, patient_id, encounter_id, expires_at, verification_type, ip_address, session_id) 
-- VALUES (1, 1, 6, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 'TOTP', '192.168.30.124', 'test_session'); 