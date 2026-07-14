## Phase 21 — Feature flags

### Implementation contract

- Feature flags are typed and separate from module activation.
- Support global and per-team values plus complete history.
- Feature flags cannot be used to work around missing authorization or module state.
- Changes require permissions, reason where meaningful, and audit.

- [ ] Create optional `FeatureFlags` module.
- [ ] Define typed feature flags.
- [ ] Support global and per-team values.
- [ ] Store history.
- [ ] Add permissions.
- [ ] Add admin management.
- [ ] Audit changes.
- [ ] Prevent feature flags from replacing module activation.
- [ ] Commit FeatureFlags module.
