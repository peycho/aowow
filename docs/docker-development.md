# Docker development and testing

The optional development image extends the production PHP 8.5 Apache image with
Composer development dependencies, Node 24/npm, Python 3, Git and Chromium.
Normal installation and production Docker commands continue using the production
image. No host PHP, Composer, Node or browser installation is required.

Host requirements are Docker, Docker Compose **2.32+**, Bash and Python 3; Make
is optional. The Apache policy suite also uses host curl. Development HTTP uses
**127.0.0.1:8011** by default; Apache fixture tests use **127.0.0.1:8012**. Keep
additional local published ports in **8010–8020**. Database ports are unpublished.

## Start a development site

```sh
make dev-init
make dev-build
make dev-up
make dev-watch
```

`dev-init` creates an ignored `.env.dev` with independent random passwords and
mode `0600`. Existing values are retained. Edit it to choose database endpoints,
an available HTTP port, or a unique `COMPOSE_PROJECT_NAME` for another checkout.
The default project is `aowow-dev`, which gives it its own networks and volumes,
separate from the production Compose project's data. Never use production
credentials or connect this mutable development environment to retained live data.

The default layout starts local AoWoW and TrinityCore MySQL instances. The
TrinityCore instance starts empty. Follow the [Docker installation guide](docker.md)
to import a compatible world database, grant read-only access, copy extracted
client files, and run setup. Use development commands throughout:

```sh
make dev-cli ARGS='--setup --skip-sounds'
make dev-cli ARGS='--configure'
make dev-shell
```

The local URL is **http://localhost:8011**. Set the application site/static URLs
accordingly. The site healthcheck reports unavailable databases until they are
provisioned. A full website requires the same TrinityCore/client inputs as normal
installation; the automated regression suites can run without those inputs.

For direct Compose commands, always use the development environment and both files:

```sh
docker compose --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml ps
docker compose --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml \
  exec trinity-db mysql -uroot -p
```

## Edit and see changes

Keep `make dev-watch` running in another terminal. Starting it refreshes a cached
source snapshot and recreates the web container, including edits made while the
watcher was stopped. Compose Watch then copies source
changes one way from the checkout into the running web container; PHP timestamps
are checked on every request. PHP, templates, localization and shipped JavaScript
can be edited without rebuilding the image. Root access rules and `crossdomain.xml`
also update their public copies. Composer manifest/lock changes rebuild the web
image with the new locked dependencies. This uses
[Compose Watch](https://docs.docker.com/compose/how-tos/file-watch/).
The startup snapshot supplies the initial files without requiring the newer
`initial_sync` Compose field, preserving compatibility with Compose 2.32+.

Private config, database storage, cache, extracted inputs, uploads, generated
assets and host Composer dependencies are excluded. Containers do not recursively
change checkout ownership or copy generated files back into source control. The
development image alone has a writable source tree; production code remains
read-only. Edits made inside a container are disposable: edit project source on
the host and commit it there.

Rebuild after changing Dockerfiles, runtime extensions or container helper scripts:

```sh
make dev-build
make dev-up
```

CLI containers use a snapshot of the image, rather than the web container's Watch
updates. `dev-cli`, `dev-shell` and test commands refresh their source/test
snapshot automatically using cached layers.
They reuse an existing production base image; use `make dev-build` after changing
runtime extensions, Apache/PHP configuration or the production Dockerfile.
Dependencies stay inside images, so installing them cannot replace host libraries.

Generated bundles still need their normal CLI generators. For example, after
editing templates compiled into `static/js/global.js`, run
`make dev-cli ARGS='--build=globaljs'`. Watch updates shipped source files;
it deliberately excludes generated outputs, uploads and extracted client data.

## Run regression suites

```sh
make dev-test
make dev-test ARGS=php
make dev-test ARGS=javascript
make dev-test ARGS=browser
make dev-test ARGS=sql
make dev-test ARGS=lint
make dev-test ARGS=apache
```

The default runs lint, PHP/HTTP, JavaScript, Chromium browser fixtures, SQL and the
existing eight Apache policy combinations. Each test invocation snapshots the
current working source and tests, including uncommitted changes, and uses locked
Composer dev dependencies. PHP/HTTP fixtures bind inside the test container and
publish no host ports. Chromium runs headlessly with the existing fixture flags.

Every non-Apache invocation gets a unique disposable Compose project and a
temporary MySQL data directory. The test database has an empty-password root
account **only inside its isolated test network**, with no published port. No
application configuration volume or deployment database credentials are mounted
into the runner. The existing SQL gate still connects to loopback: a fixed local
proxy forwards exclusively to its disposable test database. Test containers and
database state are removed on exit, including failures. Concurrent test runs use
different projects and databases.

Apache fixtures run through the host Docker CLI on port 8012. No Docker socket
is mounted inside PHP, and the helper fails if that host port is occupied. The
separate `make docker-test` command continues checking production-image startup,
permissions, imports, persistence, healthchecks and interactive CLI behavior.

## Stop and preserve development data

```sh
make dev-down
```

This stops the development project and retains its application/config/assets
volumes. Do not add `--volumes` unless deliberately discarding that development
installation. Test databases are already disposable and never require deleting
the application's volumes.

Build and test results establish local regression coverage. Full setup acceptance
still requires compatible world data and extracted assets; deployment acceptance
and production migrations remain separate work.
