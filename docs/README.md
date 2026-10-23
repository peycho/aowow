# Documentation

The [project README](../README.md) introduces the maintained AoWoW fork.
Use the guides below for installation, operation and development. Commands in
these guides run from the checkout root unless stated otherwise.

## Install and operate

| Guide | Purpose |
| --- | --- |
| [Installation and updates](installation.md) | Requirements, database and client assets, Apache rules, CLI setup, upgrades and cleanup |
| [Docker](docker.md) | Container installation, local/external databases, persistent volumes and maintenance |
| [Configuration](configuration.md) | Private settings, mail, Turnstile, feature switches and external navigation |
| [Guide discovery](guide-discovery.md) | Category introductions, related-guide links, publication rules and index rebuilds |
| [Troubleshooting](troubleshooting.md) | Common setup and site problems, diagnostics and support preparation |
| [Historical schema reconciliation](schema-reconciliation.md) | Retained-data migration preflight, compatibility and recovery |

## Develop and contribute

| Guide | Purpose |
| --- | --- |
| [Contributing](../CONTRIBUTING.md) | Branch target, development prerequisites, validation and review expectations |
| [Tests](../tests/README.md) | Focused suites, full regression gates and fixture limitations |
| [Docker development](docker-development.md) | Live source updates, development tools and disposable test databases |
| [Performance measurement](performance.md) | Repeatable cold/warm lab sampling, media loading and field acceptance |
| [Upstream integration](upstream-sync.md) | Review and integrate Sarjuuk changes with source attribution |
| [Maintenance illustration](artwork/maintenance.md) | Artwork provenance and presentation details |

## Review and project policies

| Document | Purpose |
| --- | --- |
| [Changelog](changelog.md) | Dated changes, migration/build details and historical validation |
| [Security review](aowow-security-review.md) | Findings, remediation PRs/commits and remaining deployment acceptance |
| [SEO roadmap](seo-plan.md) | Ordered improvements, implementation checkpoints and acceptance boundaries |
| [Security policy](../SECURITY.md) | Private vulnerability reporting |
| [Support](../SUPPORT.md) | Bugs, feature requests and ordinary support questions |
| [Third-party notices](../THIRD_PARTY_NOTICES.md) | Component notices and attribution |
| [Licensing provenance](licensing-provenance.md) | Verified declarations and unresolved inherited permissions |

For current usage, start with the installation and configuration guides.
The changelog and security review preserve dated evidence; historical test
results do not certify a particular deployed site.
