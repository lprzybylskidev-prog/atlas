## Phase 11 — Audit and security audit

**Status:** `not started`

## Objective

Create the full Audit module before settings, sessions, module activation, admin operations, manager hierarchy, impersonation, files, imports, integrations, reports, privacy actions, and TimeTracking depend on durable audit evidence. Consolidate audit events already produced by Phase 10 shared views without changing their producer contract.

## Dependencies

- [Phase 8 — Foundation completion and roadmap dependency repair](phase-08-foundation-completion.md)
- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Audit, privacy, deletion, and anonymization](../architecture/audit-privacy-and-deletion.md)
- [Security baseline](../architecture/security-baseline.md)

## Implementation contract

- The project owns its audit implementation; do not install `owen-it/laravel-auditing`.
- Audit meaningful domain/application actions, not every generic Eloquent update.
- Audit is append-only and has no normal edit workflow.
- Store where relevant:
  - actor;
  - actual actor during impersonation;
  - impersonated/context user;
  - target and aggregate IDs;
  - module;
  - team;
  - source: UI, API, import, integration, CLI, or job;
  - correlation/request ID;
  - action;
  - result;
  - meaningful before/after;
  - mandatory reason where required.
- Security audit is distinct enough to query authentication, MFA, sessions, impersonation, rate limits, locks, and authorization changes.
- Admin audit browser is read-only and filters by actor, actual actor, impersonated user, entity, action, team, module, correlation ID, date, result, and impersonation session.
- Logs and audit must not contain secrets or unnecessary sensitive values.

## Tasks

- [ ] Create `Audit` module.
- [ ] Consolidate existing security-audit and shared-view audit bridge records under the full Audit module contract.
- [ ] Define append-only audit model.
- [ ] Define meaningful audit event contract.
- [ ] Store actor, actual actor, target, team, module, source, correlation ID, reason, result, and meaningful before/after.
- [ ] Add security audit model.
- [ ] Audit login success and failure.
- [ ] Audit locks and unlocks.
- [ ] Audit password changes and resets.
- [ ] Audit MFA changes and resets.
- [ ] Audit session changes.
- [ ] Audit role, permission, team, manager, and module activation changes.
- [ ] Add audit browser.
- [ ] Add filters by user, entity, action, team, correlation ID, actual actor, and impersonated user.
- [ ] Enforce read-only audit UI.
- [ ] Add retention and privacy documentation.
- [ ] Commit audit foundation.

## Completion criteria

- [ ] Existing Identity/Authorization security-audit records are either migrated into or bridged through the full Audit module without data loss.
- [ ] Later high-risk and operational phases have a stable audit writer, read model, and Admin browser.
- [ ] Audit records are append-only, secret-safe, queryable, and covered by tests.
- [ ] Relevant module, architecture, and operations documentation is current.
