## Phase 11 — Settings and localization

### Implementation contract

- Settings are typed; do not use an uncontrolled universal JSON blob.
- Separate global, team, user, and security settings.
- Define explicit defaults and inheritance.
- User-controlled settings include UI language, light/dark theme, notification preferences, default team, table views, dashboard layout, and accessibility.
- Admin-controlled settings include timeouts, MFA requirements, teams, roles, permissions, activation, and security controls.
- Security-setting changes are audited.
- The regular UI supports Polish and English with Polish default.
- Language switch controls in Auth, App, and Admin shells must switch between Polish and English instead of remaining preview-only buttons.
- Selected UI language is persisted as a typed user preference when the user is authenticated and falls back to a safe temporary guest preference before login.
- Inertia shared props expose the effective locale so frontend shells, navigation, forms, and validation messages can render consistently after a language change.
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
- [ ] Wire Auth, App, and Admin language switch controls to the effective language preference.
- [ ] Persist authenticated user language selection and provide a temporary guest-language fallback for login.
- [ ] Ensure switching language refreshes Inertia-visible UI copy without leaving stale shell labels.
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
