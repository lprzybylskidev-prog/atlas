## Phase 16 — Admin operations and health

**Status:** `not started`

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
- Add Laravel Pulse as the later internal performance dashboard when Atlas has real operational traffic from workflows, queues, imports, exports, reports, and integrations.
- Health, readiness, scheduler checks, queue checks, backup checks, integration status, and Admin System Status are the baseline operational visibility.
- The Admin operational area includes an Audit browser entry that reuses the Phase 11 read-only audit browser instead of creating a separate audit surface.
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

- [ ] Build read-only Admin rate-limit policy and rejection-statistics views.
- [ ] Implement narrowly scoped confirmed reset of one rate-limit counter.
- [ ] Audit rate-limit counter resets with policy, limiter key, actor, reason, and correlation ID.
- [ ] Ensure Admin cannot edit thresholds or disable policies.
- [ ] Configure readable development logs and structured JSON production logs.
- [ ] Implement correlation ID creation and propagation for HTTP requests, queue jobs, CLI commands, scheduler runs, and integrations.
- [ ] Implement optional causation ID propagation.
- [ ] Add stable module, source, event-name, release, actor-public-ID, and team-public-ID log context.
- [ ] Implement one centralized sensitive-field redaction policy.
- [ ] Add regression tests preventing secrets, cookies, sessions, full bodies, full headers, and sensitive personal data from reaching logs or Sentry.
- [ ] Configure sanitized Sentry scope with release, environment, module, and correlation ID.
- [ ] Add Laravel Pulse as an internal performance dashboard after real operational workflows generate useful runtime signals.
- [ ] Implement scheduler heartbeat visibility.
- [ ] Implement configurable email/webhook operational alert channels.
- [ ] Add deduplicated/throttled alerts for readiness, repeated failed jobs, scheduler, backup, integration, and critical Sentry failures.
- [ ] Build a curated Admin application-log browser without arbitrary filesystem access.
- [ ] Include the read-only Audit browser in the Admin operations navigation and operational review flow.
- [ ] Document that Prometheus, OpenTelemetry, and distributed tracing are outside the current Atlas scope.
- [ ] Create `Health` module.
- [ ] Add liveness endpoint.
- [ ] Add readiness endpoint.
- [ ] Check PostgreSQL.
- [ ] Check Redis.
- [ ] Report Meilisearch as degraded by default and blocking only when Search is configured critical.
- [ ] Check storage.
- [ ] Check queues.
- [ ] Check scheduler freshness.
- [ ] Check critical configuration.
- [ ] Check ClamAV as blocking whenever Files are active in production.
- [ ] Check Chromium/PDF renderer capability and classify it as degraded or critical by configuration.
- [ ] Expose blocking versus degraded results separately in readiness and Admin System Status.
- [ ] Keep public health details minimal.
- [ ] Build detailed admin System Status.
- [ ] Add application version and release ID.
- [ ] Add last-deploy information.
- [ ] Build queues and failed-jobs admin screens.
- [ ] Add safe retry.
- [ ] Add strong confirmation for mass retries.
- [ ] Build logs browser with strict security.
- [ ] Ensure logs UI cannot manipulate arbitrary server files.
- [ ] Commit Admin operations and Health.

## Completion criteria

- [ ] Liveness/readiness, scheduler, queues, logs, alerts, and Admin System Status expose blocking versus degraded state safely.
- [ ] Correlation IDs, redaction, and sanitized Sentry context are implemented across HTTP, jobs, CLI, scheduler, and integrations.
- [ ] Later operational modules can expose diagnostics without inventing their own health/logging surface.
- [ ] Relevant tests and documentation are current.
