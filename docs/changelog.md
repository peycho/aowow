# Changelog

Installation and everyday commands are documented in the [README](../README.md).
The [test guide](../tests/README.md) covers regression checks, and the
[security review](aowow-security-review.md) records implementation details and
remaining deployment acceptance work. Commands below run from the checkout root.

## 2026-10-07

### SQL CI fixture collation

The SQL test runner now explicitly creates its seven disposable fixture databases
with `utf8mb4_unicode_ci` and normalizes their database defaults on reused runs.
Stock MySQL 8.4 selects `utf8mb4_0900_ai_ci` when the bootstrap specifies only
`CHARACTER SET utf8mb4`. The historical reconciliation fixture declares
`utf8mb4_unicode_ci`, while the initial definitions of `contribution_budget` and
`sql_update_journal` inherit the database default. This caused exact schema
comparison to report two table-collation differences in GitHub Actions.

Earlier local validation explicitly initialized the fixture database collation
and therefore missed this CI bootstrap difference. The fix changes test setup
only. Production schema, migration checksums and strict comparison remain intact;
no application database update is required for this change.

The original fatal error was reproduced with stock MySQL 8.4 database defaults.
After the fix, the full SQL group passed on disposable MySQL 8.4.10 and MariaDB
10.6.28 with PHP 8.5, including 950 legacy and 67 reconciliation/settings checks
per engine. The MySQL run covered the reused wrong-default database and fresh
fixture databases through the unmodified CI command. Repository lint also passed;
a hosted GitHub Actions rerun remains unverified.

### Missing screenshots switch

`missing_screenshots_enable` is a persistent boolean in Site Configuration → Site,
defaulting to Disabled on fresh installs and upgrades. Run `php aowow --update`
to apply `1791331200_03.sql`; existing explicit choices are retained.

Disabled mode removes Tools → Utilities → Missing Screenshots in all six languages
and returns HTTP 404 for direct `?missing-screenshots` requests before listing
generation. Other screenshot pages, uploads and utility menus remain available.
The switch uses loaded configuration, without additional database queries or
dataset builds; cached templates reflect its current value when rendered.
Deploy the endpoint, head template and navigation script together.

Enabling it retains the existing public, uncached listing and its 200-result limit
per entity type. That limit bounds returned rows, not rows examined by MySQL.
This change provides an explicit off switch; it does not optimize the enabled
listing or add request throttling. Regression coverage checks the guard before
listing generation, menu filtering alongside the profiler switch, cached headers,
settings saves without builds, fresh/legacy defaults and preservation of choices.

Validation passed the full PHP 8.5, JavaScript and lint groups, plus the full SQL
group on disposable MySQL 8.0.46, MySQL 8.4.10 and MariaDB 10.6.28 databases.
Each database passed 950 legacy-upgrade checks across 53 archived and 93 current
migrations, and 66 reconciliation/settings checks. These are fixture results;
production data and deployment have not been exercised.

### Goodies switches

`searchplugins_enable` and `searchbox_enable` are independent persistent boolean
settings in Site Configuration → Site. Both default to Enabled for fresh installs
and existing databases. Run `php aowow --update` to apply
`1791331200_02.sql`, which adds missing settings while retaining existing choices.

Disabling a setting hides its More → Goodies menu entry in all six languages and
returns HTTP 404 for its direct page URL (`?searchplugins` or `?searchbox`).
Search Plugins also controls the OpenSearch discovery link in page headers.
Normal database search and Tooltips remain available. Previously generated static
files, installed browser plugins and embedded search boxes remain usable; these
switches control page access and discovery, not revocation of static assets.

These checks use configuration already loaded at startup, without extra database
queries. Cached page templates read the current flags when rendered, so toggling
them needs no JavaScript rebuild. Deploy the head template, navigation script and
endpoints together. Regression coverage exercises both switches independently,
cached templates, localized menus, direct routes, fresh and legacy settings, and
preservation of existing choices through the ordinary journaled updater.

Validation passed the full PHP 8.5, JavaScript and lint groups, plus the full SQL
group on disposable MySQL 8.0.46, MySQL 8.4.10 and MariaDB 10.6.28 databases.
Each database passed 943 legacy-upgrade checks over 53 archived and 92 current
migrations, and 60 reconciliation/settings checks. These are fixture results;
the changes have not been deployed or tested against production data.

### Profiler switch and navigation

The existing `aowow_config.profiler_enable` setting is the single switch for
profiler navigation and routes. Set it to Disabled in Site Configuration →
Profiler, or use this query against the configured application database
(substitute the actual table prefix if necessary):

```sql
UPDATE aowow_config SET value = '0' WHERE `key` = 'profiler_enable';
```

Disabled navigation removes Tools → Profiler and its Characters, Guilds,
Arena Teams and New entries, along with Help → Profiler, in all six languages.
Profile/list/detail and action endpoints retain their server-side rejection;
profiler help now rejects direct access as well. Character browsing checks the
switch before interpreting realm URLs, avoiding realm discovery when disabled.
Talent calculators, item comparison and stat weighting remain available.
Stored profiles and community data are unchanged.

Normal startup already loads the application's configuration. Subsequent
`Cfg::get('PROFILER_ENABLE')` calls read the loaded PHP array: no extra SQL,
auth/character database probes or per-request realm discovery are added by this
switch. It is explicit and does not automatically change based on connection
availability. Menu visibility reads the current value while rendering, including
cached page templates, so toggling it needs no JavaScript rebuild for visibility.
The existing admin setting still rebuilds realm datasets when changed.

Deploy the updated navigation script, head template and endpoints together.
Regression coverage exercises cached-template toggling, all six languages,
direct route rejection before realm discovery, enabled routes and repeated
configuration reads without database calls.

### Historical schema reconciliation

Historical structure differences now have an explicit, guarded update path in
`1791331200_01.sql`; see [schema reconciliation](schema-reconciliation.md).
The initial SQL retains historical text capacity, includes the ordinary Chinese
spell-name index and agrees with the reconciled structural fixture and complete
legacy migration corpus. Unsafe conversions stop before this migration's first
ALTER. Strict execution and post-DDL verification retain the existing journal,
update locks and maintenance guarantees.
Taxi types now retain numeric codes 0 (scripted), 1 (NPC) and 2 (game object),
matching the generator and map pages. Valid scripted flights pass reconciliation
without deleting or changing their rows; unrecognized codes still stop safely.

## 2026-10-06

### Database schema validation

Run the independent setup utility from the checkout root:

```sh
php aowow --validate-schema
php aowow --validate-schema --log=/path/to/schema-validation.log
php aowow --validate-schema --help
```

The reference is `setup/sql/01-db_structure.sql` in the current checkout. The
validator parses its CREATE TABLE definitions and reads `information_schema`
and `SHOW CREATE TABLE` from the configured application database. It never runs
the reference SQL, migrations or generators, and does not change maintenance,
version markers, pending work or community records. It deliberately bypasses
configuration loading so an older `aowow_config` without `default` can be checked.
Only the application database is required; a SELECT-only account can run the
check. A configured connection must be able to see every application table's
metadata. Extracted inputs, configured locales and a world database are not needed.
Use this command alone; combining it with setup/update/build/SQL operations or
their parameters is rejected. The usual `--debug`, `--log` and `--help` options work.

The normal report groups differing attributes by table; `--debug` adds each
column/index/foreign-key finding. Counts represent differing attributes, so one
column can account for several findings. Initial-schema drift does not by itself
mean a migration failed or the database cannot run. The legacy SQL regression
audits the migrated fixture as well as fresh-install equality. The reconciliation
regression verifies agreement after migration `1791331200_01.sql`.
Some differences still affect application behavior: legacy account fields that
are `NOT NULL` without defaults can reject the current signup INSERT under
strict SQL mode. The SQL regression reproduces this using the real INSERT.
Other differences are intentional, including legacy spell text widths and
`MEDIUMTEXT` retained by migration `1791244800_01.sql`. Review each finding
against its consumer rather than treating every difference as a failed upgrade.

Every reference table is checked for missing/extra columns and their order,
types and lengths, signedness, nullability, defaults, auto-increment flags,
character sets/collations and generated expressions/storage. Index checks cover
names, uniqueness, type, ordered columns, prefix lengths and visibility. Foreign
keys include names, targets and update/delete actions. Table engines and default
character sets/collations are checked too. Unknown extra tables in the configured
application prefix are reported; other namespaces such as `dbc_*` are excluded.
Tables declared by the shipped `TrDBCcopy` generators are identified separately.
Their structures are not compared when no first-install definition exists; this
is explicit in the report and is not a claim that those structures passed.
Declaration discovery reads PHP tokens and the shipped Wrath DBC definitions
without executing generator code, loading configuration or opening extracted files.
An empty application prefix means all tables in that database are in scope.
Foreign-key targets follow the configured prefix as well.

Integer display widths, explicit/default BTREE and ASC, quoted numeric defaults,
MySQL's parenthesized literal TEXT defaults and matching charset prefixes,
`utf8`/`utf8mb3` aliases and equivalent
RESTRICT/NO ACTION rules are normalized. Tables that omit a charset/collation in
the initial SQL inherit the database defaults. Comments, row formats, current
auto-increment counters and physical storage options are excluded. Unsupported
custom DDL produces an explicit failed comparison rather than a false match.
Diagnostics identify objects and differing attributes without printing default
values, table contents, credentials or raw SQL.

Exit `0` means all 108 current reference tables match; exit `1` means structural
differences or a comparison failure. This is a comparison with the first-install
schema, not a repair command or proof of migration/generator completion. Custom
columns/indexes and wider legacy text columns retained by migrations can produce
intentional differences. Review those differences individually. Run against a
settled schema after updates/builds finish, since concurrent manual DDL can change
metadata during the check. Continue using `php aowow --update` for upgrades; do
not import initial SQL over an existing application database to resolve a report.

The focused PHP fixture checks parsing and the actual CLI dispatcher without a
database. The guarded SQL fixture imports the complete initial schema only into
a disposable database, then runs the real entrypoint with SELECT-only credentials,
tests schema drift and legacy configuration, and verifies preservation of
representative community records, maintenance, version metadata and journal data.
It also covers recognized DBC-copy tables and concise versus detailed reporting.

### Maintenance page metadata

The maintenance response now uses `Lang::meta('description', 'home')`, matching
the current locale files. The obsolete `homeDesc` lookup returned NULL and caused
a TypeError instead of displaying maintenance, including when an exception handler
constructed the response. HTTP 503, Retry-After, localized metadata and manual SEO
description precedence are preserved. This PHP correction needs no migration or
generated-data rebuild and does not change the maintenance setting.

The focused fixture exercises actual response constructors, maintenance generation,
metadata and locale files in all six shipped languages. Configuration, database
reads, identity and final output transport are synthetic. All 90 checks passed
on PHP 8.5, together with the complete PHP regression group and syntax checks.

### Legacy database upgrades

`php aowow --update` now discovers SQL in `setup/sql/updates` and its version
archives, orders all pending files by numeric date/part, and skips files at or
before the stored marker. Duplicate pending identifiers are rejected. The
confirmed legacy marker `1711739612 / 1 / sql=power / build=NULL` requires 53
files from `v2.0`, beginning with `1713730806_01.sql` and ending with
`1758578400_17.sql`. They precede the current series beginning with
`1759504522_01.sql`. Target `052513a6efce69175937323386627c851250e843`
requires 88 current files through `1791028800_01.sql`; this checkout has 90,
ending with `1791244800_01.sql`. Older archives are not replayed.

The CLI update bootstraps only maintenance and locale settings, so a legacy
configuration table without `default` can reach its introducing migration,
`1717076299_01.sql`. Full configuration is reloaded after SQL succeeds and
before generators execute. Other setup commands and web requests continue to
use the full configuration loader. CLI initialization still selects `--datasrc`
and locales before constructing generators.

Under the existing database update lock, the updater checks the actual version
table engine and converts MyISAM metadata to InnoDB without changing its row.
Migration checksums, durable statement progress, atomic version/journal
completion and refusal to replay unfinished SQL remain enforced. The enclosing
lock stays held through SQL/build generators and maintenance restoration. Pending
work is cleared only after verified completion; the archived migration itself
removes `power`. Failures retain maintenance and incomplete tasks. Success restores
the state observed under the lock, which may already have been maintenance.

Executing the complete historical corpus and checking its generator requests
exposed additional blockers: `1718468660_01.sql` queued the table name
`item_stats` instead of the shipped generator `stats`; `1718998554_01.sql`
and `1725025019_01.sql` queued retired `markup`/`locales` generators whose
output is now produced by `globaljs`;
`1760911493_01.sql` used a nonexistent profiler `flags` column instead of
`cuFlags`; `1768556688_01.sql` and `1770626911_01.sql` attempted multiple new
InnoDB FULLTEXT indexes in a single ALTER, rejected by MySQL. The column reference
and generator identifiers are corrected, and index additions are separate
statements with the same resulting indexes. Journals checksum the corrected
files when they are first applied;
existing applied entries are retained and skipped. An unfinished entry from an
older file still blocks replay, even after the SQL file has been corrected.

Archived screenshot/video migrations `1758578400_06.sql` and
`1758578400_08.sql` also retain legacy captions using `mediumtext`, avoiding
silent truncation to 200 characters with AoWoW's non-strict SQL sessions.
Existing text survives; new-submission interface limits and the fresh-install
schema remain unchanged.

Migration `1791244800_01.sql` also permits NULL in all 24 localized spell text
columns, matching the DBC reader's representation of empty strings. Historical
varchar lengths are widened where needed for current extracted text; larger
custom widths, character sets, collations and legacy MEDIUMTEXT capacity are
preserved. It queues spell, item, statistics, item-set, source and search data and
dependent datasets again, including work that an earlier generator incorrectly
acknowledged after failed writes. The same `--update` command applies this repair
to an already-migrated database and resumes retained build tasks.

SQL and file generators now report failure when their queries fail, even if the
database wrapper returns NULL and the generator returns true. Database failure
accounting survives suppressed or capped diagnostics and keeps pending tasks and
maintenance intact. Failed custom-data application also prevents completion.
Diagnostics continue to omit database values, raw SQL and exception messages.
Capture both output streams when rehearsing, for example:

```sh
php aowow --update --datasrc=/path/to/wrath-extraction/ --debug > legacy-update.log 2>&1
```

Before any pending migration SQL runs, the updater also checks legacy account
uniqueness when `1753572319_01.sql` is pending. That migration introduces unique
display names and email addresses; the legacy schema allows collisions, including
case/accent/trailing-space variants under its column collation. A conflict now
stops with a message identifying the field, without printing account values or
changing accounts. Empty email addresses are excluded because the migration
converts them to NULL. Metadata preparation and maintenance locking still occur;
no migration is journaled or version advanced on this preflight failure.

Recreating a rehearsal database removes a partially applied migration, but retains
any conflicting account values from the source backup. Resolve those associations
privately on the copy before another successful rehearsal. The updater cannot
choose which account owns an email without changing sign-in/recovery behavior,
so it does not merge accounts, erase addresses or weaken the unique constraint.

To rehearse, restore the application database and uploads into an isolated copy
and configure that checkout to use the restored application database. Keep the
original backup unchanged. Install locked Composer dependencies and use the
supported PHP CLI extensions. The update account needs the SELECT, INSERT,
UPDATE, DELETE, CREATE, ALTER, DROP and INDEX permissions required by the pending
migrations, plus access to the configured world database. It must be able to
create the InnoDB journal and alter legacy version metadata. Ensure sufficient
database space for engine/index rebuilds and writable generated-output directories.

Provide a compatible TrinityCore 3.3.5a world database independently of the
application database upgrade. This checkout requires world `cache_id >= 25101`
and expects schema revision `26091`; the numeric minimum alone does not establish
that all generator queries are compatible. Supply complete Wrath-format extracted
DBC, textures and other inputs needed by the queued generators for the configured
locales. Use an extraction already prepared according to the README. The application
updater does not upgrade the world database or extract client files.

From the isolated checkout root, the rehearsal command is:

```sh
php aowow --update --datasrc=/path/to/wrath-extraction/ --debug
```

Check the exit status, applied journal entries, final version, empty SQL/build
queues, generated assets, and community records/upload references before accepting
the rehearsal. If SQL was interrupted, restore a consistent copy or reconcile its
recorded partial work before retrying; do not blindly replay it. Generator failures
can be retried with the same command after correcting their prerequisites. A retry
preserves maintenance if the failed run left it enabled. Do not manually advance
the version, clear `power`, or load first-install SQL over an existing database.

The [legacy regression suite](../tests/README.md#legacy-database-upgrade-compatibility)
uses historical DDL and synthetic community records in disposable databases. It
executes the real CLI/kernel/configuration, every pending SQL migration and sync
accounting, substituting only generator data production. It covers both metadata
engines, already-current runs, checksum/progress accounting, SQL/configuration and
generator failures, retries, hard interruption, competing commands, and preserved
accounts, comments, custom articles, screenshots, videos, favorites, preferences
and upload references. Fixture success is not a migration of actual production
data or validation of real world data and extracted assets. Rehearsal must also
check production-specific content and custom schema changes against the upstream
schema conversions.

Validation used PHP 8.5.10, MySQL 8.4.10 and MariaDB 10.6.28. Both disposable
database engines passed 924 legacy checks, including account conflict preflight,
spell projection insertion and repair after incorrectly acknowledged SQL work,
and 366 migration/CLI checks. The complete SQL CI group and syntax and PHP/HTTP
regression groups passed, including 1,600 safe error-logging checks and 70 setup
runner checks. Earlier JavaScript/browser, Composer validation and platform
checks also passed (with existing package-metadata warnings); they were not
rerun for the spell schema repair. No production migration was performed.

### Upstream synchronization procedure

Added repository agent instructions and an [upstream-sync workflow](upstream-sync.md)
triggered by requests to check or sync with Sarjuuk. Checks fetch and review
current changes; requested imports preserve contributor credit through
cherry-picks or normal merges and validate on PHP 8.5. Each integration records
source revisions, decisions, adaptations and validation results.

### Sarjuuk upstream integration

Reviewed and incorporated `Sarjuuk/aowow` master at
`18ad819a3c7a0e072af1a80cacbeef6675d98e27`, starting from fork `dev` at
`346bccb073015fb1c5d8e76172eb8be558f746b6`. Both imports use `cherry-pick -x`
and retain Sarjuuk's author identity and dates, with source revisions in their
commit messages. The integrator remains the new committer.

| Upstream revision | Result revision | Decision and conflict resolution |
| --- | --- | --- |
| `a904053b9fbdc1796f6f1a76f1484aae394cb77a` | `457df7879db63d6fd6b00f1d27b2ad6bf2592835` | Adapted self-closing slash handling for generated markup. Retained the fork's attribute parser, empty-source rejection and standalone/paired break behavior; attribute and closing-anchor fixes were already present. |
| `18ad819a3c7a0e072af1a80cacbeef6675d98e27` | `7679d0831a2bb899b4ac67e0aa899f08ab27ff60` | Imported achievement-criteria name widening from 50 to 150 characters in all six locales, including the regeneration migration. Resolved fresh-setup version metadata to `1791142907 / 1`, preserving pending `globaljs` generation and existing fork configuration defaults. |

Focused regressions cover self-closing image/break markup, HTML and book output,
long Unicode criteria, row preservation, pending setup work and migration replay.
The existing fresh-install contribution regression now accepts newer schema
markers while still verifying that an already included migration is skipped.

Validation passed on PHP 8.5.10, Node 24 and disposable MySQL 8.4: repository
`lint`, `php`, `javascript`, `browser` and `sql` groups, each run through
`bash tests/ci/run.sh <group>`, plus `git diff --check`.
Focused suites passed 85 UI text/image/anchor/book checks
and 347 migration checks; the contribution SQL suite passed 653 checks.
Apache checks are not repeated because access rules are unchanged. Local
fixtures do not establish deployment acceptance.

For an existing installation, apply the source changes before running the
normal `php aowow --update` procedure. The new migration widens the columns and
queues `achievementcriteria` regeneration, retaining previously pending work.
The database migration and deployed data regeneration have not been executed
as part of this source integration.

### Item loot-tab labels

Item pages now retain trusted locale expressions through the loot-tab helper.
Previously, its string-only label parameter coerced `JsExpression` objects into
literal text, displaying labels such as `LANG.tab_disenchanting`. Contains,
Prospecting, Milling and Disenchanting now use their translated labels while
ordinary labels and loot names remain escaped data. Loot rows, percentages,
hidden columns and the loot-table initializer retain their existing behavior.

Regression checks execute the real item helper and Listview/Tabs serializers,
then evaluate their output against all six shipped JavaScript locale tables.
After applying the PHP change, previously cached item pages need to expire or
be refreshed. An administrator, bureaucrat or developer can request the existing
`?item=29254&refresh` URL to bypass both page-cache backends for that request.

## 2026-10-05

### Supplemented Wrath-format map data

Map generation accepts unnumbered instance maps without a terrain-floor flag,
including base-only layouts, and includes supplied base/courtyard textures
alongside numbered `DungeonMap.dbc` floors. A single image keeps `<zoneId>.jpg`;
multiple floors use `<zoneId>-<floor>.jpg`, including `-0` for the base. Generated
`Mapper.multiLevelZones` and localized floor labels follow the same floor order.
Unused floor strings are ignored and missing labels receive a localized level
name. Zone pages also detect `-0.jpg`, including the existing English fallback.

Existing Wrath fixups remain: Dalaran's floor association, Ahn'kahet's additional
floor, terrain floors for Black Temple, Sunwell and Ulduar, Stratholme's shifted
floor numbering, and Trial of the Champion's single-map filename. AoWoW consumes
3.3.5a/build 12340 DBC layouts and the existing extracted-file structure; it
does not convert later client formats or select another source format.

Keep the complete Wrath extraction and its supplemented files in one source
tree, preserving `<localeCode>/DBFilesClient/`,
`<localeCode>/Interface/WorldMap/<nameINT>/`, and
`<localeCode>/Interface/FrameXML/GlobalStrings.lua`. Base tiles use
`<nameINT>1.blp` through `<nameINT>12.blp`; numbered floors use
`<nameINT><floor>_1.blp` through `<nameINT><floor>_12.blp`. Existing PNG tiles
are also supported. Floor names use `DUNGEON_FLOOR_<nameINT><floor>` entries,
with `0` for a base and the existing Wrath numbering for special cases.

Reload the map metadata before regenerating images. For example, replacing
`/path/to/supplemented-wrath/` with the extraction root:

```sh
php aowow --dbc=worldmaparea,worldmapoverlay,dungeonmap --datasrc=/path/to/supplemented-wrath/ --locales=enUS
php aowow --build=img-maps --datasrc=/path/to/supplemented-wrath/ --locales=enUS --force
```

Use the same selected locales in both commands, such as `--locales=enUS,deDE`,
or omit `--locales` in both to use configured supported locales. Absolute
`--datasrc` paths work directly; relative paths should include the checkout-root
path, for example `--datasrc=setup/supplemented-wrath/`.
`--dbc` replaces the corresponding `dbc_*` metadata tables. `--build` normally
reuses populated DBC tables, so changing `--datasrc` or adding `--force` alone
does not reload their records. `--force` overwrites existing generated images;
the build also writes `datasets/<locale>/zones` from the supplied floor names.
No gameplay-table regeneration, database reconfiguration or deployment-tool
change is needed for these map supplements.

Regression fixtures assemble real tiled images in a temporary directory,
exercise base-only and mixed layouts, verify Wrath special cases and localized
labels, and execute the generated datasets with Mapper's floor menu.

### Instance map picker

The Maps page now fills its dungeon, raid and arena dropdowns from existing zone
metadata and generated images. Available Classic, Burning Crusade and Wrath
instances appear automatically; missing images, subzones and records excluded
from public lists stay out of the picker. Labels use the selected locale with
English name fallback and retain the existing alphabetical sorting.

Arenas appear under More using the existing localized arena category label.
They use zone category `9`; instance type `MAP_TYPE_ARENA` (`6`) is a separate
field and must not be used as a category filter. The generic map generator
already supports compatible base-only arena textures. Arenas use the same
image availability and locale fallback checks as dungeons and raids, with no
fixed list of arena IDs. Tests cover the five TBC/Wrath arenas using synthetic
Wrath-compatible records and tiles; actual extracted arena assets are required
to generate their images with the commands above.

Maps and zone pages share image detection for single maps and numbered floors,
including base/courtyard `-0` images and nonconsecutive floor numbers. An image
in the selected locale takes precedence over English. Mapper receives the
resolved image locale so English-only instance images are actually displayed
on other locale pages. Existing map links, pins, floor selection, continent
pickers and battleground pickers retain their behavior.

Apply the endpoint, helper, templates and static picker script together, and
regenerate the compiled Mapper JavaScript through the existing command:

```sh
php aowow --build=globaljs
```

Supplemented map metadata and images still use the reload/regeneration commands
above. The picker checks generated images on each request, so later image builds
do not require editing a zone list or rebuilding a separate picker dataset.

## 2026-10-04

### Setup debugging and repeat generation

Added `--debug` to setup and the `--update`, `--sync`, `--sql` and `--build`
commands. Diagnostics identify the active step and command, requested/available
and completed/missing generators, elapsed time, memory usage, failed file or
directory permissions, and exception types, codes and source locations.
Exception messages, SQL and argument values remain excluded. The flag does not
change the website's `DEBUG` setting. Combine it with `--log` to save diagnostics:

```sh
php aowow --setup --skip-sounds --debug --log=/tmp/aowow-setup-debug.log
```

SQL and build runners now retain registered generators after execution. An
update can rebuild `globaljs` and `tooltips` even when initial setup already
ran them in the same process. This fixes an upstream generator-removal bug
introduced on 2026-01-05; the stricter migration checks previously exposed it
as incomplete generation. Local references are still released and garbage
collection remains enabled where configured.

The setup failure message now identifies SQL updates or their required generators
rather than describing every update follow-up failure as a failed migration.
Pending-work accounting, maintenance handling and interruption protection remain
in place. Regression tests exercise repeat SQL/build runs, missing generators,
requirement failures, false returns, exception redaction and saved logs.

### Optional sound setup

Added `php aowow --setup --skip-sounds`. It skips the `sounds` database generator
and `soundfiles` audio-copying step. Original step numbers are preserved for
`--step` and saved `cache/setup/firstrun` progress. Include the flag again when
resuming an interrupted setup. Existing sound data and audio files are preserved
by the skipped steps.

With this option, extraction of `<localeCode>/Sound/` and audio reencoding can
be omitted. To add sounds later, extract and reencode the files described in the
[installation guide](../README.md#5-reencode-the-audio-files), then run:

```sh
php aowow --sql=sounds
php aowow --build=soundfiles
```

### Talent dataset encoding

Class and pet talent generators now use `Util::toJavaScript` for talent trees.
The security serializer change introduced explicit `JsExpression` callbacks,
but this caller still used the JSON-only API. That mismatch produced warnings
at `includes/utilities.php:613` and incomplete class registration files despite
successful file-write messages. Trusted profiler callbacks now remain executable
while talent names and tooltips retain safe text escaping.

Regenerate affected datasets with `php aowow --build=talentcalc`. Regression
fixtures execute the actual generator for every class and six locales, including
weapon restrictions, pet data and names/tooltips containing script delimiters.

### UI text attribute parsing

Fixed the SimpleHTML attribute parser so valid images and anchors render correctly,
including book pages. Empty, missing and malformed required attributes remain
rejected. Tests cover quoted/unquoted attributes, nested anchor content, image
source handling and HTML/markup/raw output.

### Apache access-rule compatibility

Updated the root, static and upload denial rules to block hidden paths,
script-like assets and private staging uploads while preserving application
routing, public assets, extensionless sounds and CGI-based PHP handlers. Removed
directives that required unavailable override permissions and the restrictive
query filter. The supported server configuration is:

Deploy the root, `static/` and `static/uploads/` `.htaccess` files together,
including on separate static hosts and upload aliases. Apache 2.4 needs
`mod_rewrite`, permitted symlink traversal (`FollowSymLinks` or
`SymLinksIfOwnerMatch`), and at least `AllowOverride Options FileInfo` for these
directories. If individual Options are restricted, permit `Indexes`, `Includes`
and `ExecCGI` for asset directories; the files only disable them. The root only
disables `Indexes`, preserving CGI-based PHP handlers. Enable `mod_headers` to apply the
response headers. Servers that ignore `.htaccess` need equivalent access rules.

Set `Options -MultiViews` in the server's corresponding `<Directory>` sections
to avoid implicit filename negotiation. This belongs in server configuration
because some hosts do not permit changing MultiViews through `.htaccess`.
The shipped rules do not require `AuthConfig` or `Indexes` override categories,
and root requests are explicitly rewritten to `index.php`. Query parameters,
including percent-encoded route keys, retain the application's routing behavior.

Keep PHP execution configured for the application entrypoint. Static and upload
directories use Apache's static handler and deny script-like filenames, including
multiple extensions, hidden paths and private pending/temp uploads. Public
screenshots, avatars, guide images, scripts, styles, widgets and extensionless
sounds remain accessible. PHP-FPM deployments must configure upload limits in
PHP (`upload_max_filesize = 20M`, `post_max_size = 25M`); the root `.htaccess`
sets these values only for mod_php.

## 2026-10-03

### Security hardening (revisions 53–68)

Implemented the following source controls, with regression coverage:

- Separate JSON data from explicit trusted JavaScript expressions; escape guide
  editor fields, headings and changelog contributor text.
- Add session CSRF protection, POST-only mutations, origin checks, explicit
  SameSite cookies and password reauthentication for email changes.
- Use cryptographically secure token generation; validate and consume unexpired
  recovery/activation tokens, revoke sessions after password resets and preserve
  existing role groups during activation.
- Bound password inputs and bcrypt work, upgrade eligible weaker hashes and reserve
  shared account/peer budgets before local password operations.
- Use validated SAPI peer addresses for IP attribution and attempt/ban checks.
  Forwarded headers cannot override the peer.
- Replace request, error and SQL dumps with safe diagnostic metadata.
- Deny direct access to pending/temp uploads, serve authenticated previews, bind
  screenshot completion to one owner/session claim and reencode bounded JPEG/PNG
  guide uploads with exclusive filenames.
- Authenticate caches, remove runtime PHP evaluation, constrain admin builds and
  preserve operator-selected filesystem permissions.
- Journal SQL updates and retain incomplete follow-up work; bound outbound video
  requests, contributions, reply paging, caches and disposable-data cleanup.
- Restrict return redirects to the configured application origin/path; gate browser
  diagnostics/configuration with exact operator IPs and deny legacy cross-domain
  access.

Existing installations require the password-budget migration
[1790899200_01.sql](../setup/sql/updates/1790899200_01.sql) and screenshot-claim
migration [1790985600_01.sql](../setup/sql/updates/1790985600_01.sql), followed by
the applicable normal updates and generated assets. Existing local sessions
require sign-in again after the password-version binding change. Pre-fix pending
tokens require expiry or reissue; historical logs and deployment permissions
need separate operator attention. Source regression checks do not establish
staging or production acceptance; see the [security review](aowow-security-review.md).

#### Cache authentication and admin builds (revision 65)

Copy [setup/security.php.example](../setup/security.php.example) to
`config/security.php` and replace the cache-key placeholder with a unique value
generated by `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Keep the file
and its parent directory deployment-owned, inaccessible over HTTP and unwritable
by PHP-FPM or other sites. Grant only the site's PHP identity read access; never
commit this file or its key. Missing/invalid keys disable response caching while
pages continue to generate normally. Revision 65 rejects older unsigned caches.

Admin rebuilds use `PHP_BINDIR/php` by default (`php.exe` on Windows). Set
`AOWOW_PHP_CLI` in the private file when a different absolute CLI path is needed;
verify PHP ≥ 8.4 and the site's required extensions/configuration. The binary and
checkout PHP must remain immutable to PHP-FPM. Grant write access only to approved
generated assets/datasets and upload/cache/session paths. New public assets use
`0644`/`0755`; existing permissions are preserved. The DB configurator writes
credentials with at most `0640`, so provision a private deployment/PHP group or
equivalent access. Precreate approved assets such as `robots.txt` so their parent
directory can remain read-only. See [the security review](aowow-security-review.md#a13--consequences-of-compromised-local-data-boundaries)
for the writable-file inventory and deployment acceptance checks.

#### SQL update accounting (revision 66)

From revision 66, `php aowow --update` stops on SQL/follow-up failures and exits
nonzero while preserving maintenance. The first update bootstraps an InnoDB
migration journal; use a deployment account with CREATE or have the schema owner
pre-provision its exact definition from `setup/sql/01-db_structure.sql`.
Keep deployment credentials out of runtime configuration. Back up the database
before updating. An interrupted migration blocks automatic replay because earlier
DDL/data changes may already be committed. Restore a consistent backup or
reconcile the recorded partial work before retrying; verify pending generators
and the site before lifting maintenance. See
[the update checks](../tests/README.md#sql-update-and-cli-failure-accounting-a14) and
[the recovery guidance](aowow-security-review.md#a14--migration-failure-accounting).

#### Contribution limits and disposable-data cleanup (revision 67)

Revision 67 adds [1791000000_01.sql](../setup/sql/updates/1791000000_01.sql), an
InnoDB contribution budget and paging/retention indexes. Apply it with the
deployment account, then finish the requested `globaljs` build. Deploy PHP,
templates and rebuilt JavaScript together; missing budget tables deny new
contributions. Runtime needs SELECT/INSERT/UPDATE on the budget, without DDL.
YouTube requests require cURL with asynchronous DNS and a working CA trust store.

From the checkout root, inspect cleanup before scheduling it:

```sh
php aowow --prune
php aowow --prune=apply
```

Preview deletes nothing. Apply processes at most 1,000 database rows per table
and examines 1,000 directory entries per root after positioning at its saved
cursor. Private cursors advance between runs; reaching a cursor may walk its
prior directory prefix, so very large historical trees need monitored cleanup.
It removes expired password/screenshot/daily-budget records, database errors
older than 30 days, recognized staging files older than two days, and recognized
file-cache entries older than seven days. Published/pending uploads, guide
images, articles, moderation records and permanent capacity counters are kept.
Schedule repeated apply runs as the site's PHP identity with DELETE on only the
four disposable tables and write access to its private cache/staging paths.
No scheduler is installed automatically. Inspect output and alert on failures.

Budgets charge attempts conservatively across workers; failed work is charged.
They track new usage from rollout and do not inventory historical storage.
Baseline existing files/database usage and provision volume/database limits,
private-log rotation and monitoring before launch. See
[A15](aowow-security-review.md#a15--outbound-calls-quotas-and-retention)
for exact limits and deployment acceptance.

#### Redirects and operator administration (revision 68)

Revision 68 confines locale and announcement return redirects to the origin and
application path in `HOST_URL`. Missing, malformed or off-origin Referers return
to the application home or announcement administration. Configure `HOST_URL`
with the site's canonical scheme, hostname, port and application path; ordinary
query links and subdirectory installations remain supported.

Browser `?admin=phpinfo`, `?admin=siteconfig` and its add/remove/update actions
now require an operator IP in addition to a signed-in ADMIN or DEV account.
The actions still require POST and CSRF protection. These five routes are
**disabled when the private operator policy is absent, empty or invalid**.
Other staff functions retain their existing access controls; trusted CLI
configuration remains available through `php aowow --configure`.

In the deployment-owned `config/security.php` described under cache authentication above, add or edit the
following constant. Preserve the existing cache key and optional CLI path;
do not overwrite an existing private file with the example. Replace these
documentation addresses with the exact addresses of the site's operators:

```php
define('AOWOW_OPERATOR_IPS', ['192.0.2.10', '2001:db8::10']);
```

The policy accepts at most 64 exact IPv4/IPv6 addresses. Any malformed entry
invalidates the entire policy. CIDRs, wildcards, hostnames, ports and forwarded
headers cannot grant access. IPv4-mapped IPv6 peers require their own explicit
entry. Keep the list empty if browser diagnostics/configuration are unused.
Only PHP's `REMOTE_ADDR` is checked. Do not allow a shared reverse proxy's
address: configure and verify trusted web-server peer handling first, or keep
these routes disabled and use the CLI. Never infer authorization from an
unverified `X-Forwarded-For` or `Forwarded` header.

From the checkout root, check the private policy without printing its contents:

```sh
php -r 'define("AOWOW_REVISION", 68); require "config/security.php"; require "includes/components/operatoraccess.class.php"; $ips = defined("AOWOW_OPERATOR_IPS") ? AOWOW_OPERATOR_IPS : []; if (!is_array($ips) || !Aowow\OperatorAccess::matches($ips[0] ?? null, $ips)) { fwrite(STDERR, "Operator policy disabled or invalid\n"); exit(1); } echo "Operator policy valid\n";'
```

This checks list syntax, not the deployed FPM peer address. In restricted
staging verify allowed ADMIN/DEV access, anonymous and ordinary-account denial,
403 responses for staff on unlisted addresses, spoofed-header denial, and
POST/CSRF enforcement on all three configuration actions. Verify `no-store`
responses and absence of shared proxy/CDN caching. Authorized diagnostics retain
native PHP environment/configuration output and must remain private.

Deploy [crossdomain.xml](../crossdomain.xml) with its explicit `none` policy to
**`/crossdomain.xml` at the origin root of every application/static host**.
For an application at `/db/`, its `/db/crossdomain.xml` alone does not install
the origin's master policy; serve the same file at `/crossdomain.xml` through
the vhost configuration. Verify GET/HEAD on each actual HTTP/HTTPS origin,
remove stale grants and purge cached copies of the former wildcard policy.
This follows the [Adobe policy specification](https://www.adobe.com/devnet-docs/acrobatetk/tools/AppSec/CrossDomain_PolicyFile_Specification.pdf):
`site-control` is effective in the origin-root master policy. The obsolete
Flash model viewer is unsupported. This XML policy does not configure CORS.

A16 requires no new SQL migration or JavaScript rebuild. Deploy its PHP files,
private configuration and XML together. Earlier migrations, generated assets
and hosting acceptance requirements still apply. Strict CSP has not been added;
the legacy UI's inline scripts/handlers and eval require a separate tested
migration. See [A16](aowow-security-review.md#a16--redirects-and-legacydiagnostic-exposure)
and [its regression checks](../tests/README.md#redirects-and-operator-administration-a16).

### External navigation configuration

Configure the Community menu links and the homepage GitHub link in
`config/config.php`, alongside the existing `$AoWoWconf` database settings:

```php
$AoWoWconf['externalLinks'] = [
    'forum'    => ['enabled' => true,  'url' => 'https://community.example.com/'],
    'blog'     => ['enabled' => false, 'url' => ''],
    'irc'      => ['enabled' => false, 'url' => ''],
    'facebook' => ['enabled' => true,  'url' => 'https://www.facebook.com/your-page'],
    'twitter'  => ['enabled' => true,  'url' => 'https://twitter.com/your-account'],
    'discord'  => ['enabled' => true,  'url' => 'https://discord.gg/your-invite'],
    'github'   => ['enabled' => true,  'url' => 'https://github.com/your-org/your-project']
];
```

Set `enabled` to the PHP boolean `false` to hide a link while keeping its URL.
Empty or invalid URLs also hide the link; URLs must be absolute HTTP or HTTPS
addresses without embedded credentials. Omitted links or fields retain their
original defaults; Discord is disabled by default with an empty URL.
Fresh database setup writes all seven default entries, and
later database configuration rewrites preserve this section. Existing installs
can add the section manually. Changes apply on the next page load, including
cached page templates, without rebuilding JavaScript or editing locale files.
Labels and icons remain localized. These deployment settings are independent
of the database-backed `?admin=siteconfig` settings.

### Wowhead integration and viewer retirement (revision 69)

Application navigation no longer adds Wowhead buttons or Blue Tracker links.
Crop selection borders use the shipped local images, including installations
under a URL subdirectory. `[forumrules]` uses the enabled `externalLinks.forum`
URL above; a disabled forum renders the localized label as plain text.

The Flash viewer, its controls, binaries, and generated JavaScript components
have been removed. Lists and pet galleries use local entity links. Account
settings save without viewer race/gender fields and ignore those fields from
older forms; stored `default_3dmodel` values remain inert. Legacy `[model]` and
`[modelviewer]` tags display an escaped label and localized retirement notice,
without loading scripts or requesting thumbnails. The six help menus omit the
viewer, while `?help=modelviewer` remains available with a localized banner above
its original article. Operator-owned generated model directories are untouched.

Article text, article seeds, guides, and comments are preserved. Existing articles
may still describe retired functionality; editorial changes remain outside this
release. Explicit author-selected Wowhead/PTR/beta markup sources and literal
external references remain supported, and ordinary game markup resolves locally.
Historical talent-URL import also remains supported.

Local tooltip endpoints, response formats, `$WowheadPower`, and widget attributes
are unchanged. Embed the rebuilt local widget using your own configured site:

```html
<script>var aowow_tooltips = {renamelinks: true, iconizelinks: true};</script>
<script src="https://database.example.com/static/widgets/power.js"></script>
<a href="https://database.example.com/?item=19019" data-wowhead="item=19019">Item</a>
```

Keep the established `rel`/`data-wowhead` and `data-disable-wowhead-tooltip`
attributes; their historical names are compatibility APIs. The widget loads
scripts, styles, icons, and tooltip data from this installation's configured
`HOST_URL`/`STATIC_URL`. Supply its extracted local game assets as usual.

Deliver PHP, templates, all six locale scripts, CSS, and rebuilt assets together:

1. Retain the previous deployment package and back up the database, including
   the `board_url` configuration row and migration/version metadata.
2. In disposable staging, apply the normal update from the checkout root:
   `php aowow --update`. [1791028800_01.sql](../setup/sql/updates/1791028800_01.sql)
   clears only a `board_url` value exactly equal to the shipped
   `http://www.wowhead.com/forums?board=` and requests `globaljs` and `tooltips`
   rebuilds. Customized values are preserved. Fresh installations omit this
   obsolete default. There are no article, guide, or comment updates.
3. Verify completed generators, or explicitly run
   `php aowow --build=globaljs,tooltips` with the staging site's configuration.
   Deploy `static/js/global.js` and `static/widgets/power.js` with the PHP/CSS/
   locale changes. Source fixtures use synthetic URLs and are not deployment
   assets. Revision 69 changes asset/cache revision keys; invalidate old page
   caches and any reverse-proxy/CDN cached pages/assets during the release.
4. Run the [retirement checks](../tests/README.md#viewer-retirement-and-local-assets)
   and staging detail/list/comparison/profile/account/talent pages with Wowhead
   and ZAM CDN traffic blocked. Check local embedding and extracted game assets,
   root/subdirectory crop borders, and all six locales before lifting maintenance.
5. For rollback, restore the coordinated previous package and backed-up affected
   configuration/version/journal state, then invalidate caches again.

Production deployment is a separate operational step. No replacement viewer,
new service, content cleanup, or article migration is included.

### Regression CI

[Security test CI](../.github/workflows/security-tests.yml) runs the complete suite
only on pushes with affected source, schema, dependency, policy, test and workflow
changes, using PHP 8.4/8.5, Node, disposable MySQL, headless Chrome and Apache
fixtures. See [the test guide](../tests/README.md#continuous-integration)
for coverage, prerequisites and local commands.

Standalone fixtures use explicit entrypoints and executed browser PASS markers.
The workflow disables native Memcached because the cache fixture supplies its own
stand-in. Regression coverage is documented in the [test guide](../tests/README.md).
