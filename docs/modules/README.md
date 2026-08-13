# Module documentation

Each file is the canonical current-state description of one Atlas module or tightly related module group.

Each module document contains a Phase 28 closure note recording the implemented foundation-repair state and the concrete Phase 28 issue IDs it satisfies. The completed binding contract and evidence remain in [Phase 28 — Foundation repair and consolidation](../roadmap/phase-28-foundation-repair-and-consolidation.md).

- [Identity, authentication, users, and sessions](identity-authentication-and-sessions.md) — account lifecycle, password, MFA, login, and session behavior.
- [Authorization](authorization.md) — permissions, roles, framework boundary, and enforcement.
- [Audit](audit.md) — application audit, security audit, immutable evidence, and Admin audit browsing.
- [Privacy and retention](privacy.md) — privacy workflows, retention, hard-delete/anonymization readiness, legal holds, and controlled-copy coverage.
- [Teams and manager hierarchy](teams-and-manager-hierarchy.md) — team assignment and manager DAG behavior.
- [Admin](admin.md) — administrative module scope and screens.
- [TimeTracking](time-tracking.md) — work time, breaks, inactivity, corrections, settlement, and reporting.
- [Files](files.md) — private storage, quarantine, malware scanning, and retention.
- [Exports](exports.md) — Core export snapshots, artifact lifecycle, render credentials, CSV/XLSX/PDF generation, and browser print.
- [Managed processes](managed-processes.md) — process definitions, runs, structured logs, queues, schedules, retry/cancel, progress, and Admin visibility.
- [Imports](imports.md) — mapping, validation, preview, execution, progress, and errors.
- [Integrations](integrations.md) — external adapters, retries, idempotency, and visibility.
- [Notifications](notifications.md) — notification types, channels, preferences, and delivery.
- [Calendar](calendar.md) — shared Core Calendar ownership, public contribution and Free/Busy boundaries, persistence, privacy, and permissions.
- [Internal communication and Chat](chat.md) — optional Chat boundary plus the accepted Calls, Meetings, recording, provider-neutral transcription, realtime, privacy, Files, Search, and retention contract.
- [Settings](settings.md) — typed settings, scope, precedence, validation, and caching.
- [Health](health.md) — liveness, readiness, dependency classification, and Admin diagnostics.
- [Search](search.md) — Meilisearch projections, indexing, rebuild, and health.
- [Feature flags](feature-flags.md) — typed rollout flags, global/team values, history, audit, and Admin management.
- [Reports](reports.md) — optional named reports, report-specific charts, catalogs, and Core Exports integration points.
