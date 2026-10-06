-- Empty DBC strings are NULL. Historical spell columns were NOT NULL and
-- sometimes narrower than the current extracted data. Keep larger/custom types,
-- character sets and collations; never shrink legacy MEDIUMTEXT to TEXT.
SET @aowow_spell_concat_limit = @@SESSION.group_concat_max_len;
SET SESSION group_concat_max_len = 8192;
SET @aowow_spell_text_ddl = (
    SELECT IF(COUNT(*) = 24,
        CONCAT('ALTER TABLE `aowow_spell` ', GROUP_CONCAT(CONCAT(
            'MODIFY COLUMN `', COLUMN_NAME, '` ',
            IF(DATA_TYPE = 'varchar', CONCAT('varchar(', GREATEST(CHARACTER_MAXIMUM_LENGTH,
                CASE COLUMN_NAME
                    WHEN 'name_loc8' THEN 184
                    WHEN 'rank_loc0' THEN 21
                    WHEN 'rank_loc2' THEN 25
                    WHEN 'rank_loc3' THEN 22
                    WHEN 'rank_loc4' THEN 21
                    WHEN 'rank_loc6' THEN 29
                    WHEN 'rank_loc8' THEN 56
                    ELSE 115
                END), ')'), COLUMN_TYPE),
            ' CHARACTER SET ', CHARACTER_SET_NAME, ' COLLATE ', COLLATION_NAME,
            ' NULL DEFAULT NULL'
        ) ORDER BY ORDINAL_POSITION SEPARATOR ', ')), NULL)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aowow_spell'
      AND COLUMN_NAME REGEXP '^(name|rank|description|buff)_loc(0|2|3|4|6|8)$'
      AND DATA_TYPE IN ('varchar', 'text', 'mediumtext', 'longtext')
);
PREPARE aowow_spell_text_upgrade FROM @aowow_spell_text_ddl;
EXECUTE aowow_spell_text_upgrade;
DEALLOCATE PREPARE aowow_spell_text_upgrade;
SET SESSION group_concat_max_len = @aowow_spell_concat_limit;

-- Earlier generators could acknowledge failed inserts. Repair spell data and
-- dependent output even if those tasks were already cleared by an older run.
UPDATE aowow_dbversion SET
    `sql` = CONCAT(IFNULL(`sql`, ''), ' spell items stats itemset source search'),
    build = CONCAT(IFNULL(build, ''), ' globaljs enchants tooltips itemsets talenticons talentcalc gems glyphs profiler');
