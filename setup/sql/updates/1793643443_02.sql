ALTER TABLE `aowow_creature`
    ADD COLUMN `pvpFlags` int unsigned NOT NULL DEFAULT 0 AFTER `dynamicFlags`;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' creature');
