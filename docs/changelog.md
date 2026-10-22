# Changelog

Current setup and everyday commands are documented in the
[installation](installation.md), [configuration](configuration.md) and
[troubleshooting](troubleshooting.md) guides. Browse the
[documentation index](README.md) for other topics.
The [test guide](../tests/README.md) covers regression checks, and the
[security review](aowow-security-review.md) records implementation details and
remaining deployment acceptance work. Commands below run from the checkout root.

## 2026-10-22 — Canonical sitemaps and public guide discovery

Sitemaps now use the shared canonical URL policy across enabled languages and
include approved guide bodies, populated database browse roots and guide category
hubs. Pending guide edits retain the previously approved revision. Archived or
missing guide bodies, staff-only area triggers, disabled sounds and
hidden/unavailable records are excluded.

Removed the invented 2015 update date and unreliable community submission dates,
including the incorrect screenshot/video flag joins. Only known, non-future guide
approval dates supply `lastmod`. Stable 10,000-entry chunks, UTF-8 XML escaping and
strict page validation bound sitemap output. Publication changes bypass
application page caching. See the [SEO roadmap](seo-plan.md) for validation and
deployment acceptance and the [test guide](../tests/README.md) for the disposable
sitemap SQL fixtures.

Validated lint, PHP/HTTP, JavaScript, Chromium, disposable SQL and all eight Apache
policy combinations. The new sitemap fixture passed 20,134 checks. Populated
local acceptance covered 34 child sitemaps, 154,199 unique entries, 93 public
page/canonical checks and nine invalid-request checks. Production deployment and
search-engine acceptance remain separate.

## 2026-10-22 — Development setup skips audio by default

Added `make dev-setup`. Development CLI setup commands (`--setup` and `-s`)
automatically include `--skip-sounds`, including resumed runs, so local client
inputs do not need the audio archive. Explicit `--sql=sounds` and
`--build=soundfiles` commands remain available. Production setup behavior is
unchanged. See [Docker development](docker-development.md).

Development maintenance commands now forward their loopback HTTP port to the
running web service, allowing localhost configuration checks without publishing
another host port. Configuration edits also discard consumed CLI operands before
scheduling rebuilds, so changing a setting with no rebuild work returns success.

Validated the complete PHP/HTTP gate (including 74 setup debug/configuration
checks), isolated Docker source-sync/CLI HTTP/exit-status checks, five CLI
argument/default/resume cases, 25 sound setup checks and four invalid-port cases.
Local game inputs remain ignored by Git and excluded from Docker build contexts
and source synchronization.

## 2026-10-22 — Initial HTML content and category pagination

Articles/guides, quick facts, sanitized item/spell tooltips, source links,
navigation and simple breadcrumbs now render in the initial HTML. Location
links work without JavaScript, homepage text is included, and an inline notice
replaces the JavaScript-disabled overlay. A bounded PHP markup renderer escapes
raw HTML, restricts URLs/images and preserves author permissions. Existing
JavaScript enhances the same content; lazy list tabs retain their fallback until
initialization succeeds.

The 23 database/guide list implementations support stable 50-row SQL pages with
separate cache entries, crawlable Previous/Next links and individual canonicals.
Invalid/unavailable pages return 404. Grouped item views preserve their first-page
behavior and reject later pages; event calendars retain their existing dataset.

`make dev-test` passed lint, PHP/HTTP, JavaScript, Chromium, disposable SQL and
all eight Apache policy combinations. Final focused coverage includes 288
initial-HTML/markup/permission/URL checks across six locales, 19 browser checks
with normal/blocked scripting, and 160 SQL/HTTP pagination checks. The final
rendering adjustments also passed the complete PHP/HTTP gate. See the
[SEO roadmap](seo-plan.md) for scope and deployment acceptance. Deploy PHP/templates, CSS and the new
`static/js/server-content.js` together and invalidate affected page caches.
The generated JavaScript bundle is unchanged. Production and indexing acceptance
remain pending.

## 2026-10-22 — SEO indexing, locale and guide visibility

New anonymous sessions use the configured English/fallback default while explicit
saved preferences remain available. Locale overrides accept only supported,
enabled values, and English SEO URLs consolidate `locale=0` into bare routes.
Homepage language-menu state follows the rendered language.

Shared rendering now applies noindex rules to search, private/editorial routes,
previews, archived guides, empty lists and unreviewed filters. Seventeen reviewed
filtered landing pages retain indexing eligibility. Language alternates represent
equivalent enabled database locales; guide aliases use their authored-language
canonical without claiming nonexistent translations. Cached metadata follows
the current policy.

Guide pages/tooltips recheck publication and permissions on every request rather
than using shared caches. Public views select the exact approved article;
historical/preview access remains restricted to owner/staff. Missing revisions
return 404 without falling back to drafts. Pending guide titles/descriptions are
hidden from public metadata because the existing schema stores body revisions
only; the public page temporarily uses `Guide #ID` and approved-body description.

Deploy PHP/templates together, regenerate robots with `php aowow --build=robots`
using the normal output destination, and invalidate affected page/tooltip caches.
The robots template permits crawlers to read noindex directives and retains the
random-route restriction. See the [SEO roadmap](seo-plan.md) for checkpoint
scope, validation and outstanding deployment acceptance. No production operation
is performed by this change.

All `make dev-test` gates passed, including disposable SQL and eight Apache
policy combinations. The final metadata adjustment also passed the complete
PHP/HTTP gate. SEO suites total 1,493 checks, and a local report-derived check
confirmed all 17 reviewed filter URLs retain indexing eligibility.

## 2026-10-22 — SEO canonical URL foundation

SEO URLs now discard tracking and irrelevant query parameters, retain the
routing pair first, encode values and sort content modifiers. Existing filters,
guide revisions and supported explicit locales remain represented. Canonical
and language-alternate attributes escape URLs; Open Graph and JSON-LD identity
refresh for each request, including page-cache hits. Manual descriptions remain
intact.

Added the [SEO roadmap](seo-plan.md) to the documentation index with incremental
implementation checkpoints. Deployment-specific Search Console exports and
baseline reports belong in the ignored `reports/` directory. Indexing policy,
anonymous locale defaults, guide aliases and translation equivalence are the
next checkpoint; rendering, pagination and sitemap work follow separately.

`make dev-test` passed all gates: lint, PHP/HTTP, JavaScript, Chromium,
disposable SQL and eight Apache policy combinations. This includes 148 new
SEO URL and cached-head checks. Deploy PHP and the head template together and
invalidate affected page caches through the normal deployment workflow.
Deployed acceptance and search-engine canonical selection remain unverified.

## 2026-10-22 — Optional Docker development environment

Removed the newer Compose Watch `initial_sync` field, which prevented CI runners
with older Compose schemas from running development commands. The startup source
snapshot already provides those files. CI now logs its Compose version and
validates the full development configuration before building images.

Added a separate development image and Compose overlay with Composer dev
dependencies, Node 24/npm, Python, Git and Chromium. `make dev-watch` refreshes a
cached source snapshot before starting live updates, including edits made while
the watcher was stopped. Development HTTP defaults to `127.0.0.1:8011`.

`make dev-test` snapshots working application source and tests and runs the
existing regression gates. Each invocation gets its own disposable SQL fixture
project; application configuration and database credentials are excluded. Apache
fixtures run through the host Docker CLI on port 8012, with no Docker socket in
PHP. The repository agent instructions document these commands for future work.
See the [development guide](docker-development.md).

Local development image build, all regression gates and live-update/restart
checks passed. Full site setup still requires compatible TrinityCore data and
extracted client assets.

## 2026-10-22 — Docker installation and maintenance

Added a PHP 8.5 Apache image, optional MySQL 8.4 services for the application and
TrinityCore databases, persistent named volumes, guarded first-install imports,
private configuration generation, healthchecks, maintenance targets and CI smoke
validation. Local HTTP defaults to `127.0.0.1:8010`; database ports are unpublished.
See the [Docker guide](docker.md) for installation, secrets, external databases,
updates and recovery.

The web root excludes private configuration and application sources. Web code is
read-only; maintenance uses the web UID for generated files and restores private
configuration ownership after exit. Setup now preserves explicit database ports,
and the robots generator accepts `AOWOW_ROBOTS_FILE` for persistent output outside
an immutable application root. Existing non-container paths remain supported.

Local image build, PHP/HTTP and SQL regression gates, all eight Apache policy
configurations, and isolated Docker smoke checks passed. Disposable TrinityCore
fixtures verify connections and read-only grants; full setup with compatible
world data and extracted client assets remains deployment acceptance work.

## 2026-10-22 — Sarjuuk integration and display compatibility (revision 75)

Reviewed [Sarjuuk/aowow](https://github.com/Sarjuuk/aowow) `master` at
`80a3f5861cdb43ac06e4afbe97e78c0ffcb619fd`, starting from fork `dev` at
`316a684ce411cd16d636ee6270e273159da2bc4d`. Integration branch:
`upstream-sync/20261022`. The 18 newer source commits use `cherry-pick -x`;
original authors, author dates, coauthors and differing original committer
credit are retained. Older imports are already accounted for by the prior
review records and the October 15 integration.

| Upstream revision | Result revision | Decision and adaptation |
| --- | --- | --- |
| `a108bcaf65b3db347f9b280bfdb5bff3289e9715` | `09291a262e07ff3c22a05ac9d9368e88ccebfaff` | Incorporate delivered-letter links for esES/frFR/ruRU, preserving sender and delay slots. |
| `14ba844282e7ebe6cdf152c030879652c36f8298` | `d8c7f5593a8bfdbb6d35de80d2a7175861bd0c35` | Incorporate valid color/icon markup; replace the stale broken-markup assertion and exercise the renderer. |
| `6613ae9ff018b80af08efffe93f1c520e0b68ede` | `8ba4ad1c8b11bb2e0247af2b1cd7ff66e31d73de` | Incorporate the deDE Talk target conditional correction. |
| `123f9f300c505b37b6d8c9624de10a6849cc8ed7` | `1b61713db3b91824ffa2211d65590437bfa2098e` | Incorporate actual Talk text-presence conditions in the other five locales. |
| `96eb82056ce6327a422c64ebd01a5f2edcefb66f` | `a312e9b954d5bea07c6d8fd5ebd0f68449b0938e` | Incorporate specific-spell versus all-auras descriptions in all six locales. |
| `184cd9e1d16747f947dbd108a5b52dc3ca402eb8` | `c2184a30d07b8e71dadb3a1f2b9dd84bfd880b51` | Incorporate the complete worldstate opening tag. |
| `6887a75013d49740ec5412c74c8e3682d1987e91` | `5bd828c1de434da02480b827b7bd934eeb6410ae` | Incorporate array-based hyperlink formatting for HTML, markup and raw output. |
| `d9b73b8088a1878f104fc3784a4aabd07d442de2` | `fc749cedd527288185aba7c0f78e260377f9d456` | Adapt nearest-flightmaster generation and its migration with fresh-setup marker advancement and retained pending work. |
| `061d1826782ea11c70ba4c7eb4048badbb1a1438` | `31b4d1f2fb21335b26298010c2b34c47c3f24bda` | Adapt aura 191 percentages to preserve the zero-DieSides convention. |
| `8f54c6467b6920b25e5645021764bbaaa284f0ad` | `c40e36ce4ace647700a0733fc19062b1115ac145` | Adapt mount/pet criteria 80/81 with bounded positive integer IDs, both profile backends and all six frontend labels. |
| `a8cd418cf9c8e574e486c60e819c1c913bb7ae36` | `b83ef042db0ba72b55d739269b742430cfe6436b` | Incorporate quest progress text before reward text when objectives are absent. |
| `aeb8ff84238e94ca9ceefdc3d24f3310fd2af00a` | `2744f171016aab337dd4cca80ba6599a26d619e6` | Incorporate aura-only stack/proc icon counts. |
| `364b7ae4f12d817ae6f8c8372dfb31c998793034` | `90eac96d003a3977a3959c125524de5cb953d8df` | Incorporate SpellModOp constants and localized time, regeneration, distance and percentage units. |
| `1eb021aaf11f00a4d61aac08a5e5f3f477457f57` | `ba43a49b0d43b8b3dfd754c116e055a4d7da25af` | Adapt display precision while preserving safe arithmetic, raw scaling inputs and formula operands; omit the generic five-second fallback. |
| `fe3b7dcc2ca4f387d0dcbbec2a50290a56df98d2` | `d46e31065721c48c10641df13617355d6da35e0a` | Incorporate related spell globals for sound-page icons; retain sound feature guards. |
| `2ac2ed320dadc4eaad165d5d6f94d8bcf10cc418` | `1137cd2a6170599d3d43cad3f67acd768c0ecd78` | Adapt horizontal/vertical speed labels with matching per-level and combo-point units. |
| `316160ab20c60b566b3335feee04685cdf3850a6` | `b4a5f93f852f773bce4662b42505f142e9861f86` | Incorporate multi-column fulltext prefixing and preserve scalar arithmetic callbacks. |
| `80a3f5861cdb43ac06e4afbe97e78c0ffcb619fd` | `e8ce2a1377f98571ad4ae30187a35f7d1e77d632` | Adapt the formatting-result correction together with the new display templates. |

Separate integrator adaptation `ef2860bb` preserves `NumericExpression`, complete
amount arithmetic and unrounded scaling coefficients. Display formatting no
longer rounds operands inside larger formulas. New scaling markers append an
optional display-precision field; browser level updates retain integer or
one-decimal output without compounding operations, and older markers remain
compatible. The PHP and `static/js/basic.js` changes must be delivered together.
Revision 75 invalidates earlier application caches. The local fixture-parser
correction from `94f9ca87564a8395123563f3d5b6afa22692287f` is retained as
`da78acb5`, with source provenance.

The generic five-second missing-period change from `1eb021aa` is intentionally
omitted. The pinned TrinityCore
[`AuraEffect::CalculatePeriodic`](https://github.com/TrinityCore/TrinityCore/blob/8300a6d8463aa862ae154392558c1f11dc2fce5d/src/server/game/Spells/Auras/SpellAuraEffects.cpp#L563)
starts with the supplied aura period and does not establish that blanket default.
The fork's existing regeneration/non-regeneration fallback remains, with focused
regressions. All 30 new `SpellModOp` constants match the same pinned
[core definitions](https://github.com/TrinityCore/TrinityCore/blob/8300a6d8463aa862ae154392558c1f11dc2fce5d/src/server/game/Spells/SpellDefines.h).
This source review does not establish client-data or gameplay acceptance.

Additional adaptations reject malformed collectible IDs before category lookups,
retain explicit guild serialization, fix normal-movement percentages when
DieSides is zero, and scale knockback level/combo-point values consistently.
Existing staff checks, saved filter criteria, request protections, supplemented
maps, access policies and configuration are preserved.

Validation passed through `bash tests/ci/run.sh`: full `lint`, `php`,
`javascript`, `browser` and `sql` gates on PHP 8.5.11, Node 24, Chromium and
disposable MySQL 8.4.10. The SQL gate passed 423 update checks, 994 legacy-upgrade
checks across 53 archived and 101 current migrations, and 92 reconciliation
checks. Focused suites passed 92 UI text/book checks, 282 expression/scaling
checks, 533 spell/filter/unit/icon checks, 88 locale/SmartAI/mail/quest/search/
profiler checks, 420 JavaScript locale/criterion checks and 41 PHP-generated
browser scaling checks. The real taxi generator selects the nearest of two
flightmasters; migration replay preserves queued tasks and unrelated custom
fields. Composer metadata/platform checks, locked package versions,
source-credit checks and `git diff --check` pass. Existing Composer license/version
recommendations remain. Apache was not rerun because access policies are
unchanged. The browser runner uses a temporary writable profile; no application
configuration or deployment database is used by these fixtures.

For existing installations, deliver the complete PHP and static-asset changes,
then use the normal verified `php aowow --update` workflow. New migration
`1792413928_01.sql` queues `taxi` regeneration and retains other pending work;
fresh metadata advances to `1792413928 / 1` while retaining `globaljs`.
Taxi regeneration needs compatible existing world/DBC inputs. No additional
client extraction or map rebuild is introduced. Source publication, maintained-
branch merging, deployed migrations, deployed regeneration and deployment were
not performed by this local integration. Synthetic fixtures do not establish
production schema/grants, historical content or gameplay acceptance.

## 2026-10-15 — Scaling fixture assertions and CodeQL review

Reviewed [PR #29](https://github.com/peycho/aowow/pull/29) at
`ba196844e76f58ce6db3a33eda7107f7a5c7b4e7`. CodeQL alerts 39–42 all point to
the two comment-stripping expressions in `tests/security-spell-scaling.mjs`.
Their results feed only `Number()` and Node assertions; there is no HTML-rendering
sink on these paths. The expressions were nevertheless too permissive for the
fixture's intended output contract.

The assertions now parse the complete expected scaling marker, numeric amount
and level marker. Extra HTML, multiline/nested comments and trailing content
fail instead of being removed. Application code is unaffected.

Validation passed on PHP 8.5.10 and Node 24: 37 focused generated-scaling checks,
the full lint and JavaScript gates, and `git diff --check`. PHP, browser, SQL and
Apache gates were not repeated for this test-only correction. Hosted CodeQL
closure remains unverified until the correction is published and analyzed.

## 2026-10-15 — Sarjuuk integration and spell scaling (revision 74)

Reviewed [Sarjuuk/aowow](https://github.com/Sarjuuk/aowow) `master` at
`0729fb23054b60267f69868bb38c1f877b2c1809`, starting from fork `dev` at
`0203c414410f0ae38c0af2fd9f6c9002e0a4e839`. Integration branch:
`upstream-sync/20261015`. Seven imports use `cherry-pick -x` and preserve the
original authors, author dates and source-SHA trailers. The lock fix also retains
Sarjuuk's differing original committer credit. Existing October 11 adaptations
remain in place; the older eleven source commits are accounted for by those
prior integrations.

| Upstream revision | Result revision | Decision and adaptation |
| --- | --- | --- |
| `aa04e14d3afced5b061ca81bd6f72cdf8d4c9e59` | `49b3d33e22fb1a2ec96fe93a78d52e01a8542663` | Incorporate merged/deduplicated spell and skill lock IDs, preserving entries with overlapping numeric result keys. |
| `3092fe259a58e0d0283f9433e881bcdce4caf36a` | `9148b4d25cf2ab0884d07daeed32739d1f727fa1` | Adapt title editing to one integer ID and UTF-8 caption truncation. Keep the existing video-delete scalar/list checks, role checks, POST/CSRF and origin enforcement. |
| `9075f0e7c9973383cdcc83d3aa878b4601b66986` | Already present | Retain the fork's employee-only criterion 202, bounded selectors and role recheck before the custom-attribute query. Preserve saved target/stance criteria 104/105. |
| `2c12b43870de7bc85250e098b862af6966161448` | `eda718d4318ba4101fc694cd7db190fb279ae7ae` | Incorporate miscellaneous equipment categorization and nonnegative equipment scores; preserve fork configuration and pending client builds in fresh-setup metadata. |
| `d1d614fa080093efb77f574e213d36110e8db25b` | `1897bc3aa25f39172bd5d26cf0d4686187926129` | Incorporate obsolete shaman spell categories for 16253/16298. Separate adaptation narrows migration deletion to `typeCat`, preserving unrelated custom fields. |
| `3611d7dc5bf8529a090a72264b99e7b6aa252875` | `1953d34c879566257bbaa64b44057b6fac4ba0d0` | Adapt scaling modifiers without reintroducing runtime PHP evaluation. Apply operations once after the complete amount/scaling calculation; additions and subtractions do not alter growth per level. |
| `e0a6e3f40eadb415c64ec51ac08e84b0fe4fa967` | `7bf752701b5788ef69fed2e7cd65ec45fc562e9f` | Incorporate correct pet-talent links/categories, listview pet types and passive cast-time handling. Preserve explicit frontend serialization and check all six locale menus. |
| `0729fb23054b60267f69868bb38c1f877b2c1809` | `ecd2e83fcd614ef43f61d34d7ddfb7e21bccd8b3` | Adapt parent-zone fishing loot fallback while retaining subzone priority, localized notes and `JsExpression` labels/callbacks. |

Separate integrator-authored adaptations are `0bfabc07847e8e3c02b34c84039422397011eea8`
(custom-field preservation) and `cf5c2ac3` (scaling, scalar unsigned title IDs,
explicit UTF-8, fishing helper and setup/cache accounting). Regression coverage
is recorded in `c9793292` and `c6896e01`. The reconciliation fixture retains
real generator registration and updater accounting while substituting SQL data
production; its retained-row checks require no extracted client files.

Scaling metadata keeps its raw coefficients and carries only a validated numeric
operation to the browser. Both sides apply that operation after scaling, including
periodic totals and NPC scaling. Invalid/nonfinite arithmetic keeps the original
value and metadata. Existing markers still work; repeated level updates do not
compound the modifier. The PHP and JavaScript changes must be delivered together.
Revision 74 invalidates earlier application caches.

Validation: full `lint`, `php`, `javascript`, `browser` and `sql` gates passed
through `bash tests/ci/run.sh` on PHP 8.5.10, Node 24 and disposable MySQL 8.4.10.
The SQL gate includes 421 update checks, 989 legacy-upgrade checks across
53 archived and 100 current migrations, and 92 reconciliation checks.
Focused checks cover 273 expression/config/spell/filter cases, 32 PHP-generated
browser scaling cases, 384 locale/criterion cases, 26 lock/fishing/gearscore cases
and 163 administration HTTP cases. Composer validation and platform checks pass;
its existing license/version recommendations remain. `git diff --check` and
source attribution checks pass. Apache was not rerun because access policies
are unchanged. These synthetic fixtures do not establish deployed PHP-FPM,
MariaDB, world data, operator grants, extracted assets or gameplay acceptance.

For existing installations, deliver the complete source/static-asset changes,
then run the normal verified `php aowow --update` workflow. The new migrations
`1791805587_01.sql`, `1791808400_01.sql` and `1791840861_01.sql` queue `items`,
`stats` and `spell` regeneration while preserving pending work. Fresh metadata
advances to `1791840861 / 1` and retains `globaljs`. No source publication,
maintained-branch merge, deployed database update, operator asset build or
deployment was performed as part of this integration.

## 2026-10-12 — Documentation organization and audit references

The README now introduces the project and points to dedicated installation,
configuration and troubleshooting guides. A documentation index groups operation,
development and review documents; contributor/support guidance and links to moved
sections follow the new structure.

The security review maps all A01–A16 and R01–R03 findings to their remediation
PRs and commits, records the later spell-filter and CodeQL changes, and separates
merged fixes from the local follow-up commit. This documentation review does not
establish fresh runtime regressions, hosted CodeQL closure or deployed-site
acceptance.

## 2026-10-11 — Icon navigation and video deletion

Icon links now classify Wowhead by the parsed hostname and its subdomains,
including case and trailing-dot normalization. Deceptive hostname suffixes,
URL credentials and query/path references no longer receive Wowhead's same-tab
behavior. Existing destinations and the HTTP/HTTPS tab policy are preserved.

Video deletion now accepts validated unsigned ID lists, allowing authorized
staff requests to reach the existing deletion handler. Regression checks cover
single/multiple IDs, malformed inputs, role restrictions and CSRF protections.
No database migration is required; rebuild `globaljs` during deployment for the
icon-template change.

## 2026-10-11 — JavaScript rendering and CodeQL review

Setup JavaScript templates now parse before configuration substitution, and the
syntax gate covers them. The locale placeholder's fallback is consumed during
generation without changing its output. HTML attributes encode repeated
metacharacters, profile status links retain literal query values, changelog names
render as text, and comparison levels normalize to integers from 1 to 80.
Interpolation preserves literal replacement tokens.

Automatically detected links for pending users compare parsed hostnames against
the domain list using exact/subdomain boundaries. Removed eleven obsolete,
repurposed or unverified domains after checking their availability. Explicit authored
links retain their separate permission rules. Video bulk-selection counts now
update the existing moderation links correctly.

Removed the obsolete handwritten JSON fallback and unused string helper.
Text extraction decodes entities once, and editor HTML conversion handles
case differences and tags revealed by prior removals. Conversion remains a
markup-authoring tool, not a security sanitizer. Regression fixtures cover all
six locales and root/subdirectory browser installations; CodeQL configuration
and existing CI check names remain unchanged.

Existing installations must rebuild the generated bundle alongside the changed
static scripts, using the site's normal configuration and deployment account:

```sh
php aowow --build=globaljs
```

No database migration is required. Repository fixtures use synthetic configuration;
their success does not establish deployed-site acceptance.

## 2026-10-11 — Contributor guidance and pull-request checks

External contributions target `master`. Added contributor and support guides,
a private vulnerability-report form, structured bug/feature/support forms,
an issue chooser and a PR template. The README identifies this maintained fork
and uses its HTTPS clone URL.

Every PR to `master`, including documentation changes, runs the existing
PHP 8.5 / Node 24 / MySQL 8.4 and Apache regression checks. Manual dispatch is
available, and separate push/PR concurrency groups prevent cross-event
cancellation. Existing push path filters and the PHP 8.4 runtime minimum are
unchanged. The test guide includes an optional Composer lockfile audit command.

Third-party notices and accompanying license texts document component terms.
The licensing-provenance report distinguishes verified notices from unresolved
permissions for inherited code, fonts, design and artwork; it does not grant a
blanket project license.
Asset notices credit Blizzard Entertainment and applicable rights holders for
World of Warcraft assets originating from Blizzard, and Beijing ZhongYi
Electronics for the bundled Chinese font.

## 2026-10-11

### Sarjuuk upstream integration and spell-filter security (revision 73)

Reviewed [Sarjuuk/aowow](https://github.com/Sarjuuk/aowow) `master` at
`bdc2c115e6344acee76c8529dc5af7ad6b45c553`, starting from fork `dev` at
`c8e553dfdfd696c0e6118c861fee4c4425d88a34`. Six imports use `cherry-pick -x`;
the filter import applies a reviewed subset of its patch before committing with
the original author/date and source-SHA trailer. Differing original committers
and existing coauthor credit are retained. Additional fork changes are separate
integrator-authored work.

| Upstream revision | Result revision | Decision and adaptation |
| --- | --- | --- |
| `a904053b9fbdc1796f6f1a76f1484aae394cb77a` | `457df7879db63d6fd6b00f1d27b2ad6bf2592835` | Already present through the prior SimpleHTML adaptation; retain the fork parser. |
| `18ad819a3c7a0e072af1a80cacbeef6675d98e27` | `7679d0831a2bb899b4ac67e0aa899f08ab27ff60` | Already present, including the criteria-name migration. |
| `d7f4716ac1ad27ac7019ea929dd05f4b3f4af8a0` | `6dc6f1ad3b6c3c1d2177f9982fdcd544454053a9` | Maintenance metadata fix originated in this fork and is already present. |
| `569b3395347fbfdf724c935f28b0e3928c4d511e` | `8486f90394f0cf163dc25adf9feddaea0fc6c93f` | Import custom-data update failure reporting; resolve SQL/build runner conflicts by retaining the fork's failure accounting, per-run completion lists, registry ownership and retry behavior. |
| `0b0e3198db861cc3aec1bdd6499c47311d165b88` | `465657d50f1c77286628d46f149b18b34bb1dbda` | Import spell/enchantment heading spacing correction. |
| `43c47f018ece955df209981d29f053498553fc22` | `cf305986b46ee1f4d881a167f869b8f7151e5f79` | Import percentage display for eight additional speed auras. |
| `45856fd65ec7ddb3d1570b9755bd586b4520fc72` | `f21874ea0cd38ca4024fd3c91fb1552cac150c10` | Import duel object links, related spells and the shared object-model effect list together. |
| `ecdedc277f8c23ea4803723a761951800ea1823f` | `586fe6b218ec6c2e7bdbed69d797e1543cc20eeb` | Import capture-point duration named-argument correction. |
| `aa499c2b7b6a55e7f26f2eb60c1d2d3e6c55efb0` | `6b669d025178e83f852c15b6216497169d89311e` | Reply-report query is already corrected; preserve content-report identity and quota enforcement. |
| `5555f60367e1efad52fc6b4e8194cc6caa3d4058` | `9bcc55252a05907ad0ea2341c37e30aa2640ac53` | Import custom spell attributes and six locale tables; retain the fork revision during the kernel conflict, then advance it to 73 for cache invalidation. |
| `bdc2c115e6344acee76c8529dc5af7ad6b45c553` | `9b48df0d1d66b79d8c832f825a2f76dd3430db43` | Import readable flag labels and bounded staff-bit validation; omit the unguarded callback and target/stance criterion changes from this import. |

The upstream custom-attribute filter used a general callback, bypassing the
server-side employee check used by other staff flags. The fork adaptation adds
employee-only criterion `202`, validates integer bit positions from 1 through 32
before shifting, and checks the employee role again before its parameterized world
query. Guest/nonemployee requests perform no world lookup. Existing criterion
`104` still searches targets and `105` still searches excluded stances, preserving
saved URLs. Item, quest and achievement staff filters retain their valid high-bit
behavior and reject malformed positions too.

Custom-data lookup failures now return false as well as update failures; zero
affected rows remain successful. Existing database error counts prevent failed
generators from being acknowledged. Custom spell attributes add a separator only
when both displayed lists contain entries, avoiding empty sections for hidden
unknown flags. PHP attribute labels follow the upstream translations; the new
JavaScript bit descriptions are English in all six locales, with localized filter
captions.

Source preflight checked all 26 custom-attribute values against the README-pinned
[TrinityCore definitions](https://github.com/TrinityCore/TrinityCore/blob/8300a6d8463aa862ae154392558c1f11dc2fce5d/src/server/game/Spells/SpellInfo.h),
and the unsigned `entry` and `attributes` columns against its
[world schema](https://github.com/TrinityCore/TrinityCore/blob/8300a6d8463aa862ae154392558c1f11dc2fce5d/sql/base/dev/world_database.sql).
Existing installations need runtime SELECT access to `spell_custom_attr` in the
compatible world database. No new SQL migration or generated-data build is
introduced by this selection. Deliver the PHP, templates, filter script and all
six locale scripts together; revision 73 invalidates earlier application caches.
Supplemented map handling, serialization, setup metadata, access rules and
community protections are retained.

Validation passed on PHP 8.5.10, Node 24 and disposable MySQL 8.4: full `lint`,
`php`, `javascript`, `browser` and `sql` gates through `bash tests/ci/run.sh`, plus
`git diff --check`. Focused suites passed 293 PHP and 366 JavaScript checks.
Apache was not rerun because access policies are unchanged. Browser checks used
Chromium's headless-shell runner. These synthetic fixtures and source preflight
do not establish deployed world-schema/grant, PHP-FPM, MariaDB, extracted-data or
gameplay acceptance. No deployed migration, data regeneration or rollout was
performed.

## 2026-10-08

### General feedback budgets (revision 72)

General feedback remains available to anonymous visitors when enabled. Validated
submissions reserve 10 attempts per trusted peer IP per hour and 1,000 site-wide
per day before CAPTCHA or duplicate/persistence work. Fixed windows start with
the first admission; changing URL, reason, subject, account or session does not
reset the peer allowance. Canonical IPv6 and mapped IPv4 spellings share a key.
Invalid/replayed tokens, provider failures, duplicate submissions and uncertain
acknowledgments retain committed attempts. Invalid local fields and disabled
feedback avoid reservations. Content-report and CAPTCHA verification limits
remain independent.

The feedback service now verifies the optional CAPTCHA itself, with the endpoint
passing its token, so direct service callers cannot bypass either policy. Known
request or storage exhaustion returns the existing localized contribution-limit
message before provider work. SQL/uncertain failures fail closed without raw
values, SQL or new application warnings. No database locks span network calls.

General feedback has a permanent cumulative cap of 10,000 records and 64 MiB of
charged bytes, whichever is reached first. Charges include UTF-8 description and
metadata bytes plus 1,024 bytes per record; this is conservative application
accounting, not a measurement of physical MySQL/disk allocation. A first-use
transaction counts existing general feedback, including closed records, and
persists the baseline independently of new submissions. Later requests read the
counters without rescanning the inbox. An already-full inbox rejects new feedback
without deleting old records. Storage charges, duplicate serialization and the
new row share one transaction; failures roll back uncommitted storage and
records. Lost commit acknowledgments retain any committed state and do not claim
success. Closing/deleting records does not refund permanent charges.

The existing InnoDB `aowow_contribution_budget` table holds separate `feedback-*`
buckets: at most 4,096 hashed peer keys, one global work key and two permanent
storage counters. An admitted request reclaims at most eight expired peer keys;
`php aowow --prune` and `php aowow --prune=apply` also handle expired work windows.
They preserve reports and permanent counters. Do not clear permanent budget rows
as routine cleanup. Existing accounts, content reports and community data are
unchanged.

Deploy the new feedback budget component, Report, contact responder and kernel
together. No new SQL migration, dependency or JavaScript build is required for
R03 on an updated installation. Older installations need the existing budget
migration through `php aowow --update`. Runtime requires DML on the InnoDB budget
and reports tables, not DDL. Verify trusted client-IP attribution, real FPM/proxy
limits and anonymous/authenticated feedback with CAPTCHA on/off after rollout.
The bounded first-use baseline scan and actual historical inbox size need hosting
acceptance; these limits do not replace inbound traffic controls or DB/disk
monitoring.

All six regression gates pass locally, including 148 focused feedback checks and
3,001 checks across 12 disposable SQL suites. Coverage includes a full historical
inbox seeded once, independent workers competing for peer/global/key/storage
capacity, duplicate serialization, failures and interrupted/uncertain commits,
retention and representative community data preservation. Fixtures use synthetic
records and an intercepted provider; they do not establish production, actual
Cloudflare, MariaDB or PHP-FPM/load acceptance.

### CAPTCHA verification budgets (revision 71)

Enabled Turnstile forms reserve verification work before contacting Siteverify.
Registration, login, password/username recovery, activation-email resend and
general feedback share 20 attempts per trusted peer IP per five-minute window
and 120 attempts globally per one-minute window. These are fixed windows starting
with the first reservation; requests do not extend an active window. Switching
forms, accounts or sessions does not give the same peer a new allowance. IPv6
spellings and mapped IPv4 addresses share canonical peer keys.

Reservations commit before network work, so database locks never span the
verification request. Invalid/replayed tokens, provider failures and uncertain
commit acknowledgments retain any committed charge. Exhaustion, missing budget
data and failed/uncertain reservations return the existing CAPTCHA form error
without contacting the provider or advancing authentication, mail or report work.
No additional application warning is raised for ordinary quota denial. Disabled
CAPTCHA and locally malformed tokens/configuration perform no reservation or
verification request. Existing TLS/hostname/action/replay checks and response
limits remain in place.

Verification uses the existing InnoDB `aowow_contribution_budget` table with
separate `captcha-*` buckets, independent of password and retained-content
allowances. At most 4,096 peer keys and one global key are admitted. A reservation
reclaims at most eight expired verification peer keys; the hard key cap bounds
storage without relying on cron. Existing peers can retry at key capacity.
`php aowow --prune` previews expired reservations and `php aowow --prune=apply`
removes them; unrelated permanent byte reservations remain intact.

Deploy the new `turnstilebudget.class.php`, updated Turnstile component and kernel
together. No new SQL migration, Composer dependency or JavaScript rebuild is
needed for R01. An older installation still needs the existing budget migration
through `php aowow --update`; runtime needs normal budget DML, not DDL. Confirm
trusted client-IP handling and normal CAPTCHA retry behavior under the actual
PHP-FPM/proxy setup before accepting the rollout. These limits bound verification
work, not all inbound traffic or concurrent PHP startup; hosting capacity/rate
controls and load acceptance remain separate.

The real SQL fixture covers admission before provider calls, shared form limits,
IPv4/IPv6 canonicalization, independent concurrent workers, hard interruption
before/after commit, missing/failed budgets, lost commit acknowledgments, bounded
key admission/reclamation, provider outages and legitimate retries. Existing TLS
and all six handler fixtures verify budget denial does not start account/mail/
report work. General-feedback persistence quotas (R03) remain separate and open,
especially when its CAPTCHA is disabled.

### Content report limits (revision 70)

Comment, screenshot, video, guide and character reports now require an activated,
unbanned account. Anonymous visitors are directed to sign in, with a return URL
to their current page. The service also enforces the restriction for direct POSTs.
General feedback remains separate: its enable switch and optional Turnstile
challenge retain their existing behavior, including anonymous feedback.

Content reports use the existing `aowow_contribution_budget` table, with 25
attempts per account, 50 per trusted peer IP and 10,000 globally per 24-hour
window. These are fixed windows beginning at the first reservation, not calendar
days. IPv6 spelling variations share one hashed peer bucket. Report reservations
also share the existing cumulative text allowance: 256 MiB per account and 4 GiB
globally, charged at 4 KiB plus submitted metadata bytes. Duplicate, nonexistent
target and failed insertion attempts consume their reservation; failed or
uncertain work is never automatically refunded. Expiring peer/account work
buckets are covered by the existing `--prune` workflow. Trustworthy client-IP
configuration remains necessary when serving behind a proxy.

Targets must exist and be visible to the reporting account. Disabled profiler
targets and unsupported forum reports are rejected. Reason checks use the actual
content author's roles. Metadata fits the declared column sizes, and descriptions
remain limited to 500 UTF-8 characters. Concurrent submissions from one account
serialize their duplicate check and insertion: changing the reason or page URL
cannot create another report for the same content. Failed checks fail closed;
ordinary invalid requests do not raise application warnings or activate
maintenance. Historical reports, accounts and community content are preserved.
The reply-report and outdated-comment callers also handle quota denial as an
ordinary failure. The reply lookup now uses the correctly spelled comments table,
and a successful outdated report no longer raises an error merely because it
has not reached the automatic moderation threshold.

No new SQL migration is needed on an updated installation with the existing
contribution-budget table. Deploy the PHP changes together and regenerate the
browser assets as the website's OS user:

```sh
php aowow --build=globaljs
```

The SQL fixture in `tests/security-reports.php` covers identity, target visibility,
metadata bounds, quotas, independent concurrent workers, unavailable budgets,
insertion/lookup failures, uncertain commit acknowledgments and existing data
preservation. The JavaScript fixtures cover guest sign-in redirects and anonymous
general-feedback compatibility. Local regression validation is not a production
rollout or a traffic benchmark. This revision did not address the separate CAPTCHA
verification-work finding; revision 71 implements its budget above.

### SMTP mail

All existing account mail templates now share a configurable transport. Missing
`AOWOW_MAIL` or `transport => 'mail'` preserves PHP `mail()` delivery. `smtp` uses
PHPMailer, locked through Composer; `disabled` sends nothing and returns failure
to the existing form handling. Templates, localization, tokens and expiration
formatting remain unchanged. General feedback continues using its existing
report storage; this change does not add feedback notification emails.

Run `composer install --no-interaction --prefer-dist --no-plugins --no-scripts`
to install the new locked dependency. Add the following to the existing private
`config/security.php`, preserving its cache key and other settings:

```php
define('AOWOW_MAIL', [
    'transport'  => 'smtp',
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'auth'       => true,
    'username'   => 'database@example.com',
    'password'   => 'YOUR_GMAIL_APP_PASSWORD',
    'from_email' => 'database@example.com'
]);
```

Gmail uses the full account email as username and an app password when available
under the account's two-step verification and organization policy. Use an
authorized sender address/alias. See Google's
[app password guidance](https://support.google.com/accounts/answer/185833) and
PHPMailer's [Gmail example](https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail.phps).
OAuth2 tokens are not configured by this adapter.

Available settings (see `setup/security.php.example`):

| Setting | Default | Behavior |
| --- | --- | --- |
| `transport` | `mail` | `mail`, `smtp`, or `disabled` |
| `host` | empty | One SMTP hostname or IP, without URL prefix or port |
| `port` | `587` | Integer from 1 to 65535 |
| `encryption` | `tls` | `tls`: required STARTTLS; `ssl`: implicit TLS, usually port 465; `none`: explicitly unauthenticated relay |
| `auth` | `true` | Authenticate to the SMTP server; credentials require encryption |
| `username`, `password` | empty | Private SMTP credentials |
| `auth_type` | empty | Automatic selection, or `LOGIN`, `PLAIN`, `CRAM-MD5` |
| `from_email`, `from_name` | empty | Empty uses `CONTACT_EMAIL` and `NAME_SHORT` |
| `reply_to` | empty | Empty uses `CONTACT_EMAIL` |
| `timeout` | `10` | Connection/socket timeout in seconds, integer 1–30 |
| `command_timeout` | `10` | Per-command time limit in seconds, integer 1–30; PHPMailer permits twice this for the final DATA acknowledgment |
| `ca_file` | empty | Optional readable trusted CA bundle; otherwise PHP's configured trust store |

TLS certificate and hostname verification remain enabled. Invalid configuration,
TLS/authentication failure and relay rejection return false without falling back
to native mail. Diagnostics contain only a generic failure category, never SMTP
credentials, recipients, tokens, provider replies or message content. Mail
delivery failures do not raise application warnings or activate maintenance.
SMTP is synchronous; timeout limits apply per operation, not to the entire send.
A successful send means relay acceptance, not proof of inbox delivery.

Existing Info-level debug mode still previews the message instead of sending it.
SMTP bypasses `sendmail_path`, including `/bin/true`: development installations
that must not send email should explicitly use
`define('AOWOW_MAIL', ['transport' => 'disabled']);`. Keep credentials out of
database/site settings, JavaScript and version control. No database migration or
asset rebuild is required. Configure OpenSSL, a trusted CA store and outbound
access to the chosen relay on the deployment host.

Validation: Composer lock/platform checks, full PHP 8.5 lint, PHP and JavaScript
gates and the full SQL gate on disposable MySQL 8.4 passed. Focused fixtures
exercise actual loopback SMTP/TLS and synthetic mail; no real Gmail delivery or
production configuration was attempted.

## 2026-10-07

### Maintenance page

WebP assets are explicitly marked binary in `.gitattributes`. The previous
text normalization removed a CR byte from the committed maintenance image,
corrupting its compressed content. The asset bytes are restored and regression
coverage now checks Git's actual clean filter preserves WebP bytes. Deploy
the updated asset alongside the attributes fix; no SQL or generator work is
needed.

Replaced the maintenance page's gnome background with a new Wrath-themed archive
repair illustration, optimized as WebP. The existing logo, heading, message and
artwork now occupy separate rows, preventing overlap. The page adapts to narrow
viewports and displays “Maintenance in progress” with a request to check back
later, without promising a completion time. HTTP 503 and Retry-After handling
remain unchanged. No SQL update or generator rebuild is required.

The [artwork notes](artwork/maintenance.md) record the generation prompt and asset.
Validation: PHP 8.5 lint and the full PHP regression gate passed, including 181
maintenance response/template checks. Local Chrome checks at 1280 and 375 pixels
confirmed image loading and separate logo, text and artwork with no horizontal
overflow. No deployment was performed.

### Optional header image

Added `HEADER_IMAGE_ENABLE` (default Disabled), `HEADER_IMAGE_URL` and
`HEADER_IMAGE_LINK` (default empty) under Site Configuration → Site. The shared
page header displays one linked image at its top right with a 468×60 area. The
homepage uses its own layout and does not display it. Images retain their aspect
ratio inside that area; a 468×60 source is recommended.

Run the existing `php8.5 aowow --update` to install `1791331200_07.sql`. It preserves
existing choices and queued SQL/build work. Fresh installs seed the same settings.
Enable the switch after setting both absolute HTTP/HTTPS URLs. Empty or invalid
URLs suppress the entire placement. Links open in a new tab with
`noopener noreferrer`, and image/link attributes and settings input values are
escaped. Credentials, control bytes and non-web schemes are rejected at render
time. The server does not fetch or upload the image; the visitor's browser loads
the configured URL. HTTPS image URLs are recommended for an HTTPS site.

Settings are read from already-loaded configuration when rendering, including
cached templates. Changing them requires no build, cache flush or additional
database query and does not change maintenance mode. The markup uses ordinary
`header-image` names, CSS sizing without advertising-size HTML attributes, and no
advertising scripts or containers. Browser extensions
can still apply URL, size or user-defined filters, so universal visibility cannot
be guaranteed.

Validation: PHP 8.5 lint, PHP and JavaScript regression gates and the full SQL
gate passed on disposable MySQL 8.4 databases. The complete first-install SQL
scripts imported successfully. Local Chrome fixtures verified the size and
position at 980- and 1366-pixel widths; no deployment was performed.

### Optional site sounds

Added the persistent boolean `SOUNDS_ENABLE` (Site category), default Enabled.
Migration `1791331200_06.sql` installs it without overwriting an existing choice
and queues `globaljs` once to install playback and markup guards. Run the usual
`php8.5 aowow --update` to install the setting and complete that build.

When Disabled, the Sounds/Playlist menu and direct sound/list/playlist URLs are
unavailable. Sound searches, sitemap entries, metadata tabs and sound-specific
lookups on item, spell, NPC, zone, race and emote pages are skipped. NPC dialogue
text and underlying game/community data remain intact. Inline sound markup,
audio controls and playlists also respect the switch. Existing static audio
files are retained; direct static file URLs are not an access-control boundary.

Signed page/search caches use distinct keys for enabled and disabled sounds,
so toggling the setting does not reuse pages containing the opposite feature
state. The setting uses configuration already loaded for the request; it does
not scan files or run an availability query. Toggling it does not queue builds,
change maintenance state or affect extraction and setup generators.

### Cloudflare Turnstile

Six independent, persistent boolean settings protect registration, login,
password recovery email, username recovery email, activation email resend and
general feedback. They default to Disabled and preserve existing choices through
`1791331200_05.sql`. Keys remain in deployment-owned `config/security.php`; the
public client configuration contains only the site key, enabled actions and a
localized retry message. Enable the account switches under Site Configuration →
Account and the feedback switch under Site Configuration → Site.

The shared verifier makes a fixed HTTPS POST to Cloudflare Siteverify and checks
strict success, the configured `HOST_URL` hostname and the expected form action.
It rejects absent/malformed/expired/reused tokens, missing keys, mismatched
responses and provider/transport failures before authentication, account creation
or mail/report work. TLS checks remain required, redirects are disabled, response
headers/body are bounded and the total timeout is five seconds with asynchronous
DNS required. Failure is a form error with no raw provider messages, secrets or
tokens in diagnostics, and does not activate maintenance mode.

Native forms render widgets independently, block pending/expired submissions and
refresh expired challenges. The general feedback dialog mounts a widget on show,
sends its token with AJAX, resets after submission and removes it on close.
Comments, uploads and content-report modes do not require Turnstile. Client flags
are read at render time for cached pages; switches do not queue builds. The update
queues a single `globaljs` build to install feedback dialog integration. Existing
CSRF, attempt budgets, password policy, email activation and recovery authority
remain intact. Email confirmation/reset links themselves require no new captcha.

Setup prerequisites and switches are documented in the
[configuration guide](configuration.md#cloudflare-turnstile). Provider semantics follow
[Cloudflare's server validation documentation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).

Validation: full PHP 8.5, JavaScript, lint and MySQL 8.4 regression gates passed
with disposable fixtures. Focused coverage includes real bounded cURL/TLS,
missing/malformed/expired/replayed tokens, action/hostname mismatch, transport
failures and timeouts, all six endpoint boundaries and cached form/settings
rendering. Chromium passed 75 real DOM checks for native and AJAX submissions,
expiry, script failure/retry and dialog cleanup. No live Cloudflare keys or
application databases were used; deployed widget acceptance remains to be tested
on each enabled form with its authorized hostname.

### Feedback switch

`feedback_enable` is an enabled-by-default persistent boolean under Site
Configuration → Site. It hides the Feedback header link and disables general
contact dialogs, including `#contact` and feedback markup. The `?contactus`
handler returns HTTP 404 for disabled general requests before creating a report;
the report service also refuses general submissions. Comment, screenshot, video,
character and guide reporting retain their existing checks and behavior. Direct
email links and existing reports are preserved.

Both cached page headers and dialog guards read the current request setting.
Toggling does not rebuild assets, enable maintenance or query a database for each
check. Migration `1791331200_04.sql` adds the setting without overwriting existing
choices and queues one `globaljs` build through `php aowow --update` to install
the client guards. Fresh installations include the same enabled default.

Validation: full PHP 8.5, JavaScript and lint gates passed, along with the full
MySQL 8.4 SQL gate on disposable fixtures. Focused checks cover current settings
on cached pages, general request rejection, content-report dialogs/submissions,
configuration saves without builds, defaults and preservation of existing choices,
durable migration accounting and client generator completion. No deployed site or
application database was modified.

### Prune filesystem diagnostics

`--prune` now reports which logical filesystem scan failed (`cache`, `staging`
or `screenshots`) when directory traversal throws an exception. Preview and
apply retain a nonzero exit status. Debug preserves the sanitized exception
chain, while ordinary diagnostics omit private paths and raw exception messages.
The command releases its database/filesystem locks and does not save a new cursor
after this failure.

Run cleanup as the website OS user that owns private cache directories, and keep
`cache/maintenance`, its lock and any cursor accessible to that same user. Cache
privacy and symlink protections are unchanged. Preview does not delete data;
an apply failure can follow earlier bounded deletions, so correct access and
preview again before retrying apply.

Validation: the full PHP 8.5 regression and lint gates and the MySQL 8.4 SQL
regression gate passed. Permission-denial fixtures exercised preview, apply and
debug diagnostics, cursor preservation and lock release. This validates the
local change; server permissions and deployment were not modified.

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
[installation guide](installation.md#5-reencode-the-audio-files), then run:

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
