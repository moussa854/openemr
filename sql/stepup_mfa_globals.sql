-- Step-Up MFA global settings
INSERT INTO globals (gl_name, gl_value)
VALUES
 ('stepup_mfa_enabled', '0'),
 ('stepup_mfa_categories', ''),
 ('stepup_mfa_timeout', '900')
ON DUPLICATE KEY UPDATE gl_value = VALUES(gl_value);