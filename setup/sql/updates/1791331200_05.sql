-- Optional Cloudflare Turnstile; preserve existing form choices. Keys remain in private configuration.
INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'turnstile_registration_enable', '0', '0', 3, 132, 'require Cloudflare Turnstile for registration'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'turnstile_registration_enable');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'turnstile_login_enable', '0', '0', 3, 132, 'require Cloudflare Turnstile for login'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'turnstile_login_enable');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'turnstile_password_recovery_enable', '0', '0', 3, 132, 'require Cloudflare Turnstile for password recovery email requests'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'turnstile_password_recovery_enable');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'turnstile_username_recovery_enable', '0', '0', 3, 132, 'require Cloudflare Turnstile for username recovery email requests'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'turnstile_username_recovery_enable');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'turnstile_resend_enable', '0', '0', 3, 132, 'require Cloudflare Turnstile for activation email resend requests'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'turnstile_resend_enable');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'turnstile_feedback_enable', '0', '0', 1, 132, 'require Cloudflare Turnstile for general feedback submissions'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'turnstile_feedback_enable');

-- Install feedback dialog token handling once; toggles do not rebuild assets.
UPDATE `aowow_dbversion` SET `build` = CONCAT(IFNULL(`build`, ''), ' globaljs');
