# Phase 33 — Private production deployment, installer, backup, restore, and rollback

**Status:** `not started`

## Objective

Deliver a reproducible, private, self-hosted production deployment model for Atlas.

Atlas is primarily an internal company system. The baseline production deployment does not require public Internet exposure.

Phase 33 must provide:

- private/intranet production topology;
- one supported production installation workflow;
- an interactive production installer;
- durable PostgreSQL and Files storage;
- infrastructure-level encryption at rest for persistent production storage;
- independently encrypted portable/off-host backup artifacts;
- backup and restore for database and Files;
- exact-release deployment;
- readiness-gated release switching;
- rollback;
- release metadata;
- simple host/operator commands;
- documented recovery procedures;
- Atlas-managed self-hosted LiveKit RTC and TURN connectivity;
- a separate self-hosted LiveKit Egress recording service;
- protected temporary recording staging and Files-owned final recordings;
- RTC/Egress health, capacity, secret, backup, restore, deploy, and rollback coverage.

The baseline deployment target is one company-controlled Linux host or VM running Docker Compose.

Kubernetes, Docker Swarm, distributed clustering, multi-node high availability, and public SaaS deployment are outside the baseline scope.

## Dependencies

Phase 33 depends on the completed technical foundation, including:

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
- completed Phase 28 and Phase 29 acceptance work;
- completed Phase 31 communication scope, including Chat, Core Calendar, Calls, Meetings, Reverb, self-hosted LiveKit, Egress, recordings, and provider-neutral transcription state;
- completed [Phase 32 Diagnostics and User Bug Reports](phase-32-error-reporting-and-diagnostics.md), including its persistence, Files, Health, retention, correlation, and private source-map contracts.

Phase 33 must build on those capabilities instead of replacing or redesigning them.

### Diagnostics deployment boundary

Diagnostics PostgreSQL data is normal durable Atlas production data in the existing PostgreSQL deployment. Persistence, backup, and restore cover Technical Issues, aggregate history, retained detailed occurrences, User Bug Reports, issue/report links, lifecycle history, configuration, and safe Diagnostics settings. Do not create a separate Diagnostics database.

User Bug Report screenshots and attachments are canonical Files artifacts. They use the same persistent Files storage, backup, restore, and infrastructure encryption-at-rest boundary. Do not create separate report-attachment volumes.

Production builds retain release-matching frontend source maps as private diagnostic artifacts. Source maps must not be published as normal reverse-proxy assets. Exact-release deploy and rollback preserve the association between the Atlas release, browser assets, and private source maps. Historical source snippets may be unavailable when old release source is no longer retained.

Release and Git commit identity remain available to Diagnostics. Production logging preserves correlation metadata required by the existing log viewer and the Diagnostics `Open related logs` action.

Diagnostics operational severity may report Degraded or Unhealthy in System Status without, by itself, failing canonical `/health/live` or `/health/ready`. A broken Diagnostics capture pipeline remains visible as a non-recursive operational signal.

The canonical scheduler and queue configuration runs Diagnostics retention. Large manual occurrence purge uses Managed Processes. Diagnostics adds no third-party runtime service.

The production installer must not add Sentry, a Sentry DSN or Cloud requirement, self-hosted Sentry, or another external error-monitoring service. Atlas Diagnostics is first-party.

## P33-W01 — Private production topology

### Contract

The baseline Atlas production topology is:

```text
trusted company network / LAN / VPN
                |
                +--> Atlas reverse proxy
                |
                +--> required configured LiveKit WebRTC/TURN endpoints
                                  |
                                  v
                         private runtime network
                         ├── PHP-FPM
                         ├── Horizon and queue workers
                         ├── scheduler
                         ├── PostgreSQL
                         ├── Redis
                         ├── Meilisearch
                         ├── ClamAV
                         ├── Chromium/PDF
                         ├── Reverb
                         ├── LiveKit
                         └── LiveKit Egress
```

Atlas does not require a public Internet-facing endpoint.

Normal Atlas HTTP remains reverse-proxy fronted. Browser WebRTC is an intentional narrow exception: trusted LAN/VPN clients may also reach only the configured LiveKit signaling/media and TURN endpoints required by the deployed LiveKit version and network policy.

PostgreSQL, Redis, Meilisearch, ClamAV, PHP-FPM, Horizon, workers, scheduler, Chromium, Egress control/health endpoints, recording staging, and LiveKit API/Admin credentials must remain private.

The roadmap does not freeze default port numbers. Phase 33 must derive and document the concrete firewall exposure from the pinned LiveKit configuration and version selected for the release, following current official LiveKit self-hosting guidance.

Network exposure must be configurable so the host administrator can bind Atlas to an internal interface, trusted subnet, VPN-accessible interface, or equivalent company-controlled network.

Atlas must not implement its own VPN or enterprise firewall product.

Host/network administrators remain responsible for infrastructure-level network policy.

### Tasks

- [ ] Finalize the single-host/VM Docker Compose production topology.
- [ ] Keep all backend/runtime services on private Docker networks.
- [ ] Keep normal Atlas HTTP reverse-proxy fronted and expose only the configured LiveKit WebRTC/TURN endpoints additionally required by trusted clients.
- [ ] Support binding the reverse proxy to a trusted/internal network interface.
- [ ] Document LAN/VPN/internal-network deployment.
- [ ] Confirm no backend service is unintentionally exposed to the client network.
- [ ] Keep Egress control/health endpoints, recording staging, and LiveKit service credentials private.
- [ ] Document and verify the concrete release-pinned LiveKit/TURN firewall exposure without relying on stale hardcoded port assumptions.
- [ ] Add production topology checks where practical.
- [ ] Document Kubernetes, Swarm, clustering, and public SaaS deployment as out of baseline scope.

## P33-W02 — Production TLS and reverse proxy

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

LiveKit signaling/media and TURN/TLS must work for the selected LAN/VPN/browser topology according to the current official self-hosting guidance for the deployed version. Atlas may use a company/internal trusted certificate, an administrator-supplied certificate, or existing company TLS termination where technically valid. A self-signed certificate that browsers do not trust is not an accepted production shortcut. Public Let's Encrypt remains optional.

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
- [ ] Configure and verify trusted LiveKit and TURN/TLS endpoints for the selected LAN/VPN topology.
- [ ] Document the concrete configured RTC/TURN firewall and certificate requirements.

## P33-W03 — Durable PostgreSQL and local Files storage

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

S3-compatible storage is not required by Phase 33.

Do not implement AWS-specific coupling merely for future flexibility.

Final Meeting recordings are Files-owned durable artifacts. LiveKit Egress output and local recording staging are temporary/intermediate state, not a second permanent recording store. Staging must use protected storage, least-privilege permissions, bounded cleanup, and the applicable encryption-at-rest boundary. Transcript state remains PostgreSQL-owned and participates in database recovery.

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
- [ ] Define protected temporary Egress recording staging and deterministic cleanup.
- [ ] Verify finalized Meeting recordings enter canonical Files persistence and survive container recreation.
- [ ] Include transcript database state in PostgreSQL durability and recovery planning.
- [ ] Keep Diagnostics persistence in the canonical durable PostgreSQL deployment without a separate database.
- [ ] Keep User Bug Report screenshots and attachments in canonical Files storage without a separate volume.

## P33-W04 — Production storage and backup encryption at rest

### Contract

Atlas production persistent data must support infrastructure-level encryption at rest.

This is a shared production-infrastructure requirement for Atlas as a whole, not a Chat-specific encryption feature.

The objective is to protect persistent data if physical or virtual storage, offline snapshots, or portable backup artifacts are lost or accessed outside the running authorized production system.

The preferred security boundary is:

```text
Atlas application
        |
        v
PostgreSQL / Files / Meilisearch / other persistent runtime data
        |
        v
persistent host/VM storage
        |
        v
infrastructure-level encryption at rest
```

Where the production topology stores persistent state on the same protected filesystem/volume, encryption at rest should protect as applicable:

- PostgreSQL data;
- Atlas Files;
- Meilisearch indexes;
- persistent Redis data where enabled;
- application/runtime logs where persisted there;
- local backup storage;
- future module-owned persistent data stored on that protected production storage.

Do not implement separate application-level encryption systems for every Atlas module.

Do not implement custom Chat message encryption merely to satisfy this infrastructure requirement.

### Supported encryption model

The concrete encryption mechanism is deployment-specific.

Accepted deployment models may include:

- Linux full-disk or filesystem encryption such as LUKS;
- encrypted VM/hypervisor volumes;
- company-managed encrypted storage;
- infrastructure/provider-managed encrypted disks;
- another reviewed equivalent mechanism.

Atlas must not invent its own disk-encryption cryptography.

### Installer boundary

The Atlas production installer must not automatically repartition disks, format disks, or silently configure destructive full-disk encryption.

Disk/storage encryption is a host/infrastructure responsibility.

The installer should, where safely practical:

- detect or accept declared encryption-at-rest status;
- present the detected/configured status to the operator;
- warn clearly when the selected persistent production storage is not known to be encrypted;
- document the accepted risk if the operator intentionally continues without infrastructure-level encryption.

Do not claim perfect automatic detection on every Linux/storage platform when the state cannot be established reliably.

### Running-system boundary

Encryption at rest does not claim to protect data from an authorized root/infrastructure administrator while the production system is running and the storage is unlocked.

Direct root/database access is governed by organizational access procedures.

Atlas application authorization remains responsible for preventing ordinary users and application Administrators from accessing data they are not allowed to access.

### Portable and off-host backup artifacts

Encryption of the production disk alone is not sufficient for backup artifacts copied away from that encrypted filesystem.

Portable/off-host backup artifacts must support independent encryption before leaving the trusted protected storage boundary.

This applies to backup material containing:

- PostgreSQL dumps;
- Files backup archives;
- combined recovery bundles;
- other sensitive Atlas backup artifacts.

The backup encryption must not be hardcoded to AWS or S3.

Encrypted artifacts may later be copied to deployment-specific destinations such as:

- NAS;
- NFS;
- another company host;
- removable backup media;
- enterprise backup systems;
- S3-compatible object storage.

### Key handling

Encryption keys/passphrases must:

- remain outside source control;
- never be committed to the repository;
- never appear in normal application logs;
- not be bundled inside the backup artifact they protect;
- be handled through documented operator/infrastructure procedures.

Do not build a custom enterprise key-management system in Atlas.

### Restore

The canonical restore workflow must support the encrypted backup format.

Restore must:

1. identify the encrypted artifact;
2. obtain the required key/secret through the approved external mechanism;
3. decrypt in a controlled temporary/recovery location;
4. verify the backup before destructive restore work;
5. follow the existing pre-restore-backup contract;
6. restore the data;
7. remove temporary plaintext recovery artifacts safely when they are no longer required;
8. run normal post-restore readiness.

### Tasks

- [ ] Define the supported infrastructure-level encryption-at-rest production contract.
- [ ] Document accepted host/VM encrypted-storage models.
- [ ] Keep encryption implementation deployment-specific rather than Atlas-cryptography-specific.
- [ ] Cover PostgreSQL, Files, Search indexes, and other applicable persistent Atlas state.
- [ ] Add installer reporting/warning for storage encryption status where safely practical.
- [ ] Prevent the installer from destructively configuring disk encryption automatically.
- [ ] Define independently encrypted portable/off-host backup artifacts.
- [ ] Keep backup encryption destination-neutral.
- [ ] Keep encryption keys outside source control and normal logs.
- [ ] Integrate encrypted backup artifacts with the canonical backup command.
- [ ] Integrate encrypted artifacts with restore.
- [ ] Remove temporary plaintext restore artifacts safely.
- [ ] Document organizational/root-access boundaries accurately.
- [ ] Add production-like verification for encrypted backup/decrypt/verify/restore behavior.

## P33-W05 — Interactive production installer

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
- LiveKit signaling/media and TURN endpoint/network configuration;
- LiveKit and Egress capacity/resource settings;
- protected Egress recording staging location;
- required SMTP configuration;
- first administrator identity.

Do not ask questions for values that can safely be generated automatically.

### Secrets

Generate secure secrets where Atlas can safely own them, including the required application/service secrets.

Secrets must remain outside source control.

The installer must generate and handle Atlas-managed LiveKit service credentials. Those credentials are consumed only by required Atlas, LiveKit, and Egress services; they are not shown in ordinary Admin UI or normal logs. The baseline installs Atlas-managed self-hosted LiveKit and Egress rather than offering an external-existing-LiveKit mode.

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

### Storage-encryption awareness

The installer does not own destructive disk-encryption provisioning.

During preflight/configuration it should, where safely practical:

- identify the selected persistent Atlas storage location;
- determine or accept operator-declared encryption-at-rest status;
- show that status before installation;
- warn when production persistent storage is not known to be encrypted;
- direct the operator to the canonical production encryption documentation.

The installer must not automatically repartition, format, or encrypt host disks.

### Tasks

- [ ] Add one canonical interactive production installer entry point.
- [ ] Add preflight checks.
- [ ] Require/verify an exact release/tag/commit.
- [ ] Collect production configuration interactively.
- [ ] Configure private/intranet network binding.
- [ ] Configure HTTP/TLS mode without assuming public Internet.
- [ ] Configure local persistent Files storage.
- [ ] Configure PostgreSQL persistent storage.
- [ ] Identify the selected persistent Atlas storage location.
- [ ] Determine or accept operator-declared encryption-at-rest status where safely practical.
- [ ] Show storage-encryption status and warn when it is not known to be encrypted.
- [ ] Link the operator to canonical production encryption documentation.
- [ ] Keep disk repartitioning, formatting, and encryption outside installer automation.
- [ ] Configure backup location, schedule, and retention.
- [ ] Configure Atlas-managed self-hosted LiveKit, required RTC/TURN endpoints, and the separate Egress service.
- [ ] Preflight CPU, RAM, network, and protected recording-staging capacity and report concrete limitations without imposing a fake product limit.
- [ ] Generate required secrets securely.
- [ ] Generate and provision LiveKit API/service credentials securely for only the services that require them.
- [ ] Keep generated secrets outside source control.
- [ ] Create required persistent directories.
- [ ] Apply correct ownership and permissions.
- [ ] Start the canonical production Compose stack.
- [ ] Run migrations.
- [ ] Run readiness.
- [ ] Bootstrap the first administrator securely.
- [ ] Configure recurring backup execution.
- [ ] Configure Diagnostics retention through the canonical scheduler and queue model.
- [ ] Preserve Managed Processes execution for large manual diagnostic-occurrence purge.
- [ ] Keep the installer free of Sentry and other external error-monitoring configuration.
- [ ] Print useful release and operational information after success.
- [ ] Fail safely when preflight or readiness fails.
- [ ] Detect an existing installation and never destroy it on installer rerun.
- [ ] Document the fresh-host installation procedure.
- [ ] Test installation against a clean supported production-like host/VM.

## P33-W06 — Database and Files backup

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

Phase 33 must not build multiple speculative backup adapters merely to support every possible destination.

It is acceptable for Atlas to produce stable backup artifacts that company infrastructure then copies off-host.

Portable/off-host backup artifacts must be independently encrypted before leaving the trusted encrypted storage boundary.

Backup tooling must verify both:

- the backup's internal validity;
- the expected encrypted artifact lifecycle.

The backup destination must remain deployment-neutral and must not be hardcoded to S3.

### Tasks

- [ ] Finalize production backup tooling.
- [ ] Create compressed timestamped PostgreSQL dumps.
- [ ] Verify PostgreSQL dump integrity/catalog.
- [ ] Store backup artifacts outside ephemeral containers.
- [ ] Independently encrypt portable/off-host backup artifacts before they leave the trusted encrypted storage boundary.
- [ ] Verify backup internal validity and the encrypted artifact lifecycle.
- [ ] Add configurable local backup retention.
- [ ] Include persistent Atlas Files in the recovery strategy.
- [ ] Include Files-owned final Meeting recordings in Files backup and recovery.
- [ ] Include transcript records and versions in PostgreSQL backup and recovery.
- [ ] Include Technical Issues, aggregates, retained occurrences, User Bug Reports, links, history, configuration, and safe Diagnostics settings in PostgreSQL backup and recovery.
- [ ] Include User Bug Report Files-owned screenshots and attachments in Files backup and recovery.
- [ ] Exclude or safely clean temporary Egress staging rather than treating it as a second canonical recording backup.
- [ ] Define a safe Files backup procedure.
- [ ] Avoid inconsistent/partial Files backup state where possible.
- [ ] Add a canonical host backup command.
- [ ] Support automated recurring production backup execution.
- [ ] Document configurable/off-host backup strategy.
- [ ] Keep S3-compatible backup storage optional.
- [ ] Document that same-host-only backup does not protect against complete host loss.

## P33-W07 — Restore and recovery

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

Restore must understand the encrypted backup artifact lifecycle:

```text
encrypted artifact
→
controlled decryption
→
verification
→
pre-restore backup
→
restore
→
readiness
→
safe cleanup of temporary plaintext material
```

### Tasks

- [ ] Add a canonical host restore command.
- [ ] Require explicit confirmation.
- [ ] Verify selected backups before restore.
- [ ] Decrypt encrypted artifacts in a controlled recovery location through the approved operator path.
- [ ] Always create a pre-restore backup.
- [ ] Restore PostgreSQL safely.
- [ ] Restore Files according to the documented recovery model.
- [ ] Restore Files-owned Meeting recordings and PostgreSQL-owned transcript state through their canonical stores.
- [ ] Restore Diagnostics PostgreSQL state and User Bug Report Files through their canonical stores.
- [ ] Run post-restore readiness.
- [ ] Safely remove temporary plaintext recovery material when no longer required.
- [ ] Document complete restore procedures.
- [ ] Execute and verify a real restore drill.
- [ ] Verify representative restored application data.

## P33-W08 — Exact-release deployment

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
- [ ] Pin compatible LiveKit and Egress images/configuration as part of the exact Atlas release.
- [ ] Build/prepare releases separately from the active release.
- [ ] Install production dependencies from lockfiles.
- [ ] Build production frontend assets.
- [ ] Retain exact-release frontend source maps as private Diagnostics artifacts that are not served publicly.
- [ ] Associate private source maps with the exact release, commit, and browser assets across deploy and rollback.
- [ ] Run required checks.
- [ ] Run required database backup before risky migration work.
- [ ] Run compatible migrations.
- [ ] Gate release switching on readiness.
- [ ] Switch releases atomically.
- [ ] Reload/restart PHP-FPM, Horizon, workers, and scheduler safely.
- [ ] Coordinate compatible Reverb, LiveKit, and Egress rollout/restart behavior without unnecessarily terminating active RTC sessions.
- [ ] Run post-switch readiness.
- [ ] Keep application source immutable inside running containers.

## P33-W09 — Rollback and migration safety

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

## P33-W10 — Operator commands and release metadata

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
- Diagnostics.

Status, readiness, logs, and Admin System Status must include safe aggregate LiveKit RTC, TURN where practical, Egress availability, and recording-processing health. They must not expose private Meeting names, participant lists, media, recordings, transcripts, or service credentials. Egress/recording failure must remain isolated so normal Chat and unrecorded Meetings can continue where their own dependencies are healthy.

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
- [ ] Expose exact release/commit identity to Diagnostics.
- [ ] Preserve request/correlation metadata needed for Open related logs.
- [ ] Keep Diagnostics operational severity separate from canonical liveness/readiness failure semantics.
- [ ] Expose Diagnostics pipeline unavailability without recursive health failure.
- [ ] Expose release metadata through appropriate Admin/readiness/log/Diagnostics surfaces.
- [ ] Add safe LiveKit RTC, TURN, Egress, and recording-processing status/readiness checks.
- [ ] Verify Egress failure isolation from normal Chat and Meeting participation.

## P33-W11 — Production durability and operational acceptance

### Contract

Phase 33 must finish with a real production-like proof, not only configuration-file inspection.

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
production storage encryption-at-rest status is verified/documented
↓
backup succeeds
↓
backup verifies
↓
encrypted database and Files/recovery artifacts are created
↓
encrypted artifacts are decrypted through the approved operator path
↓
decrypted artifacts verify
↓
restore drill succeeds
↓
temporary plaintext recovery material is cleaned up
↓
readiness succeeds
↓
representative deploy succeeds
↓
representative safe rollback succeeds
```

The proof must also exercise Atlas-managed LiveKit and separate Egress startup/readiness, trusted-network RTC/TURN connectivity, safe aggregate health reporting, resource/preflight visibility, a finalized Files-owned recording, transcript-state backup/restore, and compatible exact-release deploy/rollback for Atlas, Reverb, LiveKit, and Egress.

### Tasks

- [ ] Test fresh production installation on a clean supported production-like host/VM.
- [ ] Verify installer preflight.
- [ ] Verify production readiness after installation.
- [ ] Verify first-admin bootstrap.
- [ ] Verify PostgreSQL persistence across container recreation.
- [ ] Verify Files persistence across container recreation.
- [ ] Verify and document persistent production storage encryption-at-rest status without requiring destructive disk formatting or encryption in the test.
- [ ] Verify ClamAV/File behavior after recreation.
- [ ] Verify recurring backup configuration.
- [ ] Verify manual backup.
- [ ] Verify backup integrity.
- [ ] Verify encrypted database backup artifact creation.
- [ ] Verify encrypted Files/recovery artifact creation.
- [ ] Verify encrypted artifact decryption through the approved operator path.
- [ ] Verify the decrypted artifact.
- [ ] Verify pre-restore backup.
- [ ] Execute a restore drill.
- [ ] Verify representative application state after restore.
- [ ] Verify temporary plaintext recovery material is cleaned up.
- [ ] Verify Atlas readiness after restoring from encrypted artifacts.
- [ ] Verify exact-release deployment.
- [ ] Verify readiness-gated release switch.
- [ ] Verify safe rollback.
- [ ] Verify backend services remain inaccessible from the normal client network.
- [ ] Verify trusted clients can reach only the configured LiveKit WebRTC/TURN endpoints in addition to the Atlas reverse proxy.
- [ ] Verify LiveKit RTC, TURN connectivity where practical, Egress, and recording-processing readiness.
- [ ] Verify Egress control endpoints, staging, and credentials remain private.
- [ ] Verify a finalized Meeting recording is Files-owned, backed up, restored, and protected by the production storage encryption boundary.
- [ ] Verify transcript PostgreSQL state is backed up and restored.
- [ ] Verify Egress failure does not make normal Chat or unrecorded Meetings unavailable.
- [ ] Verify exact-release compatibility and rollback planning cover Reverb, LiveKit, and Egress.
- [ ] Verify TLS deployment where configured.
- [ ] Verify release metadata.
- [ ] Verify Diagnostics PostgreSQL persistence and User Bug Report Files survive backup and restore.
- [ ] Verify exact-release private source-map association and public source-map exclusion.
- [ ] Verify Diagnostics operational severity does not by itself fail liveness/readiness.
- [ ] Verify Diagnostics retention scheduling and Managed Processes purge integration.
- [ ] Verify no Sentry or external error-monitoring service was added to the installer/runtime.
- [ ] Update production operations documentation.

## Required permanent operational guardrails

- [ ] No backend/runtime service may become client-network facing by accident; only the reverse proxy and explicitly configured LiveKit WebRTC/TURN endpoints are reachable by trusted clients.
- [ ] LiveKit/Egress credentials, Egress control endpoints, and recording staging remain private.
- [ ] LiveKit and Egress versions/configuration are pinned and participate in exact-release deploy/rollback.
- [ ] Egress failure cannot unnecessarily disable normal Chat or unrecorded Meetings.
- [ ] Production Files cannot use ephemeral container storage.
- [ ] PostgreSQL cannot use ephemeral database storage.
- [ ] Production installation cannot use known development credentials.
- [ ] Production installation must identify an exact release.
- [ ] Installer rerun cannot destroy an existing installation.
- [ ] Secrets cannot enter source control.
- [ ] Persistent production storage encryption status must be verified or its risk explicitly documented.
- [ ] Portable/off-host backup artifacts must be independently encrypted before leaving trusted protected storage.
- [ ] Backup encryption keys cannot be stored in source control, normal logs, or the protected artifact.
- [ ] Restore must require explicit confirmation.
- [ ] Restore must create a pre-restore backup.
- [ ] Deployment switching must require readiness.
- [ ] Automatic rollback must respect migration compatibility.
- [ ] Running containers must not be manually modified as a normal deployment procedure.

## Completion criteria

Phase 33 is complete only when:

- [ ] Atlas can be installed on a clean supported internal production host using the canonical installer.
- [ ] Production baseline is private/intranet/LAN/VPN rather than public-Internet dependent.
- [ ] Only the reverse proxy and the explicitly configured LiveKit WebRTC/TURN endpoints required by browser clients are reachable from the trusted client network.
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
- [ ] Infrastructure-level persistent storage encryption-at-rest is supported and documented.
- [ ] Portable/off-host database and Files/recovery backup artifacts support independent encryption.
- [ ] Encrypted backup artifacts can be decrypted, verified, restored, and cleaned up through the approved operator workflow.
- [ ] The installer provisions Atlas-managed self-hosted LiveKit and separate self-hosted Egress with secret-safe configuration.
- [ ] LiveKit/TURN browser connectivity works under the accepted private LAN/VPN policy without requiring public Atlas exposure.
- [ ] LiveKit, TURN where practical, Egress, and recording processing have actionable readiness and safe aggregate Admin visibility.
- [ ] Final Meeting recordings are canonical Files artifacts; temporary Egress staging is protected and cleaned.
- [ ] Files-owned recordings and PostgreSQL-owned transcript state are covered by encryption, backup, restore, and recovery drills.
- [ ] Exact-release deployment and rollback keep Atlas, Reverb, LiveKit, and Egress compatible.
- [ ] Diagnostics persistence, User Bug Report Files, private source maps, release identity, correlation metadata, retention, and Health semantics are covered by production deployment and recovery.
- [ ] No Sentry or external error-monitoring service is introduced by the production installer.
- [ ] Canonical production documentation matches the implementation.
