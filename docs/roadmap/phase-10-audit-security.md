## Phase 10 — Audit and security audit

### Implementation contract

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

- [ ] Create `Audit` module.
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
