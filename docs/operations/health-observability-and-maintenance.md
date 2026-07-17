# Health, observability, maintenance, and runtime diagnostics

Canonical runtime rules for health, readiness, maintenance, logging, correlation, alerts, diagnostics, and administrative operational visibility.

## Logging and Observability

- Laravel Telescope and Laravel Debugbar are development-only diagnostics tools.
- Telescope is the default local request, query, exception, log, job, cache, mail, notification, event, and dump inspector at `/telescope`.
- Debugbar is enabled only for trusted local/development browser work and must stay disabled in tests, E2E, production, and every untrusted environment.
- Telescope and Debugbar must not replace production-safe logs, Sentry, health checks, readiness checks, alerts, audit trails, or Admin operational screens.
- Laravel Pulse is the internal application performance dashboard for runtime usage and bottleneck trends. It is mounted at `/admin/pulse`, uses the `admin.pulse.view` permission through Pulse's `viewPulse` gate, requires authentication and password confirmation, and is intended for authorized operational administrators only.
- Every HTTP response includes an `X-Request-Id` header.
- If a valid `X-Request-Id` arrives with the request, Atlas preserves it; otherwise Atlas generates a ULID request id.
- The request id is attached to Laravel context and log context as both `request_id` and initial `correlation_id`.
- Use readable text logs in development and structured JSON logs in production.
- Every HTTP request, queue job, CLI command, scheduler run, and integration call must carry a `correlation_id`.
- HTTP entrypoints create or preserve the request correlation ID. CLI commands and scheduler tasks create a correlation ID when one does not already exist. Laravel context carries request correlation data into queued jobs, and Atlas refreshes stable source/module/event metadata when queue jobs, CLI commands, and scheduler tasks begin executing.
- Queue jobs restore propagated `request_id`, `correlation_id`, optional `causation_id`, actor public ID, and team public ID from Laravel's queued log context payload before job-specific observability metadata is applied.
- Propagate the originating correlation ID into jobs and downstream integration work where available.
- Use `causation_id` where one operation causes another distinct event or job; queued jobs preserve this value when it is included in the dispatched context.
- Standard safe log context includes environment, release version, release ID, source, stable event name, module, correlation ID, and, where applicable, request ID, causation ID, public actor ID, and public team ID.
- Log only sanitized context through the centralized Atlas redaction policy.
- Never log passwords, password-reset links, MFA secrets, recovery codes, access tokens, API keys, cookies, session identifiers, full request bodies, full headers, or unnecessary personal data.
- Use one centralized redaction policy for sensitive field names and values.
- Sentry context follows the same redaction rules through the shared event processor. Sentry receives release ID, release version, environment, source, module, stable event name, and correlation ID tags/context, and public user identifiers only.
- Do not add Prometheus, OpenTelemetry, or distributed tracing unless explicitly requested for a concrete project.
- Administrative log viewing must expose curated application log records only, never arbitrary server-file browsing.
- The Admin application-log browser reads Atlas' canonical application log source, currently labelled as `laravel.log`, and never accepts an operator-supplied filesystem path. It may parse structured JSON production records and readable development records, groups multiline stack traces under their originating log entry, and must keep log context sanitized before display.
- Admin rate-limit operations expose configured named policies and aggregated rejection statistics only. Operators may reset one exact limiter key after recording a reason; thresholds remain configuration-owned and cannot be edited or disabled through Admin.
- Admin queue operations expose failed jobs from the configured failed-job table only. Operators may retry selected failed-job UUIDs; mass retry requires typed confirmation and retry actions are audited as security-sensitive queue operations. Atlas does not expose shell access, arbitrary queue commands, failed-job flushing, queue clearing, or `queue:retry all` through Admin.
- Operational alerts are dispatched by `system:operational-alerts`, scheduled every five minutes. Alerts are disabled by default and enabled with `ATLAS_ALERTS_ENABLED=true`.
- Alert channels:
  - email recipients from `ATLAS_ALERTS_EMAIL_TO`, comma-separated;
  - webhook endpoint from `ATLAS_ALERTS_WEBHOOK_URL`.
- Alert deduplication and throttling use cache keys controlled by `ATLAS_ALERTS_DEDUPE_SECONDS` and `ATLAS_ALERTS_THROTTLE_SECONDS`.
- Baseline alert checks cover readiness failures, scheduler heartbeat failure, repeated failed jobs, backup failure signal, persistent integration failure signal, and critical Sentry signal. Backup, integration, and Sentry signals are configuration-driven until their owning modules/integrations provide concrete runtime status inputs.
- Module-owned operational execution is guarded by ModuleGate for existing queued notification delivery, scheduled module activation application, and failed-job retry actions.
- Pulse recording is controlled by `PULSE_ENABLED`; its route path defaults to `admin/pulse`. Pulse dashboard query caching defaults to the `array` cache driver through `PULSE_CACHE_DRIVER` because Atlas intentionally disables cache unserialization of arbitrary classes. Pulse owns package compatibility tables named `pulse_values`, `pulse_entries`, and `pulse_aggregates` because Laravel Pulse's storage implementation uses fixed table names.

## Health, Maintenance, and Deployment

### Health

Separate:

- liveness;
- readiness.

Readiness distinguishes:

- **blocking dependencies**: PostgreSQL, Redis used for sessions/queues/locks, required storage, queues, scheduler freshness, critical configuration, and every service explicitly required by an active capability;
- **degraded dependencies**: optional services whose outage permits safe core operation.

Scheduler freshness is recorded through the `system:scheduler-heartbeat` command, scheduled every minute. Admin System Status shows the latest heartbeat status, last successful run, runtime, stale threshold, and last error. A heartbeat older than `ATLAS_SCHEDULER_HEARTBEAT_STALE_SECONDS`, default 180 seconds, is stale and readiness treats it as a blocking scheduler freshness failure.

Admin System Status also exposes module activation scheduler diagnostics. Failed scheduled module activation changes remain in `module_activation_schedules`, are recorded as audit action `module.schedule_failed`, and surface as an operational status card until an operator reviews the failed schedule.

Public liveness is available at `GET /health/live`. It only confirms that the application process can respond and returns a minimal JSON payload.

Public readiness is available at `GET /health/ready`. It evaluates blocking and degraded dependencies, returns HTTP `503` only for blocking failures, and keeps the public payload minimal by exposing only overall status, release version/ID, checked timestamp, and blocking/degraded counts. Detailed per-check diagnostics are available through Admin System Status.

Current readiness checks cover critical configuration, PostgreSQL, required Redis capabilities, queue backend reachability/configuration, writable application storage, scheduler heartbeat freshness, Meilisearch optional availability, ClamAV, and Chromium/PDF rendering capability.

Meilisearch is degraded by default and becomes blocking only when Atlas marks Search as critical through `ATLAS_HEALTH_MEILISEARCH_CRITICAL=true`.

ClamAV is blocking whenever the Files capability is active in production because unscanned files must remain quarantined. It may also be forced critical through `ATLAS_HEALTH_CLAMAV_CRITICAL=true`; the daemon endpoint is configured with `ATLAS_HEALTH_CLAMAV_HOST` and `ATLAS_HEALTH_CLAMAV_PORT`.

The Chromium/Playwright renderer is degraded by default and becomes blocking only when Atlas marks PDF rendering critical through `ATLAS_HEALTH_CHROMIUM_CRITICAL=true`. The renderer binary path may be configured with `ATLAS_HEALTH_CHROMIUM_BINARY`; when no explicit path is configured, readiness auto-detects common Chromium locations, including the local Dev Container Playwright path under `/ms-playwright` and the runtime package path under `/usr/bin/chromium`.

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

The foundation release identity is configured through `ATLAS_RELEASE_VERSION` and `ATLAS_RELEASE_ID`.

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

Admin System Status exposes release version, release ID, environment, and optional last-deploy metadata from `ATLAS_RELEASE_DEPLOYED_AT`, `ATLAS_RELEASE_DEPLOYED_BY`, and `ATLAS_RELEASE_SOURCE`.

Frontend and backend must share one release version.

Cache and assets use release versioning.

### Time

Business time uses `APP_TIMEZONE`, defaulting to `Europe/Warsaw`.

Technical storage timestamps use UTC unless a later module contract states otherwise.

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
