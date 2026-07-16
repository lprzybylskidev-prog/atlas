## Phase 21 — Imports

**Status:** `not started`

## Objective

Implement reusable import pipelines after files, notifications, audit, integrations, module activation, and operational health are complete.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 20 — Integrations](phase-20-integrations.md)
- [Imports module documentation](../modules/imports.md)

## Implementation contract

- Use one transport-independent import pipeline:
  source adapter -> parsing -> normalization -> input DTO -> validation -> deduplication/idempotency -> domain use cases -> audit/error report.
- Supported adapters include CSV, XLSX, XML, internal API, and external API.
- An API adapter cannot bypass validation, use cases, invariants, authorization, or audit.
- Every import process stores ID, source, original file, status, statistics, and row/field errors.
- Large imports run in queues and publish progress notifications.
- Preserve original files according to retention.
- Import jobs are idempotent and safe to retry according to explicit rules.
- Admin can inspect status, source, statistics, errors, and allowed retries.

## Tasks

- [ ] Create optional `Imports` module.
- [ ] Define source adapter contracts.
- [ ] Add CSV adapter.
- [ ] Add XLSX adapter.
- [ ] Add XML adapter.
- [ ] Add internal API adapter.
- [ ] Add external API adapter.
- [ ] Implement parsing.
- [ ] Implement normalization.
- [ ] Implement typed input DTOs.
- [ ] Implement validation.
- [ ] Implement deduplication and idempotency.
- [ ] Route imported data through domain use cases.
- [ ] Store process ID, source, file, status, statistics, and row/field errors.
- [ ] Queue large imports.
- [ ] Enforce ModuleGate and active-team context for import creation, API adapters, queued import jobs, retry actions, and progress endpoints.
- [ ] Register import deactivation guards for running or retryable imports.
- [ ] Preserve original import files.
- [ ] Add retry rules.
- [ ] Add import administration.
- [ ] Add notifications and progress.
- [ ] Add development-only demo seeders for example import processes and statuses after real import tables exist.
- [ ] Commit Imports module.

## Completion criteria

- [ ] Every adapter flows through the same validation, use case, idempotency, audit, file, notification, and progress contracts.
- [ ] Large imports are queued, observable, retry-safe, and administrable.
- [ ] API adapters cannot bypass domain use cases or security boundaries.
- [ ] Relevant tests and documentation are current.
