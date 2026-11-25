-- Add stock difficulty mappings without replacing operator data.
ALTER TABLE `aowow_objectdifficulty` ADD COLUMN `comment` varchar(100) NOT NULL DEFAULT '';

UPDATE `aowow_objectdifficulty` SET `comment` = 'naxxramas: four horsemen chest' WHERE `normal10` = 181366 AND `normal25` = 193426 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 181366, 193426, 0, 0, 2, 'naxxramas: four horsemen chest' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 181366 AND `normal25` = 193426 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'eoe: alexstrasza''s gift' WHERE `normal10` = 193905 AND `normal25` = 193967 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 193905, 193967, 0, 0, 2, 'eoe: alexstrasza''s gift' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 193905 AND `normal25` = 193967 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: cache of winter' WHERE `normal10` = 194307 AND `normal25` = 194308 AND `heroic10` = 194200 AND `heroic25` = 194201 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 194307, 194308, 194200, 194201, 2, 'ulduar: cache of winter' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 194307 AND `normal25` = 194308 AND `heroic10` = 194200 AND `heroic25` = 194201 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: cache of storms' WHERE `normal10` = 194312 AND `normal25` = 194314 AND `heroic10` = 194313 AND `heroic25` = 194315 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 194312, 194314, 194313, 194315, 2, 'ulduar: cache of storms' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 194312 AND `normal25` = 194314 AND `heroic10` = 194313 AND `heroic25` = 194315 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: freya''s gift +1 elder' WHERE `normal10` = 194324 AND `normal25` = 194328 AND `heroic10` = 194325 AND `heroic25` = 194329 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 194324, 194328, 194325, 194329, 2, 'ulduar: freya''s gift +1 elder' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 194324 AND `normal25` = 194328 AND `heroic10` = 194325 AND `heroic25` = 194329 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: freya''s gift +2 elder' WHERE `normal10` = 194324 AND `normal25` = 194328 AND `heroic10` = 194326 AND `heroic25` = 194330 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 194324, 194328, 194326, 194330, 2, 'ulduar: freya''s gift +2 elder' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 194324 AND `normal25` = 194328 AND `heroic10` = 194326 AND `heroic25` = 194330 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: freya''s gift +3 elder' WHERE `normal10` = 194324 AND `normal25` = 194328 AND `heroic10` = 194327 AND `heroic25` = 194331 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 194324, 194328, 194327, 194331, 2, 'ulduar: freya''s gift +3 elder' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 194324 AND `normal25` = 194328 AND `heroic10` = 194327 AND `heroic25` = 194331 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: cache of innovation' WHERE `normal10` = 194789 AND `normal25` = 194956 AND `heroic10` = 194957 AND `heroic25` = 194958 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 194789, 194956, 194957, 194958, 2, 'ulduar: cache of innovation' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 194789 AND `normal25` = 194956 AND `heroic10` = 194957 AND `heroic25` = 194958 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: gift of the observer' WHERE `normal10` = 194821 AND `normal25` = 194822 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 194821, 194822, 0, 0, 2, 'ulduar: gift of the observer' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 194821 AND `normal25` = 194822 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'ulduar: cache of living stone' WHERE `normal10` = 195046 AND `normal25` = 195047 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 195046, 195047, 0, 0, 2, 'ulduar: cache of living stone' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 195046 AND `normal25` = 195047 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc25: champions'' cache' WHERE `normal10` = 195631 AND `normal25` = 195632 AND `heroic10` = 195633 AND `heroic25` = 195635 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 195631, 195632, 195633, 195635, 2, 'toc25: champions'' cache' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 195631 AND `normal25` = 195632 AND `heroic10` = 195633 AND `heroic25` = 195635 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'icc: gunship armory (horde)' WHERE `normal10` = 202178 AND `normal25` = 202180 AND `heroic10` = 202177 AND `heroic25` = 202179 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 202178, 202180, 202177, 202179, 2, 'icc: gunship armory (horde)' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 202178 AND `normal25` = 202180 AND `heroic10` = 202177 AND `heroic25` = 202179 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'icc: gunship armory (alliance)' WHERE `normal10` = 201873 AND `normal25` = 201874 AND `heroic10` = 201872 AND `heroic25` = 201875 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 201873, 201874, 201872, 201875, 2, 'icc: gunship armory (alliance)' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 201873 AND `normal25` = 201874 AND `heroic10` = 201872 AND `heroic25` = 201875 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'icc: deathbringer''s cache' WHERE `normal10` = 202239 AND `normal25` = 202240 AND `heroic10` = 202238 AND `heroic25` = 202241 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 202239, 202240, 202238, 202241, 2, 'icc: deathbringer''s cache' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 202239 AND `normal25` = 202240 AND `heroic10` = 202238 AND `heroic25` = 202241 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'icc: cache of the dreamwalker' WHERE `normal10` = 201959 AND `normal25` = 202339 AND `heroic10` = 202338 AND `heroic25` = 202340 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 201959, 202339, 202338, 202340, 2, 'icc: cache of the dreamwalker' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 201959 AND `normal25` = 202339 AND `heroic10` = 202338 AND `heroic25` = 202340 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc25: argent crusade tribute chest 1TL' WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195668 AND `heroic25` = 195672 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 0, 0, 195668, 195672, 2, 'toc25: argent crusade tribute chest 1TL' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195668 AND `heroic25` = 195672 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc25: argent crusade tribute chest 25TL' WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195667 AND `heroic25` = 195671 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 0, 0, 195667, 195671, 2, 'toc25: argent crusade tribute chest 25TL' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195667 AND `heroic25` = 195671 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc25: argent crusade tribute chest 45TL' WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195666 AND `heroic25` = 195670 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 0, 0, 195666, 195670, 2, 'toc25: argent crusade tribute chest 45TL' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195666 AND `heroic25` = 195670 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc25: argent crusade tribute chest 50TL' WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195665 AND `heroic25` = 195669 AND `mapType` = 2 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 0, 0, 195665, 195669, 2, 'toc25: argent crusade tribute chest 50TL' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 0 AND `normal25` = 0 AND `heroic10` = 195665 AND `heroic25` = 195669 AND `mapType` = 2);

UPDATE `aowow_objectdifficulty` SET `comment` = 'hellfire ramparts: reinforced fel iron chest' WHERE `normal10` = 185168 AND `normal25` = 185169 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 185168, 185169, 0, 0, 1, 'hellfire ramparts: reinforced fel iron chest' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 185168 AND `normal25` = 185169 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'mechanar: cache of the legion' WHERE `normal10` = 184465 AND `normal25` = 184849 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 184465, 184849, 0, 0, 1, 'mechanar: cache of the legion' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 184465 AND `normal25` = 184849 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'halls of stone: tribunal chest' WHERE `normal10` = 190586 AND `normal25` = 193996 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 190586, 193996, 0, 0, 1, 'halls of stone: tribunal chest' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 190586 AND `normal25` = 193996 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'cot - cos: dark runed chest' WHERE `normal10` = 190663 AND `normal25` = 193597 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 190663, 193597, 0, 0, 1, 'cot - cos: dark runed chest' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 190663 AND `normal25` = 193597 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'oculus: cache of eregos' WHERE `normal10` = 191349 AND `normal25` = 193603 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 191349, 193603, 0, 0, 1, 'oculus: cache of eregos' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 191349 AND `normal25` = 193603 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc5: champion''s cache' WHERE `normal10` = 195709 AND `normal25` = 195710 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 195709, 195710, 0, 0, 1, 'toc5: champion''s cache' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 195709 AND `normal25` = 195710 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc5: confessor''s cache' WHERE `normal10` = 195323 AND `normal25` = 195324 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 195323, 195324, 0, 0, 1, 'toc5: confessor''s cache' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 195323 AND `normal25` = 195324 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'toc5: eadric''s cache' WHERE `normal10` = 195374 AND `normal25` = 195375 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 195374, 195375, 0, 0, 1, 'toc5: eadric''s cache' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 195374 AND `normal25` = 195375 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);

UPDATE `aowow_objectdifficulty` SET `comment` = 'hor: captain''s chest' WHERE `normal10` = 201710 AND `normal25` = 202336 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1 AND `comment` = '';

INSERT INTO `aowow_objectdifficulty` (`normal10`, `normal25`, `heroic10`, `heroic25`, `mapType`, `comment`)
SELECT 201710, 202336, 0, 0, 1, 'hor: captain''s chest' WHERE NOT EXISTS (SELECT 1 FROM `aowow_objectdifficulty` WHERE `normal10` = 201710 AND `normal25` = 202336 AND `heroic10` = 0 AND `heroic25` = 0 AND `mapType` = 1);
