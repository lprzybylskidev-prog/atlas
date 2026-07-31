# Audit, privacy, deletion, and anonymization

Canonical cross-module requirements for audit evidence, soft delete, hard delete, anonymization, retention participation, privacy, and irreversible operations.

## Deletion, Hard Delete, and Anonymization

Three distinct modes exist.

`App\Modules\Core\Privacy\PrivacyModule` owns the cross-module privacy and retention orchestration surface. It is separate from Audit: Audit stores immutable evidence, while Privacy coordinates deletion, anonymization, retention, legal-hold, and controlled-copy participation workflows.

The Admin entry point is `/admin/privacy-retention`. The current surface shows readiness, controlled-copy coverage, and durable dry-run impact previews. Destructive execution routes must not be introduced until the full high-risk workflow is implemented.

### Soft delete

Default behavior where suitable.

### Hard delete

Allowed only through dedicated administrative use cases with:

- separate permission;
- strong reauthentication;
- multi-step confirmation;
- dry run for mass operations;
- exact impact preview;
- required reason;
- complete audit.

Financial, audit, legal, and retention-controlled data generally must not be hard deleted.

The central Privacy executor may run hard-delete only from a saved executable preview. It must reserve the operation, verify the typed confirmation phrase, recheck legal/retention blockers, call registered lifecycle participants idempotently, persist the terminal status, and write a security audit event.

### Irreversible anonymization

A dedicated explicit process that de-identifies all controlled copies, including:

- core tables;
- related tables;
- audit records where legally permitted;
- logs where controlled;
- managed-process runs and structured process logs where controlled;
- files and attachments;
- search indexes;
- cache;
- queues;
- read models;
- exports;
- generated copies.

Preserve only neutral technical records where required.

The same central Privacy executor coordinates irreversible anonymization. Module participants own the actual de-identification of their controlled copies, while the Privacy module owns the high-risk guardrails, status transitions, blocker handling, execution metadata, and audit record.

Core related tables are covered by module-owned participants. Identity/Users neutralizes the user account and removes authentication secrets and session-derived rows. Teams ends active memberships and manager relationships while preserving neutral history. Authorization removes user-specific role, direct-permission, and onboarding-package assignments while preserving system definitions.

Shared derived data such as subject-derived cache entries, cache locks, and pending queued jobs is removed idempotently by the shared lifecycle participant. Durable audit evidence, outbox history, failed-job diagnostics, and other legally or operationally retained records are not generic cache cleanup targets; they require explicit module-owned redaction or retention policy.

Respect legal retention rules.

---

## Audit

Use an internal audit system.

Do not use `owen-it/laravel-auditing`.

The Core Audit module owns audit persistence and read models. Existing Identity, Authorization, and Phase 10 shared-view producers may continue using the earlier `SecurityAuditRecorder` producer contract, but that contract is now implemented by the Audit module and writes into `audit_events` plus `audit_security_events`.

Audit persistence depends on an explicit current audit context provider instead of Laravel HTTP globals. Web implementations may read session state, including impersonation state, but the database recorder must remain safe for CLI, scheduler, queue, and request-less execution.

Audit meaningful domain and application events, not every Eloquent timestamp update.

Audit records should include where relevant:

- actor;
- actual actor;
- impersonated user;
- target;
- action;
- time;
- module;
- active team;
- source: UI, API, import, integration, CLI, or job;
- correlation/request ID;
- meaningful before and after values;
- reason;
- result.

Audit persistence must be secret-safe. The database recorder redacts sensitive text and payload keys in reasons, before/after values, and metadata before writing rows. Producers must still avoid sending raw credentials, tokens, headers, full request/response bodies, or unnecessary personal data into audit events.

Audit is append-only and not normally editable. PostgreSQL triggers reject ordinary updates and deletes on audit tables.

Maintain a separate security audit for authentication, impersonation, sessions, permissions, MFA, locks, and suspicious activity.

Security audit producers provide an explicit typed category. Runtime category fallback based on action-name fragments is not allowed; old migration-time mapping may exist only to import legacy local records.

---
