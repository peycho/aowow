-- Reconcile the historical upgrade path with the current fresh-install schema.
-- SqlUpdate checks every conversion under its existing lease before this file starts.
-- Existing MEDIUMTEXT storage and wider Chinese spell ranks remain supported.
-- No rows, identifiers, ownership references or upload references are deleted.

ALTER TABLE `aowow_account`
    MODIFY COLUMN `extId` int(10) unsigned DEFAULT NULL COMMENT 'external user id',
    MODIFY COLUMN `curIP` varchar(45) NOT NULL DEFAULT '',
    MODIFY COLUMN `prevIP` varchar(45) NOT NULL DEFAULT '',
    MODIFY COLUMN `curLogin` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'unixtime',
    MODIFY COLUMN `prevLogin` int(10) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `avatar` tinyint(4) DEFAULT 0,
    MODIFY COLUMN `description` text NOT NULL DEFAULT ('');

ALTER TABLE `aowow_account_cookies`
    MODIFY COLUMN `data` mediumtext NOT NULL;

ALTER TABLE `aowow_account_excludes`
    MODIFY COLUMN `mode` enum('EXCLUDE','INCLUDE') NOT NULL;

ALTER TABLE `aowow_account_profiles`
    MODIFY COLUMN `extraFlags` int(10) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `aowow_account_weightscales`
    MODIFY COLUMN `icon` varchar(51) NOT NULL DEFAULT '';

ALTER TABLE `aowow_achievement`
    MODIFY COLUMN `chainId` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `chainPos` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `category` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `parentCat` smallint(6) NOT NULL DEFAULT 0,
    MODIFY COLUMN `points` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `orderInGroup` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `iconIdBak` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `flags` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `reqCriteriaCount` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `refAchievement` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `itemExtra` mediumint(8) unsigned DEFAULT NULL,
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `name_loc0` varchar(78) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(79) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(86) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(86) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(78) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(76) DEFAULT NULL,
    MODIFY COLUMN `description_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `reward_loc0` varchar(74) DEFAULT NULL,
    MODIFY COLUMN `reward_loc2` varchar(88) DEFAULT NULL,
    MODIFY COLUMN `reward_loc3` varchar(92) DEFAULT NULL,
    MODIFY COLUMN `reward_loc4` varchar(92) DEFAULT NULL,
    MODIFY COLUMN `reward_loc6` varchar(83) DEFAULT NULL,
    MODIFY COLUMN `reward_loc8` varchar(95) DEFAULT NULL;

ALTER TABLE `aowow_achievementcategory`
    MODIFY COLUMN `parentCat` smallint(6) NOT NULL DEFAULT 0,
    MODIFY COLUMN `parentCat2` smallint(6) NOT NULL DEFAULT 0;

ALTER TABLE `aowow_announcements`
    MODIFY COLUMN `text_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc8` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_classes`
    MODIFY COLUMN `fileString` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `name_loc0` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `powerType` tinyint(4) NOT NULL DEFAULT 0,
    MODIFY COLUMN `raceMask` int(11) NOT NULL DEFAULT 0,
    MODIFY COLUMN `roles` int(11) NOT NULL DEFAULT 0,
    MODIFY COLUMN `skills` varchar(32) NOT NULL DEFAULT '',
    MODIFY COLUMN `flags` mediumint(9) NOT NULL DEFAULT 0,
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `weaponTypeMask` int(11) NOT NULL DEFAULT 0,
    MODIFY COLUMN `armorTypeMask` int(11) NOT NULL DEFAULT 0,
    MODIFY COLUMN `expansion` tinyint(4) NOT NULL DEFAULT 0;

ALTER TABLE `aowow_config`
    MODIFY COLUMN `comment` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `aowow_creature`
    MODIFY COLUMN `modelId` mediumint(9) NOT NULL DEFAULT 0,
    MODIFY COLUMN `name_loc0` varchar(100) DEFAULT NULL;

ALTER TABLE `aowow_creature_sounds`
    MODIFY COLUMN `greeting` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `farewell` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `angry` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `exertion` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `exertioncritical` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `injury` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `injurycritical` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `death` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `stun` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `stand` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `footstep` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `aggro` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `wingflap` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `wingglide` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `alert` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `fidget` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `customattack` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `loop` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `jumpstart` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `jumpend` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `petattack` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `petorder` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `petdismiss` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `birth` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spellcast` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `submerge` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `submerged` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `transform` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `transformanimated` smallint(5) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `aowow_creature_waypoints`
    MODIFY COLUMN `floor` tinyint(4) NOT NULL DEFAULT -1,
    MODIFY COLUMN `wait` int(10) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `aowow_currencies`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `itemId` int(11) NOT NULL DEFAULT 0,
    MODIFY COLUMN `cap` int(10) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `name_loc0` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `description_loc0` varchar(256) DEFAULT NULL,
    MODIFY COLUMN `description_loc2` varchar(256) DEFAULT NULL,
    MODIFY COLUMN `description_loc3` varchar(256) DEFAULT NULL,
    MODIFY COLUMN `description_loc4` varchar(256) DEFAULT NULL,
    MODIFY COLUMN `description_loc6` varchar(256) DEFAULT NULL,
    MODIFY COLUMN `description_loc8` varchar(256) DEFAULT NULL;

ALTER TABLE `aowow_dbversion`
    MODIFY COLUMN `sql` mediumtext DEFAULT NULL,
    MODIFY COLUMN `build` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_declinedword`
    MODIFY COLUMN `word` varchar(127) DEFAULT NULL;

ALTER TABLE `aowow_declinedwordcases`
    MODIFY COLUMN `word` varchar(131) DEFAULT NULL;

ALTER TABLE `aowow_emotes`
    MODIFY COLUMN `isAnimated` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `flags` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `parentEmote` smallint(6) NOT NULL DEFAULT 0,
    MODIFY COLUMN `soundId` smallint(6) NOT NULL DEFAULT 0,
    MODIFY COLUMN `state` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `stateParam` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `extToExt_loc0` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToExt_loc2` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToExt_loc3` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToExt_loc4` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToExt_loc6` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToExt_loc8` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToMe_loc0` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToMe_loc2` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToMe_loc3` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToMe_loc4` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToMe_loc6` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToMe_loc8` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToExt_loc0` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToExt_loc2` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToExt_loc3` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToExt_loc4` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToExt_loc6` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToExt_loc8` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToNone_loc0` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToNone_loc2` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToNone_loc3` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToNone_loc4` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToNone_loc6` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `extToNone_loc8` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToNone_loc0` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToNone_loc2` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToNone_loc3` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToNone_loc4` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToNone_loc6` varchar(150) DEFAULT NULL,
    MODIFY COLUMN `meToNone_loc8` varchar(150) DEFAULT NULL;

ALTER TABLE `aowow_emotes_aliasses`
    MODIFY COLUMN `command` varchar(20) NOT NULL;

ALTER TABLE `aowow_errors`
    MODIFY COLUMN `message` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_events`
    MODIFY COLUMN `id` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `startTime` int(11) NOT NULL,
    MODIFY COLUMN `endTime` int(11) NOT NULL,
    MODIFY COLUMN `occurence` int(10) unsigned NOT NULL,
    MODIFY COLUMN `length` int(10) unsigned NOT NULL;

ALTER TABLE `aowow_factions`
    MODIFY COLUMN `repIdx` smallint(6) NOT NULL,
    MODIFY COLUMN `baseRepRaceMask1` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseRepRaceMask2` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseRepRaceMask3` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseRepRaceMask4` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseRepClassMask1` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseRepClassMask2` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseRepClassMask3` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseRepClassMask4` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `name_loc0` varchar(35) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(49) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(40) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(40) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(50) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(47) DEFAULT NULL;

ALTER TABLE `aowow_holidays`
    MODIFY COLUMN `bossCreature` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `achievementCatOrId` mediumint(9) NOT NULL DEFAULT 0,
    MODIFY COLUMN `name_loc0` varchar(36) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(42) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(36) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(36) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(49) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(29) DEFAULT NULL,
    MODIFY COLUMN `description_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `textureString` varchar(30) NOT NULL DEFAULT '';

ALTER TABLE `aowow_home_featuredbox`
    MODIFY COLUMN `text_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `text_loc8` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_home_featuredbox_overlay`
    MODIFY COLUMN `title_loc0` varchar(100) DEFAULT '',
    MODIFY COLUMN `title_loc2` varchar(100) DEFAULT '',
    MODIFY COLUMN `title_loc3` varchar(100) DEFAULT '',
    MODIFY COLUMN `title_loc4` varchar(100) DEFAULT '',
    MODIFY COLUMN `title_loc6` varchar(100) DEFAULT '',
    MODIFY COLUMN `title_loc8` varchar(100) DEFAULT '';

ALTER TABLE `aowow_home_oneliner`
    MODIFY COLUMN `text_loc0` varchar(200) DEFAULT NULL,
    MODIFY COLUMN `text_loc2` varchar(200) DEFAULT NULL,
    MODIFY COLUMN `text_loc3` varchar(200) DEFAULT NULL,
    MODIFY COLUMN `text_loc4` varchar(200) DEFAULT NULL,
    MODIFY COLUMN `text_loc6` varchar(200) DEFAULT NULL,
    MODIFY COLUMN `text_loc8` varchar(200) DEFAULT NULL;

ALTER TABLE `aowow_icons`
    MODIFY COLUMN `name_source` varchar(55) NOT NULL DEFAULT '';

ALTER TABLE `aowow_itemenchantment`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `name_loc0` varchar(65) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(91) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(84) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(84) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(89) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(96) DEFAULT NULL;

ALTER TABLE `aowow_itemrandomenchant`
    MODIFY COLUMN `name_loc0` varchar(250) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(250) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(250) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(250) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(250) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(250) DEFAULT NULL;

ALTER TABLE `aowow_items`
    MODIFY COLUMN `name_loc0` varchar(127) DEFAULT NULL,
    MODIFY COLUMN `flags` int(10) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `buyPrice` int(11) NOT NULL DEFAULT 0,
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `description_loc0` varchar(255) DEFAULT NULL;

ALTER TABLE `aowow_itemset`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `name_loc0` varchar(255) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(255) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(255) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(255) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(255) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(255) DEFAULT NULL,
    MODIFY COLUMN `item1` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item2` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item3` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item4` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item5` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item6` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item7` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item8` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item9` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `item10` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell1` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell2` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell3` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell4` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell5` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell6` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell7` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `spell8` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus1` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus2` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus3` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus4` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus5` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus6` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus7` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonus8` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `bonusText_loc0` text DEFAULT NULL,
    MODIFY COLUMN `bonusText_loc2` text DEFAULT NULL,
    MODIFY COLUMN `bonusText_loc3` text DEFAULT NULL,
    MODIFY COLUMN `bonusText_loc4` text DEFAULT NULL,
    MODIFY COLUMN `bonusText_loc6` text DEFAULT NULL,
    MODIFY COLUMN `bonusText_loc8` text DEFAULT NULL,
    MODIFY COLUMN `npieces` tinyint(4) NOT NULL DEFAULT 0,
    MODIFY COLUMN `minLevel` smallint(6) NOT NULL DEFAULT 0,
    MODIFY COLUMN `maxLevel` smallint(6) NOT NULL DEFAULT 0,
    MODIFY COLUMN `classMask` mediumint(9) NOT NULL DEFAULT 0,
    MODIFY COLUMN `heroic` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'bool',
    MODIFY COLUMN `quality` tinyint(4) NOT NULL DEFAULT 0,
    MODIFY COLUMN `type` smallint(6) NOT NULL DEFAULT 0 COMMENT 'g_itemset_types',
    MODIFY COLUMN `contentGroup` smallint(6) NOT NULL DEFAULT 0 COMMENT 'g_itemset_notes',
    MODIFY COLUMN `eventId` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `skillId` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `skillLevel` smallint(5) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `aowow_mails`
    MODIFY COLUMN `subject_loc0` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `subject_loc2` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `subject_loc3` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `subject_loc4` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `subject_loc6` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `subject_loc8` varchar(128) DEFAULT NULL,
    MODIFY COLUMN `text_loc0` text DEFAULT NULL,
    MODIFY COLUMN `text_loc2` text DEFAULT NULL,
    MODIFY COLUMN `text_loc3` text DEFAULT NULL,
    MODIFY COLUMN `text_loc4` text DEFAULT NULL,
    MODIFY COLUMN `text_loc6` text DEFAULT NULL,
    MODIFY COLUMN `text_loc8` text DEFAULT NULL;

ALTER TABLE `aowow_objects`
    MODIFY COLUMN `reqQuest` mediumint(9) NOT NULL DEFAULT 0;

ALTER TABLE `aowow_pet`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `name_loc0` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(64) DEFAULT NULL;

ALTER TABLE `aowow_profiler_arena_team`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags';

ALTER TABLE `aowow_profiler_profiles`
    MODIFY COLUMN `renameItr` tinyint(3) unsigned DEFAULT NULL,
    MODIFY COLUMN `skincolor` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `hairstyle` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `haircolor` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `facetype` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `features` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `title` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `description` mediumtext DEFAULT NULL,
    MODIFY COLUMN `playedtime` int(10) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `gearscore` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `achievementpoints` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `lastupdated` int(11) NOT NULL DEFAULT 0,
    MODIFY COLUMN `talenttree1` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'points spend in 1st tree',
    MODIFY COLUMN `talenttree2` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'points spend in 2nd tree',
    MODIFY COLUMN `talenttree3` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'points spend in 3rd tree',
    MODIFY COLUMN `talentbuild1` varchar(105) NOT NULL DEFAULT '',
    MODIFY COLUMN `talentbuild2` varchar(105) NOT NULL DEFAULT '',
    MODIFY COLUMN `glyphs1` varchar(45) NOT NULL DEFAULT '',
    MODIFY COLUMN `glyphs2` varchar(45) NOT NULL DEFAULT '',
    MODIFY COLUMN `activespec` tinyint(3) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `aowow_quests`
    MODIFY COLUMN `rewardArenaPoints` smallint(6) NOT NULL DEFAULT 0,
    MODIFY COLUMN `objectives_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectives_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectives_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectives_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectives_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectives_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `details_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `details_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `details_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `details_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `details_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `details_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `end_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `end_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `end_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `end_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `end_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `end_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `offerReward_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `offerReward_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `offerReward_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `offerReward_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `offerReward_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `offerReward_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `requestItems_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `requestItems_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `requestItems_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `requestItems_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `requestItems_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `requestItems_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `completed_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `completed_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `completed_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `completed_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `completed_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `completed_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText1_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText1_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText1_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText1_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText1_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText1_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText2_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText2_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText2_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText2_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText2_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText2_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText3_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText3_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText3_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText3_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText3_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText3_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText4_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText4_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText4_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText4_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText4_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `objectiveText4_loc8` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_races`
    MODIFY COLUMN `id` int(10) unsigned NOT NULL,
    MODIFY COLUMN `classMask` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `flags` tinyint(3) unsigned NOT NULL,
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `factionId` smallint(6) NOT NULL,
    MODIFY COLUMN `startAreaId` smallint(6) NOT NULL,
    MODIFY COLUMN `leader` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `baseLanguage` tinyint(3) unsigned NOT NULL,
    MODIFY COLUMN `side` tinyint(3) unsigned NOT NULL,
    MODIFY COLUMN `fileString` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc0` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(64) DEFAULT NULL;

ALTER TABLE `aowow_reports`
    MODIFY COLUMN `description` mediumtext NOT NULL;

ALTER TABLE `aowow_screenshots`
    MODIFY COLUMN `caption` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_shapeshiftforms`
    MODIFY COLUMN `flags` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `creatureType` tinyint(4) NOT NULL,
    MODIFY COLUMN `displayIdA` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `displayIdH` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `spellId1` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `spellId2` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `spellId3` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `spellId4` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `spellId5` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `spellId6` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `spellId7` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `spellId8` mediumint(8) unsigned NOT NULL;

ALTER TABLE `aowow_skillline`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `categoryId` tinyint(4) NOT NULL,
    MODIFY COLUMN `name_loc0` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(64) DEFAULT NULL,
    MODIFY COLUMN `description_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc8` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_sounds`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags';

ALTER TABLE `aowow_sounds_files`
    MODIFY COLUMN `type` enum('OGG','MP3') NOT NULL;

ALTER TABLE `aowow_spawns`
    MODIFY COLUMN `spawnMask` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `phaseMask` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `areaId` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `floor` tinyint(3) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `pathId` int(10) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `aowow_spell`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `procCharges` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `maxLevel` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `baseLevel` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `spellLevel` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `stackAmount` mediumint(8) unsigned NOT NULL,
    MODIFY COLUMN `reagent1` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagent2` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagent3` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagent4` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagent5` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagent6` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagent7` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagent8` mediumint(9) NOT NULL,
    MODIFY COLUMN `reagentCount1` tinyint(4) NOT NULL,
    MODIFY COLUMN `reagentCount2` tinyint(4) NOT NULL,
    MODIFY COLUMN `reagentCount3` tinyint(4) NOT NULL,
    MODIFY COLUMN `reagentCount4` tinyint(4) NOT NULL,
    MODIFY COLUMN `reagentCount5` tinyint(4) NOT NULL,
    MODIFY COLUMN `reagentCount6` tinyint(4) NOT NULL,
    MODIFY COLUMN `reagentCount7` tinyint(4) NOT NULL,
    MODIFY COLUMN `reagentCount8` tinyint(4) NOT NULL,
    MODIFY COLUMN `effect1DieSides` int(11) NOT NULL,
    MODIFY COLUMN `effect2DieSides` int(11) NOT NULL,
    MODIFY COLUMN `effect3DieSides` int(11) NOT NULL,
    MODIFY COLUMN `effect1CreateItemId` int(11) NOT NULL,
    MODIFY COLUMN `effect2CreateItemId` int(11) NOT NULL,
    MODIFY COLUMN `effect3CreateItemId` int(11) NOT NULL,
    MODIFY COLUMN `iconIdAlt` mediumint(8) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `rank_loc4` varchar(22) DEFAULT NULL,
    MODIFY COLUMN `description_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `description_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `buff_loc0` mediumtext DEFAULT NULL,
    MODIFY COLUMN `buff_loc2` mediumtext DEFAULT NULL,
    MODIFY COLUMN `buff_loc3` mediumtext DEFAULT NULL,
    MODIFY COLUMN `buff_loc4` mediumtext DEFAULT NULL,
    MODIFY COLUMN `buff_loc6` mediumtext DEFAULT NULL,
    MODIFY COLUMN `buff_loc8` mediumtext DEFAULT NULL,
    MODIFY COLUMN `spellDescriptionVariableId` smallint(6) NOT NULL;

ALTER TABLE `aowow_spell_sounds`
    MODIFY COLUMN `animation` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `ready` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `precast` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `cast` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `impact` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `state` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `statedone` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `channel` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `casterimpact` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `targetimpact` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `castertargeting` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `missiletargeting` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `instantarea` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `persistentarea` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `casterstate` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `targetstate` smallint(5) unsigned NOT NULL DEFAULT 0,
    MODIFY COLUMN `missile` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'not predicted by js',
    MODIFY COLUMN `impactarea` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'not predicted by js';

ALTER TABLE `aowow_taxinodes`
    MODIFY COLUMN `type` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0: scripted; 1: NPC; 2: GOBJECT',
    MODIFY COLUMN `name_loc0` varchar(59) DEFAULT NULL,
    MODIFY COLUMN `name_loc2` varchar(84) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(61) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(59) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(89) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(142) DEFAULT NULL;

ALTER TABLE `aowow_titles`
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `male_loc0` varchar(33) DEFAULT NULL,
    MODIFY COLUMN `male_loc2` varchar(35) DEFAULT NULL,
    MODIFY COLUMN `male_loc3` varchar(37) DEFAULT NULL,
    MODIFY COLUMN `male_loc4` varchar(37) DEFAULT NULL,
    MODIFY COLUMN `male_loc6` varchar(34) DEFAULT NULL,
    MODIFY COLUMN `male_loc8` varchar(37) DEFAULT NULL,
    MODIFY COLUMN `female_loc0` varchar(33) DEFAULT NULL,
    MODIFY COLUMN `female_loc2` varchar(35) DEFAULT NULL,
    MODIFY COLUMN `female_loc3` varchar(39) DEFAULT NULL,
    MODIFY COLUMN `female_loc4` varchar(39) DEFAULT NULL,
    MODIFY COLUMN `female_loc6` varchar(35) DEFAULT NULL,
    MODIFY COLUMN `female_loc8` varchar(41) DEFAULT NULL;

ALTER TABLE `aowow_user_ratings`
    MODIFY COLUMN `type` enum('COMMENT','GUIDE') NOT NULL;

ALTER TABLE `aowow_videos`
    MODIFY COLUMN `caption` mediumtext DEFAULT NULL;

ALTER TABLE `aowow_zones`
    MODIFY COLUMN `category` smallint(5) unsigned NOT NULL,
    MODIFY COLUMN `cuFlags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'see defines.php for flags',
    MODIFY COLUMN `attunementsN` mediumtext NOT NULL COMMENT 'space separated; type:typeId',
    MODIFY COLUMN `attunementsH` mediumtext NOT NULL COMMENT 'space separated; type:typeId',
    MODIFY COLUMN `name_loc0` varchar(120) DEFAULT NULL COMMENT 'Map Name',
    MODIFY COLUMN `name_loc2` varchar(120) DEFAULT NULL,
    MODIFY COLUMN `name_loc3` varchar(120) DEFAULT NULL,
    MODIFY COLUMN `name_loc4` varchar(120) DEFAULT NULL,
    MODIFY COLUMN `name_loc6` varchar(120) DEFAULT NULL,
    MODIFY COLUMN `name_loc8` varchar(120) DEFAULT NULL;

-- Some fresh-install schemas omitted the ordinary Chinese spell-name index.
SET @aowow_schema_index_ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aowow_spell' AND INDEX_NAME = 'idx_name4'), 'DO 0', 'ALTER TABLE `aowow_spell` ADD INDEX `idx_name4` (`name_loc4`)');
PREPARE aowow_schema_index_upgrade FROM @aowow_schema_index_ddl;
EXECUTE aowow_schema_index_upgrade;
DEALLOCATE PREPARE aowow_schema_index_upgrade;
