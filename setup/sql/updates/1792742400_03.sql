-- The bundled Children's Week and Midsummer bodies were attached to each other's guides.
-- Only move the exact original English articles; preserve customized/pending revisions.
CREATE TEMPORARY TABLE `aowow_festival_article_repair` LIKE `aowow_articles`;
INSERT INTO `aowow_festival_article_repair` (`type`,`typeId`,`locale`,`url`,`rev`,`editAccess`,`article`)
SELECT a.`type`, 9 - a.`typeId`, a.`locale`, a.`url`, a.`rev`, a.`editAccess`, a.`article`
FROM `aowow_articles` a
JOIN `aowow_articles` midsummer ON midsummer.`type` = 300 AND midsummer.`typeId` = 4
    AND midsummer.`locale` = 0 AND midsummer.`rev` = 1 AND midsummer.`editAccess` = 2
    AND midsummer.`url` = 'midsummer-fire-festival'
    AND SHA2(midsummer.`article`, 256) = '69ea92ff71008e7031f5bb48da9e3e56dfd2e48b578e364ca2c94f886a311a2d'
JOIN `aowow_articles` children ON children.`type` = 300 AND children.`typeId` = 5
    AND children.`locale` = 0 AND children.`rev` = 1 AND children.`editAccess` = 2
    AND children.`url` = 'childrens-week'
    AND SHA2(children.`article`, 256) = 'd26eeba81d2cb4fa1d3276f922b243777a2bc908676a75bf7f9923c7b18d9bcc'
JOIN `aowow_guides` currentGuide ON currentGuide.`id` = a.`typeId`
    AND currentGuide.`userId` = 0 AND currentGuide.`status` = 3 AND currentGuide.`rev` = 1
JOIN `aowow_guides` targetGuide ON targetGuide.`id` = 9 - a.`typeId`
    AND CAST(targetGuide.`url` AS BINARY) = CAST(a.`url` AS BINARY)
    AND targetGuide.`userId` = 0 AND targetGuide.`status` = 3 AND targetGuide.`rev` = 1
WHERE a.`type` = 300 AND a.`typeId` IN (4,5) AND a.`locale` = 0 AND a.`rev` = 1
    AND NOT EXISTS (SELECT 1 FROM `aowow_articles` newer
        WHERE newer.`type` = 300 AND newer.`typeId` IN (4,5) AND newer.`rev` > 1);
DELETE a FROM `aowow_articles` a
JOIN `aowow_festival_article_repair` repaired ON repaired.`type` = a.`type`
    AND repaired.`url` = a.`url` AND repaired.`locale` = a.`locale` AND repaired.`rev` = a.`rev`;
INSERT INTO `aowow_articles` SELECT * FROM `aowow_festival_article_repair`;
DROP TEMPORARY TABLE `aowow_festival_article_repair`;

-- Event URLs need imported game-event IDs, not client holiday IDs.
-- Match complete native URL tags so longer IDs and unrelated guides remain unchanged.
UPDATE `aowow_articles` a
JOIN `aowow_events` e ON e.`holidayId` = 327
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=event=327]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=?event=327]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=/?event=327]', CONCAT('[url=?event=', e.`id`, ']'))
WHERE a.`type` = 300 AND a.`url` IN ('lunar-festival');

UPDATE `aowow_articles` a
JOIN `aowow_events` e ON e.`holidayId` = 181
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=event=181]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=?event=181]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=/?event=181]', CONCAT('[url=?event=', e.`id`, ']'))
WHERE a.`type` = 300 AND a.`url` IN ('noblegarden');

UPDATE `aowow_articles` a
JOIN `aowow_events` e ON e.`holidayId` = 423
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=event=335]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=?event=335]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=/?event=335]', CONCAT('[url=?event=', e.`id`, ']'))
WHERE a.`type` = 300 AND a.`url` IN ('love-is-in-the-air');

UPDATE `aowow_articles` a
JOIN `aowow_events` e ON e.`holidayId` = 372
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=event=372]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=?event=372]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=/?event=372]', CONCAT('[url=?event=', e.`id`, ']'))
WHERE a.`type` = 300 AND a.`url` IN ('love-is-in-the-air','brewfest');

UPDATE `aowow_articles` a
JOIN `aowow_events` e ON e.`holidayId` = 341
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=event=341]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=?event=341]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=/?event=341]', CONCAT('[url=?event=', e.`id`, ']'))
WHERE a.`type` = 300 AND a.`url` IN ('midsummer-fire-festival','childrens-week');

UPDATE `aowow_articles` a
JOIN `aowow_events` e ON e.`holidayId` = 141
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=event=141]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=?event=141]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=/?event=141]', CONCAT('[url=?event=', e.`id`, ']'))
WHERE a.`type` = 300 AND a.`url` IN ('winter-veil');

-- Pilgrim's Bounty had the right ID but omitted the query-string prefix.
UPDATE `aowow_articles` a
JOIN `aowow_events` e ON e.`holidayId` = 404
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=event=26]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=?event=26]', CONCAT('[url=?event=', e.`id`, ']')),
    '[url=/?event=26]', CONCAT('[url=?event=', e.`id`, ']'))
WHERE a.`type` = 300 AND a.`url` = 'pilgrims-bounty';

UPDATE `aowow_dbversion` SET `build` = CONCAT(IFNULL(`build`, ''), ' guidereferences');
