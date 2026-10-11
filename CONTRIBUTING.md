# Contributing to AoWoW

This maintained fork targets World of Warcraft 3.3.5a and the compatible
TrinityCore database described in the [README](README.md#requirements).
External pull requests target **`master`**. Work in your own branch or fork.

## Development and validation

Follow the [installation guide](README.md#install) for PHP 8.4 or 8.5, required
extensions, Composer and compatible data. Install the committed dependencies
with development packages when running tests:

```sh
composer install --no-interaction --prefer-dist --no-plugins --no-scripts
composer check-platform-reqs
```

Keep configuration and credentials private. Most focused tests use synthetic
fixtures and need no installed site. Database tests require a dedicated disposable
MySQL 8.4 service; never point them at application or player databases.
The [test guide](tests/README.md#continuous-integration) gives prerequisites,
focused commands and the full groups:

```sh
bash tests/ci/run.sh lint
bash tests/ci/run.sh php
bash tests/ci/run.sh javascript
bash tests/ci/run.sh browser
bash tests/ci/run.sh sql
bash tests/ci/run.sh apache
composer --no-plugins --no-scripts audit --locked --no-interaction
```

Run focused suites for affected behavior during development. Before submitting,
run the relevant full groups and describe exactly what passed and what could
not be exercised. Shared rendering, bootstrap, security or setup changes need
all affected consumers checked, not just one helper. CI runs the full regression
suite on PHP 8.5 for every PR to `master`; the PHP 8.4 runtime minimum remains
unchanged. The Composer audit above is an optional local dependency check.

## Changes and review

Match the surrounding PHP, JavaScript and SQL conventions; preserve LF endings
and existing license/copyright notices. Keep changes focused. Explain nontrivial
security boundaries, side effects and failure behavior with concise comments.
Preserve public URLs, localization, existing configuration defaults and supported
TrinityCore behavior unless the PR explicitly explains a necessary change.

Use the PR template to explain the problem, resulting behavior and validation.
Distinguish synthetic/local checks from deployed-site, historical-data and
external-integration acceptance. Update the README for everyday usage changes
and [changelog](docs/changelog.md) for dated technical details.

For schema changes, include an ordered migration with upgrade/recovery
prerequisites and validate both fresh and retained-data paths. Do not silently
advance migration metadata or import first-install SQL over existing content.
For generator/template changes, identify required rebuilds and verify generated
PHP/JavaScript/assets remain compatible with their consumers. Keep extracted
client assets, private configuration and real uploads out of commits.

## Upstream attribution and licensing

For upstream imports, preserve original authorship and source-commit provenance
using the [upstream integration guide](docs/upstream-sync.md).

Read [third-party notices](THIRD_PARTY_NOTICES.md) and the
[provenance report](docs/licensing-provenance.md). This repository has no blanket
project license. Keep applicable component notices and provide source/license
evidence for newly introduced dependencies or assets. Contributions do not
resolve permissions for inherited code or artwork.

Use the [security policy](SECURITY.md) for vulnerabilities and
[support guide](SUPPORT.md) for installation questions.
