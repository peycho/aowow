ALTER TABLE `aowow_screenshots`
    -- Preserve legacy captions; new submissions retain the current interface limit.
    MODIFY COLUMN `caption` mediumtext DEFAULT NULL;
