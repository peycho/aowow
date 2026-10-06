-- Reviewed legacy AoWoW account cleanup, 2026-10-06.
-- Run only on an isolated, restored application database with site writes stopped.
-- Keep an unchanged backup and save a checkpoint before applying this file.
--
-- Scope:
--   Batch 1: reviewed criteria; 438 deletions were confirmed on the test copy.
--            Its exact account IDs were not captured. A different restored copy
--            can select a different count; inspect the ID preview below.
--   Batch 2: 15 exact IDs passed the relationship audit; deletion was not confirmed.
--   Earlier approximately 500 manually removed accounts: IDs and exact selection
--            were not supplied, so their deletion is not reconstructed here.
--
-- These are the same nine ownership/relationship checks used for the reviewed
-- batches. They do not cover custom tables, all editor/moderator references,
-- standalone upload files, or every account setting. Inspect those separately
-- where relevant. Remaining accounts and duplicate-email ownership are manual.
--
-- Use the mysql/MariaDB client with the restored database selected:
--   SOURCE docs/sql/legacy-account-cleanup.sql;
-- The file ends with ROLLBACK: its default execution previews the deletion effects.
-- After reviewing the IDs and counts, change ONLY the final ROLLBACK to COMMIT
-- to apply the cleanup to that copy. Do not use mysql --force.
--
-- The review reference is fixed at 2026-10-06 00:00:00 UTC (before the reviewed
-- operations that day). This conservative boundary prevents later runs from
-- progressively including newer registrations. Boundary rows may need review.
-- No passwords, emails, tokens or login names are stored in this file or printed.
-- It does not modify version/journal metadata or run any upgrade migrations.

SET @aowow_cleanup_reference = 1791244800;
SET @aowow_cleanup_ready = (
    @@SESSION.foreign_key_checks = 1
    AND COALESCE((
        SELECT ENGINE = 'InnoDB'
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'aowow_account'
    ), 0)
    AND EXISTS (
        SELECT 1 FROM aowow_dbversion
        WHERE `date` = 1711739612 AND `part` = 1
    )
);

SELECT @aowow_cleanup_ready AS preconditions_met;
-- A result of 0 blocks both DELETE statements. Check the selected database,
-- legacy marker, account engine and enabled foreign-key checks before proceeding.

START TRANSACTION;

-- Account IDs that currently pass the reviewed criteria.
SELECT
    CASE WHEN a.id IN (929,985,1021,1154,1201,1205,1339,1340,
      1348,1405,1447,1451,1455,1456,1457)
         THEN 'batch_2' ELSE 'batch_1' END AS cleanup_batch,
    a.id AS account_id
FROM aowow_account AS a
WHERE @aowow_cleanup_ready = 1
  AND a.status = 1
  AND a.statusTimer > 0
  AND a.statusTimer < @aowow_cleanup_reference
  AND a.joinDate > 0
  AND a.joinDate < @aowow_cleanup_reference - 30 * 86400
  AND a.extId = 0
  AND a.userPerms = 0
  AND a.userGroups IN (0, 16384)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_comments WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_guides WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_guides_changelog WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_screenshots WHERE userIdOwner = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_videos WHERE userIdOwner = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_user_ratings WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_account_favorites WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_account_profiles WHERE accountId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_reports WHERE userId = a.id)
  AND (
      (a.curLogin = 0 AND a.prevLogin = 0)
      OR a.id IN (929,985,1021,1154,1201,1205,1339,1340,
      1348,1405,1447,1451,1455,1456,1457)
  )
ORDER BY cleanup_batch, a.id;

-- Batch 1: old, expired, unactivated accounts with neither login recorded.
-- Keep the fixed Batch 2 IDs in their own batch even if timestamps were changed.
DELETE a
FROM aowow_account AS a
WHERE @aowow_cleanup_ready = 1
  AND a.status = 1
  AND a.statusTimer > 0
  AND a.statusTimer < @aowow_cleanup_reference
  AND a.joinDate > 0
  AND a.joinDate < @aowow_cleanup_reference - 30 * 86400
  AND a.extId = 0
  AND a.userPerms = 0
  AND a.userGroups IN (0, 16384)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_comments WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_guides WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_guides_changelog WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_screenshots WHERE userIdOwner = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_videos WHERE userIdOwner = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_user_ratings WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_account_favorites WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_account_profiles WHERE accountId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_reports WHERE userId = a.id)
  AND a.curLogin = 0
  AND a.prevLogin = 0
  AND a.id NOT IN (929,985,1021,1154,1201,1205,1339,1340,
      1348,1405,1447,1451,1455,1456,1457);
SET @aowow_cleanup_batch_1 = ROW_COUNT();
SELECT @aowow_cleanup_batch_1 AS batch_1_deleted_accounts;

-- Batch 2: the 15 reviewed expired registrations, including recorded logins.
DELETE a
FROM aowow_account AS a
WHERE @aowow_cleanup_ready = 1
  AND a.status = 1
  AND a.statusTimer > 0
  AND a.statusTimer < @aowow_cleanup_reference
  AND a.joinDate > 0
  AND a.joinDate < @aowow_cleanup_reference - 30 * 86400
  AND a.extId = 0
  AND a.userPerms = 0
  AND a.userGroups IN (0, 16384)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_comments WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_guides WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_guides_changelog WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_screenshots WHERE userIdOwner = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_videos WHERE userIdOwner = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_user_ratings WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_account_favorites WHERE userId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_account_profiles WHERE accountId = a.id)
  AND NOT EXISTS (
      SELECT 1 FROM aowow_reports WHERE userId = a.id)
  AND a.id IN (929,985,1021,1154,1201,1205,1339,1340,
      1348,1405,1447,1451,1455,1456,1457);
SET @aowow_cleanup_batch_2 = ROW_COUNT();
SELECT @aowow_cleanup_batch_2 AS batch_2_deleted_accounts;

SELECT
    @aowow_cleanup_batch_1 AS batch_1_deleted_accounts,
    @aowow_cleanup_batch_2 AS batch_2_deleted_accounts,
    @aowow_cleanup_batch_1 + @aowow_cleanup_batch_2 AS total_deleted_accounts;

-- Counts are observed inside the transaction. The default run restores all rows.
-- An already-cleaned copy may report zero; this does not identify the earlier
-- manual deletions or prove that the original production database was cleaned.
ROLLBACK;
