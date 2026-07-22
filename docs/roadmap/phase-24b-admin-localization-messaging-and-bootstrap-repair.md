# Phase 24b — Admin localization, messaging, operational visibility, and bootstrap repair

**Status:** `not started`

## Objective

Repair cross-cutting issues discovered after Phase 24a by making Admin UI fully localizable, removing Admin-only-English assumptions, normalizing flash and notification behavior, ensuring Admin operational views provide full data visibility with filters instead of failure-only subsets, and separating mandatory system bootstrap seeders from the currently empty development demo seeder.

## Dependencies

- [Phase 0 — Repository bootstrap](phase-00-bootstrap.md)
- [Phase 7 — Authorization and teams](phase-07-authorization-teams.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Phase 24a — Core export foundation and Admin data integration](phase-24a-core-export-foundation.md)
- [Admin module documentation](../modules/admin.md)
- [Authorization module documentation](../modules/authorization.md)
- [Notifications module documentation](../modules/notifications.md)
- [Settings module documentation](../modules/settings.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)
- [Quality gates and git workflow](../operations/quality-gates-and-git.md)

## Related documentation

- Module: `../modules/admin.md`
- Module: `../modules/authorization.md`
- Module: `../modules/notifications.md`
- Module: `../modules/settings.md`
- Architecture: `../architecture/frontend-ui.md`
- Architecture: `../architecture/security-baseline.md`
- Architecture: `../architecture/tables-reports-exports-and-print.md`
- Operations: `../operations/quality-gates-and-git.md`

## Implementation contract

- Admin UI is user-facing UI and must support the same Polish and English localization model as Auth and regular application UI.
- Remove repository-wide and canonical documentation statements that require Admin UI, Admin validation messages, Admin flash messages, Admin breadcrumbs, or Admin backend-rendered UI text to remain English-only.
- Technical documentation, code, commit messages, technical errors, logs, CLI output, operational command output, exception class names, route names, permission names, database identifiers, enum keys, and other implementation identifiers remain English.
- Admin language switching must be real, persisted through the existing typed localization preference, reflected in Inertia shared props, and available from the Admin shell.
- Admin and shared UI translation catalogs must maintain PL/EN parity and must not depend on hardcoded source strings.
- Backend validation, flash messages, toasts, notification titles/bodies, breadcrumbs, form labels, table labels, empty/error/loading states, export lifecycle messages, and Admin shell/navigation text must render localized human text.
- Technical values may be displayed in Admin operational data views when they are useful for diagnosis or audit, including IDs, public identifiers, route names, permission names, module keys, event names, queue names, job classes, payload summaries, exception classes, correlation IDs, and raw operational statuses.
- Technical values must not leak into ordinary user-facing messages such as flash messages, toasts, notification titles/bodies, validation messages, confirmation copy, or success/error summaries unless the value is explicitly the subject of an operator action and is rendered with clear human context.
- Flash messages and notifications must have distinct ownership: immediate request outcome belongs to one flash/toast, while asynchronous terminal outcome belongs to one notification with a useful deep link when an artifact or result exists.
- Export, import, managed-process, file-scan, retry, rebuild, integration, and other queued workflows must avoid duplicate user messages from multiple layers for the same state transition.
- A queued export action must produce one immediate flash message confirming that generation was queued and one terminal notification when the artifact is ready, failed, or otherwise finished; ready notifications must link to the file or result view.
- Flash messages, toasts, notification dropdown data, unread counts, and notification center state must update deterministically after redirects, Inertia partial reloads, realtime polling events, and language changes without requiring a manual browser refresh.
- Admin operational views must present the full relevant operational dataset by default or provide clear default filters that can be removed by the operator. Failure-only views are allowed only when the bounded resource is genuinely a failure table, not when broader current or historical state exists elsewhere.
- The Admin queues area must evolve from failed-jobs-only visibility into a full queue operations view where available data covers pending, reserved/running, delayed, failed, retried, and completed or historical work according to Atlas' persistence capabilities. Filters may narrow the view to failed jobs.
- The full-operational-visibility rule applies beyond queues. Review Admin logs, system status, managed processes, imports, integrations, files, feature flags, search, module activation, audit/security history, rate limits, exports, and other Admin operational areas for misleading failure-only, active-only, stale-only, or partial default surfaces, then repair any equivalent issues found.
- Admin export providers must follow the repaired Admin data contracts: exported values may include safe technical operational columns where appropriate, but export status messaging remains localized and human-readable.
- Mandatory system bootstrap data must be created by normal seeders, not by development demo seeders.
- The `Administration` team is mandatory system bootstrap data. It must be created idempotently by normal seeders.
- The local/development administrator account, when configured for development bootstrap, is mandatory development foundation data and must not depend on the demo seeder.
- The `Administration` team must automatically receive all currently registered module access and all currently registered permissions through explicit bootstrap assignment, role synchronization, or module activation mechanisms.
- Newly added modules and permissions must be picked up by the Administration bootstrap path without requiring demo data.
- The Administration bootstrap must not implement a hidden authorization bypass. Administrators still use normal teams, roles, permissions, ModuleGate checks, validations, confirmations, and audit.
- The development demo seeder's current state is empty or no-op because no demo business data is currently needed. This is a current repository state for this phase, not a permanent repository-wide rule.
- Future demo data may be added only when requested as concrete representative demo scope and must stay separate from mandatory system bootstrap.
- Tests must not depend on demo seeder data or demo account credentials.

## Review inventory

The following areas must be reviewed during this phase. Any equivalent issue found must be fixed or documented with a clear rationale.

| Area | Required review |
| --- | --- |
| Admin localization | Admin shell, navigation, breadcrumbs, forms, tables, filters, empty/error/loading states, dialogs, confirmations, export UI, validation messages, flashes, and notification copy. |
| Shared UI localization | Shared components used by Admin and regular UI, especially table, form, alert/toast, notification, badge, formatter, and dialog primitives. |
| Flash/toast pipeline | Inertia shared props, redirects, partial reloads, queue dispatch responses, frontend alert queue, duplicate message sources, dismissal state, and language refresh behavior. |
| Notifications/realtime | Polling event handling, notification dropdown refresh, unread count refresh, notification center state, deep links, terminal queued-work events, and duplicate delivery. |
| Exports | Immediate queued feedback, terminal artifact notification, PDF/browser-print lifecycle, managed-process linkage, artifact deep links, and localized status/error messages. |
| Queues | Pending/reserved/delayed/failed/retried/completed visibility according to available persistence, filters, retry actions, dashboard signals, and safe technical detail rendering. |
| Managed processes/imports/search rebuilds | Active, queued, historical, failed, warning, cancelled, retry, and scheduled visibility; message duplication; terminal notifications. |
| Files/integrations/feature flags/modules/rate limits/logs/audit | Check for partial operational visibility, misleading default failure-only views, duplicate messages, untranslated Admin copy, and unsafe technical values in human messages. |
| Seeders/bootstrap | Normal seeders, demo reset command, development administrator creation, Administration team creation, permission catalogs, role synchronization, module activation, and tests that currently rely on demo data. |

## Tasks

- [ ] Update roadmap and canonical documentation to replace Admin-only-English contracts with full Admin PL/EN localization while keeping technical artifacts English.
- [ ] Add or repair the Admin shell language switch so it persists the existing typed language preference and refreshes Admin Inertia props consistently.
- [ ] Audit Admin and shared frontend/backend user-facing strings, add missing PL/EN translations, and preserve missing-key/parity checks.
- [ ] Update validation attribute names, accepted values, flash messages, notification titles/bodies, breadcrumbs, and backend-rendered Admin copy to use localized human text.
- [ ] Define and implement a single flash/toast ownership contract for synchronous request outcomes.
- [ ] Define and implement a single terminal notification ownership contract for asynchronous workflows with deep links to artifacts or result views.
- [ ] Repair export messaging so queued export requests emit one immediate flash message and one terminal notification, with no duplicate flash/toast/notification storm.
- [ ] Review and repair equivalent messaging duplication in imports, managed processes, file scans, retries, rebuilds, integrations, and other queued operations.
- [ ] Diagnose and fix cases where flash messages, toasts, notification dropdowns, unread counts, or notification-center rows require manual page refresh.
- [ ] Add frontend/backend tests covering deterministic message updates after redirects, partial reloads, realtime polling events, notification creation, notification read state changes, and language changes.
- [ ] Replace failure-only Admin queue visibility with a full queue operations view using filters for failed jobs and other states supported by Atlas persistence.
- [ ] Review all Admin operational areas for equivalent partial-data views and repair any surfaces that hide available current or historical operational data behind failure-only or overly narrow defaults.
- [ ] Ensure Admin operational tables and exports safely expose useful technical diagnostic values while ordinary messages remain localized and human-readable.
- [ ] Move mandatory `Administration` team creation out of demo seeding and into normal idempotent system seeders.
- [ ] Move local/development administrator account bootstrap out of demo seeding and into the appropriate normal development/bootstrap seeder path.
- [ ] Ensure the `Administration` team receives all registered module access and all registered permissions automatically through explicit bootstrap synchronization.
- [ ] Ensure new modules and new permissions are included by the Administration bootstrap path without depending on demo data.
- [ ] Make the current development demo seeder empty or no-op, without documenting emptiness as a permanent repository-wide rule.
- [ ] Update tests that depend on demo seeder data so they create explicit fixtures or use the normal bootstrap data only where appropriate.
- [ ] Add regression coverage for Administration team bootstrap, administrator account bootstrap, automatic permission/module assignment, demo seeder no-op behavior, Admin localization, messaging, and full operational visibility.
- [ ] Run relevant quality gates and update canonical docs.
- [ ] Commit Admin localization, messaging, operational visibility, and bootstrap repair.

## Completion criteria

- [ ] Admin UI supports real PL/EN switching and no canonical documentation still requires Admin UI to be English-only.
- [ ] Technical artifacts remain English, while user-facing Admin copy is localized and human-readable.
- [ ] Technical diagnostic values remain available in appropriate Admin operational data views but do not leak into ordinary messages.
- [ ] Flash/toast and notification behavior is deterministic, non-duplicative, and does not require manual refresh.
- [ ] Queued exports and equivalent asynchronous workflows emit one immediate queued message and one terminal notification with a useful link when available.
- [ ] Admin operational views provide full relevant data visibility with filters, and any intentionally narrow view has a documented rationale.
- [ ] Mandatory Administration team, administrator account bootstrap, permissions, and module access are created by normal seeders, not demo seeders.
- [ ] The current demo seeder is empty or no-op and no test depends on demo data.
- [ ] Relevant automated tests and canonical documentation are current.
- [ ] `WORKROAD.md` points to the next correct active phase.
