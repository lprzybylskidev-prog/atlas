# Audit

Canonical current behavior for application audit, security audit, immutable evidence, and the Admin audit browser.

## Ownership

`App\Modules\Core\Audit\AuditModule` owns the full Atlas audit implementation.

Atlas uses its own audit system. It does not use `owen-it/laravel-auditing`.

Current public writer:

- `App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder`;
- `App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent`.

The earlier Identity `SecurityAuditRecorder` producer contract remains available as a compatibility producer contract for existing Identity, Authorization, and shared table view producers. Its implementation now writes into the full Audit module. It no longer owns a separate `security_audit_events` table.

## Persistence

Audit persistence uses:

- `audit_events` for the complete append-only audit record;
- `audit_security_events` as the security-focused read model for authentication, MFA, password, session, authorization, impersonation, lock, and suspicious-activity querying.

Fresh installations do not create the former `security_audit_events` table. The Phase 11 migration can import that legacy table when it exists in a pre-Phase-11 local database, then removes it.

Audit records are append-only. PostgreSQL triggers reject ordinary updates and deletes on audit tables.

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
- onboarding package application during user creation;
- Admin role create, update, and delete attempts;
- Admin team create, update, activation, deactivation, and delete attempts;
- shared DataTable team/system saved-view create, update, and delete.

Future session administration, manager hierarchy, module activation, impersonation, imports, integrations, files, reports, privacy, and TimeTracking workflows use the same Audit module writer instead of creating separate audit tables.

## Admin Browser

The Admin audit browser is available at `/admin/audit`.

It is read-only and uses the shared `DataTable` wrapper with backend-validated state and saved views. It exposes the audit read model for filtering/searching by visible audit fields such as actor, action, target, target type, team, module, source, result, correlation ID, and security flag.

Audit browser saved views include table state and active audit filters.

The route permission is `admin.audit.index`.
