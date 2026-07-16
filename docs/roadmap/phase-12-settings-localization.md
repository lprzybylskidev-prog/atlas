## Phase 12 — Settings and localization

**Status:** `in progress`

## Objective

Implement typed settings and real PL/EN localization before sessions, Admin mode, notifications, tables, module activation, reports, and TimeTracking rely on configurable preferences or security settings.

## Dependencies

- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Settings module documentation](../modules/settings.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)

## Implementation contract

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

## Tasks

- [x] Create `Settings` module.
- [x] Implement typed global settings.
- [x] Implement typed team settings.
- [x] Implement typed user settings.
- [x] Separate security settings.
- [x] Add defaults and inheritance.
- [x] Add language preference.
- [ ] Wire Auth, App, and Admin language switch controls to the effective language preference.
- [x] Persist authenticated user language selection and provide a temporary guest-language fallback for login.
- [x] Ensure switching language refreshes Inertia-visible UI copy without leaving stale shell labels.
- [x] Add theme preference.
- [x] Add notification preferences.
- [x] Add default-team preference.
- [x] Add table-view preferences.
- [x] Add dashboard preferences.
- [x] Add accessibility preferences.
- [x] Audit security-setting changes.
- [ ] Add PL and EN translation catalogs.
- [ ] Add missing-key and parity checks.
- [ ] Ensure admin panel remains English only.
- [ ] Commit settings and localization.

## Completion criteria

- [x] Typed global, team, user, and security settings exist with defaults, inheritance, validation, caching, and audit where required.
- [ ] Auth/App language controls switch real PL/EN UI copy instead of preview-only state.
- [x] Later phases can store preferences and security timeouts without temporary local storage or config mutation.
- [ ] PL/EN parity and missing-key checks pass.
