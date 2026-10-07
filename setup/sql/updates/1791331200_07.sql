-- Optional linked header image; preserve operator choices and existing queued work.
INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'header_image_enable', '0', '0', 1, 132, 'display a linked 468x60 image in the standard page header; excludes the homepage'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'header_image_enable');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'header_image_url', '', '', 1, 136, 'absolute HTTP/HTTPS URL of the header image; recommended size 468x60'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'header_image_url');

INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`)
SELECT 'header_image_link', '', '', 1, 136, 'absolute HTTP/HTTPS destination URL; opens in a new browser tab'
WHERE NOT EXISTS (SELECT 1 FROM `aowow_config` WHERE `key` = 'header_image_link');
