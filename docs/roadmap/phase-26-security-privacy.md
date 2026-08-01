# Phase 26 — Security, privacy, deletion, and anonymization

**Status:** `complete`

## Objective

Implement the full security, privacy, hard-delete, and anonymization orchestration after the shared capabilities that own controlled copies already exist.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 18 — Administrative mode and impersonation](phase-18-admin-impersonation.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Phase 22 — Search](phase-22-search.md)
- [Phase 24 — Reports, exports, PDF, charts, and print](phase-24-reports-exports-print.md)
- [Phase 25 — Admin panel rebuild and operational UX repair](phase-25-admin-panel-rebuild-operational-ux.md)
- [Audit, privacy, deletion, and anonymization](../architecture/audit-privacy-and-deletion.md)
- [Security baseline](../architecture/security-baseline.md)

## Implementation contract

- Three distinct data-removal modes exist:
  - soft delete as default where appropriate;
  - hard delete only through dedicated high-risk Admin use cases;
  - irreversible anonymization.
- Every foreign key uses `RESTRICT`; cascade delete is forbidden.
- Hard delete requires separate permission, strong reauthentication, multi-step confirmation, mandatory reason, exact impact preview, full audit, and dry-run for mass operations.
- Financial, audit, legal, and retention-controlled records generally cannot be hard deleted.
- Anonymization is an explicit use case and must de-identify every controlled copy: core/related tables, permitted audit/log fields, managed-process runs and logs where controlled, files, attachments, search indexes, cache, queues, read models, exports, and copies controlled by the project.
- Preserve only neutral technical records required by law or integrity.
- Respect retention obligations.
- Security includes least privilege, CSRF, secure headers, no production stack traces, explicit mass assignment, no unsafe deserialization/eval, encryption where justified, dependency vulnerability checks, secret-free logs/audit, and reauthentication for destructive actions.
- Central rate limits cover login, API, search, imports, exports, and expensive operations by user/IP/team/operation.
- Admin can view blocks/abuse. Bypass exists only through explicit permission and configuration.

## Tasks

- [x] Add central hard-delete framework.
- [x] Add separate permissions.
- [x] Add reauthentication.
- [x] Add dry-run.
- [x] Add impact preview.
- [x] Add typed confirmation.
- [x] Add reason and audit.
- [x] Add irreversible anonymization framework.
- [x] Cover related tables.
- [x] Cover files.
- [x] Cover managed-process runs, structured process logs, queued work references, and schedule metadata.
- [x] Cover search indexes.
- [x] Cover cache.
- [x] Cover queued and derived data under project control.
- [x] Cover exports.
- [x] Document legal-retention exceptions.
- [x] Add security headers.
- [x] Add dependency vulnerability checks.
- [x] Add rate-limit management and visibility.
- [x] Add secret-safe logs and audit verification.
- [x] Add authorization regression tests.
- [x] Commit security and privacy foundation.

## Progress log

| Date       | Area                              | Routes / pages                                      | Notes                                                                                                                                                                                                                                                                                                                   |
| ---------- | --------------------------------- | --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-07-31 | Privacy and retention Admin entry | `/admin/privacy-retention`; `Admin/PrivacyRetention/Index` | Added the Core `privacy` module, `admin.privacy-retention.index` and preview/execute/legal-hold permissions, lifecycle participant registry, controlled-copy coverage catalog, backend-applied coverage filters, shared DataTable saved-view support, desktop/mobile navigation, breadcrumbs, PL/EN localization, and focused feature coverage. Destructive execution routes are intentionally not exposed yet. |
| 2026-07-31 | Durable dry-run preview framework | `/admin/privacy-retention/hard-delete/preview`; `/admin/privacy-retention/anonymization/preview` | Added `core_privacy.operation_requests` and `operation_previews`, high-risk preview routes, mandatory reason validation, generated typed confirmation phrases, participant impact/blocker aggregation, persisted dry-run preview results, and `privacy` security audit events. Preview blockers still prevent execution until controlled-copy coverage is complete for the selected subject. |
| 2026-07-31 | Central privacy execution framework | `/admin/privacy-retention/hard-delete/{operation}/execute`; `/admin/privacy-retention/anonymization/{operation}/execute` | Added `PrivacyOperationExecutor` for saved executable previews. Execution verifies the exact operation and typed confirmation phrase, reserves the request as `executing`, rechecks legal holds, runs registered lifecycle participants idempotently, stores `executed` or `blocked` with step metadata, and records `privacy` security audit events. The current UI does not expose execute buttons yet. |
| 2026-07-31 | Files lifecycle participant | Data lifecycle participant tag | Registered Files as a privacy lifecycle participant. File subjects now report `files.private_objects` impact during hard-delete/anonymization previews and delegate idempotent delete/anonymize execution to the existing Files `FileLifecycle` contract. Full destructive execution remains blocked by incomplete controlled-copy coverage outside Files. |
| 2026-07-31 | Legal holds and retention blockers | `/admin/privacy-retention/legal-holds`; `/admin/privacy-retention/legal-holds/create`; `Admin/PrivacyRetention/LegalHolds`; `Admin/PrivacyRetention/LegalHoldCreate` | Added `core_privacy.legal_holds`, separate reason-required legal-hold creation, legal-hold metrics and filtered DataTable, top-navbar Privacy subnavigation, privacy security audit events for hold creation, and `active_legal_hold` preview blockers for matching subjects. Release workflow remains a later Phase 26 increment. |
| 2026-07-31 | Privacy operation history | `/admin/privacy-retention/operations`; `Admin/PrivacyRetention/Operations` | Added a filtered operation-history subview for persisted hard-delete/anonymization requests and previews, including blocker/participant/record metrics, executable status, team/actor context, DataTable saved-view support, breadcrumbs, PL/EN localization, and feature coverage. |
| 2026-07-31 | Core related table lifecycle coverage | Data lifecycle participant tag | Added module-owned participants for user account/authentication data, Teams memberships and manager relationships, and Authorization user assignments/onboarding snapshots. Execution removes credential/session-derived rows and user-specific authorization pivots, ends active team and manager relationships, and redacts the Identity user record as a neutral technical anchor. |
| 2026-07-31 | Search lifecycle coverage | Data lifecycle participant tag | Verified the optional Search lifecycle participant inside Privacy previews. Mapped search documents now report `search.indexes` impact during hard-delete previews, and Search execution already removes projected documents idempotently through its document store. |
| 2026-07-31 | Managed process lifecycle coverage | Data lifecycle participant tag | Added `ManagedProcessDataLifecycleParticipant` for process runs, structured process logs, schedules, and queued work payloads. Active runs block privacy execution, completed process copies are redacted in place to preserve operational history, queued derived work is removed idempotently, and focused tests cover preview blockers and execution redaction. |
| 2026-07-31 | Shared cache and queued derived data | Data lifecycle participant tag | Added `SharedDerivedDataLifecycleParticipant` for subject-derived cache entries, cache locks, and pending queued jobs. The participant reports preview impacts and removes derived records idempotently while leaving durable audit/outbox/failed-job evidence to explicit module retention policies. |
| 2026-07-31 | Export lifecycle coverage | Data lifecycle participant tag | Added `ExportDataLifecycleParticipant` for export request snapshots, generated artifacts, render credentials, and linked private file copies. Active export generation blocks privacy execution; completed export snapshots are redacted, render credentials are removed, artifacts are expired, and artifact files are removed through Files lifecycle. |
| 2026-07-31 | Secret-safe logs and audit verification | Audit persistence and observability processors | Verified the existing shared log/Sentry redaction path and extended Audit persistence to redact sensitive text and payload keys in reasons, before/after values, and metadata before rows are stored. Focused regression coverage confirms stable operational fields remain visible while secrets and personal data are neutralized. |
| 2026-07-31 | HTTP security headers and dependency audit coverage | Middleware and technical configuration | Added global HTTP security headers, configurable CSP/referrer/permissions policies, and dependency audit command inventory for Composer and pnpm lockfiles. These remain technical protections and are intentionally not exposed as a read-only Admin screen. |
| 2026-07-31 | Rate-limit management and visibility | `/admin/rate-limits`; `Admin/RateLimits/Index` | Verified the existing read-only rate-limit policy browser, rejection statistics, backend filters, exports, and audited one-counter reset workflow against Phase 26. Admin cannot edit thresholds, create/delete policies, or disable rate limiting. |

## Completion criteria

- [x] Hard delete and anonymization are high-risk, audited, previewed, reasoned, and covered across core tables, files, search, cache, queues, exports, and derived data under project control.
- [x] Retention/legal exceptions are explicit.
- [x] Security hardening and rate-limit visibility are complete without bypass switches.
- [x] Relevant tests and documentation are current.
