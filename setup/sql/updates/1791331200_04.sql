-- General feedback switch; preserve existing choices and content-reporting tools.
INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'feedback_enable', '1', '1', 1, 132, 'enable/disable general Feedback links, form and submissions; content reports remain available'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'feedback_enable');

-- Install client-side form guards once; changing the setting needs no rebuild.
UPDATE `aowow_dbversion` SET `build` = CONCAT(IFNULL(`build`, ''), ' globaljs');
