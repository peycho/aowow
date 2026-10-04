![logo](static/images/logos/home.png)


## Build Status
![fuck it ship it](https://forthebadge.com/badges/fuck-it-ship-it.svg)


## Introduction

AoWoW is a Database tool for World of Warcraft v3.3.5 (build 12340)
It is based upon the other famous Database tool for WoW, featuring the red smiling rocket.
While the first releases can be found as early as 2008, today it is impossible to say who created this project.
This is a complete rewrite of the serverside php code and update to the clientside javascripts from 2008 to something 2013ish.

I myself take no credit for the clientside scripting, design and layout that these php-scripts cater to.
Also, this project is not meant to be used for commercial purposes of any kind!


## External navigation links

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

## Wowhead integration and viewer retirement (revision 69)

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
   `php aowow --update`. [1791028800_01.sql](setup/sql/updates/1791028800_01.sql)
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
4. Run the [retirement checks](tests/README.md#viewer-retirement-and-local-assets)
   and staging detail/list/comparison/profile/account/talent pages with Wowhead
   and ZAM CDN traffic blocked. Check local embedding and extracted game assets,
   root/subdirectory crop borders, and all six locales before lifting maintenance.
5. For rollback, restore the coordinated previous package and backed-up affected
   configuration/version/journal state, then invalidate caches again.

Production deployment is a separate operational step. No replacement viewer,
new service, content cleanup, or article migration is included.

## Requirements

+ Webserver running PHP ≥ 8.4 including extensions:
  + [SimpleXML](https://www.php.net/manual/en/book.simplexml.php)
  + [GD](https://www.php.net/manual/en/book.image)
  + [MySQL Improved](https://www.php.net/manual/en/book.mysqli.php)
  + [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
  + [File Information](https://www.php.net/manual/en/book.fileinfo.php)
  + [cURL](https://www.php.net/manual/en/book.curl.php) with TLS verification and asynchronous DNS
  + [Internationalization](https://www.php.net/manual/en/book.intl.php)
  + [GNU Multiple Precision](https://www.php.net/manual/en/book.gmp.php) (When using TrinityCore as auth source)
+ MySQL ≥ 5.7.0 OR MariaDB ≥ 10.6.4 OR similar
+ [Composer](https://getcomposer.org/download/)
+ [TDB 335.25101](https://github.com/TrinityCore/TrinityCore/releases/tag/TDB335.25101) including updates up to [TrinityCore/TrinityCore@8300a6d](https://github.com/TrinityCore/TrinityCore/commit/8300a6d8463aa862ae154392558c1f11dc2fce5d) (no other other providers are supported at this time)
+ WIN: php.exe needs to be added to the `PATH` system variable, if it isn't already. 
+ Tools require cmake: Please refer to the individual repositories for detailed information
  + [MPQExtractor](https://github.com/Sarjuuk/MPQExtractor) / [FFmpeg](https://ffmpeg.org/download.html) / (optional: [BLPConverter](https://github.com/Sarjuuk/BLPConverter))
  + WIN users may find it easier to use these alternatives
     + [MPQEditor](http://www.zezula.net/en/mpq/download.html) / [FFmpeg](http://ffmpeg.zeranoe.com/builds/) / (optional: [BLPConverter](https://github.com/PatrickCyr/BLPConverter))

audio processing may require [lame](https://sourceforge.net/projects/lame/files/lame/3.99/) or [vorbis-tools](https://www.xiph.org/downloads/) (which may require libvorbis (which may require libogg))


#### Highly Recommended
+ setting the following configuration values on your TrinityCore server (and running it once) will greatly increase the accuracy of spawn points
  > Calculate.Creature.Zone.Area.Data = 1  
  > Calculate.Gameobject.Zone.Area.Data = 1


## Install

#### Apache access rules

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

#### 1. Acquire the required repositories
`git clone git@github.com:Sarjuuk/aowow.git aowow`  
`git clone git@github.com:Sarjuuk/MPQExtractor.git MPQExtractor`  

#### 2. Prepare the database  
Ensure that the account you are going to use has **full** access on the database AoWoW is going to occupy and ideally only **read** access on the world and optionally auth and characters databases you are going to reference.  
Import files 01 - 03 from `setup/sql/` in order into the AoWoW database `mysql --default-character-set=utf8 -p {your-db-here} < setup/sql/01-db_structure.sql`, etc.  

**Optional**: If you are using MySQL ≥ 8.4.0 and want to support fulltext search for locale zhCN, additionally import `setup/sql/04-db_optional_mysql_only.sql`. Enables this in settings after AoWoW has been set up.  

#### 3. Server created files
See to it, that the web server is able to write the following directories and their children. If they are missing, the setup will create them with appropriate permissions
 * `cache/`
 * `config/`
 * `static/download/`
 * `static/widgets/`
 * `static/js/`
 * `static/uploads/`
 * `static/images/wow/`
 * `datasets/`  
 
#### 4. Extract the client archives (MPQs)
Extract the following directories from the client archives into `setup/mpqdata/`, while maintaining patch order (named MPQs first -> patch.mpq -> patch-[2 -> 9].mpq -> patch-[A -> Z].mpq). Replace files from previous patches if asked to.  
⚠ DO NOT change the case of the extracted files. (i.e. don't use the `-c` switch when using the MPQExtractor)  
  
   .. for every locale you are going to use:
   > \<localeCode>/DBFilesClient/  
   > \<localeCode>/Interface/WorldMap/  
   > \<localeCode>/Interface/FrameXML/GlobalStrings.lua  
   
   .. once is enough (still apply the localeCode though):
   > \<localeCode>/Interface/TalentFrame/  
   > \<localeCode>/Interface/Icons/  
   > \<localeCode>/Interface/Spellbook/  
   > \<localeCode>/Interface/PaperDoll/  
   > \<localeCode>/Interface/Glues/CharacterCreate/  
   > \<localeCode>/Interface/Pictures  
   > \<localeCode>/Interface/PvPRankBadges  
   > \<localeCode>/Interface/FlavorImages  
   > \<localeCode>/Interface/Calendar/Holidays/  
   > \<localeCode>/Sound/  

#### 5. Reencode the audio files
If you will run setup with `--skip-sounds`, you can omit extraction of
`<localeCode>/Sound/` and skip this reencoding step.

WAV-files need to be reencoded as `ogg/vorbis` and some MP3s may identify themselves as `application/octet-stream` instead of `audio/mpeg`.  
 * [example for WIN](https://gist.github.com/Sarjuuk/d77b203f7b71d191509afddabad5fc9f)  
 * [example for \*nix](https://gist.github.com/Sarjuuk/1f05ef2affe49a7e7ca0fad7b01c081d)

#### 6. Install dependencies with composer
`php composer.phar install --no-dev` on a project level composer install, or  
`composer install --no-dev` on a system level composer install

#### 7. Run the initial setup from the CLI
`php aowow --setup`.  
This should guide you through with minimal input required from your end, but will take some time though, especially compiling the zone-images. Use it to familiarize yourself with the other functions this setup has. Yes, I'm dead serious: *Go read the code!* It will help you understand how to configure AoWoW and keep it in sync with your world database.  
When you've created your admin account you are done.

To install without sounds, run `php aowow --setup --skip-sounds`. This skips the
`sounds` database generator and `soundfiles` audio copying step. Step numbers
remain unchanged; include `--skip-sounds` again when resuming an interrupted
setup. Existing sound data and audio files are preserved by the skipped steps.
To add sounds later, extract and reencode the audio as described above, then run
`php aowow --sql=sounds` followed by `php aowow --build=soundfiles`.

For additional diagnostics, add `--debug`, for example:

```sh
php aowow --setup --skip-sounds --debug --log=/tmp/aowow-setup-debug.log
```

The option also works with `--update`, `--sync`, `--sql` and `--build`. It reports
the active step/command, requested and available generators, completed/missing
work, timings, file permission failures and exception types/source locations.
Exception messages, SQL and argument values remain excluded. It does not change
the site's `DEBUG` setting. Keep the optional log outside publicly served paths.

#### 8. Configure private cache authentication and admin builds

Copy [setup/security.php.example](setup/security.php.example) to
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
directory can remain read-only. See [the security review](docs/aowow-security-review.md#a13--consequences-of-compromised-local-data-boundaries)
for the writable-file inventory and deployment acceptance checks.

#### 9. Apply updates with verified migration accounting

From revision 66, `php aowow --update` stops on SQL/follow-up failures and exits
nonzero while preserving maintenance. The first update bootstraps an InnoDB
migration journal; use a deployment account with CREATE or have the schema owner
pre-provision its exact definition from `setup/sql/01-db_structure.sql`.
Keep deployment credentials out of runtime configuration. Back up the database
before updating. An interrupted migration blocks automatic replay because earlier
DDL/data changes may already be committed. Restore a consistent backup or
reconcile the recorded partial work before retrying; verify pending generators
and the site before lifting maintenance. See
[the update checks](tests/README.md#sql-update-and-cli-failure-accounting-a14) and
[the recovery guidance](docs/aowow-security-review.md#a14--migration-failure-accounting).


#### 10. Contribution limits and disposable-data cleanup

Revision 67 adds [1791000000_01.sql](setup/sql/updates/1791000000_01.sql), an
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
[A15](docs/aowow-security-review.md#a15--outbound-calls-quotas-and-retention)
for exact limits and deployment acceptance.

#### 11. Restrict diagnostics/configuration and deploy the legacy deny policy

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

In the deployment-owned `config/security.php` from step 8, add or edit the
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

Deploy [crossdomain.xml](crossdomain.xml) with its explicit `none` policy to
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
migration. See [A16](docs/aowow-security-review.md#a16--redirects-and-legacydiagnostic-exposure)
and [its regression checks](tests/README.md#redirects-and-operator-administration-a16).

## Tests

[Security test CI](.github/workflows/security-tests.yml) runs the complete suite
only on pushes with affected source, schema, dependency, policy, test and workflow
changes, using PHP 8.4/8.5, Node, disposable MySQL, headless Chrome and Apache
fixtures. See [the test guide](tests/README.md#continuous-integration)
for coverage, prerequisites and local commands.

## Troubleshooting

Q: The Page appears white, without any styles.  
A: The static content is not being displayed. You are either using SSL and AoWoW is unable to detect it or STATIC_HOST is not defined properly. Either way this can be fixed via config `php aowow --configure`

Q: Fatal error: Can't inherit abstract function \<functionName> (previously declared abstract in \<className>) in \<path>  
A: You are using multiple cache optimization modules for php that are in conflict with each other. (Zend OPcache, XCache, ..) Disable all but one.

Q: How can i get the modelviewer to work?  
A: You can't anymore. Wowhead switched from Flash to WebGL (as they should) and moved or deleted the old files in the process.

Q: I'm getting random javascript errors!  
A: Some server configurations or external services (like Cloudflare) come with modules, that automatically minify js and css files. Sometimes they break in the process. Disable the module in this case.

Q: Some search results within the profiler act rather strange. How does it work?  
A: Whenever you try to view a new character, AoWoW needs to fetch it first. Since the data is structured for the needs of TrinityCore and not for easy viewing, AoWoW needs to save and restructure it locally. To this end, every char request is placed in a queue. While the queue is not empty, a single instance of `prQueue` is run in the background as not to overwhelm the characters database with requests. This also means complex search queries can't be run against the characters database and have to use the incomplete/outdated cached profiles of AoWoW.

Q: Screenshot upload fails, because the file size is too large and/or the subdirectories are visible from the web!  
A: That's a web server configuration issue. If you are using Apache you may need to [enable the use of .htaccess](http://httpd.apache.org/docs/2.4/de/mod/core.html#allowoverride). Other servers require individual configuration.  

Q: An Item, Quest or NPC i added or edited can't be searched. Why?  
A: A search is only conducted against the currently used locale. You may have only edited the name field in the base table instead of adding multiple strings into the appropriate \*_locale tables. In this case searches in a non-english locale are run against an empty name field.  

Q: Images embedded in readable Items / Gameobjects are missing!  
A: Check that you didn't change the case of the files extracted from the mpq archives. The paths stored in TDBs page_text table are used as \<img> src and while AoWoW is case agnostic and will happily process all files, a web server runnig on a unix system will only serve files matching the exact case.  


## Thanks

@mix: for providing the php-script to parse .blp and .dbc into usable images and tables  
@LordJZ: the wrapper-class for DBSimple; the basic idea for the user-class  
@kliver: basic implementation of screenshot uploads  
@Sarjuuk: maintainer of the project  


## Special Thanks
Said website with the red smiling rocket, for providing this beautiful website!
Please do not regard this project as blatant rip-off, rather as "We do really liked your presentation, but since time and content progresses, you are sadly no longer supplying the data we need".

![uses badges](https://forthebadge.com/badges/uses-badges.svg)
