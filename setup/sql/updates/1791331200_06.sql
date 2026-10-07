-- Site-wide sound functionality switch; preserve existing operator choices.
INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'sounds_enable', '1', '1', 1, 132, 'enable/disable sound pages, search, related tabs and playback'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'sounds_enable');

-- Install client playback/markup guards once; toggling the setting needs no build.
UPDATE `aowow_dbversion` SET `build` = CONCAT(IFNULL(`build`, ''), ' globaljs');
