![AoWoW logo](static/images/logos/home.png)

# AoWoW

This is the maintained [peycho/aowow](https://github.com/peycho/aowow) fork of
AoWoW, a World of Warcraft database application for version 3.3.5 (build 12340).
External contributions target `master`; see the [contributor guide](CONTRIBUTING.md).
It provides PHP server code and a client interface based on the earlier database
website with the red smiling rocket. The project dates back to 2008; the original
creator is unknown. The PHP code was rewritten and the client JavaScript updated
from the original 2008 implementation.

The project takes no credit for the original client scripting, design or layout
and is not intended for commercial use.
World of Warcraft assets originating from Blizzard Entertainment remain the
property of Blizzard Entertainment and applicable rights holders. Other
third-party assets belong to their respective owners.

## Documentation

- [Installation and updates](docs/installation.md): requirements, assets, setup and upgrades.
- [Docker](docs/docker.md): container installation, database layouts and persistent volumes.
- [Docker development](docs/docker-development.md): live source updates and isolated regression suites.
- [Configuration](docs/configuration.md): mail, Turnstile and optional site features.
- [Troubleshooting](docs/troubleshooting.md): common problems and support preparation.
- [Documentation index](docs/README.md): maintenance guides, audit records and changelog.
- [Security review](docs/aowow-security-review.md): findings, fix references and deployment acceptance.

## Contributing and support

Read the [contributor guide](CONTRIBUTING.md) for development and pull requests.
The [test guide](tests/README.md) covers local checks and the regression workflow.
Use [Support](SUPPORT.md) for bugs, feature requests and installation questions,
and the [security policy](SECURITY.md) for private vulnerability reporting.

See [third-party notices](THIRD_PARTY_NOTICES.md) for component notices and
licensing provenance.

## Thanks

- @mix: PHP scripts for parsing BLP and DBC files into images and tables.
- @LordJZ: the DBSimple wrapper and the original user-class design.
- @kliver: the initial screenshot-upload implementation.
- @Sarjuuk: project maintenance.

Special thanks to the original database website for its presentation and design.
