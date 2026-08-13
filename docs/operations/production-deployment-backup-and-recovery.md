# Private production deployment, installation, backup, restore, and recovery

Canonical production topology and operational procedures. This document complements the binding [Phase 33 deployment roadmap](../roadmap/phase-33-deployment-backup-rollback.md).

## Deployment baseline

Atlas is a self-hosted internal company system. Its baseline production deployment is private and does not require public Internet exposure, public DNS, or a public endpoint.

The supported baseline target is one company-controlled Linux host or VM running Docker Compose. Kubernetes, Docker Swarm, distributed clustering, multi-node high availability, and public SaaS deployment are outside this baseline.

Phase 33 provides one canonical production installation workflow through an interactive installer after the expanded Phase 31 internal-communication scope is complete. It builds on the existing production images, runtime configuration validation, readiness, runtime smoke, queues, scheduler, Files, ClamAV, PDF, search, security, privacy, Reverb, self-hosted LiveKit, and Egress foundations rather than replacing them.

## Phase 28 prerequisite boundary

Phase 28 completed the prerequisite production image build, runtime configuration, `.dockerignore`/COPY boundaries, secrets handling, internal HTTP smoke stack, queue/scheduler parity, ClamAV/PDF/Search/File readiness, PostgreSQL volume verification, and backup-image buildability.

Application and nginx production images are reproducible and smoke-tested; production runtime secrets are externalized; broad `DB_SEARCH_PATH` masking is removed; and non-HTTP services remain private. Phase 33 adds the private host installation workflow, optional TLS configuration, durable local Files storage, deployment, backup, restore, and rollback on that verified runtime foundation.

Tracked Phase 28 issue IDs: `P28-RUNTIME-001` through `P28-RUNTIME-014`.

## Private production topology

```text
trusted company network / LAN / VPN
                |
                v
        Atlas reverse proxy
                |
                v
       private Docker network
       ├── PHP-FPM
       ├── Horizon
       ├── queue workers
       ├── scheduler
       ├── PostgreSQL
       ├── Redis
       ├── Meilisearch
       ├── ClamAV
       └── Chromium/PDF
```

Normal Atlas HTTP remains reverse-proxy fronted. After Phase 31 adds RTC, trusted LAN/VPN clients may additionally reach only the configured LiveKit WebRTC/TURN endpoints required by browser media connectivity. PostgreSQL, Redis, Meilisearch, ClamAV, PHP-FPM, Horizon, queue workers, scheduler, Chromium/PDF, LiveKit API/Admin surfaces, Egress control/health endpoints, and recording staging remain private and must not be exposed to normal clients.

The host administrator can bind the reverse proxy to an internal interface, trusted subnet, VPN-accessible interface, or equivalent company-controlled network. Clients may reach Atlas through a LAN, company network, VPN, or another controlled private segment. Atlas does not implement a VPN, firewall, or enterprise network-policy product; infrastructure-level access policy remains the host/network administrator's responsibility.

The Phase 28 smoke stack remains intentionally internal HTTP, bound to `127.0.0.1:8080` by default. It is a runtime proof, not the Phase 33 deployment topology.

### Future LiveKit, TURN, and Egress production boundary

Phase 33 installs and configures Atlas-managed self-hosted LiveKit; the baseline does not connect to an already-existing external LiveKit installation and does not require LiveKit Cloud. LiveKit service credentials are generated and externalized like other production secrets, consumed only by required services, omitted from normal logs, and unavailable to ordinary Admin UI.

The installed LiveKit version and configuration determine the exact WebRTC, signaling, ICE, and TURN exposure. Deployment and firewall documentation must follow current official LiveKit self-hosting guidance for that pinned release rather than treating historical default port numbers as permanent. TURN/TLS must support the accepted LAN/VPN/browser topology using a company/internal trusted certificate, administrator-supplied certificate, or existing company TLS termination where technically valid; public Let's Encrypt remains optional.

Self-hosted LiveKit Egress runs as a separate private service, uses the LiveKit-compatible Redis/control path, exposes only private health/metrics as configured, and receives explicit CPU/RAM/storage capacity planning. Recording failure is isolated from normal Chat and unrecorded Meeting participation. Temporary recording staging is protected, permission-scoped, encrypted at rest where applicable, bounded, and cleaned. The finalized Meeting recording becomes a Files-owned artifact; transcript records and versions remain PostgreSQL state.

Readiness and Admin System Status expose safe aggregate LiveKit RTC, TURN where practical, Egress, and recording-processing health without Meeting names, participant lists, media, recordings, transcripts, or credentials. Exact-release deployment and rollback pin compatible Atlas, Reverb, LiveKit, and Egress versions/configuration. Files backup/restore includes finalized Meeting recordings, and PostgreSQL backup/restore includes transcript state; Egress staging is not a second canonical data store.

## TLS and reverse proxy

HTTPS/TLS is supported and recommended for production, including internal deployments, but public Let's Encrypt and public ACME access are not required. A concrete installation may use:

- a certificate issued by an internal or company CA;
- an administrator-supplied certificate and key;
- TLS terminated by existing company infrastructure;
- an external reverse proxy or load balancer;
- Let's Encrypt or another ACME provider when that installation chooses it.

When Atlas terminates TLS, certificate and key material remains outside source control, appropriate security headers apply, and HTTP redirects to HTTPS. Trusted internal HTTP is permitted only when the installation operator explicitly accepts it for the controlled deployment environment. External TLS termination must preserve the same private backend topology and trusted proxy configuration.

## Durable PostgreSQL and Files storage

PostgreSQL runs inside the production Docker Compose stack under project control and uses durable persistent storage. A container recreation, image rebuild, or release deployment must not remove database data.

Atlas production Files use local persistent private storage as the baseline/default backend. The Files path is mounted into the required runtime services from a durable volume or host path outside ephemeral application-container storage. Ownership, permissions, backup access, and the minimum runtime write access must be explicit. Container recreation, image rebuild, and release deployment must not remove application files.

```text
Atlas Files
     |
     v
storage abstraction
     |
     ├── local persistent storage   <- baseline/default
     |
     └── S3-compatible storage      <- optional/future deployment choice
```

The existing backend-neutral Files storage abstraction remains authoritative. Business code and the Files module must not be coupled to the local filesystem, S3, or AWS-specific behavior. S3-compatible Files storage is an optional/future deployment backend and is not required by Phase 33. Local persistence must retain the existing ClamAV, quarantine, validation, metadata, and audit behavior.

### PostgreSQL 18 durability boundary

The Compose service declares `PGDATA=/var/lib/postgresql/18/docker` and mounts the durable `postgres-data` volume at `/var/lib/postgresql`. This parent mount is required by the PostgreSQL 18 image layout and prevents an image update or container recreation from silently selecting an unmounted data directory. The runtime smoke proves survival across container recreation. Do not change either path independently; a future major PostgreSQL upgrade requires a planned data migration and recovery proof.

## Production encryption at rest

Production persistent data must support infrastructure-level encryption at rest. This is one production-infrastructure boundary for Atlas as a whole, not per-module application encryption. On a shared protected host/VM filesystem or volume, the boundary covers PostgreSQL, Atlas Files, Meilisearch indexes, persistent Redis data when enabled, persisted runtime logs, local backups, and future module-owned persistent state as applicable.

The concrete mechanism is deployment-specific and may be Linux full-disk/filesystem encryption such as LUKS, encrypted VM or hypervisor volumes, company-managed encrypted storage, provider-managed encrypted disks, or another reviewed equivalent. Atlas does not invent disk cryptography or separate encryption systems for every module.

The installer identifies the selected persistent storage location and, where safely practical, detects or accepts the operator-declared encryption status, displays it, and warns when storage is not known to be encrypted. It may record an explicitly accepted risk. It does not claim universal detection and must never automatically repartition, format, or encrypt host disks.

Encryption at rest protects data lost or accessed outside the authorized running system. It does not claim protection from an authorized root or infrastructure administrator while the host is running and storage is unlocked. Organizational access procedures govern that boundary; Atlas application authorization continues to protect data from ordinary users and application Administrators.

## Interactive production installation

A fresh supported production host is installed through one canonical interactive entry point. The exact command name follows repository conventions; the intended operator flow is:

```text
clone repository
↓
checkout exact release/tag/commit
↓
run production installer
↓
preflight
↓
interactive configuration
↓
secrets and persistent storage created
↓
canonical production Compose stack started
↓
migrations executed
↓
first administrator bootstrapped
↓
backup schedule configured
↓
readiness verified
↓
installation completed
```

The installer is an orchestrator/bootstrapper, not a second deployment framework. It reuses the canonical Docker Compose, deployment, secret, migration, readiness, backup, and application commands. It does not reimplement Docker, PostgreSQL, networking, certificate management, or service management.

Before destructive or persistent changes, preflight verifies the supported operating environment, Docker and Docker Compose availability, permissions, filesystem access, practical disk-space requirements, requested network binding, storage paths, existing Atlas installation state, and exact release identity. A failed preflight stops before leaving a partially installed system whenever possible.

Interactive configuration collects the installation/company name, hostname or internal address, timezone, internal network binding, HTTP/TLS mode, supplied certificate paths, persistent Files path, PostgreSQL storage, backup location/retention/schedule, required SMTP configuration, optional Sentry configuration, and first-administrator identity where applicable. Values that Atlas can safely own are generated rather than requested.

Secrets are generated securely, remain outside source control, and are not printed in normal logs. Generated `.env` or secret values are never committed. The installer bootstraps the first production administrator securely and never relies on known development credentials such as `admin@example.test` and `password`.

Re-running the installer never wipes or silently reinitializes an existing installation. It detects that installation and stops safely or directs the operator to canonical operational commands. Safe and idempotent validation may repeat; destructive initialization may not.

During preflight/configuration, the installer reports the known or declared encryption-at-rest status for selected persistent storage, warns when it is unknown or absent, and points the operator to this production encryption contract. Host disk-encryption provisioning remains an infrastructure responsibility.

## Production images and secrets

`docker/production/php/Dockerfile` builds one versioned `atlas-runtime:<release-id>` artifact used unchanged by PHP-FPM, Horizon, and the scheduler. Composer installs from `composer.lock`; Vite assets build from `pnpm-lock.yaml`. Runtime artifacts come from repository source and lockfiles, not host `vendor`, `node_modules`, or local build leftovers. Application commands and runtime services operate as non-root users.

`docker/production/nginx/Dockerfile` builds the same locked Vite inputs, copies public files and generated assets into nginx, and forwards only `index.php` to PHP-FPM. Its internal HTTP listener does not itself claim TLS termination; Phase 33 supplies the selected reverse-proxy/TLS deployment configuration.

Use `docker/production/.env.example` only as the non-secret Compose schema. Set a unique `ATLAS_RELEASE_ID` and meaningful release metadata. Secret values are external files described in `docker/production/secrets/README.md`. The repository templates contain paths only.

Production validation remains fail-fast: debug mode is disabled, the deployed marker is true, timezone and database search path match the Atlas contract, typed settings are valid, required values are non-empty, and the fake Files scanner is forbidden. Phase 33 owns host secret provisioning and rotation without weakening these Phase 28 rules.

One-off Artisan operations use the canonical operator wrapper. At the underlying Compose boundary, a new `docker compose run --rm --no-deps php-fpm ...` process passes through the image entrypoint and loads mounted `_FILE` secrets before dropping privileges; `docker compose exec` bypasses that entrypoint and is not suitable for secret-dependent application commands.

## Database and Files backup

A PostgreSQL persistent volume is not a backup. The supported production recovery strategy covers two distinct assets:

```text
PostgreSQL backup
+
Files storage backup
```

Database backup produces verified, compressed, timestamped PostgreSQL dumps. It writes through a partial artifact, verifies the dump catalog, and atomically publishes the complete artifact. Files backup safely captures the durable local Files store while avoiding inconsistent or partial state where practical. Both artifact types remain outside ephemeral application containers and have configurable local retention.

Local persistent backup storage is the baseline destination. A second or off-host copy is strongly recommended because same-host-only backups do not protect against complete host loss. The destination is installation-specific and may be a NAS, mounted company backup storage, NFS, another host, enterprise backup software, S3-compatible object storage, or a future backend. Atlas does not hardcode AWS/S3 or build speculative adapters for every destination; it may produce stable artifacts for company infrastructure to copy off-host.

The canonical host `backup` command performs the supported database and Files backup procedure, verifies the produced artifacts, reports failure clearly, and supports recurring execution according to the configured schedule and retention.

Portable or off-host database dumps, Files archives, combined recovery bundles, and other sensitive backup artifacts are independently encrypted before leaving the trusted encrypted storage boundary. Disk encryption alone does not protect an artifact after it is copied elsewhere. The encrypted artifact format and tooling verify both internal backup validity and the encrypted lifecycle without hardcoding the destination to AWS or S3; artifacts may be transferred to NAS, NFS, another company host, removable media, enterprise backup systems, or S3-compatible storage.

Encryption keys and passphrases remain outside source control and normal logs, are never stored inside the artifact they protect, and follow documented operator/infrastructure handling. Atlas does not build a custom enterprise key-management system.

### Preliminary Phase 28 backup image

The opt-in `operations` profile builds `atlas-backup:<release-id>` from the PostgreSQL 18 client image. Its narrow preliminary interface accepts only:

```text
atlas-backup create
atlas-backup verify /backups/<artifact>.dump
```

This is a safe buildable foundation, not the complete production backup system. Phase 33 adds scheduling, retention, Files coverage, off-host strategy, restore, application verification, monitoring, and operational runbooks.

## Restore and recovery

Restore is a first-class supported production operation. The canonical host `restore` command:

1. identifies the selected encrypted artifact;
2. obtains the key or secret through the approved external mechanism;
3. decrypts into a controlled temporary or recovery location;
4. verifies the backup before destructive work;
5. requires explicit operator confirmation;
6. creates and verifies a new pre-restore backup of the current production state;
7. restores the selected PostgreSQL state safely;
8. restores Files according to the selected documented recovery procedure;
9. runs application verification and readiness;
10. safely removes temporary plaintext recovery material when no longer required;
11. fails safely and reports clearly when recovery cannot complete.

Backup existence alone is insufficient. Phase 33 requires a real restore drill and verification of representative restored application data. Restore procedures must state how database and Files artifacts relate so the operator does not unknowingly restore an inconsistent pair.

## Exact-release deployment

Production never deploys a floating `main` branch or floating image. The operator selects an exact release, tag, or commit and exact image identifiers. Releases are prepared separately from the active release whenever possible:

```text
exact release selected
↓
release prepared in a versioned location
↓
locked production dependencies and frontend built
↓
required checks
↓
database backup when required
↓
compatible migrations
↓
new-release readiness
↓
atomic current-release switch
↓
PHP-FPM/Horizon/workers/scheduler reload
↓
post-switch readiness
```

Application source remains immutable inside running containers. Operators do not edit production container files manually. Maintenance mode is reserved for incompatible operations that cannot be performed safely while serving traffic.

## Rollback and migration safety

The previous deployable release is tracked. If post-switch readiness fails, Atlas returns automatically to that release only when database migrations remain compatible. Automatic rollback must never imply that every migration can be reversed safely.

Risky or irreversible migrations require explicit recognition, a fresh verified backup, a documented deployment plan, and a documented recovery strategy. When compatibility cannot be established, the rollback command refuses automatic action or requires an explicit operator-led recovery procedure.

## Operator interface and release metadata

Routine production work uses one small canonical operator interface rather than memorized internal Docker commands. It provides or wraps at least:

- `install`;
- `deploy`;
- `rollback`;
- `status`;
- `restart`;
- `logs`;
- `artisan`;
- `composer`;
- `pnpm`;
- `backup`;
- `restore`.

Release metadata records the release ID, Git commit, Git tag, exact image identifiers, deployment timestamp, and operator identity where available. Appropriate operational surfaces expose it through Admin System Status, readiness, logs, and Sentry without leaking secrets.

## Production acceptance and recovery drills

Phase 33 acceptance uses a clean supported production-like host or VM. The proof covers installer preflight, exact-release identity, interactive configuration, first-admin bootstrap, readiness, administrator sign-in, representative Files storage and ClamAV behavior, Atlas-managed LiveKit/TURN connectivity, separate Egress readiness and failure isolation, a finalized Files-owned recording, PostgreSQL and Files survival across container recreation, documented persistent-storage encryption status, recurring and manual backup, independently encrypted database and Files/recovery artifacts, recording and transcript backup/restore, approved-path decryption, decrypted-artifact verification, pre-restore backup, a real restore drill, plaintext-temporary cleanup, representative restored state and readiness, exact-release deployment, readiness-gated switching, safe rollback, backend network isolation, configured TLS, and release metadata. It may use an appropriate production-like encrypted-volume or environment fixture and does not need to format or encrypt the test machine's disk automatically.

The permanent operational guardrails are:

- only the reverse proxy and explicitly configured LiveKit WebRTC/TURN endpoints may become reachable from the trusted client network;
- backend/runtime services remain private;
- PostgreSQL and Files never rely on ephemeral container storage;
- installation uses an exact release and never development credentials;
- installer reruns never destroy an existing installation;
- secrets never enter source control;
- persistent production storage encryption status is verified or its accepted risk is documented;
- portable/off-host backup artifacts are independently encrypted and their keys remain separate;
- restore requires confirmation and a pre-restore backup;
- restore safely cleans temporary plaintext recovery material;
- readiness gates deployment switching;
- automatic rollback respects migration compatibility;
- running containers are not modified manually as a normal deployment procedure.

## Internal HTTP runtime smoke

Run static validation without starting services:

```text
composer runtime:check
```

Run the isolated build and runtime proof from a Docker-capable trusted development environment:

```text
composer runtime:smoke
```

The smoke command builds the production artifacts, verifies final-image boundaries and non-root execution, starts isolated PostgreSQL, Redis, Meilisearch, ClamAV, PHP-FPM, nginx, Horizon, and scheduler services, applies fresh migrations, and checks liveness/readiness and generated assets. It exercises queues, scheduler, ClamAV, PDF rendering, and PostgreSQL persistence across recreation before clean teardown.

This smoke remains a runtime prerequisite, not a deployment procedure. It does not install a production host, configure the selected network/TLS mode, manage releases, schedule database and Files backups, restore production state, or perform rollback. Those remain Phase 33 work.

## Manual Ubuntu/Debian runtime parity

The non-container Ubuntu/Debian runtime contract remains relevant for behavioral parity, external dependency documentation, and supported operational mechanisms. It requires the same PHP extensions, locked Composer and Node dependencies, Chromium, ClamAV, PostgreSQL, Redis, Meilisearch, queues, scheduler, writable application storage, private Files storage, health checks, and non-root execution as the container runtime.

This parity contract does not replace the Phase 33 baseline installation workflow: the canonical production installer targets the company-controlled single-host/VM Docker Compose topology.

## Release checklist

Before any release containing copied third-party UI templates, paid component source, paid chart source, or proprietary design-system assets, verify and document that the license permits the intended Atlas use, redistribution, and source-transfer model.
