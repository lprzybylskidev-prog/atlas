# Privacy and retention

Canonical current behavior for privacy, retention, hard-delete, anonymization, legal-hold readiness, and controlled-copy coverage.

## Ownership

`App\Modules\Core\Privacy\PrivacyModule` owns the cross-module privacy and retention orchestration surface.

The module is a Core module with the key `privacy`. It depends on Identity, Authorization, Teams, Audit, Files, Managed Processes, and Exports, and treats Search as an optional participant because search projections can be absent from a deployed environment.

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
- a shared DataTable of controlled-copy coverage with backend-applied owner, coverage, retention, and lifecycle-participant filters;
- saved-view support through the shared Admin DataTable foundation.

The current screens do not expose an execute button yet. The backend execution routes exist for saved executable previews and require the full Phase 26 guardrail chain: separate permission, fresh high-risk reauthentication, typed confirmation phrase, mandatory reason from the approved preview, exact impact preview, retention/legal blocker evaluation, idempotent participant execution, and audit.

## Persistence

Privacy persistence uses the `core_privacy` PostgreSQL schema:

- `operation_requests` stores the requested operation, subject, dry-run flag, status, requesting user/team, reason, typed confirmation phrase, correlation ID, and lifecycle timestamps;
- `operation_previews` stores the preview impacts, blockers, participant count, estimated record count, and whether the current preview could execute;
- `legal_holds` stores subject-level legal/retention blockers, creator, team context, mandatory reason, optional expiry date, and release metadata reserved for the later release workflow.

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

The execute permissions are intentionally separate from preview and Admin visibility permissions. Hard delete and irreversible anonymization remain high-risk operations and must use the existing high-risk administrative operation classes before execution routes are introduced.

Preview routes already require the matching high-risk administrative operation freshness:

- `hard_delete` for hard-delete previews;
- `irreversible_anonymization` for anonymization previews.

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

Users/Identity provides a registered lifecycle participant for `user` subjects. It reports and executes against the user account row, password history, reset tokens, WebAuthn credentials, and database-backed sessions. Execution removes credential/session-derived rows and redacts the user account into an inactive neutral record so existing audit and foreign-key references keep a non-personal technical anchor.

Teams and Authorization provide registered lifecycle participants for `user` subjects. Teams ends active team assignments, clears head-manager status, ends active manager relationships involving the user, and removes creator/ender references where they are only actor metadata. Authorization removes the user's role assignments, direct permission assignments, and onboarding-package snapshots while leaving role, permission, and package definitions intact.

Files provide a registered privacy lifecycle participant for `file` and `file_object` subjects. The participant reports `files.private_objects` preview impact for live file public IDs and delegates idempotent delete/anonymize execution to the existing Files `FileLifecycle` contract.

Managed Processes provides a registered privacy lifecycle participant for process runs, structured process logs, process schedules, and queued work payloads that contain a lifecycle subject identifier. Active process runs add a `managed_process_active_run` blocker; completed process copies are redacted while operational history is preserved.

Shared infrastructure provides a registered privacy lifecycle participant for subject-derived cache entries, cache locks, and pending queued jobs. Privacy execution removes those derived copies idempotently. Durable outbox events and failed-job diagnostic records are not purged by this participant; they remain governed by their own audit/operations retention rules.

Exports provides a registered privacy lifecycle participant for export request snapshots, generated artifact metadata/file copies, and render credentials. Active export requests add an `export_generation_active` blocker; completed export snapshots are redacted, render credentials are deleted, and artifact file copies are removed through Files lifecycle while neutral export history is preserved.

Search registers a data-lifecycle participant for projected documents when the optional Search module is loaded. Privacy previews include `search.indexes` impact for lifecycle subjects mapped by Search projectors, and execution removes projected documents idempotently through the Search document store. Remaining areas are tracked by Phase 26.

Preview creation records a `privacy` security audit event with the operation, dry-run flag, participant count, estimated records, blocker codes, subject, aggregate privacy request ID, team, actor, and reason.

Execution is coordinated by `PrivacyOperationExecutor`. It reserves an executable preview by moving the request to `executing`, verifies the exact typed confirmation phrase and operation type, rechecks active legal holds, calls each registered lifecycle participant through the shared idempotent `execute` contract, then stores `executed` or `blocked` with affected-record and step metadata. Completed and blocked execution attempts record `privacy.hard_delete_executed` or `privacy.anonymization_executed` security audit events.

## Retention

Financial, audit, legal, and retention-controlled records generally cannot be hard deleted. They must either block the operation, be preserved as neutral technical records, or be de-identified only where legally permitted by an explicit anonymization workflow.

An active legal hold is any `legal_holds` record for the preview subject with no release timestamp and either no expiry date or an expiry date on or after the current UTC date. Active legal holds add an `active_legal_hold` blocker to hard-delete and anonymization previews.
