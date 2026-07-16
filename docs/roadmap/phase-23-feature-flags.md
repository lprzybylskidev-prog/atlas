## Phase 23 — Feature flags

**Status:** `not started`

## Objective

Implement typed feature flags before later optional/business capabilities use controlled feature rollout, while keeping flags separate from module activation and authorization.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)

## Implementation contract

- Feature flags are typed and separate from module activation.
- Support global and per-team values plus complete history.
- Feature flags cannot be used to work around missing authorization or module state.
- Changes require permissions, reason where meaningful, and audit.

## Tasks

- [ ] Create optional `FeatureFlags` module.
- [ ] Define typed feature flags.
- [ ] Support global and per-team values.
- [ ] Store history.
- [ ] Add permissions.
- [ ] Add admin management.
- [ ] Audit changes.
- [ ] Prevent feature flags from replacing module activation.
- [ ] Commit FeatureFlags module.

## Completion criteria

- [ ] Feature flags are typed, audited, permissioned, and scoped without replacing module activation or authorization.
- [ ] History is complete for global and per-team flag changes.
- [ ] Later phases can use flags only for rollout behavior, not missing security or lifecycle controls.
- [ ] Relevant tests and documentation are current.
