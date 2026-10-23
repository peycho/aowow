CREATE TABLE IF NOT EXISTS `aowow_guide_references` (
  `guideId` mediumint(8) unsigned NOT NULL,
  `rev` tinyint(3) unsigned NOT NULL,
  `locale` tinyint(3) unsigned NOT NULL,
  `type` smallint(5) unsigned NOT NULL,
  `typeId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`guideId`,`rev`,`locale`,`type`,`typeId`),
  KEY `entity` (`type`,`typeId`,`locale`,`guideId`,`rev`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `aowow_dbversion` SET `build` = CONCAT(IFNULL(`build`, ''), ' guidereferences');
