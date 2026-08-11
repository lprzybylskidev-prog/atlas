# Production deployment, backup, restore, and recovery

Canonical production topology and operational procedures. This document complements the binding deployment roadmap phase.

## Phase 28 prerequisite boundary

Current state: production deployment is planned for Phase 29. Phase 28 completed the prerequisite production image build, runtime configuration, `.dockerignore`/COPY boundaries, secrets handling, internal HTTP smoke stack, queue/scheduler parity, ClamAV/PDF/Search/File readiness, PostgreSQL volume verification, and backup-image buildability.

Target state before Phase 29: application and nginx production images are reproducible and smoke-tested; production runtime secrets are externalized; broad `DB_SEARCH_PATH` masking is removed; non-HTTP services remain private; and Phase 29 can build HTTPS, deployment, backup, restore, and rollback on a verified runtime foundation.

Tracked issue IDs: `P28-RUNTIME-001` through `P28-RUNTIME-014`.

## Production Topology

- The baseline production topology is one application host or VM running Docker Compose.
- In the Phase 29 deployment topology, public traffic will enter only through the TLS reverse proxy on ports 80/443. The Phase 28 smoke stack intentionally provides HTTP only, bound to `127.0.0.1:8080` by default.
- PostgreSQL runs inside the production Docker Compose stack under project control and uses a durable persistent volume.
- Redis, Meilisearch, ClamAV, Horizon, queue workers, scheduler, and the Chromium renderer remain private.
- The production PHP runtime image includes Node.js, the runtime `playwright` package, and system Chromium so queued PDF exports can render through the same Node/Playwright/Chromium chain that readiness verifies. Chromium runs as the unprivileged `www-data` user. Playwright's inner Chromium user-namespace sandbox is explicitly disabled because Docker's default seccomp profile blocks that namespace operation; never compensate with `SYS_ADMIN`, privileged mode, or an unconfined seccomp profile. The non-root private container boundary is the accepted runtime sandbox.
- Queue workers and Horizon are configured to tolerate long managed-process and import jobs. Keep worker timeout and Redis `retry_after` aligned so operational scripts that may run for hours are not duplicated while still executing.
- Use versioned releases tied to exact commits/tags/images and switch through a `current` symlink only after readiness succeeds.
- Do not introduce Kubernetes, Docker Swarm, or a distributed cluster unless explicitly requested for a concrete project.
- Secrets stay outside the repository and application containers are never edited manually in place.

## Phase 28 immutable image contract

`docker/production/php/Dockerfile` builds one versioned `atlas-runtime:<release-id>` artifact used unchanged by php-fpm, Horizon, and the scheduler. Composer installs from `composer.lock` with development dependencies and Composer itself absent from the final image. Vite assets are built from `pnpm-lock.yaml`; only production Node dependencies, the Node binary, system Chromium, application source, and runtime PHP extensions enter the final stage. Tests, host `vendor`, host `node_modules`, package managers, documentation, caches, local configuration, and secret files are excluded by `.dockerignore` and explicit COPY allowlists.

The PHP container starts with the minimum privilege needed to read owner-only Compose secret files, validates and exports the registered `_FILE` values, and immediately executes its configured command as `www-data`. PHP-FPM logs to the writable application storage volume. Application commands never run as root. The image build performs package discovery, authoritative autoload generation, and view compilation; runtime configuration and route caches remain deployment-time concerns because they depend on external environment values.

`docker/production/nginx/Dockerfile` independently builds the same locked Vite inputs, copies real public files and generated assets into nginx, and serves them with immutable caching. It forwards only `index.php` to PHP-FPM, rejects arbitrary PHP paths, and listens on internal HTTP port `8080`. It does not claim TLS termination.

Pinned PHP, Node.js, Composer, pnpm, nginx, PostgreSQL, Redis, and Meilisearch image tags are intentional runtime inputs. Upgrade them as a separate reviewed change, validate lockfile compatibility, rebuild all affected artifacts, and rerun the runtime smoke. The build context must remain source-only; `.dockerignore` and the explicit Dockerfile COPY lists are permanent reproducibility and secret-exclusion controls.

## Production environment and secrets contract

Use `docker/production/.env.example` only as the non-secret Compose schema. Set a unique `ATLAS_RELEASE_ID` and meaningful release metadata. Every application container receives the same explicit production settings, `Europe/Warsaw`, and minimal PostgreSQL `DB_SEARCH_PATH=public`; module schemas are always referenced explicitly by application code.

Secret values are external files described in `docker/production/secrets/README.md`. The runtime supports `<VARIABLE>_FILE` for the application key, PostgreSQL, Redis, Meilisearch, SMTP, Sentry, and Files S3 credentials, rejects simultaneous plain and file values, and fails startup when a configured secret is unreadable. The repository templates contain paths only. The Compose stack keeps PostgreSQL, Redis, Meilisearch, PHP-FPM, workers, scheduler, and operational backup traffic on the internal network.

Run one-off Artisan operations with `docker compose run --rm --no-deps php-fpm ...`, not `docker compose exec`. A new Compose run passes through the image entrypoint and therefore loads mounted `_FILE` secrets before dropping privileges; an exec-created process bypasses the entrypoint and must not be used for secret-dependent application commands.

Production validation is fail-fast: debug mode must be disabled, the deployed marker must be true, timezone and database search path must match the Atlas contract, ports and booleans must be typed correctly, required settings must be non-empty, and the fake Files scanner is forbidden. Phase 28 establishes this schema; Phase 29 owns host secret provisioning and rotation.

## Internal HTTP smoke stack

Run static validation without starting services:

```text
composer runtime:check
```

Run the isolated build and runtime proof from a Docker-capable trusted development environment:

```text
composer runtime:smoke
```

The smoke command builds all three artifacts from repository source, verifies the final-image allowlists and non-root application UID, rejects unsupported backup commands, starts isolated PostgreSQL, Redis, Meilisearch, ClamAV, PHP-FPM, nginx, Horizon, and scheduler services, and applies fresh migrations. It checks liveness/readiness and a generated Vite asset, executes a harmless probe through every canonical queue, proves scheduler heartbeat freshness, rejects the EICAR test payload through real ClamAV signatures, renders and validates a true PDF through Node/Playwright/system Chromium, recreates PostgreSQL, and proves a probe row survived in the PostgreSQL volume. It then performs a clean Compose teardown. `ATLAS_SMOKE_HTTP_PORT` may select a different loopback port; inside the Dev Container, retain `ATLAS_WORKSPACE_SOURCE` so the host Docker daemon can resolve temporary secret paths.

This is deliberately not a deployment procedure. It provides no HTTPS, certificates, public host routing, release switching, backup schedule, retention, encryption, off-host copy, restore drill, or rollback. Those remain Phase 29.

## PostgreSQL 18 durability boundary

The Compose service declares `PGDATA=/var/lib/postgresql/18/docker` and mounts the durable `postgres-data` volume at `/var/lib/postgresql`. This parent mount is required by the PostgreSQL 18 image layout and prevents an image update or container recreation from silently selecting an unmounted data directory. The runtime smoke explicitly proves survival across container recreation. Do not change either path independently; a future major PostgreSQL upgrade requires a planned data migration and recovery proof.

## Preliminary backup image

The opt-in `operations` profile builds `atlas-backup:<release-id>` from the PostgreSQL 18 client image. Its narrow interface accepts only:

```text
atlas-backup create
atlas-backup verify /backups/<artifact>.dump
```

`create` reads the database password from a mounted secret, writes a compressed custom-format dump below `/backups` through a `.partial` file, verifies its catalog, refuses overwrites and paths outside the backup volume, and atomically publishes the completed artifact. `verify` checks only that an existing dump catalog is readable. The service runs as the PostgreSQL image's unprivileged user and is never started by the default profile.

This interface is only a safe buildable foundation. It is not an accepted production backup system: scheduling, retention, encryption, off-host replication, restore into an isolated database, application-level verification, monitoring, runbooks, and rollback remain binding Phase 29 work.

## Manual Ubuntu/Debian runtime parity

Atlas may also be installed directly on an Ubuntu/Debian-style server without the production containers. That installation must satisfy the same external mechanism contract as the Dev Container and production image:

- PHP with the extensions used by the production PHP image;
- Composer dependencies installed from the committed lockfile;
- Node.js available on `PATH`;
- production Node dependencies installed with `pnpm install --frozen-lockfile --prod`;
- an executable Chromium-compatible browser, preferably the distribution `chromium` package or an explicit path configured with `ATLAS_HEALTH_CHROMIUM_BINARY`;
- ClamAV `clamd` reachable on the private network (normally TCP 3310), with `freshclam` signature updates and a persistent signature directory;
- PostgreSQL, Redis, Meilisearch, queue workers, and scheduler configured to the same service contracts as the container topology;
- writable application storage and private file storage paths owned by the runtime user.

After installation, run `/health/ready` before accepting traffic. Admin System Status must show the same external mechanism state that would be expected in the Dev Container and production Docker stack. Chromium/PDF is healthy only when Node, the Atlas renderer script, the runtime `playwright` package, and an executable browser are all available to the PHP runtime user.

Manual services run `php artisan horizon` and `php artisan schedule:work` as the same non-root release user. Configure the service manager with a 60-second stop allowance, restart-on-failure, and graceful release switching via `php artisan horizon:terminate`. Validate with `php artisan horizon:status`, `php artisan system:queue-smoke`, and `php artisan system:scheduler-status`.

## Release checklist

Before any release containing copied third-party UI templates, paid component source, paid chart source, or proprietary design-system assets, verify and document that the license permits the intended Atlas use, redistribution, and source-transfer model.
