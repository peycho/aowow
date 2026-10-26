-- Correct only the stock English Midsummer article; retain CMS edits and newer revisions.
-- Normalize the imported event ID for the stock-content fingerprint.
UPDATE `aowow_articles` a
JOIN `aowow_guides` g ON g.`id` = a.`typeId` AND g.`locale` = a.`locale`
JOIN `aowow_events` e ON e.`holidayId` = 341
LEFT JOIN `aowow_articles` newer ON newer.`type` = a.`type` AND newer.`typeId` = a.`typeId`
    AND newer.`locale` = a.`locale` AND newer.`rev` > 1
SET a.`article` = REPLACE(REPLACE(REPLACE(a.`article`,
    '[url=?search&q=Honor+the+Flame#quests]', '[url=?search=Honor+the+Flame#quests]'),
    '[url=?search&q=Desecrate+this+Fire#quests]', '[url=?search=Desecrate+this+Fire#quests]'),
    '[url=?search&q=stealing+flame#quests]', '[url=?search=stealing+flame#quests]')
WHERE a.`type` = 300 AND a.`typeId` = 5 AND a.`locale` = 0 AND a.`rev` = 1 AND a.`editAccess` = 2
    AND a.`url` = 'midsummer-fire-festival' AND CAST(g.`url` AS BINARY) = CAST(a.`url` AS BINARY)
    AND g.`userId` = 0 AND g.`status` = 3 AND g.`rev` = 1
    AND SHA2(REPLACE(a.`article`, CONCAT('[url=?event=', e.`id`, ']'), '[url=?event=1]'), 256)
        = '081d94e0310efaca18b5c9bd6d99354a41e4277cb9bcdfb22e88a7394d316377'
    AND newer.`typeId` IS NULL;

UPDATE `aowow_dbversion` SET `build` = CONCAT(IFNULL(`build`, ''), ' guidereferences');
