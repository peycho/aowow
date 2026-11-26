DROP TABLE IF EXISTS `dbc_spellduration`;
DROP TABLE IF EXISTS `dbc_spellcasttimes`;
DROP TABLE IF EXISTS `dbc_spell`;

-- Preserve pre-conversion timing values for inspection and rollback. They are
-- seconds/milliseconds, not DBC IDs; the new ID columns are rebuilt from inputs.
CREATE TABLE `aowow_spell_timing_legacy` (
  `id` mediumint(8) unsigned NOT NULL,
  `castTime` float unsigned NOT NULL,
  `duration` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `aowow_spell_timing_legacy` (`id`, `castTime`, `duration`)
SELECT `id`, `castTime`, `duration` FROM `aowow_spell`;

ALTER TABLE `aowow_spell`
    DROP COLUMN `castTime`,
    ADD COLUMN `castTimeId` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `targetAuraSpellNot`,
    DROP COLUMN `duration`,
    ADD COLUMN `durationId` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `talentLevel`,
    ADD INDEX idx_casttime (`castTimeId`),
    ADD INDEX idx_duration (`durationId`),
    ADD COLUMN `minFactionId` smallint(6) unsigned NOT NULL AFTER `damageClass`,
    ADD COLUMN `minReputation` tinyint(3) unsigned NOT NULL AFTER `minFactionId`;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' spell');
