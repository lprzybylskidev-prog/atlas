# Phase 21 — Imports

**Status:** `implemented pending acceptance and commit`

## Objective

Implement reusable import pipelines on top of the managed-process foundation after files, notifications, audit, integrations, module activation, and operational health are complete.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 20 — Integrations](phase-20-integrations.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Imports module documentation](../modules/imports.md)
- [Managed processes module documentation](../modules/managed-processes.md)

## Implementation contract

- Use one transport-independent import pipeline:
  source adapter -> parsing -> normalization -> input DTO -> validation -> deduplication/idempotency -> domain use cases -> audit/error report.
- Supported adapters include CSV, XLSX, XML, internal API, and external API.
- An API adapter cannot bypass validation, use cases, invariants, authorization, or audit.
- Every import execution is a managed process run.
- Every import process stores import-specific source metadata, original file or API request reference, statistics, row/field errors, and idempotency state while reusing managed-process status, progress, logs, queue dispatch, retry/cancel, notification, schedule, audit, and Admin visibility.
- Large imports run through managed-process queues and publish progress notifications through the managed-process foundation.
- Preserve original files according to retention.
- Import jobs are idempotent and safe to retry according to explicit rules.
- Admin can inspect import status, source, statistics, row/field errors, timeline logs, and allowed retries through the managed-process Admin UI plus import-specific detail panels.

## Tasks

- [x] Create optional `Imports` module.
- [x] Define source adapter contracts.
- [x] Add CSV adapter.
- [x] Add XLSX adapter.
- [x] Add XML adapter.
- [x] Add internal API adapter.
- [x] Add external API adapter.
- [x] Implement parsing.
- [x] Implement normalization.
- [x] Implement typed input DTOs.
- [x] Implement validation.
- [x] Implement deduplication and idempotency.
- [x] Route imported data through domain use cases.
- [x] Store managed process run ID, source, file/API reference, statistics, idempotency state, and row/field errors.
- [x] Queue large imports.
- [x] Enforce ModuleGate and active-team context for import creation, API adapters, queued import jobs, retry actions, and progress endpoints.
- [x] Register import deactivation guards for running or retryable imports.
- [x] Preserve original import files.
- [x] Add import-specific retry rules through managed-process retry policies.
- [x] Add import administration by extending managed-process Admin run details with import source, mapping, preview, statistics, and row/field error panels.
- [x] Add import notifications and progress through the managed-process foundation.
- [x] Add development-only demo seeders for example import processes and statuses after real import tables exist.
- [x] Remove development-only demo import adapters, process handlers, seeded import executions, and artificial import row errors before clean-app handoff.
- [ ] Commit Imports module after final owner confirmation.

## Completion criteria

- [x] Every adapter flows through the same validation, use case, idempotency, audit, file, notification, progress, structured-log, and managed-process contracts.
- [x] Large imports are queued, observable, retry-safe, cancellable where safe, and administrable through managed processes.
- [x] API adapters cannot bypass domain use cases or security boundaries.
- [x] Relevant tests and documentation are current.
