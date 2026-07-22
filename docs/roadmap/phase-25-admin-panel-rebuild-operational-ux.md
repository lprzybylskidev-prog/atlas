# Phase 25 — Admin panel rebuild and operational UX repair

**Status:** `not started`

## Objective

Rebuild the Admin panel views from the existing backend foundations and shared frontend primitives so Admin becomes a coherent operational tool instead of a collection of mixed, duplicated, partially localized, and noisy screens.

This phase is the single active Admin repair scope.

## Dependencies

- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Phase 21 — Imports](phase-21-imports.md)
- [Phase 22 — Search](phase-22-search.md)
- [Phase 23 — Feature flags](phase-23-feature-flags.md)
- [Phase 24a — Core export foundation and Admin data integration](phase-24a-core-export-foundation.md)
- [Admin module documentation](../modules/admin.md)
- [Notifications module documentation](../modules/notifications.md)
- [Settings module documentation](../modules/settings.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)
- [Tables, reports, exports, and print](../architecture/tables-reports-exports-and-print.md)
- [Quality gates and git workflow](../operations/quality-gates-and-git.md)

## Related documentation

- Module: `../modules/admin.md`
- Module: `../modules/notifications.md`
- Module: `../modules/settings.md`
- Architecture: `../architecture/frontend-ui.md`
- Architecture: `../architecture/tables-reports-exports-and-print.md`
- Operations: `../operations/quality-gates-and-git.md`

## Implementation contract

- The existing Admin backend foundations, domain/application logic, permissions, policies, route contracts, shared UI primitives, DataTable foundation, forms, dialogs, toasts, notifications, localization, and export infrastructure should be preserved where they are sound.
- The current Admin views must be treated as disposable. Rebuild the Admin Vue pages and Admin dashboard composition intentionally instead of patching low-quality screens with more explanatory copy.
- In Phase 25, "clean Admin views" means actually clearing/removing the existing Admin view implementations and rebuilding them from scratch on top of the accepted backend and shared UI foundations. It does not mean incremental styling, copy edits, or local patching of the current screens.
- Clearing Admin views does not mean discarding accepted frontend foundations. Rebuilt views must use the existing shared frontend modules, UI primitives, composables, services, formatters, table foundation, form primitives, modal/toast infrastructure, layouts, theme system, localization helper, route helpers, and test fixtures wherever they fit the accepted contracts.
- Existing Admin page code may be inspected only to recover required contracts, routes, props, permissions, actions, data ownership, and edge cases. It must not be treated as the design baseline for the rebuilt views.
- New frontend modules or primitives may be introduced only when the existing accepted frontend modules cannot express the rebuilt workflow cleanly; reusable additions must live in the shared frontend layer rather than inside one-off Admin pages.
- Do not remove backend capabilities merely because the current view is poor. Replace the view and presentation contract while preserving useful tested backend behavior.
- Admin pages must be operational tools, not documentation pages. If a view needs a long explanation to justify why it is useful, redesign the view with proper pagination, filters, drill-down, ownership, and actions.
- Remove the bounded-view-disclaimer pattern as a primary UI mechanism. If data is bounded, the view must expose a proper table, pagination, filters, date/range controls, counters, or a subtle scoped affordance. Long warning/explanation blocks are allowed only for genuinely exceptional or dangerous conditions.
- Admin dashboard must show concise, actionable operational summaries. It must not duplicate sidebar navigation, raw process steps, raw failed jobs, low-level event streams, or architecture explanations.
- Admin dashboard operational signals must be correctly attributed to their owner or rendered as global signals. Queue failures, managed-process failures, health/readiness failures, scheduler issues, operational alerts, module activation problems, audit/security findings, integration failures, file scan failures, search rebuild failures, and export/import failures must not appear under unrelated module cards.
- Queues and Managed Processes must have clear boundaries. Queues own low-level transport/job diagnostics. Managed Processes own Atlas workflow execution. When a queue failure belongs to a managed process, the richer workflow view must be the primary owning view and secondary views must cross-link instead of duplicating the problem as unrelated.
- The same asynchronous-work failure must not appear as independent unrelated errors in Dashboard, Queues, Managed Processes, Notifications, and module screens. It needs one owner, deduped summaries, and useful source deep links.
- Administrators must be able to acknowledge, resolve, and reopen operational issues that would otherwise keep warning on the dashboard. Acknowledged/resolved issues must stop shouting unless they recur, worsen, or meet an explicit escalation rule.
- Operational issue lifecycle actions must be backend-authorized, audited, team/module scoped where applicable, concurrency-safe, and localized.
- Notifications and toasts are not operational incident storage. They may inform about important state changes, but the durable Admin operational model owns acknowledgement, resolution, reopening, deduplication, and dashboard visibility.
- Exporting from any Admin DataTable must produce at most one immediate request outcome toast/flash and one terminal notification. Managed-process step/status events such as running, validation, generation, storage, generated, or failed must not create visible toast stacks.
- Admin UI localization is complete only when rendered screens are reviewed in the browser and user-facing titles, subtitles, section labels, descriptions, card labels, table labels, empty states, action labels, dialog copy, validation messages, flashes, toasts, notifications, breadcrumbs, and helper text follow the active Polish/English locale.
- Technical diagnostic values may remain English where they are genuine operational identifiers, such as route names, permission names, queue names, job classes, process keys, exception classes, module keys, public identifiers, and correlation IDs. They must not be used as ordinary user-facing copy.
- Laravel translation files remain the canonical source of truth for Atlas-owned copy. Frontend views must receive translated text or a Laravel-derived active-locale dictionary and must not reintroduce independent manual PL/EN frontend catalogs.
- Every rebuilt Admin view must define its view contract before implementation: route name, Vue page, backend controller/provider, permission, module gate, active-team behavior, data ownership, primary actions, table/export behavior, dashboard signal behavior, breadcrumb, localization keys, manual review URL, and tests.
- Phase 25 must be implemented sidebar link by sidebar link. For each Admin sidebar entry, rebuild the full linked workflow before moving to the next entry: index/list, create, edit, show/detail, dialogs, row actions, bulk actions, filters, exports, empty/error/loading states, breadcrumbs, permissions, module gates, notifications/toasts, and all related subviews.
- After each sidebar entry is rebuilt, pause for owner browser review and explicit acceptance of that entry before continuing to the next Admin sidebar entry.
- During this phase, every rebuilt Admin sidebar entry must receive deterministic review/demo data that exposes the full practical range of states, options, permissions, module states, edge cases, empty states, validation errors, operational failures, and action paths for that entry.
- After each Admin sidebar entry is accepted, the database must still contain a useful representative dataset for that entry so the owner can keep reviewing the complete cross-section of available options while later entries are rebuilt.
- During Phase 25, all review-only seeders, helper classes, generated data, temporary routes, temporary UI controls, and temporary test harnesses required for owner review must remain available until the owner gives final acceptance for the whole phase.
- After final owner acceptance of Phase 25, perform a cleanup pass that removes all Phase 25 review-only code, seeders, fixtures, generated demo data, temporary routes, temporary UI controls, and temporary test harnesses. The repository must return to the clean accepted state: no review-only code and the development demo seeder empty/no-op unless a separate permanent demo-data scope is explicitly accepted.
- Rebuilt Admin views must compose existing shared primitives first. New primitives may be added only when they remove real duplication and become part of the shared UI layer.
- Rebuilt views must support light/dark themes, keyboard interaction, accessible labels, clean browser console, deterministic query-string state where applicable, and no overlapping text.
- Admin implementation must include meaningful browser-level verification. Backend feature tests alone are insufficient for this phase.

## Scope

Rebuild or replace the current Admin presentation for at least:

- Admin dashboard and operational summary;
- system status, health/readiness, release metadata, scheduler, operational alerts;
- queues and failed job diagnostics;
- managed processes, process details, imports, schedules, and definitions;
- files and file scan operations;
- integrations and sync runs;
- search and rebuild runs;
- feature flags and flag history;
- modules, module activation, and activation history;
- audit browser and security history;
- users, teams, manager hierarchy, roles, permissions, and onboarding packages;
- rate limits;
- notifications center/dropdown where it interacts with Admin operational workflows;
- Admin DataTable export actions and terminal notification behavior.

## Known findings to repair

The following findings must remain in scope and cannot be dropped during rebuild:

- Admin dashboard after demo reset showed most content in English while navigation links were Polish.
- Admin panel contained mixed-language user-facing copy across dashboard, operational cards, backend-provided props, and frontend page text.
- Laravel-owned translations must remain canonical; frontend must not maintain a second manual translation catalog.
- Natural-language keys must not be used for Atlas-owned copy, except Laravel/framework/vendor source-string compatibility entries.
- Export actions created toast storms with managed-process step messages such as `Failed`, `Running`, `Snapshot validated`, `Generating report export`, `Storing generated artifact`, and `Report export generated`.
- Toast bodies exposed raw technical values such as `managed_process`.
- Admin dashboard appeared to show queue failures under a `core Authorization` module/status block, suggesting broken signal attribution.
- Queues and Managed Processes presented overlapping async failure information while one view was poorer and the dashboard kept warning without an operator lifecycle.
- Admin views used large bounded-view notices such as "newest 80 runs", "newest 20 syncs", "newest 50 flag history", and similar explanatory copy instead of good pagination/filter/drill-down UI.
- Admin dashboard and operational screens exposed architecture explanations rather than concise actionable operator state.
- Operational failures had no acknowledge/resolve/reopen lifecycle, so handled problems could keep shouting indefinitely.
- Admin dashboard signal ownership, deduplication, and source deep-linking were unclear.
- Existing automated tests were too weak to catch broken rendered Admin localization, toast storms, and poor operational UX.

## Tasks

- [ ] Make this phase the active Admin repair phase in `WORKROAD.md`.
- [ ] Inventory all current Admin Vue pages, Admin route contracts, controllers/providers, shared primitives, permissions, module gates, breadcrumbs, dashboard signals, tables, exports, notifications, and tests.
- [ ] Identify backend foundations that should be preserved and presentation code that should be deleted or replaced.
- [ ] Clear/remove the current Admin view implementations and rebuild them from scratch on top of accepted backend contracts and shared UI primitives.
- [ ] Reuse accepted shared frontend modules, UI primitives, composables, services, formatters, table/form/dialog/toast infrastructure, layouts, theme system, localization helper, and route helpers while rebuilding each Admin view.
- [ ] Use existing Admin page code only as an inventory source for routes, props, permissions, actions, edge cases, and data ownership; do not reuse it as the rebuilt design baseline.
- [ ] Design the rebuilt Admin information architecture: dashboard, identity/access, organization, oversight, operations, diagnostics, and system configuration groupings.
- [ ] Define the sidebar-link rebuild order and an acceptance checklist that must be completed for each entry before work continues to the next entry.
- [ ] Build deterministic Admin review/demo seed data for each sidebar entry that exposes the full practical range of options, states, permissions, module states, operational failures, empty states, validation paths, and action paths for that entry.
- [ ] For each Admin sidebar entry, rebuild and review the complete workflow before moving on: index/list, create, edit, show/detail, dialogs, filters, row/bulk actions, exports, empty/error/loading states, breadcrumbs, permissions, module gates, toasts/notifications, and subviews.
- [ ] After each sidebar entry, run focused backend/frontend tests and owner browser review, record acceptance, and preserve a representative seeded dataset for that accepted entry before proceeding to the next entry.
- [ ] Define the Admin dashboard signal model, ownership, severity, deduplication, source deep links, and global-versus-owned rendering.
- [ ] Implement operational issue lifecycle persistence and services for open, acknowledged, resolved, reopened, comments/history, source identity, severity, ownership, and timestamps.
- [ ] Add authorization, audit, validation, concurrency handling, and localization for acknowledge, resolve, reopen, comment, and ownership/severity changes.
- [ ] Rebuild Admin dashboard as a concise actionable operational overview using the operational issue model and correctly attributed global/owned signals.
- [ ] Rebuild Queues as low-level transport diagnostics with clear links to managed-process/workflow owners when applicable.
- [ ] Rebuild Managed Processes as the primary Atlas workflow execution view with proper filters, pagination, detail logs, retry/cancel actions, and links to related queue/export/import/file/search records.
- [ ] Rebuild Files, Imports, Search, Integrations, Feature Flags, Modules, Audit/Security, Rate Limits, and Notifications Admin views with proper data ownership, filters, pagination, empty states, actions, exports, and localized copy.
- [ ] Rebuild Users, Teams, Managers, Roles, Permissions, and Onboarding Packages views only as needed to meet the same Admin UX, localization, table, form, validation, and action standards.
- [ ] Remove bounded-view warning blocks and replace them with proper data controls or subtle scoped metadata.
- [ ] Ensure every export action emits at most one immediate request outcome and one terminal notification, with no managed-process toast storm.
- [ ] Ensure notifications, toast viewport, realtime reloads, and Admin dashboard summaries do not duplicate the same operational state.
- [ ] Move all Atlas-owned Admin copy into Laravel-owned translation files and verify PL/EN rendered UI completeness.
- [ ] Add backend feature tests for rebuilt Admin route contracts, Inertia props, authorization, validation, operational issue lifecycle, signal attribution, deduplication, and export message ownership.
- [ ] Add Vitest coverage for Admin UI services/composables, localization helper use, toast/notification behavior, dashboard signal mapping, table state, and shared primitives introduced or changed by this phase.
- [ ] Add Playwright coverage for the rebuilt Admin dashboard, major Admin areas, Polish/English rendered localization, no accidental English user-facing copy in Polish mode, no export toast storm, acknowledge/resolve/reopen, source deep links, light/dark themes, and clean browser console.
- [ ] Run `composer demo:reset`, `pnpm build`, and browser-review every rebuilt Admin area in Polish and English before marking localization complete.
- [ ] Keep all Phase 25 review-only seeders, fixtures, helper classes, generated review data, temporary routes, temporary UI controls, and temporary test harnesses available until the owner gives final acceptance for the whole phase.
- [ ] After final owner acceptance, remove all Phase 25 review-only code, seeders, fixtures, generated review data, temporary routes, temporary UI controls, and temporary test harnesses, returning the repository to a clean state with an empty/no-op development demo seeder unless a separate permanent demo-data scope is explicitly accepted.
- [ ] Update Admin, Notifications, Settings, Operations, Frontend UI, testing, and roadmap documentation.
- [ ] Run relevant quality gates.
- [ ] Commit Admin panel rebuild and operational UX repair.

## Completion criteria

- [ ] Existing poor Admin views have been removed or replaced with coherent rebuilt views.
- [ ] Admin dashboard is a concise actionable operational overview with correct signal ownership, deduplication, source links, and incident lifecycle behavior.
- [ ] Queues and Managed Processes have clear boundaries and no longer present the same async failure as unrelated duplicated errors.
- [ ] Administrators can acknowledge, resolve, and reopen operational issues; handled issues stop warning unless they recur, worsen, or escalate.
- [ ] Admin views no longer rely on large bounded-view disclaimer blocks to compensate for weak data controls.
- [ ] Admin DataTable exports no longer create managed-process toast storms.
- [ ] Admin rendered UI is complete in Polish and English except for allowed technical diagnostic values.
- [ ] Laravel translations remain canonical and no independent frontend PL/EN catalog returns.
- [ ] Rebuilt views are accessible, responsive, theme-complete, keyboard-usable, and free from obvious text overlap.
- [ ] Backend, Vitest, and Playwright regression coverage protects the rebuilt Admin UX and operational behavior.
- [ ] Every Admin sidebar entry was rebuilt as a complete workflow, reviewed in the browser by the owner, and explicitly accepted before moving to the next entry.
- [ ] During owner review, the Phase 25 review dataset shows a full practical cross-section of every rebuilt Admin sidebar entry.
- [ ] After final owner acceptance, all Phase 25 review-only scaffolding, helper classes, generated review data, temporary routes, temporary UI controls, and temporary test harnesses have been removed.
- [ ] After final owner acceptance, the development demo seeder is empty/no-op again unless a separate permanent demo-data scope is explicitly accepted.
- [ ] Canonical documentation is current and Phase 26 can begin safely.
