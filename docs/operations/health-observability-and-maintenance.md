# Health, observability, maintenance, and runtime diagnostics

Canonical runtime rules for health, readiness, maintenance, logging, correlation, alerts, diagnostics, and administrative operational visibility.

## Logging and Observability

- Use readable text logs in development and structured JSON logs in production.
- Every HTTP request, queue job, CLI command, and integration call must carry a `correlation_id`.
- Propagate the originating correlation ID into jobs and downstream integration work where available.
- Use `causation_id` where one operation causes another distinct event or job.
- Log only sanitized context.
- Never log passwords, password-reset links, MFA secrets, recovery codes, access tokens, API keys, cookies, session identifiers, full request bodies, full headers, or unnecessary personal data.
- Use one centralized redaction policy for sensitive field names and values.
- Sentry context must follow the same redaction rules.
- Do not add Prometheus, OpenTelemetry, or distributed tracing unless explicitly requested for a concrete project.
- Administrative log viewing must expose curated application log records only, never arbitrary server-file browsing.

## Health, Maintenance, and Deployment

### Health

Separate:

- liveness;
- readiness.

Readiness distinguishes:

- **blocking dependencies**: PostgreSQL, Redis used for sessions/queues/locks, required storage, queues, scheduler freshness, critical configuration, and every service explicitly required by an active capability;
- **degraded dependencies**: optional services whose outage permits safe core operation.

Meilisearch is degraded by default and becomes blocking only when a Atlas marks Search as critical.

ClamAV is blocking whenever the Files capability is active in production because unscanned files must remain quarantined.

The Chromium/Playwright renderer is blocking for PDF generation capability but its outage may expose degraded application readiness when normal business operation remains safe; a Atlas may mark it critical.

Public health endpoint exposes no sensitive details.

Admin panel may show detailed status.

Docker health checks must use readiness.

Deploy succeeds only after readiness passes.

### Maintenance

Use controlled maintenance mode with:

- clear page;
- optional allowed users/IPs;
- explicit queue behavior;
- explicit scheduler behavior;
- audited start and end;
- user warning before scheduled maintenance.

Prefer zero-downtime deploys.

Use maintenance only for incompatible changes.

### Versioning

Record:

- Git tag;
- commit;
- deploy date;
- operator;
- release ID.

Show version in:

- Admin;
- readiness;
- logs;
- Sentry.

Frontend and backend must share one release version.

Cache and assets use release versioning.

### Production Docker

Production includes:

- nginx reverse proxy;
- php-fpm;
- workers;
- scheduler;
- PostgreSQL;
- Redis;
- Meilisearch;
- backup service.

Production excludes:

- Mailpit;
- pgAdmin;
- RedisInsight.

Expose only HTTP and HTTPS publicly.

Force HTTPS.

Use automatic certificate renewal and security headers.

### Backups

PostgreSQL remains in Docker under project control.

Use:

- durable volumes;
- daily compressed timestamped `pg_dump`;
- local rotation;
- encrypted offsite S3-compatible copies;
- tested restore procedures.

Provide host commands:

- `backup`
- `restore`

Restore requires:

- explicit confirmation;
- pre-restore backup.

Test restores regularly.

### Deploy

Use versioned releases and a `current` symlink.

Deploy exact tag or commit, never blindly deploy latest branch.

Process:

1. build release separately;
2. install production dependencies;
3. build frontend;
4. run checks;
5. back up database;
6. run migrations;
7. switch symlink;
8. reload php-fpm and workers;
9. run readiness;
10. automatically roll back on failure.

Provide host commands at minimum:

- `deploy`
- `rollback`
- `status`
- `restart`
- `logs`
- `artisan`
- `composer`
- `pnpm`

---
