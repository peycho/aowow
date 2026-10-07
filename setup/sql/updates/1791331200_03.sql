-- Disable the uncached missing-screenshots utility by default; retain existing choices.
INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'missing_screenshots_enable', '0', '0', 1, 132, 'enable/disable the public Missing Screenshots utility (uncached database listing)'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'missing_screenshots_enable');
