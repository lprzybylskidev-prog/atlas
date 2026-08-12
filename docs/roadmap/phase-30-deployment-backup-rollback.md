# Phase 30 — Private production deployment, installer, backup, restore, and rollback

**Status:** `not started`

## Objective

Deliver a reproducible, private, self-hosted production deployment model for Atlas.

Atlas is primarily an internal company system. The baseline production deployment does not require public Internet exposure.

Phase 30 must provide:

- private/intranet production topology;
- one supported production installation workflow;
- an interactive production installer;
- durable PostgreSQL and Files storage;
- backup and restore for database and Files;
- exact-release deployment;
- readiness-gated release switching;
- rollback;
- release metadata;
- simple host/operator commands;
- documented recovery procedures.

The baseline deployment target is one company-controlled Linux host or VM running Docker Compose.

Kubernetes, Docker Swarm, distributed clustering, multi-node high availability, and public SaaS deployment are outside the baseline scope.

## Dependencies

Phase 30 depends on the completed technical foundation, including:

- production runtime images;
- runtime configuration validation;
- PostgreSQL durability;
- Files;
- ClamAV;
- Chromium/PDF;
- queues;
- Horizon;
- scheduler;
- Meilisearch;
- readiness;
- runtime smoke;
- security and privacy foundations;
- completed Phase 28 and Phase 29 acceptance work.

Phase 30 must build on those capabilities instead of replacing or redesigning them.

## P30-W01 — Private production topology

### Contract

The baseline Atlas production topology is:

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

Atlas does not require a public Internet-facing endpoint.

Only the reverse proxy may be reachable from the trusted client network.

PostgreSQL, Redis, Meilisearch, ClamAV, PHP-FPM, Horizon, workers, scheduler, and Chromium must remain private.

Network exposure must be configurable so the host administrator can bind Atlas to an internal interface, trusted subnet, VPN-accessible interface, or equivalent company-controlled network.

Atlas must not implement its own VPN or enterprise firewall product.

Host/network administrators remain responsible for infrastructure-level network policy.

### Tasks

- [ ] Finalize the single-host/VM Docker Compose production topology.
- [ ] Keep all backend/runtime services on private Docker networks.
- [ ] Make the reverse proxy the only normal client-facing service.
- [ ] Support binding the reverse proxy to a trusted/internal network interface.
- [ ] Document LAN/VPN/internal-network deployment.
- [ ] Confirm no backend service is unintentionally exposed to the client network.
- [ ] Add production topology checks where practical.
- [ ] Document Kubernetes, Swarm, clustering, and public SaaS deployment as out of baseline scope.

## P30-W02 — Production TLS and reverse proxy

### Contract

HTTPS/TLS is supported and recommended for production, including internal deployments.

Atlas must not require public Let's Encrypt or public ACME access.

Supported deployment models may include:

- a certificate issued by an internal/company CA;
- an administrator-supplied certificate and key;
- TLS terminated by existing company infrastructure;
- an external reverse proxy/load balancer;
- Let's Encrypt or another ACME provider only when a concrete installation chooses it.

Public DNS and public certificate issuance are not baseline requirements.

### Tasks

- [ ] Finalize production reverse-proxy configuration.
- [ ] Support HTTPS/TLS configuration.
- [ ] Support administrator-provided certificate/key material.
- [ ] Support TLS termination by external company infrastructure where appropriate.
- [ ] Keep Let's Encrypt/ACME optional rather than mandatory.
- [ ] Add appropriate security headers.
- [ ] Support HTTP-to-HTTPS redirect when TLS is enabled.
- [ ] Document trusted internal HTTP deployment only where explicitly accepted by the installation operator.
- [ ] Keep certificate secrets outside source control.

## P30-W03 — Durable PostgreSQL and local Files storage

### Contract

PostgreSQL remains project-controlled and uses durable persistent storage.

Atlas production Files storage uses local persistent private storage as the baseline.

Files must not depend on the ephemeral filesystem of an application container.

Container recreation, image rebuild, or release deployment must not delete application files.

The canonical storage model is:

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

Atlas business code and the Files module must not become hardcoded to a specific storage backend.

S3-compatible storage is not required by Phase 30.

Do not implement AWS-specific coupling merely for future flexibility.

### Tasks

- [ ] Finalize PostgreSQL persistent-volume configuration.
- [ ] Finalize PostgreSQL health checks and backup access.
- [ ] Define production local Files persistent storage.
- [ ] Configure the Files volume/bind mount outside ephemeral container storage.
- [ ] Define required ownership and permissions.
- [ ] Ensure application runtime has only the required filesystem access.
- [ ] Verify Files remain available after application-container recreation.
- [ ] Verify Files remain compatible with existing ClamAV/security behavior.
- [ ] Preserve the existing backend-neutral storage abstraction.
- [ ] Document S3-compatible storage as an optional/future backend, not the baseline.

## P30-W04 — Interactive production installer

### Contract

A fresh supported production host must be installable through one documented interactive installation entry point.

Expected operator flow:

```text
clone repository
↓
checkout exact release/tag/commit
↓
run production installer
↓
answer configuration questions
↓
installer performs preflight
↓
persistent storage/secrets/configuration created
↓
production stack started
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

Example operator experience:

```text
git clone <atlas-repository>
cd atlas
git checkout <exact-release>
./install
```

The exact command name may follow repository conventions, but Atlas must provide one canonical entry point.

The installer is an orchestrator/bootstrapper, not a second deployment framework.

It must reuse canonical Docker Compose, deployment, secret, migration, readiness, backup, and application commands.

Do not build a large parallel implementation of Docker, PostgreSQL, networking, certificates, or service management inside the installer.

### Required preflight

Before destructive or persistent changes, verify at least:

- supported operating environment;
- Docker availability;
- Docker Compose availability;
- required permissions;
- required filesystem access;
- sufficient disk space where practical;
- requested network binding;
- storage paths;
- existing Atlas installation detection;
- exact release identity.

Failed preflight must stop before leaving a partially installed system whenever possible.

### Installer configuration

The installer should collect the deployment-specific configuration required by the current production contract, including where applicable:

- Atlas installation/company name;
- hostname or internal address;
- timezone;
- internal network binding;
- HTTP/TLS mode;
- certificate paths when supplied;
- persistent Files path;
- PostgreSQL persistent storage;
- backup location;
- backup retention;
- backup schedule;
- required SMTP configuration;
- optional Sentry configuration;
- first administrator identity.

Do not ask questions for values that can safely be generated automatically.

### Secrets

Generate secure secrets where Atlas can safely own them, including the required application/service secrets.

Secrets must remain outside source control.

Do not print sensitive secrets into normal logs.

Do not commit generated `.env`/secret values.

### First administrator

The installer must securely bootstrap the first production administrator.

No production installation may rely on development credentials such as:

```text
admin@example.test
password
```

### Re-running the installer

Re-running the installer must never wipe or silently reinitialize an existing production installation.

The installer must detect an existing installation and stop safely or guide the operator toward the normal operational commands.

Safe/idempotent validation steps may be repeated. Destructive initialization must not.

### Tasks

- [ ] Add one canonical interactive production installer entry point.
- [ ] Add preflight checks.
- [ ] Require/verify an exact release/tag/commit.
- [ ] Collect production configuration interactively.
- [ ] Configure private/intranet network binding.
- [ ] Configure HTTP/TLS mode without assuming public Internet.
- [ ] Configure local persistent Files storage.
- [ ] Configure PostgreSQL persistent storage.
- [ ] Configure backup location, schedule, and retention.
- [ ] Generate required secrets securely.
- [ ] Keep generated secrets outside source control.
- [ ] Create required persistent directories.
- [ ] Apply correct ownership and permissions.
- [ ] Start the canonical production Compose stack.
- [ ] Run migrations.
- [ ] Run readiness.
- [ ] Bootstrap the first administrator securely.
- [ ] Configure recurring backup execution.
- [ ] Print useful release and operational information after success.
- [ ] Fail safely when preflight or readiness fails.
- [ ] Detect an existing installation and never destroy it on installer rerun.
- [ ] Document the fresh-host installation procedure.
- [ ] Test installation against a clean supported production-like host/VM.

## P30-W05 — Database and Files backup

### Contract

A PostgreSQL persistent volume is not a backup.

Atlas must provide a real PostgreSQL backup mechanism based on verified compressed timestamped dumps.

Production backup must also account for locally persisted Files.

Backup responsibilities therefore include:

```text
PostgreSQL backup
+
Files storage backup
```

Backups must live outside the ephemeral application containers.

The baseline backup destination may be local persistent backup storage.

A second/off-host copy is operationally recommended because backups stored only on the same host do not protect against total host loss.

Off-host storage must not be hardcoded to AWS/S3.

Possible deployment-specific destinations may include:

- NAS;
- mounted company backup storage;
- NFS;
- another host;
- enterprise backup software;
- S3-compatible object storage;
- another future backend.

Phase 30 must not build multiple speculative backup adapters merely to support every possible destination.

It is acceptable for Atlas to produce stable backup artifacts that company infrastructure then copies off-host.

### Tasks

- [ ] Finalize production backup tooling.
- [ ] Create compressed timestamped PostgreSQL dumps.
- [ ] Verify PostgreSQL dump integrity/catalog.
- [ ] Store backup artifacts outside ephemeral containers.
- [ ] Add configurable local backup retention.
- [ ] Include persistent Atlas Files in the recovery strategy.
- [ ] Define a safe Files backup procedure.
- [ ] Avoid inconsistent/partial Files backup state where possible.
- [ ] Add a canonical host backup command.
- [ ] Support automated recurring production backup execution.
- [ ] Document configurable/off-host backup strategy.
- [ ] Keep S3-compatible backup storage optional.
- [ ] Document that same-host-only backup does not protect against complete host loss.

## P30-W06 — Restore and recovery

### Contract

Restore must be a first-class supported production operation.

A restore operation must:

- identify the selected backup;
- verify it before destructive work;
- require explicit operator confirmation;
- create a new backup of the current production state before restore;
- restore the selected database state;
- restore Files where included in the selected recovery procedure;
- run required application verification/readiness;
- fail safely and clearly when recovery cannot be completed.

Backup existence alone is not sufficient.

Restore must be actually tested.

### Tasks

- [ ] Add a canonical host restore command.
- [ ] Require explicit confirmation.
- [ ] Verify selected backups before restore.
- [ ] Always create a pre-restore backup.
- [ ] Restore PostgreSQL safely.
- [ ] Restore Files according to the documented recovery model.
- [ ] Run post-restore readiness.
- [ ] Document complete restore procedures.
- [ ] Execute and verify a real restore drill.
- [ ] Verify representative restored application data.

## P30-W07 — Exact-release deployment

### Contract

Production deployment must never mean blindly running the current `main` branch.

Deploy an exact release/tag/commit and exact image identifiers.

Release preparation must happen separately from the currently active release whenever possible.

The intended flow is:

```text
exact release selected
↓
release prepared
↓
production dependencies installed/built
↓
frontend built
↓
checks
↓
database backup when required
↓
compatible migrations
↓
new-release readiness
↓
atomic current-release switch
↓
PHP/Horizon/workers/scheduler reload
↓
post-switch readiness
```

Do not edit application source manually inside running production containers.

### Tasks

- [ ] Define versioned release directories.
- [ ] Define the current release pointer/symlink or equally simple atomic release pointer.
- [ ] Deploy exact release/tag/commit.
- [ ] Record exact image identifiers.
- [ ] Build/prepare releases separately from the active release.
- [ ] Install production dependencies from lockfiles.
- [ ] Build production frontend assets.
- [ ] Run required checks.
- [ ] Run required database backup before risky migration work.
- [ ] Run compatible migrations.
- [ ] Gate release switching on readiness.
- [ ] Switch releases atomically.
- [ ] Reload/restart PHP-FPM, Horizon, workers, and scheduler safely.
- [ ] Run post-switch readiness.
- [ ] Keep application source immutable inside running containers.

## P30-W08 — Rollback and migration safety

### Contract

Application rollback is only automatically safe when database migrations remain compatible with the previous application release.

Do not pretend every deployment can always be automatically rolled back.

Risky or irreversible migrations require:

- explicit recognition;
- fresh backup;
- documented deployment plan;
- documented recovery strategy.

### Tasks

- [ ] Add a canonical rollback host command.
- [ ] Track the previous deployable release.
- [ ] Gate automatic rollback on migration compatibility.
- [ ] Automatically return to the previous release when post-switch readiness fails and rollback is safe.
- [ ] Refuse or require explicit operator action when automatic rollback is unsafe.
- [ ] Document backward-compatible migration expectations.
- [ ] Document risky/irreversible migration procedure.
- [ ] Test representative safe rollback.

## P30-W09 — Operator commands and release metadata

### Contract

Routine production operations should not require administrators to remember long internal Docker commands.

Provide a small canonical operator interface for at least:

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

Exact command naming may follow repository conventions, but there must be one documented operational interface.

Atlas must also expose enough release metadata to identify exactly what is running.

Release metadata should include where available:

- release ID;
- Git commit;
- Git tag;
- image identifiers;
- deploy timestamp;
- operator identity.

Release information should be available to appropriate operational surfaces such as:

- Admin System Status;
- readiness;
- logs;
- Sentry.

### Tasks

- [ ] Add/document canonical operator commands.
- [ ] Add status.
- [ ] Add restart.
- [ ] Add logs.
- [ ] Add safe artisan wrapper.
- [ ] Add appropriate composer and pnpm operational wrappers where required.
- [ ] Integrate backup.
- [ ] Integrate restore.
- [ ] Integrate deploy.
- [ ] Integrate rollback.
- [ ] Record release metadata.
- [ ] Expose release metadata through appropriate Admin/readiness/log/Sentry surfaces.

## P30-W10 — Production durability and operational acceptance

### Contract

Phase 30 must finish with a real production-like proof, not only configuration-file inspection.

At minimum test a clean supported host/VM installation workflow:

```text
clean supported host/VM
↓
clone repository
↓
checkout exact release
↓
run installer
↓
complete interactive setup
↓
Atlas becomes ready
↓
administrator can sign in
↓
representative file is stored
↓
application containers recreated
↓
PostgreSQL data survives
↓
Files survive
↓
backup succeeds
↓
backup verifies
↓
restore drill succeeds
↓
readiness succeeds
↓
representative deploy succeeds
↓
representative safe rollback succeeds
```

### Tasks

- [ ] Test fresh production installation on a clean supported production-like host/VM.
- [ ] Verify installer preflight.
- [ ] Verify production readiness after installation.
- [ ] Verify first-admin bootstrap.
- [ ] Verify PostgreSQL persistence across container recreation.
- [ ] Verify Files persistence across container recreation.
- [ ] Verify ClamAV/File behavior after recreation.
- [ ] Verify recurring backup configuration.
- [ ] Verify manual backup.
- [ ] Verify backup integrity.
- [ ] Verify pre-restore backup.
- [ ] Execute a restore drill.
- [ ] Verify representative application state after restore.
- [ ] Verify exact-release deployment.
- [ ] Verify readiness-gated release switch.
- [ ] Verify safe rollback.
- [ ] Verify backend services remain inaccessible from the normal client network.
- [ ] Verify TLS deployment where configured.
- [ ] Verify release metadata.
- [ ] Update production operations documentation.

## Required permanent operational guardrails

- [ ] No backend/runtime service may become client-network facing by accident.
- [ ] Production Files cannot use ephemeral container storage.
- [ ] PostgreSQL cannot use ephemeral database storage.
- [ ] Production installation cannot use known development credentials.
- [ ] Production installation must identify an exact release.
- [ ] Installer rerun cannot destroy an existing installation.
- [ ] Secrets cannot enter source control.
- [ ] Restore must require explicit confirmation.
- [ ] Restore must create a pre-restore backup.
- [ ] Deployment switching must require readiness.
- [ ] Automatic rollback must respect migration compatibility.
- [ ] Running containers must not be manually modified as a normal deployment procedure.

## Completion criteria

Phase 30 is complete only when:

- [ ] Atlas can be installed on a clean supported internal production host using the canonical installer.
- [ ] Production baseline is private/intranet/LAN/VPN rather than public-Internet dependent.
- [ ] Only the reverse proxy is reachable from the trusted client network.
- [ ] Backend/runtime services remain private.
- [ ] TLS can be configured without requiring public Let's Encrypt.
- [ ] PostgreSQL uses durable persistent storage.
- [ ] Files use durable local persistent storage as the baseline production backend.
- [ ] Container recreation does not lose PostgreSQL data.
- [ ] Container recreation does not lose Atlas Files.
- [ ] S3-compatible Files storage remains optional rather than mandatory.
- [ ] PostgreSQL backup is implemented and verified.
- [ ] Files backup/recovery is covered.
- [ ] Recurring production backup can be configured.
- [ ] Off-host backup strategy is documented without hardcoded S3 dependency.
- [ ] Restore requires confirmation and creates a pre-restore backup.
- [ ] A real restore drill passes.
- [ ] Exact-release deployment works.
- [ ] Readiness gates release switching.
- [ ] Safe rollback works when migrations permit it.
- [ ] Unsafe automatic rollback is prevented.
- [ ] Canonical operator commands are available.
- [ ] Release metadata identifies the deployed system.
- [ ] Secrets remain outside source control.
- [ ] Canonical production documentation matches the implementation.
