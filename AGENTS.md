# Repository workflows

When the user asks to check, review, sync or incorporate changes from Sarjuuk's
AoWoW source, read and follow
[the upstream-sync skill](docs/skills/aowow-upstream-sync/SKILL.md).
Requests such as "sync check with Sarjuuk", "check upstream changes" and
"sync with Sarjuuk" invoke this workflow.

A check requests inspection and a report. A request to apply or incorporate
changes also authorizes local integration and validation. Follow the user's
stated scope and existing session authorization. Publishing and deployment
require their own authorization.

# Development and validation

Use the optional Docker development environment for reproducible local checks;
see [the development guide](docs/docker-development.md). `make dev-test` runs
lint, PHP/HTTP, JavaScript, Chromium, disposable SQL and Apache policy gates.
Select a focused gate with `make dev-test ARGS=php` (or `lint`, `javascript`,
`browser`, `sql`, `apache`). These commands snapshot current working source,
including uncommitted changes, without installing dependencies on the host.
Run `make dev-build` first after changing runtime extensions, Apache/PHP image
configuration or the production Dockerfile; ordinary test runs reuse that base.

Development HTTP defaults to `127.0.0.1:8011`; Apache fixtures use port `8012`.
Keep additional local published ports within `8010–8020` and check availability.
Tests use independent disposable databases; never substitute retained or live
databases. `make docker-test` separately validates the production Docker image.
Full setup with TrinityCore/client assets and production acceptance remain
separate from synthetic regression coverage.
