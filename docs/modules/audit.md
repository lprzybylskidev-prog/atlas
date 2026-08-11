# Audit

Canonical current behavior for application audit, security audit, immutable evidence, and the Admin audit browser.

## Ownership

`App\Modules\Core\Audit\AuditModule` owns the full Atlas audit implementation.

Atlas uses its own audit system. It does not use `owen-it/laravel-auditing`.

Current public writer:

- `App\Shared\Application\Audit\Contracts\AuditRecorder`;
- `App\Shared\Application\Audit\DTOs\AuditEvent`.

Current bounded public readers:

- `App\Modules\Core\Audit\Application\Public\Contracts\AuditEventLookup`;
- `App\Modules\Core\Audit\Application\Public\DTOs\AuditEventSummary`.

Current context provider:

- `App\Shared\Application\Audit\Contracts\AuditActorContextProvider`;
- `App\Shared\Application\Audit\DTOs\AuditActorContext`.
- `App\Shared\Application\Audit\Enums\SecurityAuditCategory`.

Current producer catalog:

- `App\Shared\Application\Audit\Contracts\AuditCatalog`;
- `App\Shared\Application\Audit\ConfiguredAuditCatalog`;
- module-key registrations under `config/audit.php`, including actions, sources, target and aggregate types, metadata keys, and security categories;
- the executable critical-operation outcome matrix under `audit.required_operation_coverage`.

The earlier Identity `SecurityAuditRecorder` producer contract remains available as a compatibility producer contract for existing Identity, Authorization, and shared table view producers. Its implementation now writes into the full Audit module. It no longer owns a separate `security_audit_events` table.

## Persistence

Audit persistence uses:

- `audit_events` for the complete append-only audit record;
- `audit_security_events` as the security-focused read model for authentication, MFA, password, session, authorization, impersonation, lock, and suspicious-activity querying.

Fresh installations do not create or import the former `security_audit_events` table. The pre-production migration squash removed that local-history bridge; development databases created before the squash must be reset through the documented local reset workflow.

Audit records are append-only. PostgreSQL triggers reject ordinary updates and deletes on audit tables.

Audit persistence records rows only. It does not read Laravel's global `request()` helper, HTTP session, or impersonation session keys directly. Request/session-specific context is provided through `AuditActorContextProvider`, with safe empty behavior for CLI, scheduler, queue, and request-less execution.

## Event Contents

Audit events store where relevant:

- actor public ID;
- actual actor public ID;
- impersonated user public ID;
- impersonation session ID;
- target type and public ID;
- aggregate type and public ID;
- active team public ID;
- module;
- source;
- correlation ID;
- action;
- result;
- reason;
- before values;
- after values;
- secret-safe metadata.

Audit records must not contain passwords, password hashes, MFA secrets, recovery codes, tokens, raw credentials, full sensitive payloads, or unnecessary personal data.

The database recorder applies Atlas' shared `SensitiveDataRedactor` before persistence. The recorder redacts sensitive values in `reason`, `before`, `after`, and `metadata` while preserving stable non-sensitive operational fields such as public identifiers, action names, result, source, team, and correlation ID. Audit producers must still avoid passing secrets deliberately; recorder sanitization is a final persistence guard, not a reason to send raw credentials into audit events.

Security audit events must provide an explicit shared `SecurityAuditCategory` enum value. The stored database value remains the enum's stable string value, such as `authentication`, `password`, `mfa`, `session`, `authorization`, `impersonation`, `administrative_mode`, `rate_limit`, `settings`, `queue_operations`, `files`, `integrations`, or `privacy`. Runtime code must not infer security category from fragments of the action name.

## Retention And Privacy

Audit records are retained as durable operational and security evidence. Financial, security, legal, and retention-controlled audit evidence must not be hard deleted through ordinary workflows.

Future privacy, deletion, and anonymization workflows may neutralize legally erasable personal identifiers inside audit records only through explicit irreversible processes with documented scope, reason, impact preview, and audit of the privacy action itself.

Ordinary and detailed Audit exports inherit the same recorder redaction and retention rules. Detailed export never restores values already redacted at persistence.

## Current Producers

Registered producers include:

- login success and failure;
- logout;
- login locks and administrative unlocks;
- password reset, first-password setup, and password change;
- MFA reset;
- first-administrator bootstrap;
- administrator role catalog synchronization;
- preset application during user creation;
- Admin role create, update, and delete attempts;
- Admin team create, update, activation, deactivation, and delete attempts;
- shared DataTable team/system saved-view create, update, and delete;
- module activation global changes, team override changes, schedule creation, schedule cancellation, attempted/rejected/succeeded deactivation, rejected activation attempts, and rejected schedule attempts;
- administrative mode enter/exit, high-risk reauthentication, impersonation start/end, sensitive-account override decisions, and impersonated actions enriched with actual administrator and impersonation context.
- feature flag global value changes, team override changes, and team override clear actions.
- Files upload, scan, scan failure, rejected rescan/delete, download, lifecycle, controlled retention copy/export, and maintenance outcomes;
- Privacy preview, legal-hold, successful execution, rejected missing/confirmation/operation/state attempts, and participant execution failure;
- Integrations connection-test success, rejection, and failure plus external operation/idempotency evidence;
- managed-process lifecycle and queue administration;
- TimeTracking session, break, Other-work, correction, maintenance, and Admin decision operations, including before/after decision evidence.

Imports, Search, Exports, Reports, and future business modules use the same shared producer contract instead of creating separate audit tables. A module adding an audited mutation must extend its catalog registration and the lowest effective outcome test in the same change.

## Admin Browser

The Admin audit browser is available at `/admin/audit`.

It is read-only and uses the shared `DataTable` wrapper with backend-validated state and saved views. It exposes the audit read model for filtering/searching by visible audit fields such as actor, action, target, target type, team, module, source, result, correlation ID, and security flag.

Audit browser saved views include table state and active audit filters.

Audit-owned screens read audit tables directly, but user and team display labels come from owner-owned Identity `UserLookup` and Teams `TeamLookup` public contracts. Audit browser filters and security history do not join or query Identity or Teams persistence tables for display labels.

The route permission is `admin.audit.index`.

The main event list and impersonation-session event list are database-backed read models. Filtering, search, sorting, counts, and pagination execute in PostgreSQL; ordering uses the requested indexed column with `id` as a stable tie-breaker. The browser has no fixed recent-record ceiling, so older evidence remains reachable. Audit exports stream the complete filtered result instead of truncating it to the browser's former 5,000-row working set.

Impersonation session detail is available at `/admin/audit/impersonation/{session}` for `admin.audit.impersonation.show`. It reads the append-only audit events for one impersonation session ID and shows session metadata plus successful and rejected operations.

## Admin Security History

Administrators may view security history for all users at `/admin/audit/security-history` with permission `admin.audit.security-history.index`. The screen reads the append-only audit records owned by the Audit module and shows recent security events across actors, actual actors, impersonated users, targets, teams, actions, results, reasons, and impersonation session IDs. Admin can filter the screen by a selected user; the filter matches events where that user is the actor, actual actor, impersonated user, or target. Audit events and security history use the Core Exports Admin DataTable provider contract for CSV, XLSX, PDF, and browser print exports. Ordinary Audit event exports omit metadata. The Audit event table exposes a distinct detailed action only with `exports.audit-export`; it includes recorder-redacted metadata as deterministic JSON and carries that permission in the immutable request authorization snapshot through generation, download, print, and render access.

Impersonation events appear here without sending a real-time user notification by default.

## Producer value and transaction contract

Audit results persisted by the recorder are exactly `succeeded`, `rejected`, or `failed`. Legacy aliases such as `success`, `blocked`, and `partial` are rejected rather than normalized. The configured catalog rejects unknown modules, actions, results, sources, target types, aggregate types, metadata keys, and security categories before persistence. Actions use stable namespaced keys; a producer reference is accepted only when the owning module registered that value. Security events require an explicit `SecurityAuditCategory`.

The Audit recorder enriches an omitted effective actor and correlation ID from propagated request/queue/CLI context, while preserving explicit effective, actual, impersonated actor, and impersonation-session values. Recording a security event inserts the primary audit row and its security-history row in one database transaction; failure of the security projection rolls back the primary row. Critical producers call the recorder inside the same owning transaction as their state change, so mandatory audit failure prevents a terminal unaudited commit. Irreversible Privacy participant mutations, terminal status, and mandatory audit row share one transaction; stale previews, participant failures, and final audit failures roll back the complete execution before separate rejected or failed evidence is recorded.

## Enforcement and required tests

`AuditCatalogArchitectureTest` scans application producers, rejects legacy results and uncataloged event shapes, verifies a non-vacuous module/action catalog, and requires every critical-operation matrix entry to name a real outcome test. Feature and integration tests cover connection-test success/rejection/failure, Files terminal/rejected/retention failures, Privacy success/rejection/participant failure, TimeTracking decision before/after/security context and rollback, module deactivation outcomes, context propagation, request-less recording, append-only triggers, redaction, and primary/security pair rollback. The Audit browser regression fixture exceeds 5,000 rows and proves both old-page reachability and complete streaming export traversal.
# Phase 28 foundation repair target

Current state: Core Audit owns append-only audit/security records, registered producer catalogs, context completion, redaction, DB-backed Admin browsing/security and impersonation history, and complete filtered export traversal. Critical operation coverage and atomicity are protected by executable catalog, outcome-matrix, large-dataset, and rollback tests. Audit browser team labels and security-history user labels use Identity/Teams public lookup contracts, while the bounded `AuditEventLookup` reader lets TimeTracking detail screens show recent Audit-owned evidence without reading Audit tables directly.

Ongoing contract: every new audited operation must extend the owning catalog and effective outcome tests without weakening append-only, secret-safe, transaction, export-authorization, or ownership boundaries.

Tracked issue IDs: `P28-ARCH-001`, `P28-ARCH-002`, `P28-ARCH-005`, `P28-ARCH-006`, `P28-AUDIT-001`, `P28-AUDIT-002`, `P28-AUDIT-003`, `P28-AUDIT-008`, `P28-AUDIT-009`, `P28-AUDIT-010`, `P28-AUDIT-011`, `P28-MODAUD-005`.
