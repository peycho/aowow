-- Restore native bag, ammunition and quiver categories with the corrected generator.
-- Normal regeneration reapplies custom item overrides and refreshes derived stats.
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' items stats');
