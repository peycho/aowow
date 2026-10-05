![AoWoW logo](static/images/logos/home.png)

# AoWoW

AoWoW is a World of Warcraft database application for version 3.3.5 (build 12340).
It provides PHP server code and a client interface based on the earlier database
website with the red smiling rocket. The project dates back to 2008; the original
creator is unknown. The PHP code was rewritten and the client JavaScript updated
from the original 2008 implementation.

The project takes no credit for the original client scripting, design or layout
and is not intended for commercial use.

- [Changelog](docs/changelog.md): security, configuration and setup changes.
- [Test guide](tests/README.md): regression coverage and local commands.
- [Security review](docs/aowow-security-review.md): findings and deployment acceptance.

## Requirements

- PHP ≥ 8.4 with SimpleXML, GD, MySQLi, mbstring, fileinfo, cURL and intl.
  cURL needs TLS verification and asynchronous DNS. GMP is required when using
  TrinityCore as the authentication source.
- MySQL ≥ 5.7.0 or MariaDB ≥ 10.6.4.
- [Composer](https://getcomposer.org/download/).
- [TDB 335.25101](https://github.com/TrinityCore/TrinityCore/releases/tag/TDB335.25101)
  with updates through [TrinityCore commit 8300a6d](https://github.com/TrinityCore/TrinityCore/commit/8300a6d8463aa862ae154392558c1f11dc2fce5d).
  Other database providers are unsupported.

Asset preparation uses [MPQExtractor](https://github.com/Sarjuuk/MPQExtractor),
[FFmpeg](https://ffmpeg.org/download.html) and optionally
[BLPConverter](https://github.com/Sarjuuk/BLPConverter). Consult their repositories
for build requirements, including CMake. Windows alternatives include
[MPQEditor](http://www.zezula.net/en/mpq/download.html) and
[BLPConverter](https://github.com/PatrickCyr/BLPConverter); add `php.exe` to `PATH`.
Audio processing may also require [lame](https://sourceforge.net/projects/lame/files/lame/3.99/)
or [vorbis-tools](https://www.xiph.org/downloads/) and its dependencies.

### TrinityCore spawn accuracy

Set these values on the TrinityCore server and run it once to improve spawn-point
accuracy:

```ini
Calculate.Creature.Zone.Area.Data = 1
Calculate.Gameobject.Zone.Area.Data = 1
```

## Install

Run the CLI commands from the AoWoW checkout root.

### Apache access rules

Deploy the root, `static/` and `static/uploads/` `.htaccess` files together.
Apache 2.4 requires `mod_rewrite`, permitted symlink traversal and at least
`AllowOverride Options FileInfo`. Enable `mod_headers` for response headers
and set `Options -MultiViews` in the server's corresponding `<Directory>` sections.
Configure PHP upload limits as `upload_max_filesize = 20M` and
`post_max_size = 25M`; the shipped root rules set these only for mod_php.
Servers that ignore `.htaccess` require equivalent rules. See the
[Apache configuration details](docs/changelog.md#apache-access-rule-compatibility).

### 1. Acquire the required repositories

```sh
git clone git@github.com:Sarjuuk/aowow.git aowow
git clone git@github.com:Sarjuuk/MPQExtractor.git MPQExtractor
```

### 2. Prepare the database

Use an account with full access to the AoWoW database and read access to the
referenced world database and optional auth/characters databases. Import files
01–03 from `setup/sql/` in order, for example:

```sh
mysql --default-character-set=utf8 -p {your-db-here} < setup/sql/01-db_structure.sql
```

For MySQL ≥ 8.4.0, optionally import `setup/sql/04-db_optional_mysql_only.sql`
for zhCN fulltext search, then enable it in settings after setup.

### 3. Server-created files

Setup creates generated directories with appropriate permissions. Grant the
setup identity write access to generated assets and datasets. The web identity
needs write access to caches and uploads, and to approved generated outputs if
browser admin rebuilds are enabled:

- `cache/`
- `static/download/`
- `static/widgets/`
- `static/js/`
- `static/uploads/`
- `static/images/wow/`
- `datasets/`

Keep application code and private configuration deployment-owned. Provision
`config/` for CLI setup, then keep it unwritable by PHP-FPM; see step 8.

### 4. Extract the client archives (MPQs)

Extract into `setup/mpqdata/`, preserving filenames and case. Apply named MPQs
first, followed by `patch.mpq`, `patch-[2–9].mpq` and `patch-[A–Z].mpq`, replacing
older files when prompted. Do not use MPQExtractor's `-c` switch.

For each locale, extract:

- `<localeCode>/DBFilesClient/`
- `<localeCode>/Interface/WorldMap/`
- `<localeCode>/Interface/FrameXML/GlobalStrings.lua`

Extract these once, still beneath a locale directory:

- `<localeCode>/Interface/TalentFrame/`
- `<localeCode>/Interface/Icons/`
- `<localeCode>/Interface/Spellbook/`
- `<localeCode>/Interface/PaperDoll/`
- `<localeCode>/Interface/Glues/CharacterCreate/`
- `<localeCode>/Interface/Pictures/`
- `<localeCode>/Interface/PvPRankBadges/`
- `<localeCode>/Interface/FlavorImages/`
- `<localeCode>/Interface/Calendar/Holidays/`
- `<localeCode>/Sound/` (omit when using `--skip-sounds`)

For an existing installation with supplemented Wrath-format map data, see the
[map metadata reload and image regeneration commands](docs/changelog.md#supplemented-wrath-format-map-data).

### 5. Reencode the audio files

Skip this step when using `--skip-sounds`. Otherwise reencode WAV files as
Ogg/Vorbis and ensure MP3 files identify as `audio/mpeg`:

- [Windows example](https://gist.github.com/Sarjuuk/d77b203f7b71d191509afddabad5fc9f)
- [Unix example](https://gist.github.com/Sarjuuk/1f05ef2affe49a7e7ca0fad7b01c081d)

### 6. Install dependencies with Composer

```sh
composer install --no-dev
```

Use `php composer.phar install --no-dev` if Composer is installed locally.

### 7. Run the initial setup from the CLI

```sh
php aowow --setup
```

Setup guides you through configuration, data generation and administrator
account creation. Map compilation and additional locales can take time.
Use `--skip-sounds` to omit sound setup, `--debug` for additional diagnostics
and `--log=<file>` to save output:

```sh
php aowow --setup --skip-sounds --debug --log=/tmp/aowow-setup-debug.log
```

Setup can resume after interruption. Include the sound/debug options again when
resuming; use `php aowow --setup --help` for available options and step information.
See the [setup change details](docs/changelog.md#setup-debugging-and-repeat-generation).

### 8. Configure private cache authentication and admin builds

Copy [setup/security.php.example](setup/security.php.example) to
`config/security.php` and replace the cache-key placeholder with a unique value:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Keep the file and its directory deployment-owned, inaccessible over HTTP and
unwritable by PHP-FPM. Grant the site's PHP identity read access. Missing or
invalid keys disable response caching.

Admin rebuilds use `PHP_BINDIR/php` by default. Set `AOWOW_PHP_CLI` in the private
file if another absolute PHP CLI path is required. Grant write access only to
approved generated assets. See the [cache and build configuration details](docs/changelog.md#cache-authentication-and-admin-builds-revision-65).

### 9. Apply updates with verified migration accounting

Back up the database and run:

```sh
php aowow --update
```

Use a deployment account with the required schema permissions. Updates stop on
SQL or generator failure and preserve pending work. An interrupted SQL migration
must be restored or reconciled before replay; a failed generator can leave an
already-applied migration with pending rebuilds. Check the journal and complete
pending work before lifting maintenance. See the
[update and recovery details](docs/changelog.md#sql-update-accounting-revision-66).

### 10. Contribution limits and disposable-data cleanup

Apply the normal updates and complete requested asset builds. Preview cleanup
before applying or scheduling it:

```sh
php aowow --prune
php aowow --prune=apply
```

Cleanup processes bounded batches and preserves published content. No scheduler
is installed automatically. See the [limits and retention details](docs/changelog.md#contribution-limits-and-disposable-data-cleanup-revision-67).

### 11. Restrict diagnostics/configuration and deploy the legacy deny policy

Set `HOST_URL` to the canonical site URL, including any application subdirectory.
Browser diagnostics and site-configuration routes require a signed-in ADMIN/DEV
account and an exact operator IP listed in the private `config/security.php`:

```php
define('AOWOW_OPERATOR_IPS', ['192.0.2.10', '2001:db8::10']);
```

Replace these documentation addresses with actual operator addresses, or leave
the list empty to disable those routes. Only `REMOTE_ADDR` is checked; do not
allow a shared proxy address. CLI configuration remains available through
`php aowow --configure`.

Serve [crossdomain.xml](crossdomain.xml) at `/crossdomain.xml` on every application
and static origin, including subdirectory installations. See the
[operator policy and deployment details](docs/changelog.md#redirects-and-operator-administration-revision-68).

## Configuration

Configure Community-menu links and the homepage GitHub link through
`$AoWoWconf['externalLinks']` in `config/config.php`. Entries support an `enabled`
boolean and an absolute HTTP/HTTPS `url`; disabled, empty or invalid entries are
hidden. See the [complete configuration example](docs/changelog.md#external-navigation-configuration).

Local tooltips and widgets remain supported. The Flash model viewer is retired.
See the [widget example and upgrade notes](docs/changelog.md#wowhead-integration-and-viewer-retirement-revision-69).

## Tests

The [security workflow](.github/workflows/security-tests.yml) runs PHP, JavaScript,
browser, SQL and Apache regressions on affected pushes. See the
[test guide](tests/README.md#continuous-integration) for prerequisites and commands.

## Troubleshooting

- **Setup fails:** rerun with `--debug` and `--log=<file>`. Inspect generator errors
  and pending work; consult the [update recovery details](docs/changelog.md#sql-update-accounting-revision-66)
  before retrying an interrupted SQL migration.
- **Styles are missing:** check the configured static URL and SSL settings using
  `php aowow --configure`.
- **JavaScript errors:** disable server/proxy features that automatically rewrite
  or minify JavaScript and CSS, then check the original assets.
- **Screenshot uploads fail or directories are exposed:** verify PHP upload limits
  and the server's access rules; see the Apache section above.
- **Custom entities are missing from search:** check the active locale and the
  corresponding world-database locale tables.
- **Book images are missing:** preserve case when extracting MPQs. Unix web servers
  require the image path to match the extracted filename.
- **Profiler searches appear incomplete:** character requests are queued and
  processed by `prQueue`; searches use AoWoW's cached profiles, which may be outdated.

## Thanks

- @mix: PHP scripts for parsing BLP and DBC files into images and tables.
- @LordJZ: the DBSimple wrapper and the original user-class design.
- @kliver: the initial screenshot-upload implementation.
- @Sarjuuk: project maintenance.

Special thanks to the original database website for its presentation and design.
