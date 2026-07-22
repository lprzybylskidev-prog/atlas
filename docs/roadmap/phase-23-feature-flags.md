# Phase 23 — Feature flags

**Status:** `complete`

## Objective

Implement typed feature flags before later optional/business capabilities use controlled feature rollout, while keeping flags separate from module activation and authorization.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 22a — Frontend rebuild and design system hardening](phase-22a-frontend-rebuild.md)

## Implementation contract

- Feature flags are typed and separate from module activation.
- Support global and per-team values plus complete history.
- Feature flags cannot be used to work around missing authorization or module state.
- Changes require permissions, reason where meaningful, and audit.

## Tasks

- [x] Create optional `FeatureFlags` module.
- [x] Define typed feature flags.
- [x] Support global and per-team values.
- [x] Store history.
- [x] Add permissions.
- [x] Add admin management.
- [x] Audit changes.
- [x] Prevent feature flags from replacing module activation.
- [x] Commit FeatureFlags module.

## Completion criteria

- [x] Feature flags are typed, audited, permissioned, and scoped without replacing module activation or authorization.
- [x] History is complete for global and per-team flag changes.
- [x] Later phases can use flags only for rollout behavior, not missing security or lifecycle controls.
- [x] Relevant tests and documentation are current.
