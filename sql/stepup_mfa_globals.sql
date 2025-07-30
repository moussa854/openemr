-- Step-Up MFA global settings
INSERT INTO globals (gl_name, gl_value, gl_category, gl_description)
VALUES
 ('stepup_mfa_enabled', '0', 'Security', 'Enable Step-Up MFA for sensitive encounters'),
 ('stepup_mfa_categories', '', 'Security', 'Comma-separated pc_catid values requiring Step-Up MFA'),
 ('stepup_mfa_timeout', '900', 'Security', 'Grace period in seconds after successful Step-Up MFA')
ON DUPLICATE KEY UPDATE gl_value = VALUES(gl_value);