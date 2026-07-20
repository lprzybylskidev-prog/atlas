# Audit

Canonical current behavior for application audit, security audit, immutable evidence, and the Admin audit browser.

## Ownership

`App\Modules\Core\Audit\AuditModule` owns the full Atlas audit implementation.

Atlas uses its own audit system. It does not use `owen-it/laravel-auditing`.

Current public writer:

- `App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder`;
- `App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent`.

Current context provider:

- `App\Modules\Core\Audit\Application\Public\Contracts\AuditActorContextProvider`;
- `App\Modules\Core\Audit\Application\Public\DTOs\AuditActorContext`.

The earlier Identity `SecurityAuditRecorder` producer contract remains available as a compatibility producer contract for existing Identity, Authorization, and shared table view producers. Its implementation now writes into the full Audit module. It no longer owns a separate `security_audit_events` table.

## Persistence

Audit persistence uses:

- `audit_events` for the complete append-only audit record;
- `audit_security_events` as the security-focused read model for authentication, MFA, password, session, authorization, impersonation, lock, and suspicious-activity querying.

Fresh installations do not create the former `security_audit_events` table. The Phase 11 migration can import that legacy table when it exists in a pre-Phase-11 local database, then removes it.

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

Security audit events must provide an explicit `SecurityAuditCategory` enum value. The stored database value remains the enum's stable string value, such as `authentication`, `password`, `mfa`, `session`, `authorization`, `impersonation`, `administrative_mode`, `rate_limit`, `settings`, `queue_operations`, `files`, or `integrations`. Runtime code must not infer security category from fragments of the action name.

## Retention And Privacy

Audit records are retained as durable operational and security evidence. Financial, security, legal, and retention-controlled audit evidence must not be hard deleted through ordinary workflows.

Future privacy, deletion, and anonymization workflows may neutralize legally erasable personal identifiers inside audit records only through explicit irreversible processes with documented scope, reason, impact preview, and audit of the privacy action itself.

Audit exports and reports generated in later phases must inherit the same secret-redaction and retention rules.

## Current Producers

Current producers include:

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
- shared DataTable team/system saved-view create, update, and delete.
- module activation global changes, team override changes, schedule creation, schedule cancellation, rejected activation attempts, and rejected schedule attempts.
- administrative mode enter/exit, high-risk reauthentication, impersonation start/end, sensitive-account override decisions, and impersonated actions enriched with actual administrator and impersonation context.

Future imports, integrations, files, reports, privacy, and TimeTracking workflows use the same Audit module writer instead of creating separate audit tables.

## Admin Browser

The Admin audit browser is available at `/admin/audit`.

It is read-only and uses the shared `DataTable` wrapper with backend-validated state and saved views. It exposes the audit read model for filtering/searching by visible audit fields such as actor, action, target, target type, team, module, source, result, correlation ID, and security flag.

Audit browser saved views include table state and active audit filters.

The route permission is `admin.audit.index`.

Impersonation session detail is available at `/admin/audit/impersonation/{session}` for `admin.audit.impersonation.show`. It reads the append-only audit events for one impersonation session ID and shows session metadata plus successful and rejected operations.

## Admin Security History

Administrators may view security history for all users at `/admin/audit/security-history` with permission `admin.audit.security-history.index`. The screen reads the append-only audit records owned by the Audit module and shows recent security events across actors, actual actors, impersonated users, targets, teams, actions, results, reasons, and impersonation session IDs. Admin can filter the screen by a selected user; the filter matches events where that user is the actor, actual actor, impersonated user, or target.

Impersonation events appear here without sending a real-time user notification by default.
