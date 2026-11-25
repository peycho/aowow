-- Refresh derived datasets after the November tooltip/schema changes.
UPDATE `aowow_dbversion` SET
    `sql` = CONCAT(IFNULL(`sql`, ''), ' spellrange spellduration spellcasttimes'),
    `build` = CONCAT(IFNULL(`build`, ''), ' profiler pets');
