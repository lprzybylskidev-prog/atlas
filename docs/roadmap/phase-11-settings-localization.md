## Phase 11 — Settings and localization

### Implementation contract

- Settings are typed; do not use an uncontrolled universal JSON blob.
- Separate global, team, user, and security settings.
- Define explicit defaults and inheritance.
- User-controlled settings include UI language, light/dark theme, notification preferences, default team, table views, dashboard layout, and accessibility.
- Admin-controlled settings include timeouts, MFA requirements, teams, roles, permissions, activation, and security controls.
- Security-setting changes are audited.
- The regular UI supports Polish and English with Polish default.
- Translation keys are stable technical keys, never source strings.
- No hardcoded user-facing strings.
- PL/EN parity and missing-key checks are required.
- Backend exceptions, CLI, documentation, and Admin panel remain English.

- [ ] Create `Settings` module.
- [ ] Implement typed global settings.
- [ ] Implement typed team settings.
- [ ] Implement typed user settings.
- [ ] Separate security settings.
- [ ] Add defaults and inheritance.
- [ ] Add language preference.
- [ ] Add theme preference.
- [ ] Add notification preferences.
- [ ] Add default-team preference.
- [ ] Add table-view preferences.
- [ ] Add dashboard preferences.
- [ ] Add accessibility preferences.
- [ ] Audit security-setting changes.
- [ ] Add PL and EN translation catalogs.
- [ ] Add missing-key and parity checks.
- [ ] Ensure admin panel remains English only.
- [ ] Commit settings and localization.
