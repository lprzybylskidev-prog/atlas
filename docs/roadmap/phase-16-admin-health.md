## Phase 16 — Admin operations and health

**Status:** `complete`

## Objective

Complete operational health, readiness, logging, alerts, queues, diagnostics, and Admin operational screens before files, imports, integrations, search, reporting, privacy, deployment, and TimeTracking depend on operational visibility.

## Dependencies

- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Admin module documentation](../modules/admin.md)
- [Health, observability, and maintenance](../operations/health-observability-and-maintenance.md)

## Implementation contract

- Admin panel is developed in parallel with foundations, is English-only, and has its own layout, route namespace, menu, and permissions.
- Admin uses the same Domain/Application use cases as the regular UI, with stronger audit and confirmation where needed.
- Admin is not a generic CRUD generator or an incubator for misplaced business logic.
- Initial Admin areas include Users, Roles, Permissions, Teams, Managers, Logs, Storage, System Status, Queues, Failed Jobs, Imports, Integrations, Feature Flags, Audit, and Module Activation.
- System Status shows PostgreSQL, Redis, Meilisearch, queue health, scheduler freshness, storage, version, release ID, and last deploy.
- Failed jobs support safe retry; mass retry requires strong confirmation.
- Liveness and readiness are separate.
- Readiness distinguishes blocking and degraded dependencies. PostgreSQL, required Redis capabilities, required storage, queues, scheduler freshness, and critical configuration are blocking. Meilisearch is degraded by default unless Search is explicitly critical. ClamAV is blocking when Files are active in production. Chromium/PDF renderer reports degraded capability unless explicitly configured as critical.
- Public health exposes no sensitive details. Admin may expose detailed diagnostics.
- Docker and deploy use readiness as the success condition.
- Controlled maintenance mode has a clear page, optional allowed users/IPs, explicit queue/scheduler behavior, planned warnings, audit, and is used only when an incompatible deploy cannot be zero-downtime.
- Development logs are human-readable text.
- Production logs are structured JSON.
- Every HTTP request, queue job, CLI command, scheduler run, and integration call has a `correlation_id`.
- Jobs inherit the originating correlation ID when dispatched from an existing operation.
- Use `causation_id` when one distinct operation or event directly causes another.
- Structured log context includes, where applicable and safe:
  - timestamp;
  - level;
  - environment;
  - release identifier;
  - module;
  - correlation ID;
  - causation ID;
  - public actor identifier;
  - public team identifier;
  - source type such as HTTP, job, CLI, scheduler, or integration;
  - stable event name;
  - sanitized contextual fields.
- Maintain one centralized sensitive-field redaction policy.
- Never log passwords, password or first-password secrets, MFA secrets, recovery codes, access tokens, API keys, cookies, session identifiers, full request bodies, full request/response headers, or unnecessary personal and financial data.
- Sentry receives exception details, release, environment, correlation ID, module, and sanitized context only.
- Do not include Prometheus, OpenTelemetry, distributed tracing, or a separate telemetry stack in the initial Atlas scope.
- Keep Laravel Telescope and Laravel Debugbar as development-only diagnostics; they must remain disabled for tests, E2E, production, and untrusted environments.
- Add Laravel Pulse as the internal performance dashboard, protected by Admin authorization, for runtime usage and bottleneck trends.
- Health, readiness, scheduler checks, queue checks, backup checks, integration status, and Admin System Status are the baseline operational visibility.
- Support configurable alert delivery such as email and webhook.
- Baseline alerts cover readiness failures, repeated failed jobs, scheduler heartbeat failure, backup failure, persistent integration failure, and critical Sentry exceptions.
- Alert deduplication and throttling prevent repeated notification storms.
- The Admin log browser may expose curated application log entries or indexed operational events.
- It must never provide arbitrary filesystem paths, unrestricted file download, shell access, or generic server-log browsing.
- Admin may inspect registered rate-limit policies, current configured values, and rejection statistics.
- Admin may reset one concrete limiter counter after explicit confirmation.
- Counter reset must identify the exact policy and limiter key, be narrowly scoped, and be audited.
- Admin cannot edit policies, change thresholds, or disable rate limiting.

## Tasks

- [x] Build read-only Admin rate-limit policy and rejection-statistics views.
- [x] Implement narrowly scoped confirmed reset of one rate-limit counter.
- [x] Audit rate-limit counter resets with policy, limiter key, actor, reason, and correlation ID.
- [x] Ensure Admin cannot edit thresholds or disable policies.
- [x] Configure readable development logs and structured JSON production logs.
- [x] Implement correlation ID creation and propagation for HTTP requests, queue jobs, CLI commands, scheduler runs, and integrations.
- [x] Implement optional causation ID propagation.
- [x] Add stable module, source, event-name, release, actor-public-ID, and team-public-ID log context.
- [x] Implement one centralized sensitive-field redaction policy.
- [x] Add regression tests preventing secrets, cookies, sessions, full bodies, full headers, and sensitive personal data from reaching logs or Sentry.
- [x] Configure sanitized Sentry scope with release, environment, module, and correlation ID.
- [x] Add Laravel Pulse as an internal performance dashboard protected by Admin authorization.
- [x] Implement scheduler heartbeat visibility.
- [x] Enforce ModuleGate for module-owned queued jobs, scheduled tasks, and operational retry actions.
- [x] Audit and surface module activation scheduler failures as Admin operational diagnostics.
- [x] Implement configurable email/webhook operational alert channels.
- [x] Add deduplicated/throttled alerts for readiness, repeated failed jobs, scheduler, backup, integration, and critical Sentry failures.
- [x] Build a curated Admin application-log browser without arbitrary filesystem access.
- [x] Document that Prometheus, OpenTelemetry, and distributed tracing are outside the current Atlas scope.
- [x] Create `Health` module.
- [x] Add liveness endpoint.
- [x] Add readiness endpoint.
- [x] Check PostgreSQL.
- [x] Check Redis.
- [x] Report Meilisearch as degraded by default and blocking only when Search is configured critical.
- [x] Check storage.
- [x] Check queues.
- [x] Check scheduler freshness.
- [x] Check critical configuration.
- [x] Check ClamAV as blocking whenever Files are active in production.
- [x] Check Chromium/PDF renderer capability and classify it as degraded or critical by configuration.
- [x] Expose blocking versus degraded results separately in readiness and Admin System Status.
- [x] Keep public health details minimal.
- [x] Build detailed admin System Status.
- [x] Add application version and release ID.
- [x] Add last-deploy information.
- [x] Build queues and failed-jobs admin screens.
- [x] Add safe retry.
- [x] Add strong confirmation for mass retries.
- [x] Build logs browser with strict security.
- [x] Ensure logs UI cannot manipulate arbitrary server files.
- [x] Commit Admin operations and Health.

## Completion criteria

- [x] Liveness/readiness, scheduler, queues, logs, alerts, and Admin System Status expose blocking versus degraded state safely.
- [x] Correlation IDs, redaction, and sanitized Sentry context are implemented across HTTP, jobs, CLI, scheduler, and integrations.
- [x] Later operational modules can expose diagnostics without inventing their own health/logging surface.
- [x] Relevant tests and documentation are current.
