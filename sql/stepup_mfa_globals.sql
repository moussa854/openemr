-- Initialize globals for Step-Up MFA
INSERT INTO globals (gl_name, gl_index, gl_value) VALUES
('stepup_mfa_enabled', 0, '0'),
('stepup_mfa_categories', 0, ''),
('stepup_mfa_timeout', 0, '900'),
('stepup_mfa_check_controlled_substances', 0, '0'),
('stepup_mfa_ohio_compliance_logging', 0, '0')
ON DUPLICATE KEY UPDATE gl_value = VALUES(gl_value);
