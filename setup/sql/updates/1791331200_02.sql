-- Runtime switches for goodies pages and navigation; retain any existing choices.
INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'searchplugins_enable', '1', '1', 1, 132, 'enable/disable the Search Plugins page and browser discovery'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'searchplugins_enable');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'searchbox_enable', '1', '1', 1, 132, 'enable/disable the Search Box goodies page'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'searchbox_enable');
