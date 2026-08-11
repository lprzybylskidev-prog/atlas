# Privacy and retention

Canonical current behavior for privacy, retention, hard-delete, anonymization, legal-hold readiness, and controlled-copy coverage.

## Ownership

`App\Modules\Core\Privacy\PrivacyModule` owns the cross-module privacy and retention orchestration surface.

The module is a Core module with the key `privacy`. Its formal dependencies are Identity, Teams, and Audit. Authorization, Files, Exports, ManagedProcesses, Search, and shared infrastructure contribute lifecycle participants through the neutral shared lifecycle contract; Privacy does not import their internals or require Optional modules to be deployed.

## Admin operations

The Admin privacy and retention browser is available at `/admin/privacy-retention`.

The area has top-level subnavigation:

- `/admin/privacy-retention` shows controlled-copy coverage and high-risk dry-run previews;
- `/admin/privacy-retention/legal-holds` shows legal holds and retention blockers;
- `/admin/privacy-retention/legal-holds/create` contains the legal-hold creation workflow;
- `/admin/privacy-retention/operations` shows persisted privacy operation requests and preview outcomes.

The route permission is `admin.privacy-retention.index`.

The sidebar label is **Privacy and retention** in English and **Prywatność i retencja** in Polish. This area is separate from the Audit / Security browser: Audit remains immutable diagnostic evidence, while Privacy and retention owns deletion, anonymization, retention, legal-hold, and controlled-copy readiness workflows.

The current screen exposes:

- operational metrics for controlled-copy areas, partial coverage, blocked hard-delete areas, and registered lifecycle participants;
- high-risk dry-run impact preview creation for hard delete and anonymization;
- modal preview results with non-zero impact rows and a CodeViewer drill-down of sanitized record details for each impacted dataset;
- legal-hold creation and visibility for subjects blocked by legal or retention obligations;
- persisted operation history for hard-delete and anonymization previews, including blockers, estimated records, participants, actor/team context, and typed confirmation phrases;
- permission-gated final execution from an executable preview after the operator types the exact confirmation phrase;
- a shared DataTable of controlled-copy coverage with backend-applied owner, coverage, retention, and lifecycle-participant filters;
- saved-view support through the shared Admin DataTable foundation.

Privacy Admin history and legal-hold list surfaces read Privacy-owned persistence and resolve actor/team labels through Identity `UserLookup` and Teams `TeamLookup`. They must not join Identity or Teams tables directly for display labels.

The preview result exposes final execution only when the saved preview is executable and the active actor/team has the matching hard-delete or anonymization execute permission. The execution routes require the full guardrail chain: separate permission, fresh high-risk reauthentication, configured MFA when required, exact typed confirmation phrase, mandatory reason from the approved preview, current impact snapshot, retention/legal blocker evaluation, idempotent participant execution, and audit.

## Persistence

Privacy persistence uses the `core_privacy` PostgreSQL schema:

- `operation_requests` stores the requested operation, subject, dry-run flag, status, requesting user/team, reason, typed confirmation phrase, correlation ID, and lifecycle timestamps;
- `operation_previews` stores the preview impacts, blockers, participant count, estimated record count, deterministic snapshot hash, and whether the current preview could execute;
- `legal_holds` stores subject-level legal/retention blockers, creator, team context, mandatory reason, optional expiry date, and reasoned release metadata. An authorized operator can release an active team-scoped hold from the legal-hold table; the locked state change and mandatory security audit commit atomically, while missing or already released targets produce rejected security evidence without overwriting the hold.

Foreign keys use `RESTRICT`.

## Permissions

Current Privacy permissions:

- `admin.privacy-retention.index`;
- `admin.privacy-retention.hard-delete.preview`;
- `admin.privacy-retention.hard-delete.execute`;
- `admin.privacy-retention.anonymization.preview`;
- `admin.privacy-retention.anonymization.execute`;
- `admin.privacy-retention.legal-holds.index`;
- `admin.privacy-retention.legal-holds.create`;
- `admin.privacy-retention.legal-holds.store`;
- `admin.privacy-retention.operations.index`.

The execute permissions are intentionally separate from preview and Admin visibility permissions. Hard delete and irreversible anonymization remain high-risk operations. Execution is available only from an executable saved preview and revalidates authorization, confirmation, stale-write, lifecycle-participant, transaction, and audit contracts at the final request.

Preview routes already require the matching high-risk administrative operation freshness:

- `hard_delete` for hard-delete previews;
- `irreversible_anonymization` for anonymization previews.

Privacy owns its high-risk reauthentication continuation payload. Before a preview request is redirected to password confirmation, the Privacy continuation validates the preview request, stores only the allowlisted preview fields, flashes the same fields for the form, and lets the generic high-risk middleware remain unaware of Privacy route names and form fields. The recovered payload is consumed once by the Privacy preview flow and cleared after the preview is created.

Execution routes use the same high-risk administrative operation freshness and are intentionally separate from preview routes:

- `POST /admin/privacy-retention/hard-delete/{operation}/execute`;
- `POST /admin/privacy-retention/anonymization/{operation}/execute`.

## Controlled-copy participation

Atlas already has the shared `App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant` contract from the modular foundation. Phase 26 builds orchestration around that contract instead of introducing a second lifecycle mechanism.

The Privacy module provides a participant registry backed by the `atlas.data_lifecycle_participants` service tag. Registered participants must be idempotent, support preview before execution, report blockers explicitly, and avoid leaking secrets or unnecessary personal data in previews, results, logs, or audit metadata.

Preview impacts may include sanitized record details for operator review. These details identify the records expected to be removed, redacted, or de-indexed, but must not expose secrets, raw credentials, token hashes, session payloads, private file contents, full queued-job payloads, or broad sensitive JSON snapshots.

Current known controlled-copy areas are:

- Identity users;
- team and authorization assignments;
- Audit events;
- Files private objects;
- Managed Process runs and structured logs;
- Search index documents;
- shared cache and queued derived data;
- Export artifacts.

Users/Identity provides a registered lifecycle participant for `user` subjects. It reports and executes against the user account row, password history, reset tokens, and database-backed sessions. Execution removes credential/session-derived rows and redacts the user account into an inactive neutral record so existing audit and foreign-key references keep a non-personal technical anchor.

Teams and Authorization provide registered lifecycle participants for `user` subjects. Teams ends active team assignments, clears head-manager status, ends active manager relationships involving the user, and removes creator/ender references where they are only actor metadata. Authorization removes the user's role assignments, direct permission assignments, and onboarding-package snapshots while leaving role, permission, and package definitions intact.

Files provide a registered privacy lifecycle participant for `file` and `file_object` subjects. The participant reports `files.private_objects` preview impact for live file public IDs and delegates idempotent delete/anonymize execution to the existing Files `FileLifecycle` contract.

Managed Processes provides a registered privacy lifecycle participant for process runs, structured process logs, process schedules, and queued work payloads that contain a lifecycle subject identifier. Active process runs add a `managed_process_active_run` blocker; completed process copies are redacted while operational history is preserved.

Shared infrastructure provides a registered privacy lifecycle participant for subject-derived cache entries, cache locks, and pending queued jobs. Privacy execution removes those derived copies idempotently. Durable outbox events and failed-job diagnostic records are not purged by this participant; they remain governed by their own audit/operations retention rules.

Exports provides a registered privacy lifecycle participant for export request snapshots, generated artifact metadata/file copies, and render credentials. Active export requests add an `export_generation_active` blocker; completed export snapshots are redacted, render credentials are deleted, and artifact file copies are removed through Files lifecycle while neutral export history is preserved.

Search registers a data-lifecycle participant for projected documents when the optional Search module is loaded. Privacy previews include `search.indexes` impact for lifecycle subjects mapped by Search projectors, and execution removes projected documents idempotently through the Search document store. Remaining areas are tracked by Phase 26.

Privacy has no dependency on Optional ManagedProcesses or Search. Every lifecycle participant exposes a stable owner key through the shared `DataLifecycleParticipant` contract and contributes through the `atlas.data_lifecycle_participants` tag. Privacy derives coverage from those keys without class strings or imports of provider-module internals. Missing optional participants therefore produce an explicit reduced coverage row without making the Core module undeployable or changing its startup graph.

Preview creation records a `privacy` security audit event with the operation, dry-run flag, participant count, estimated records, blocker codes, subject, aggregate privacy request ID, team, actor, and reason.

Execution is coordinated by `PrivacyOperationExecutor`. Inside one database transaction it locks and reserves an executable preview, verifies the exact typed confirmation phrase and operation type, recomputes every registered participant preview, rechecks active legal holds, participant count, blockers, estimated records, and the deterministic impact snapshot hash, then calls each participant through the shared idempotent `execute` contract. Any stale preview or participant failure rolls back all participant mutations. Successful execution state and mandatory audit evidence commit atomically; rejected and failed attempts are persisted in a separate failure-evidence transaction only after the owning transaction has rolled back.

## Retention

Financial, audit, legal, and retention-controlled records generally cannot be hard deleted. They must either block the operation, be preserved as neutral technical records, or be de-identified only where legally permitted by an explicit anonymization workflow.

An active legal hold is any `legal_holds` record for the preview subject with no release timestamp and either no expiry date or an expiry date on or after the current UTC date. Active legal holds add an `active_legal_hold` blocker to hard-delete and anonymization previews.
# Phase 28 foundation repair target

Current state: Privacy owns retention/legal-hold/preview/execution workflows, Admin history/legal-hold labels use owner-owned Identity/Teams lookups, optional lifecycle coverage is discovered through stable shared participant keys without Optional module dependencies, and execution success/rejection/failure evidence, stale-preview protection, rollback, and terminal-state atomicity are enforced.

Target state: Privacy uses owner-owned public contracts, audits every high-risk success/rejection/failure path atomically, keeps lifecycle participant boundaries explicit, and has clear localized Admin workflows.

Tracked issue IDs: `P28-ARCH-003`, `P28-ARCH-009`, `P28-AUDIT-007`, `P28-AUDIT-009`, `P28-MODAUD-011`.
