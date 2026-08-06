# Phase 29 — Production deployment, backup, restore, and rollback

**Status:** `not started`

## Objective

Finalize production topology, deployment, backup, restore, readiness, release metadata, and rollback after the technical foundation capabilities they must operate are complete.

## Dependencies

- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 22 — Search](phase-22-search.md)
- [Phase 24 — Reports, exports, PDF, charts, and print](phase-24-reports-exports-print.md)
- [Phase 26 — Security, privacy, deletion, and anonymization](phase-26-security-privacy.md)
- [Production deployment, backup, and recovery](../operations/production-deployment-backup-and-recovery.md)
- [Health, observability, and maintenance](../operations/health-observability-and-maintenance.md)

## Implementation contract

- Baseline production runs on one application host or VM using Docker Compose.
- Use separate services for:
  - nginx reverse proxy;
  - PHP-FPM/application runtime;
  - queue worker;
  - scheduler;
  - Horizon;
  - PostgreSQL;
  - Redis;
  - Meilisearch;
  - ClamAV;
  - Chromium/Playwright PDF renderer;
  - backup tooling.
- PostgreSQL runs inside the application Docker Compose stack under project control and uses a durable persistent volume.
- Production file storage uses S3-compatible object storage.
- Only HTTP/HTTPS through the reverse proxy are public.
- Redis, Meilisearch, ClamAV, Horizon, workers, scheduler, and renderer remain on private networks and are not publicly exposed.
- Force HTTPS, automate Let's Encrypt renewal, and apply security headers.
- Use durable volumes only where explicitly required and configure log rotation.
- Keep secrets outside the repository.
- Never edit files manually inside running containers.
- Backups:
  - daily compressed timestamped `pg_dump` from the project-controlled PostgreSQL container;
  - local retention rotation;
  - encrypted S3-compatible offsite copy;
  - host `backup` and `restore` commands;
  - restore requires explicit confirmation;
  - always create a pre-restore backup;
  - test restore regularly.
- Deploy exact release tag or commit and exact image tags, never a floating latest branch/image.
- Use versioned release directories with a `current` symlink.
- Deployment flow:
  1. build release separately;
  2. install production dependencies;
  3. build frontend;
  4. run checks;
  5. back up the PostgreSQL database when required;
  6. run compatible migrations;
  7. verify readiness of the new release;
  8. switch the `current` symlink;
  9. reload php-fpm, Horizon, workers, and scheduler;
  10. run post-switch readiness;
  11. automatically roll back on failure when database compatibility permits.
- Rollback to the previous release is allowed only when migrations remain backward compatible.
- Risky or irreversible migrations require a fresh backup and an explicit deployment plan.
- Record release tag, commit, image identifiers, deploy date, operator, and release ID.
- Show release metadata in Admin, readiness, logs, and Sentry.
- Provide host commands `deploy`, `rollback`, `status`, `restart`, `logs`, `artisan`, `composer`, and `pnpm`.
- Prefer no-downtime deployment. Use maintenance mode only for incompatible steps and notify active users beforehand.
- Required environments are local/development and production.
- Staging is optional and enabled per customer/project need.
- Kubernetes, Docker Swarm, and distributed clustering are outside the baseline scope.

## Tasks

- [ ] Finalize production nginx configuration.
- [ ] Force HTTPS.
- [ ] Add security headers.
- [ ] Add Let's Encrypt issuance.
- [ ] Add automatic renewal.
- [ ] Finalize php-fpm container.
- [ ] Finalize worker container.
- [ ] Finalize scheduler container.
- [ ] Finalize Horizon container.
- [ ] Finalize PostgreSQL container and durable volume.
- [ ] Finalize PostgreSQL container, health checks, backup access, and durable volume.
- [ ] Finalize Redis.
- [ ] Finalize Meilisearch.
- [ ] Finalize ClamAV.
- [ ] Finalize Chromium/Playwright renderer.
- [ ] Configure S3-compatible production file storage.
- [ ] Keep all non-HTTP services on private Docker networks.
- [ ] Ensure only reverse-proxy ports 80/443 are public.
- [ ] Finalize backup tooling.
- [ ] Add daily compressed timestamped `pg_dump`.
- [ ] Add local backup rotation.
- [ ] Add encrypted S3-compatible offsite copy.
- [ ] Add `backup` host command.
- [ ] Add `restore` host command.
- [ ] Require explicit confirmation for restore.
- [ ] Create pre-restore backup.
- [ ] Document and test restore.
- [ ] Add versioned release directories.
- [ ] Add `current` symlink.
- [ ] Deploy exact tag, commit, and image identifiers.
- [ ] Build release separately.
- [ ] Install production dependencies.
- [ ] Build frontend.
- [ ] Run full checks.
- [ ] Back up database before risky migrations.
- [ ] Run compatible migrations.
- [ ] Gate symlink switching on readiness.
- [ ] Switch symlink atomically.
- [ ] Reload php-fpm, Horizon, workers, and scheduler.
- [ ] Run post-switch readiness.
- [ ] Automatically roll back only when migration compatibility permits.
- [ ] Add `deploy`, `rollback`, `status`, `restart`, `logs`, `artisan`, `composer`, and `pnpm` host commands.
- [ ] Add release metadata to Admin, readiness, logs, and Sentry.
- [ ] Document persistent-volume ownership and retention.
- [ ] Prohibit manual edits inside running containers.
- [ ] Keep secrets outside source control.
- [ ] Support local/development and production; keep staging optional.
- [ ] Document Kubernetes, Swarm, and distributed clusters as out of scope.
- [ ] Commit deployment system.

## Completion criteria

- [ ] Production Compose, HTTPS, private networks, release metadata, host commands, backup, restore, readiness, and rollback are implemented and documented.
- [ ] Restore is tested and always creates a pre-restore backup.
- [ ] Rollback behavior is explicit about migration compatibility.
- [ ] Relevant operational checks pass.
