# Audit, privacy, deletion, and anonymization

Canonical cross-module requirements for audit evidence, soft delete, hard delete, anonymization, retention participation, privacy, and irreversible operations.

## Deletion, Hard Delete, and Anonymization

Three distinct modes exist.

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

### Irreversible anonymization

A dedicated explicit process that de-identifies all controlled copies, including:

- core tables;
- related tables;
- audit records where legally permitted;
- logs where controlled;
- files and attachments;
- search indexes;
- cache;
- queues;
- read models;
- exports;
- generated copies.

Preserve only neutral technical records where required.

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

Audit is append-only and not normally editable. PostgreSQL triggers reject ordinary updates and deletes on audit tables.

Maintain a separate security audit for authentication, impersonation, sessions, permissions, MFA, locks, and suspicious activity.

Security audit producers provide an explicit typed category. Runtime category fallback based on action-name fragments is not allowed; old migration-time mapping may exist only to import legacy local records.

---
