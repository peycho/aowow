-- Retire only the shipped unused forum target; preserve customized values and all editorial content.
UPDATE `aowow_config`
SET `value` = ''
WHERE `key` = 'board_url'
  AND BINARY `value` = BINARY 'http://www.wowhead.com/forums?board=';

UPDATE `aowow_dbversion` SET `build` = CONCAT_WS(' ', `build`, 'globaljs', 'tooltips');
