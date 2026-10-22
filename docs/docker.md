# Docker installation and operation

For live source editing and containerized regression suites, see the optional
[development environment](docker-development.md).

The image uses PHP 8.5 with Apache, the locked Composer dependencies, all required
PHP extensions, and GMP for TrinityCore authentication (see the
[official PHP image](https://hub.docker.com/_/php)). Docker Compose v2.20 or
newer and a Docker daemon running Linux containers are required. A Makefile
provides shortcuts; every target prints its equivalent Compose command.

The default host binding is **127.0.0.1:8010**. Apache listens on port **8080
inside the container**. Database ports are not published. Additional host ports
used for local testing must be available and within **8010–8020**.

## First installation

```sh
cp .env.example .env
chmod 600 .env
```

Edit `.env` before starting. Set unique `MYSQL_ROOT_PASSWORD` and
`AOWOW_DB_PASSWORD` values. The default `COMPOSE_PROFILES=local-db` starts a local
AoWoW database; point `WORLD_DB_HOST` and the other world settings at a compatible
existing TrinityCore database with a dedicated read-only account. Optional auth
and characters connections are disabled while their `*_DB_NAME` values are empty.
Use single-quoted values in `.env` when passwords contain `$`, `#` or spaces.

```sh
make docker-build
make docker-up
docker compose ps
```

The one-shot `init` service provisions persistent directories, refreshes shipped
assets, and generates private configuration. Local MySQL creates **only the
AoWoW database** and imports `setup/sql/01-db_structure.sql`,
`02-db_initial_data.sql`, and `03-db_initial_articles.sql`, in that order, once.
Wait for `db` to become healthy before running setup. `web` remains unhealthy
until required database connections are available; importing a compatible world
database is still necessary. Database health and the PHP probe do not establish
that client assets or application setup are complete.

Follow the [client extraction and audio instructions](installation.md#4-extract-the-client-archives-mpqs)
on the host. Extraction and audio encoders are not bundled. Copy the **contents**
of the extracted directory into the named volume, preserving case and paths.
The web mount is read-only; populate it through the maintenance service:

```sh
docker compose run --rm --no-deps --volume "$PWD/extracted:/input:ro" cli \
  cp -a /input/. /opt/aowow/setup/mpqdata/
make docker-setup ARGS='--skip-sounds'
```

Use the maintenance command above for copying; its process owns the destination
as the web UID. Omit `--skip-sounds` after preparing audio if sound generation is
required. The setup wizard remains interactive and resumable. Leave the generated
database entries unchanged unless intentionally reconfiguring them. Set the site
and static host to `http://localhost:8010` for local use; choose file caching if
no Memcached server is provided. Check the selected locales and complete the
administrator creation step.

Open **http://localhost:8010**. To change the local host port, set `HTTP_PORT` to
an available value in `8010–8020`, recreate `web`, and update the application's
site/static URL settings with `--configure`. Port settings do not automatically
rewrite URLs stored in the application database.

## Database layouts

| Layout | `COMPOSE_PROFILES` | Connection settings |
| --- | --- | --- |
| Local AoWoW, existing TrinityCore | `local-db` | AoWoW host `db`; world/auth/characters use external DNS names |
| Both MySQL instances in Docker | `local-db,trinity-db` | AoWoW host `db`; TrinityCore schemas use `trinity-db` |
| All databases external | empty | Set every enabled role to the existing server and database |

Every role has independent host, port, username, password and database variables.
`localhost` inside a container refers to that container. Prefer DNS names and
dedicated TCP connections. For external AoWoW, create an empty database and grant
the application user read/write/schema privileges **only on that database**.
After initialization, import the shipped application SQL explicitly:

```sh
make docker-init
make docker-import
```

The importer refuses any non-empty schema, including partially imported schemas.
Never use it for retained application databases. Updates use `--update`, not
first-install SQL. Optional MySQL-only SQL file 04 is not imported automatically;
see the [installation guide](installation.md#2-prepare-the-database).

### Local TrinityCore instance

Set `COMPOSE_PROFILES=local-db,trinity-db` and a separate
`TRINITY_MYSQL_ROOT_PASSWORD`, then start the stack. This service creates **no
TrinityCore schemas, users, or data automatically**. Provision schemas and
accounts using its administrative client:

```sh
docker compose exec trinity-db mysql -uroot -p
```

An example for a new world schema, entered interactively with actual credentials:

```sql
CREATE DATABASE world CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aowow_reader'@'%' IDENTIFIED BY '<unique password>';
GRANT SELECT ON world.* TO 'aowow_reader'@'%';
```

Import a compatible, operator-supplied TrinityCore world dump using an
administrative account. One method is to copy the dump into this database
container, open the interactive client, select the schema, and use `SOURCE`:

```sh
docker compose cp ./world.sql trinity-db:/tmp/world.sql
docker compose exec trinity-db mysql -uroot -p world
```

```sql
SOURCE /tmp/world.sql;
```

Prepare optional auth/characters schemas the same way and grant only `SELECT`
on each selected schema. Separate users per database are supported. Set
`AUTH_DB_NAME`, `CHARACTERS_DB_NAME`, and `CHARACTERS_REALM_ID` when needed.
TrinityCore servers themselves use their own accounts with their required write
privileges; never reuse those credentials for AoWoW. Compatibility requirements
remain those in the [installation guide](installation.md#requirements).

### Existing servers, host access and shared Docker networks

Existing MySQL and MariaDB servers are supported through independent connection
settings. Permit connections from the Docker host/network and grant only the
necessary schema permissions. External database services are not Compose
dependencies; startup connectivity is reported by the application healthcheck.

For a database on the Docker host, use a reachable host address. On Linux, an
ignored `docker-compose.override.yml` can add:

```yaml
services:
  init:
    extra_hosts: ["host.docker.internal:host-gateway"]
  cli:
    extra_hosts: ["host.docker.internal:host-gateway"]
  web:
    extra_hosts: ["host.docker.internal:host-gateway"]
```

Set the corresponding DB hostname to `host.docker.internal`. For databases in
another Compose project, attach `web`, `init`, and `cli` to that project's
existing network using a Compose override with an `external: true` network;
retain their existing `database` network and use the database's network alias.
No host database port needs to be published between containers sharing a network.

## Configuration and secrets

`.env` bootstraps configuration; it is not loaded on every PHP request.
`config/config.php` and `config/security.php` persist in a named volume outside
the document root. The web service receives no database password environment
variables and mounts configuration read-only. Files are deployment-owned
`root:www-data`, mode `0640`, with directory mode `0750`.

On first initialization, a random cache key is generated and operator IP access
is disabled. Existing files are retained on later starts. To apply environment
database changes deliberately:

```sh
make docker-configure
```

This refreshes the three main connection entries and the configured characters
realm, preserving other realm entries, additional connection options and
unrelated configuration. An empty auth database name disables that connection.
An empty characters database name leaves existing realm entries untouched;
remove retired realms explicitly. Changes to a live database user's password
must also be made on the database server: changing MySQL initialization variables
does not rotate users in an existing data volume.

For private edits or additional character realms:

```sh
make docker-shell
```

Use the shell to write config files with PHP or copy a prepared file from a
read-only bind mount supplied to `docker compose run`. Additional realms use
`$AoWoWconf['characters'][REALM_ID]` entries with `host`, integer `port`, `user`,
`pass`, `db`, and `prefix`. Edit `security.php` for SMTP, Turnstile, operator IPs,
or cache key rotation. CLI commands temporarily permit private config writes in
the maintenance container, then restore ownership/modes on normal exit, failure,
or handled termination. The web container always retains a read-only mount.
After a forced kill or host crash, rerun `make docker-init` to restore ownership.
Maintenance commands are serialized with a lock in the config volume.

For production, prefer file-mounted secrets over plaintext password variables.
Set a role's `*_DB_PASSWORD_FILE=/run/secrets/<name>` and leave its password
variable blank. Add the secret to **both init and cli**, and also **db** when it
is the local AoWoW password. For example, an ignored override:

```yaml
services:
  init:
    secrets: [aowow-password]
  cli:
    secrets: [aowow-password]
  db:
    secrets: [aowow-password]
secrets:
  aowow-password:
    file: ./docker/secrets/aowow-password
```

Root password files are mounted only into their respective database service.
World/auth/characters password files are mounted into init and cli. Secret files
needed by the CLI must be readable by UID 33; protect their host parent directory
and never commit them. Compose mounts file secrets; this is not an encrypted
secret store (see [Compose secrets](https://docs.docker.com/compose/how-tos/use-secrets/)).
Keep `.env`, overrides, backups, private config, and secret files
out of source control and build contexts.

## Persistent volumes and maintenance

| Volume | Container path | Purpose |
| --- | --- | --- |
| `database`, `trinity-database` | `/var/lib/mysql` in respective services | MySQL data |
| `config` | `/opt/aowow/config` | Private settings and initialization metadata |
| `cache` | `/opt/aowow/cache` | Cache, private sessions and setup state |
| `datasets` | `/opt/aowow/datasets` | Generated datasets |
| `uploads` | `/opt/aowow/static/uploads` | User content |
| `downloads`, `widgets`, `javascript` | Respective `static/` directories | Generated and shipped public files |
| `wow-images` | `/opt/aowow/static/images/wow` | Extracted/generated images |
| `sounds`, `modelviewer` | Respective `static/` directories | Optional large assets |
| `mpqdata` | `/opt/aowow/setup/mpqdata` | Operator-provided extracted client data |

The image sets `AOWOW_ROBOTS_FILE=static/download/robots.txt` for the CLI generator;
Apache serves that persistent output at `/robots.txt`. Non-container installs
continue generating the file in the application root.

Initialization refreshes image-owned assets and removes only files recorded as
previously shipped and no longer shipped. Generated outputs and uploads remain.
Do not customize shipped files inside volumes; make source changes and rebuild.
The web container has a read-only root filesystem; only approved volumes and
temporary storage are writable. Never bind-mount the whole checkout over the
production application directory.

```sh
make docker-cli ARGS='--help'
make docker-cli ARGS='--configure'
make docker-cli ARGS='--validate-schema'
make docker-cli ARGS='--prune'
make docker-composer
make docker-logs
make docker-down
```

`docker-composer` checks the installed platform. Composer installation happens
during `docker-build`; use a changed lockfile and rebuild to update dependencies.
`docker-down` retains named volumes. **`docker compose down --volumes` destroys
persistent data** and is only appropriate for explicitly disposable stacks.

For upgrades, back up the application database and persistent content, rehearse
on restored copies under a distinct `COMPOSE_PROJECT_NAME` and HTTP port, then
take a final backup and put the site into maintenance. Stop `web`, build the new
image, run `make docker-init` to refresh shipped assets, and run
`make docker-update` with compatible world/client inputs. Recreate `web` after
validation. Never run first-install imports during upgrades. A rollback after
schema or generator changes requires the matching old image **and restored
database/config/assets**, not just restarting an old container.

Use logical database dumps or database-consistent backups. Archive config,
uploads, datasets and generated assets while writes are stopped; preserve private
cache/session permissions when restoring. Keep extracted client data backed up
or reproducible. Test restores in a separate Compose project. Do not copy live
MySQL volume files as an ordinary filesystem backup.

An import failure leaves a pending marker and/or partial tables; automatic replay
is blocked. Inspect logs and restore a known-good backup. For a confirmed
disposable new database only, recreate that database/data volume deliberately
and repeat initialization. Never delete retained content to satisfy the empty
schema guard.

## Production deployment and validation

Terminate TLS at a trusted reverse proxy. Set the canonical site/static URLs and
`FORCE_SSL` using CLI configuration so secure cookies and generated URLs match
the public HTTPS origin. Bind the published HTTP listener to the proxy's
reachable interface deliberately. Browser operator routes use the actual
`REMOTE_ADDR` policy; do not allow a shared proxy address to enable them.

External database connections should cross a trusted private network or an
authenticated encrypted tunnel. These Docker variables do not configure MySQL
certificate verification; use a verified transport appropriate to the host.
Never grant root or TrinityCore write credentials to the web application.

Pin image digests in production using `PHP_IMAGE`, `COMPOSER_IMAGE`,
`MYSQL_IMAGE`, and a release-specific `AOWOW_IMAGE`. Rebuild regularly for security
updates. Choose CPU/memory limits for the host and set
`APACHE_MAX_REQUEST_WORKERS` (default 4) for web concurrency. Setup
can consume over 1 GB per PHP process; the application's database-controlled
`MEMORY_LIMIT` can override the PHP baseline. Keep generator workloads separate
from peak web traffic, and size concurrent workers against available memory.

`make docker-test` runs an isolated smoke stack with disposable volumes and
available HTTP ports in 8010–8020. It exercises imports, grants, healthchecks,
permissions, persistence and asset refresh. The existing PHP/HTTP and Apache
regression suites remain required. Fixture success does not establish full setup
acceptance: complete a rehearsal using a compatible TrinityCore database and
properly extracted client files before deployment.
