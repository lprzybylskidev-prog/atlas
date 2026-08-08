# Phase 28 — Foundation repair and consolidation

**Status:** `in progress`

## Objective

Repair and consolidate the known foundation drift discovered after Phases 27 and 27a, before production deployment. Phase 28 is one roadmap phase that closes the known architectural, UI, audit, persistence, seeding, mail, runtime, and guardrail defects listed here. It does not implement debt collection business modules, production deployment, backup scheduling, restore, rollback, or the final release audit.

## Background and reasons for consolidation

Phases 27b, 27c, and the earlier Phase 28 were not started. Their full scopes are merged into this new Phase 28 so the roadmap does not carry several partial foundation-repair phases. Phase 27 and Phase 27a remain completed historical phases. Their completed checkboxes are not rewritten; this phase records later findings that must be fixed before Phase 29 and Phase 30.

Known foundation debt must not be deferred beyond this phase. A later phase may be proposed only for a new accepted debt-collection business feature that is not a repair of an existing foundation, not a known missing module behavior, and not required for the current application to work correctly.

## Dependencies

- [Phase 27 — Optional TimeTracking module](phase-27-time-tracking.md)
- [Phase 27a — Foundation architecture and quality-gate hardening](phase-27a-foundation-architecture-quality-hardening.md)
- [Phase 29 — Production deployment, backup, restore, and rollback](phase-29-deployment-backup-rollback.md), which depends on this phase for a reproducible runtime foundation
- [Phase 30 — Final test audit, full-app E2E review, and foundation verification](phase-30-final-verification.md), which depends on this phase and Phase 29
- [Modular-monolith architecture](../architecture/modular-monolith.md)
- [Module registry and activation](../architecture/module-registry-and-activation.md)
- [Audit, privacy, deletion, and anonymization](../architecture/audit-privacy-and-deletion.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)
- [Tables, reports, exports, charts, and print](../architecture/tables-reports-exports-and-print.md)
- [Data contracts, formatting, validation, errors, and concurrency](../architecture/data-contracts-validation-and-concurrency.md)
- [Security baseline](../architecture/security-baseline.md)
- [Foundation repair target contracts](../architecture/foundation-repair-target-contracts.md)
- All module documents under [Module documentation](../modules/README.md)
- [Development environment](../operations/development-environment.md)
- [Seeding and demo data](../operations/seeding-and-demo-data.md)
- [Quality gates, Git, and commits](../operations/quality-gates-and-git.md)
- [Testing environment](../operations/testing-environment.md)
- [Health, observability, maintenance, and runtime diagnostics](../operations/health-observability-and-maintenance.md)
- [Production deployment, backup, restore, and recovery](../operations/production-deployment-backup-and-recovery.md)

## Non-goals

- Do not implement production HTTPS, certificate issuance, host release routing, backup schedule, retention, encryption, off-host copy, restore verification, or rollback. Those remain Phase 29.
- Do not perform the final whole-application release audit, stable release tagging, or release gate. Those remain Phase 30.
- Do not introduce debt collection business modules.
- Do not keep two parallel implementations with a plan to remove one later.
- Do not mark any implementation task complete until code, tests, documentation, legacy removal, and evidence for that task are complete.

## Implementation rules

- Phase 28 is one phase, executed sequentially by workstreams and closed packages.
- A later prompt must not implement "the whole phase" in one pass. Each implementation prompt covers one closed package.
- Before a package starts, read this phase file, relevant canonical documentation, and evidence from previous Phase 28 packages.
- Each package must finish code, tests, documentation, legacy removal, and quality evidence for its scope.
- Each completed package must leave the repository in a coherent commit-ready state: one logical diff, no unrelated churn, updated evidence, relevant quality results, and a proposed Conventional Commit message.
- After every completed package, report the remaining Phase 28 work as an approximate percentage to the project owner. The percentage is a planning signal based on completed workstreams, issue risk, tests, docs, and legacy-removal state; it is not a substitute for the completion criteria.
- A package is not complete if it leaves old and new implementations running in parallel without an explicit temporary migration boundary tracked in this file.
- Checkbox status must reflect the actual repository state after each package.
- The phase remains `in progress` until all completion criteria are satisfied.
- Passing `composer check` is not enough to close the phase.
- Final phase acceptance requires backend, frontend, container, migration, mail, seeder, runtime, audit, and E2E evidence.

## Mandatory inventories

Phase 28 inventory work is tracked in this file. The temporary scanner at `tools/phase28/generate-inventory.php` can regenerate working evidence while this phase is active, but generated snapshots are not canonical roadmap files. The first baseline and scanner packages have started the inventories, but the checkboxes below remain open until the required matrices are complete and traceable to implementation, tests, documentation, and legacy-removal evidence.

Use `php tools/phase28/generate-inventory.php --prep-report-markdown` for the current consolidated preparation report.

- [ ] **P28-INV-001 Module inventory:** create a matrix row for Identity, Authorization, Teams, Users, Audit, Settings, Notifications, Health, Files, Exports, Privacy, FeatureFlags, Integrations, ManagedProcesses, Imports, Search, Reports, TimeTracking, ModuleRegistry, ModuleGate, module activation, module deactivation guards, Outbox, saved table views, DataTable, Inertia shared-data registry, route availability contributions, breadcrumbs, observability, sensitive-data redaction, data lifecycle, and queue/scheduler foundations. Each row records owner, category, declared required dependencies, declared optional dependencies, actual imports, exported public contracts, consumers, direct foreign-table reads/writes, provider, permission catalogs, routes, commands, jobs, scheduler entries, managed processes, settings, audit events, notifications, health checks, integrations, activation support, frontend entrypoints, user/Admin/manager/CLI/automatic surfaces, migrations, seeders, tests, documentation, dead/duplicated elements, and target decision: keep, repair, move, merge, or remove.
- [ ] **P28-INV-002 Backend surface inventory:** inventory every table, field, index, status, enum, setting, permission, policy, public contract, DTO, queue, job, command, schedule, managed process, notification type, mail, export provider, report, route, controller, and frontend page. Assign exactly one surface: regular user, manager, administrator, CLI/operations, worker/scheduler, or internal-only mechanism. Every item without a surface receives one decision: expose, connect to automation, document as internal-only with diagnostics/tests, or remove.
- [ ] **P28-INV-003 Frontend route/view matrix:** for every Inertia page record route name, URL, Vue page, layout mode, canonical page name, browser title, breadcrumb, sidebar entry, mobile navigation entry, shell subnavigation entry, permission, module gate, active-team behavior, controller/provider, primary user task, primary/secondary/row/bulk actions, create/edit/show/index counterparts, loading/empty/no-results/error/permission-denied/offline states, PL/EN copy, mobile/desktop review, light/dark theme, keyboard and screen-reader behavior, deterministic seeder fixture, Vitest coverage, Playwright coverage, and migration status to the target UI contract.
- [ ] **P28-INV-004 Audit event matrix:** for every material process record owning module, action key, security category, source, success/rejection/failure result, actor, actual actor, impersonated user, target, aggregate, team, correlation, reason requirement, required before/after values, allowed metadata, transaction owner, test, browser visibility, and retention class.
- [ ] **P28-INV-005 Runtime parity matrix:** compare Dev Container workspace, development php-fpm, development worker, development scheduler, PHPUnit, Playwright, production php-fpm, production worker, production scheduler, nginx, PostgreSQL, Redis, Meilisearch, ClamAV, Chromium/PDF, backup container, and manual Ubuntu/Debian installation. For each record PHP version, extensions, Composer dependencies, Node/pnpm version, frontend assets, source, configuration, secrets, queues, timeouts, storage, healthchecks, readiness, logging, shutdown, migrations, cache preparation, required binaries, non-root user, network exposure, and persistent volumes.

### Current inventory evidence

The current Phase 28 scanner reports these working totals:

- modules: 18;
- module PHP files: 662;
- controllers: 79;
- command classes: 14;
- jobs: 1;
- Vue pages: 62;
- migrations: 31;
- seeders: 5;
- public persistence table classes: 14;
- uses of public persistence table classes in `app`: 204;
- raw frontend `fetch(` calls: 8;
- mail signals: 31;
- seeder direct-write signals: 88;
- runtime matrix signals: 183;
- backend surface signals: 410;
- audit matrix signals: 294;
- frontend consistency signals: 1493;
- orphan module roots: 1, currently `app/Modules/Application/Demo`;
- migration table operations: 100;
- migration column signals: 899;
- migration index/unique/primary signals: 193;
- migration foreign-key signals: 103;
- PostgreSQL `after` usages in migrations: 13;
- global provider/middleware/shared module imports: 75.

Current module dependency drift evidence: 11 modules have 30 observed undeclared import edges. The affected modules are Audit, Authorization, FeatureFlags, Files, Identity, Integrations, ManagedProcesses, Privacy, Search, Teams, and Users.

Current backend surface buckets: administrator 186, regular user 54, manager 24, CLI/operations 8, worker/scheduler 45, operations 4, mail 31, mail/realtime 5, seed/test/demo 5, internal application 45, internal route signals 3. Signal types include 195 HTTP routes, 79 controllers, 36 export providers, 14 command classes, 1 job class, 12 queue signals, 3 schedule signals, 31 mail signals, 5 notification classes, 29 managed-process signals, and 5 seeder classes.

Current framework route/view buckets: administrator 137, regular user 33, manager 20, internal 3, operations 2. Static extraction does not infer indirect render helpers, so TimeTracking operation-list helpers and similar patterns still need manual confirmation.

Current persistence evidence: 100 `Schema::create/table` operations, 899 column signals, 193 index/unique/primary signals, 103 foreign-key signals, 13 PostgreSQL `after` usages, 5 unresolved dynamic Spatie permission table operations, and 3 public/vendor Pulse table operations.

Current runtime evidence: package manager, queue/Horizon, scheduler, PostgreSQL/search-path, Redis, Meilisearch, ClamAV, Chromium/PDF, network exposure, secrets, storage, and backup signals across `.env.example`, config files, Dockerfiles, Compose files, package manifests, and Playwright config. Static extraction cannot prove runtime behavior, image buildability, smoke readiness, queue execution, scheduler heartbeat, ClamAV reachability, Chromium/PDF rendering, or PostgreSQL volume durability.

Current audit evidence: 294 static audit signals across 15 owners: 46 recorder-contract consumers, 50 event constructors, 76 `action` arguments, 54 `result` arguments, and 68 `source` arguments. The scanner found 55 dynamic audit values such as `$action`, `$result`, and `$event->source`; these require owner confirmation before they can become typed catalog values or explicit exceptions.

Current frontend evidence: 62 Vue pages exist across auth, regular user, manager, administrator, notifications, teams, and TimeTracking surfaces. The temporary scanner now provides `--frontend-consistency-markdown` as an inspection aid and reports 1493 frontend consistency signals: 49 `AppLayout` usages, 7 `AuthLayout` usages, 62 `<Head>` title bindings, 49 page title icons, 147 DataTable/action-type signals, 47 record/action signals, 29 form-action signals, 86 SurfaceCard signals, 22 large pages, 140 local option-builder signals, 136 local status-label/status-logic signals, 222 navigation registry signals, 434 technical-token column/value signals, and 1 raw `fetch(` signal. These are not all defects one by one; they identify places that Phase 28 must compare manually.

`P28-W01D` manual frontend product consistency baseline is recorded here so this does not get lost outside the phase file. Static scanner output is not enough to close frontend prep because Phase 28 frontend consistency is a product review problem, not only a code-shape problem. The review must compare naming, placement, layout parity, create/edit/show/list parity, field presence, action placement, icon usage, sidebar/topbar/subnavigation logic, breadcrumbs, title/browser-title alignment, status labels, table/filter behavior, empty/error/offline states, light/dark/mobile behavior, and whether similar workflows expose the same controls in the same way.

The frontend baseline found these repair groups:

- Separate Admin Managers pages/routes/breadcrumbs/navigation still exist under `Admin/Managers/*` and `/admin/managers`, while the target contract says manager hierarchy belongs inside Teams. This affects `P28-AUTH-001`, `P28-AUTH-002`, `P28-NAV-*`, `P28-FORM-*`, and legacy-removal evidence.
- Desktop and mobile navigation are not obviously parity-safe: desktop sidebar already has route-backed manager work-time groups, while mobile still exposes the legacy `/time-tracking/manager-report` route path. Navigation labels, active-state rules, route permissions, sidebar groups, mobile groups, and shell subnavigation need one registry-backed contract.
- TimeTracking has very large user/manager/admin pages and partially shared controller/view patterns. `UserReport`, `ManagerReport`, `AdminOperations`, and admin detail pages need side-by-side product comparison for tab names, filters, columns, action availability, dialogs, status labels, source labels, detail links, empty/error states, and manager/admin scope differences.
- User profile is an overloaded page that combines profile, avatar, password, MFA, additional notification emails, and notification preferences. MFA still uses raw `fetch` for QR/recovery-code retrieval. The repair target is a coherent profile/security/password/MFA/email/preferences workflow with shared network, loading, error, high-risk action, and secure recovery-code handling.
- Create/edit/show/list parity is uneven across Users, Teams, Roles, Packages, Modules, Managers, ManagedProcesses, Privacy, Files, Integrations, Search, Queues, FeatureFlags, and TimeTracking. Similar resources need the same structure for titles, breadcrumbs, icon choice, primary/secondary/back/cancel/save actions, dirty-state handling, object summaries, status badges, filters, and row actions unless a view contract documents the difference.
- Table and status presentation is fragmented: many pages define local columns, actions, options, status labels, hidden technical columns, and fallback labels. The repair target is one DataTable/action/status/filter/state contract with role-aware safe columns and no accidental raw tokens in regular or manager UI.
- Breadcrumb, page-title, browser-title, navigation-label, and title-icon parity must be verified as a product rule, not merely by checking that `<Head>` and `:title-icon` exist. The final breadcrumb/link label, browser title, visible page title, active navigation entry, and icon must line up for each user, manager, and administrator surface.
- UI review during implementation must include browser-rendered light/dark/mobile checks and Playwright/console-clean coverage for changed critical workflows. Prep records the source-level baseline; visual acceptance belongs to the relevant frontend repair packages.

### Prep closure for code changes

Phase 28 preparation is complete as of the `P28-W01D` entry in the evidence log. Automated backend/runtime/audit inventories are repeatable, and the manual frontend product consistency baseline is recorded in this single phase file. This preparation does not complete any repair work.

Preparation readiness: `100%`. Automated backend/runtime/audit inventory readiness: `ready`. Frontend product consistency review: `recorded`. Foundation repair implementation completion: `0%`.

The first real code package after prep is `P28-W02A`:

- strengthen the existing architecture test foundation around `CrossModuleArchitectureTest`, `PublicQueryContractArchitectureTest`, `ModuleRegistryTest`, and related foundation tests;
- make the module graph check non-vacuous against real deployed modules, declared dependencies, actual imports, cycles, global provider/middleware imports, and public persistence table-constant usage;
- record known current violations as explicit failing or quarantined Phase 28 expectations before replacing implementations;
- avoid broad module rewrites until the guard proves it can see the real dependency drift.

The next implementation packages after `P28-W02A` are:

1. `P28-W02B` neutral owner-owned lookup/display contracts for Identity, Teams, and Audit labels.
2. `P28-W02C` first cross-module SQL/table-constant removal slice, starting with low-risk read-only display paths.
3. `P28-W03A` ModuleRegistry and ModuleGate metadata validation once the graph guard is reliable.

## Workstream dependency order

1. **P28-W01 — Inventory and traceability**
2. **P28-W02 — Module graph and public contract boundaries**
3. **P28-W03 — ModuleRegistry, ModuleGate, activation, and technical availability**
4. **P28-W04 — Audit coverage, contracts, atomicity, and enforcement**
5. **P28-W05 — Unified Atlas UI/UX contract and glossary**
6. **P28-W06 — Navigation, layout, and mobile/desktop parity**
7. **P28-W07 — Actions, forms, CRUDs, tables, and shared states**
8. **P28-W08 — Authorization, Teams, and integrated manager structure**
9. **P28-W09 — Existing-view migration to the target UI contract**
10. **P28-W10 — TimeTracking and remaining module surfaces**
11. **P28-W11 — Migrations, PostgreSQL, and data ownership**
12. **P28-W12 — Seeders and deterministic test/demo data**
13. **P28-W13 — Bilingual mail**
14. **P28-W14 — Development, production image, and smoke-stack runtime**
15. **P28-W15 — Queues, scheduler, Horizon, Files, ClamAV, Chromium, Search, and managed processes**
16. **P28-W16 — Tests, guardrails, Playwright, and legacy cleanup**
17. **P28-W17 — Final Phase 28 closure**

## Full issue register

Each issue below is mandatory. The `Task`, `Test`, `Guardrail`, and `Done` columns are binding implementation requirements, not examples.

| ID | Current state | Target state | Scope | Task | Test | Guardrail | Done |
| --- | --- | --- | --- | --- | --- | --- | --- |
| P28-ARCH-001 | `AuditModule`, Identity, Authorization, and Teams declare dependencies that do not match imports and create an unclear Identity-Audit cycle. | Acyclic explicit graph with neutral contracts where mutual collaboration is required. | Identity, Audit, Authorization, Teams. | Move/rename contracts so dependency direction is honest and update `ModuleDefinition`. | Architecture test compares imports to declarations and detects cycles. | New module imports fail when undeclared or cyclic. | Graph is acyclic and declarations match code. |
| P28-ARCH-002 | Audit registers export providers based on Exports public classes while Exports declares Audit required, hiding Audit-Exports coupling. | Audit and Exports dependency direction is explicit or mediated by neutral shared contract. | Audit, Exports. | Redesign provider registration and dependency metadata. | Registry/import tests cover Audit/Exports combinations. | Hidden dependency test includes provider registrations. | Audit and Exports start in valid configured modes. |
| P28-ARCH-003 | Core Exports and Core Privacy require Optional ManagedProcesses, weakening Optional meaning. | Core does not require Optional unless classification is changed by ADR; required shared capability is Core/Shared. | Exports, Privacy, ManagedProcesses. | Remove Core-to-Optional dependency or reclassify with documented decision. | Startup tests cover optional module absent. | Core-to-Optional dependency guard. | Core modules boot without undeployed Optional modules or ADR documents reclassification. |
| P28-ARCH-004 | Multiple modules expose table-name classes as pseudo public APIs for foreign SQL. | Owner-owned capability contracts replace foreign table reads/writes. | All modules. | Remove cross-module use of `Application/Public/Persistence/*DatabaseTable`. | Static test detects foreign table constants and schema-qualified direct SQL. | Public table constants are forbidden across module boundaries. | Foreign SQL is removed or only internal to owner/migration/framework exceptions. |
| P28-ARCH-005 | Audit browser joins audit events to Teams. | Audit browser uses owner-owned display label providers. | Audit, Teams. | Replace joins with Team summary contract. | Browser query tests assert no Teams table access from Audit. | Cross-module SQL guard. | Audit browser resolves team labels without direct Teams SQL. |
| P28-ARCH-006 | Audit security history and export providers read Identity users directly. | Audit uses Identity-owned user summary/display contracts. | Audit, Identity. | Replace direct reads with public query contract. | Audit history/export tests with contract fakes. | Cross-module SQL guard. | Audit has no direct Identity SQL. |
| P28-ARCH-007 | TimeTrackingAudit, reports, and controllers read Identity and Teams. | TimeTracking uses owner-owned user/team read contracts. | TimeTracking, Identity, Teams. | Replace reads with typed lookup/query contracts. | TimeTracking feature/report tests verify same output and no foreign SQL. | Cross-module SQL guard. | TimeTracking no longer reads Identity/Teams tables. |
| P28-ARCH-008 | Files storage/controllers read Identity and Teams. | Files uses public user/team summaries and active-team contracts. | Files, Identity, Teams. | Replace direct reads with owner contracts. | Files upload/browser tests cover labels and authorization. | Cross-module SQL guard. | Files has no direct Identity/Teams SQL. |
| P28-ARCH-009 | Privacy legal hold and controllers read Identity and Teams. | Privacy uses owner-owned subject/team contracts. | Privacy, Identity, Teams. | Replace direct reads with public contracts. | Privacy preview/legal-hold tests cover missing subjects and labels. | Cross-module SQL guard. | Privacy has no direct Identity/Teams SQL. |
| P28-ARCH-010 | ManagedProcesses reads Identity, Teams, Files, and Imports. | ManagedProcesses uses public process-subject, file, and import read models/contracts. | ManagedProcesses, Identity, Teams, Files, Imports. | Replace foreign queries with capability-specific contracts. | Process browser/tests cover imported/file-backed runs without foreign SQL. | Cross-module SQL guard. | ManagedProcesses has no direct foreign SQL. |
| P28-ARCH-011 | EffectivePermissionChecker, Authorization stores/previewers, onboarding store, lifecycle participant, and Authorization membership mutation read or mutate Identity/Teams directly. | Authorization uses owner-owned subject resolution, team lookup, membership command, and lifecycle contracts. | Authorization, Identity, Teams. | Extract/consume small typed contracts and move membership mutation to owner. | Permission, assignment, lifecycle, preview tests verify behavior. | Cross-module SQL/write guard. | Authorization no longer directly queries/mutates foreign tables. |
| P28-ARCH-012 | Shared RegistryModuleGateStateProvider and DatabaseModuleActivationService read Teams. | Shared module gate and activation use Teams-owned active-team/team state contracts. | Shared, Teams. | Replace direct Teams access with shared-safe contracts. | ModuleGate tests cover active team and activation states. | Shared-to-module SQL guard. | Shared foundation does not query Teams tables. |
| P28-ARCH-013 | Global `AppServiceProvider` and global middleware can depend on module internals. | Global composition knows only public contracts, shared contracts, and explicit Presentation contributions. | Global providers, middleware, all modules. | Move request-aware contracts to Presentation and remove internal imports. | Architecture tests scan providers/middleware. | Global internal-import guard. | No global provider/middleware imports module internals, Infrastructure, Eloquent, or foreign tables. |
| P28-ARCH-014 | Architecture guard scans only simple `use` patterns. | Guard scans full PHP syntax and relevant config/migration references. | Architecture tests. | Implement AST/static analysis for use, grouped use, FQCN, new, extends, implements, types, attributes, static calls, `::class`, class-string, config, migrations, providers, middleware, table names/constants. | Mutation fixtures prove every rule catches violations. | Non-vacuous scan asserts roots, file count, inputs, and automatic module discovery. | Guard catches declared/actual dependency drift and direct SQL reliably. |
| P28-MOD-001 | `ModuleRegistry` validates declared required dependencies only. | Registry validates actual graph, optional dependency behavior, startup order, Core/Optional policy, and deployed-module variants. | ModuleRegistry. | Extend registry validation and module metadata checks. | Startup tests with different deployed module sets. | Optional dependency unsafe-use guard. | Registry rejects invalid graph and supports safe reduced modes. |
| P28-MOD-002 | ModuleGate treats required dependencies as satisfied when deployed. | Gate checks existence, technical availability, activation state, denial reason, and cycle safety for each dependency. | ModuleGate, activation. | Implement dependency-state evaluation. | Tests for missing, inactive, degraded dependencies. | Denial reason coverage guard. | Gate reports specific dependency denial without recursion. |
| P28-MOD-003 | `technicallyAvailable` effectively equals deployed. | Technical availability reads real health, integrations, binaries, services, queues, storage, configuration, and credentials. | ModuleGate, Health, all modules. | Implement technical requirement providers. | Availability tests for each degraded dependency type. | Secret-safe degraded metadata guard. | UI/API distinguish unavailable from disabled. |
| P28-MOD-004 | Module metadata can be stale decoration. | Metadata is executable/validated or removed. | All `ModuleDefinition`s. | Fix dependencies, integrations, health checks, frontend entrypoints, activation support for TimeTracking, Reports, Imports, Health, and integration-aware modules. | Metadata-to-registration tests. | Stale metadata guard. | Metadata reflects real registrations or no stale metadata remains. |
| P28-MOD-005 | Deactivation guards are incomplete for reverse dependencies and in-flight work. | Deactivation checks reverse dependencies, jobs, process runs, schedules, exports, imports, file scans, search indexing, TimeTracking sessions, and privacy operations. | Activation, all process-owning modules. | Implement owner guards and audit attempts/rejections/success. | Deactivation tests per blocker. | Guard registry coverage test. | Unsafe deactivation is blocked with audited human reasons. |
| P28-AUDIT-001 | Audit uses loose strings for module/action/result/source/target/aggregate. | Each module registers an audit action catalog with typed allowed values and metadata rules. | Audit, all mutating modules. | Create catalog contract and migrate producers. | Catalog validation tests. | Hardcoded audit-string guard. | No uncataloged audit actions/results/sources remain. |
| P28-AUDIT-002 | Results have parallel meanings such as `success`, `succeeded`, `blocked`, `rejected`, `failed`, `partial`. | Results are canonical and explicitly defined. | Audit. | Normalize result enum/catalog and migrate producers/tests. | Result compatibility tests. | Unknown result guard. | Every audit result is one cataloged value. |
| P28-AUDIT-003 | Mutating/security/irreversible operations lack complete coverage matrix. | Success, rejection, failure, missing-object security attempts, unauthorized attempts, invalid confirmation, stale writes, and invariant rejections are audited where required. | Admin, security, permissions, membership, hierarchy, modules, settings, flags, Files, Privacy, Integrations, ManagedProcesses, Imports, Search, Exports, TimeTracking. | Build and satisfy audit event matrix. | Per-action success/rejected/failed tests. | Required-operation coverage guard. | Every required operation has cataloged evidence. |
| P28-AUDIT-004 | Integrations connection test lacks audit. | Connection tests audit success/rejection/failure safely. | Integrations. | Add catalog action and producer. | Feature tests for outcomes. | Audit coverage guard. | Test connection evidence exists. |
| P28-AUDIT-005 | TimeTracking `decideOtherWork`, `before` values, security category control, and Identity/Teams SQL are incomplete. | TimeTracking audit covers all decisions with before/after, explicit category, reason, actor context, and no foreign SQL. | TimeTracking, Audit. | Refactor TimeTrackingAudit and producers. | TimeTracking audit tests. | Audit catalog and SQL guards. | TimeTracking audit rows are complete and boundary-safe. |
| P28-AUDIT-006 | Files lacks audit for `markScanFailed()` and consistent rejected rescan/delete. | Files scan failure, rejected rescan, rejected delete, and terminal operations are audited. | Files. | Add Files audit actions. | Files operation tests for success/rejected/failed. | Coverage guard. | Files evidence exists for all required outcomes. |
| P28-AUDIT-007 | Privacy rejection/failure audit is incomplete. | Privacy audits rejected anonymization, failed retention copy/export, missing operation, bad confirmation phrase, mismatched operation type, stale/non-executable state. | Privacy, Files, Exports. | Add Privacy audit producers and typed outcomes. | Privacy feature/integration tests. | Coverage guard. | Privacy high-risk attempts produce complete evidence. |
| P28-AUDIT-008 | Basic `actorPublicId` is not automatically completed. | Audit context fills actor, actual actor, impersonated actor, session, and correlation consistently. | Audit context providers. | Centralize actor context completion. | HTTP, CLI, queue tests. | Missing actor/correlation guard. | Required actor fields are present or explicitly system-scoped. |
| P28-AUDIT-009 | Atomicity between `audit_events`, `audit_security_events`, and business changes is not guaranteed everywhere. | Security event pairs and critical business changes share a transaction; mandatory audit failure blocks critical change. | Audit and all critical producers. | Implement transaction contracts and retry/idempotency rules. | Transaction rollback tests. | Mandatory audit atomicity guard. | No half-written security event or unaudited critical commit. |
| P28-AUDIT-010 | Audit browser loads up to 5000 records into memory and hides older records. | DB-backed read model with DB filtering/sorting/pagination, stable `occurred_at + id`, indexes, export path, and owner-owned labels. | Audit Admin browser/export. | Replace array processing. | Browser/export pagination tests. | Large dataset regression test. | Older records remain reachable without foreign joins. |
| P28-AUDIT-011 | Audit producer documentation is stale. | Docs list current producers, catalogs, atomicity, and required tests. | Audit docs. | Update architecture/module docs. | Documentation link check. | Doc checklist in phase closure. | Docs match implemented audit contract. |
| P28-UI-001 | Names are inconsistent: dashboard/panel, users/accounts, identity/account status, team access, authorization reason/save/delete reason, permissions/direct permissions, Email/E-mail/address, Manager/menedżer, status/state/activation, Public ID variants, work-time names, managed processes. | One PL/EN glossary drives menus, breadcrumbs, titles, forms, tables, messages, mails, docs, and tests. | Whole UI/docs/mail. | Create glossary and migrate labels. | Glossary label tests and no-legacy-synonym tests. | Canonical label guard. | Listed synonyms are removed or documented as technical internals. |
| P28-UI-002 | Polish UI exposes technical terms: Dry-run, Hard delete, TimeTracking, Feed, Offline, unclear auto-acceptance, current/previous ambiguity, Guard, Boolean, Security, Correlation ID, Core, Runtime, Storage, Scheduler, readiness, circuit breaker. | User/manager UI uses human language; Admin sees technical identifiers only when operationally needed. | UI copy. | Copy audit and translation rewrite. | Rendered PL/EN tests. | Technical-token exposure guard. | No forbidden raw technical tokens on user/manager surfaces. |
| P28-UI-003 | Defective texts remain: "Pierwsze hasło oczekuje", "MFA niepotwierdzone", "Wszystkie liczby", "Drabinka", "Próba usunięcia roli została wykonana", "Obniżona sprawność". | Text is natural, localized, and not humanized from tokens. | UI/notifications/audit messages. | Replace copy and audit all views. | Missing translation and rendered copy tests. | No humanized-token fallback guard. | Listed phrases are gone. |
| P28-UI-004 | Edit/detail titles and page contexts are generic or inconsistent, including edit user/team/role/package, repeated headings, raw `moduleKey`, and breadcrumb/sidebar/mobile/title/Head drift. | Object pages name the object and all page context uses the same canonical label. | CRUD and module pages. | Migrate page contracts. | Route-view matrix tests. | Page title/breadcrumb/navigation parity guard. | Every object page identifies its object. |
| P28-UI-005 | Regular and manager dashboards risk being filled as "empty state fixes". | Main regular-user and manager dashboards remain intentionally empty until a product decision. | User and manager dashboards. | Document and test intentional empty dashboards. | Playwright asserts no artificial cards/placeholders. | Dashboard-content guard. | Dashboards stay intentionally blank except shell context. |
| P28-UI-006 | Multiple layout/navigation sources and passive wrappers cause desktop/mobile drift. | One `AppLayout` mode and one typed navigation registry feed sidebar, mobile, TopBar, subnavigation, breadcrumbs, route availability, active state, permission, module gate, and mode. | Shell/navigation. | Remove pass-through layout wrappers and duplicate link configs. | Desktop/mobile navigation parity tests. | Single navigation registry guard. | Navigation links, names, and states match across shells. |
| P28-UI-007 | Mobile navigation has stale TimeTracking links, old manager report link, missing active states, all groups expanded, inconsistent collapse, missing subnav below `lg`, no mobile section switch, duplicate configs, local tabs, and old routes. | Mobile drawer and subnavigation are accessible, route-backed, parity-safe, focus-trapped, Escape-closeable, scrollable, labeled, and restore focus. | Mobile shell, TimeTracking. | Rebuild mobile navigation/subnav from registry. | Playwright keyboard/focus/mobile tests. | Responsive navigation guard. | Mobile and desktop expose the same accepted routes. |
| P28-UI-008 | Admin mode context is not persistently obvious. | Shell always shows admin mode, impersonation, active team, offline state, and high-risk requirement when relevant. | Shell/security context. | Add unified context indicators. | Playwright tests for modes. | Security-context visual guard. | User always knows active security context. |
| P28-ACTION-001 | `RecordActions.vue` and `DataTableAction` are parallel action systems. | One typed action contract covers stable key, label, icon, tone, placement, href/endpoint, method, navigation mode, permission, module gate, availability, disabled reason, confirmation, reason, optimistic behavior, loading, feedback, refresh, and row/detail/edit/bulk availability. | Tables, CRUD, dialogs. | Replace parallel systems. | Component and integration tests. | Single action-system guard. | Only canonical action system remains. |
| P28-ACTION-002 | List/edit/detail actions lack parity across Teams, Roles, Packages, Users, and Modules; locations and icons vary. | One action definition feeds list/detail/edit with primary action plus accessible overflow. | Admin CRUD. | Migrate actions and align placements/icons. | List/show/edit parity tests. | Action parity guard. | Comparable resources expose comparable actions. |
| P28-ACTION-003 | Destructive/deactivate/retry/rescan/acknowledge semantics and confirmations are inconsistent; Teams may show Activate and Deactivate together; deactivate uses delete key/trash. | Delete, deactivate, archive, revoke, retry, rescan, acknowledge have distinct semantics, icons, confirmation labels, hidden/disabled rules, and reason handling. | Action contract. | Migrate semantic action catalog and confirmations. | Confirmation rendered tests. | Dangerous-action semantic guard. | Confirm button names the exact operation and unavailable actions behave consistently. |
| P28-FORM-001 | User/Team create/edit, Roles, Packages, status placement, action placement, breakpoints, colors, icons, and object titles are inconsistent. | One CRUD contract defines index/create/show/edit/actions/status/audit/breadcrumb/form/primary/secondary behavior. | CRUD pages. | Build shared CRUD/form contract and migrate resources. | Create/edit/show parity tests. | CRUD contract guard. | Similar CRUDs share structure unless documented. |
| P28-FORM-002 | `Wróć` and `Anuluj` are mixed with inconsistent Save order. | `Anuluj` abandons edits, `Wróć` navigates outside forms, dirty changes warn consistently, and primary/secondary action order is stable on pages/dialogs/mobile. | Forms/dialogs. | Standardize form footer and dirty navigation. | Form behavior tests. | Cancel/back semantics guard. | Save/cancel/back behavior is uniform. |
| P28-FORM-003 | Some pages do not make save scope clear, especially Team Edit, User Edit, user-team assignments, and authorization assignment. | Single transaction uses one Save; independent sections are named workflows with own dirty/loading/success/failure. | User/Team/Authorization forms. | Split or unify save scopes. | Dirty-state and save-scope tests. | Ambiguous save guard. | User understands exactly what each Save writes. |
| P28-FORM-004 | User profile is overloaded and MFA sits under password-like surface. | Profile is split into profile, security, password, MFA, avatar, additional emails, and notification preferences. | User profile/security. | Create route-backed or coherent sections. | Playwright profile/security workflow tests. | Profile contract guard. | MFA belongs to security and profile workflows are clear. |
| P28-FORM-005 | MFA uses raw fetch for QR/recovery codes, lacks loading/error states, has inconsistent confirmation/high-risk flow, silent errors, and unsafe recovery-code regeneration/display. | MFA uses shared network/high-risk/dialog patterns with explicit loading, errors, secure recovery-code presentation/regeneration. | Identity MFA. | Refactor MFA workflows. | Feature, Vitest, Playwright tests. | Raw fetch/error-state guard. | MFA failure/success paths are visible and safe. |
| P28-FORM-006 | Forgot/reset password flow lacks complete visible screens and bilingual mail coverage. | Login has "forgot password", GET initiation screen, neutral send response, rate limit, bilingual mail, reset screen, invalid/expired token, success state, PL/EN tests, and Playwright. | Identity auth. | Complete reset-password workflow. | Feature/mail/Playwright tests. | Auth flow coverage guard. | Reset flow works safely in PL and EN. |
| P28-AUTH-001 | Separate Admin "Managers" area duplicates hierarchy workflows. | Manager hierarchy is managed only inside Teams; manager panel/scope remain. | Teams, Admin navigation/routes/pages/controllers/translations/permissions/tests/seeders. | Remove sidebar/mobile entries, routes, breadcrumbs, pages, controllers, forms, duplicated workflows, exclusive permissions, fixtures, tests, dead code. | Route absence and Teams structure tests. | Legacy Managers area reference guard. | No separate Managers CRUD remains. |
| P28-AUTH-002 | Team structure editing is not the canonical surface for manager hierarchy. | Team Structure Editor supports members, add/remove, active/history membership, head manager, manager/subordinate/direct reports/subtree, moves, effective dates, reason, audit preview, DAG/self/membership validation, last-head-manager protection, active-process blocker, optimistic concurrency, tree visualization, mobile, empty state, and tests. | Teams/manager hierarchy. | Build integrated editor. | DAG, scope, permission, audit, mobile tests. | Teams structure editor coverage guard. | Hierarchy is managed in team context only. |
| P28-AUTH-003 | User/team authorization workflows are duplicated across TeamMemberAccessWorkflow, UserTeamAccessWorkflow, AuthorizationAssignmentPreview, OnboardingPackageForm preview, and local role/permission/limit copies. | One component and one backend use case with explicit mode work from user side and team side. | Authorization, Users, Teams. | Consolidate workflow and previews. | User-side/team-side parity tests. | Duplicate workflow guard. | One canonical assignment workflow remains. |
| P28-AUTH-004 | Assignment provenance is incomplete and edits can pretend source is manual. | Persist source type manual/preset/copy, source public ID, display-name snapshot, copied-from user, preset version/snapshot, applied by/at, reason, resulting roles/direct permissions/limits, and explicit divergence rules. | Authorization assignments. | Add provenance model/use case/UI. | Provenance tests for manual/preset/copy/edit. | Provenance-required guard. | Every assignment has truthful provenance. |
| P28-AUTH-005 | Repeating assignment entries in User Edit/Team Edit do not have consistent expansion, summaries, focus, error, or save behavior. | Single entry expands; multiple entries expand first; others collapse with summary; keyboard/focus works; invalid entry opens; save preserves continuation. | User/Team authorization UI. | Implement shared repeatable-section behavior. | Component and Playwright tests. | Repeatable workflow guard. | Expansion behavior is predictable and accessible. |
| P28-TABLE-001 | Manual tables recreate sorting, empty states, or actions in Integrations, Search, Queues, Files, TimeTracking, Manager reports, ManagedProcesses, and other screens. | Shared DataTable is used for normal tabular data; specialized views still use shared states/actions/responsiveness. | Frontend tables. | Inventory and migrate manual tables. | DataTable rendered tests per migrated surface. | Manual-table duplication guard. | Normal tables use the shared contract. |
| P28-TABLE-002 | DataTable is a god-component. | Query state, filters, columns, saved views, sorting, pagination, selection, row/bulk/export actions, states, responsive rendering, formatting, and permissions are decomposed. | DataTable foundation. | Split DataTable internals. | Unit/component tests. | Component size/responsibility guard. | DataTable remains composed from focused units. |
| P28-TABLE-003 | Saved views may leak Admin endpoint behavior to regular/manager tables and Notifications. | Saved views are enabled per table and disabled by default outside accepted Admin surfaces until a user-facing contract exists. | DataTable saved views. | Gate saved-view capability per table/surface. | Role/surface tests. | Saved-view surface guard. | Non-Admin tables cannot use Admin saved-view endpoints. |
| P28-TABLE-004 | Column visibility lacks visible/hidden/allowed/forbidden semantics. | Forbidden columns never appear in menus; regular/manager users cannot enable internal IDs, non-user-facing public IDs, raw enums, deep links, session IDs, technical event names, DB values, or precise diagnostics. | Tables. | Add role-aware column contract. | Column-menu tests by role. | Forbidden-column guard. | Sensitive technical columns are inaccessible. |
| P28-TABLE-005 | Tables prioritize public IDs or technical values in Users, Teams, Roles, Packages, Permissions, Modules, Files, Integrations. | First user-facing column is human name/business value; public ID first only on justified technical Admin surfaces. | Admin/user tables. | Reorder columns and labels. | Rendered column-order tests. | First-column guard. | Listed tables use proper first column. |
| P28-TABLE-006 | Filters have inconsistent layouts, labels, apply/clear positions, active states, single-filter clearing, and no-results behavior. | One responsive filter layout with canonical labels, active-filter chips, clear-one, clear-all, and no-results state. | Tables/filter panels. | Implement/migrate shared filters. | Filter interaction tests. | Filter layout guard. | Filter UX is consistent. |
| P28-TABLE-007 | Empty/loading/error states are inconsistent through `UiState`, gray text, empty table rows, local alerts, or silence. | Shared state contract covers initial/refresh loading, empty dataset, no results, recoverable/fatal error, permission denied, module unavailable, offline, and stale data with human text. | UI state components. | Consolidate state components. | State rendering tests. | Duplicate state presentation guard. | Technical phrases like "Brak rekordów dla bieżącego stanu tabeli" are gone. |
| P28-TABLE-008 | Status rendering is fragmented across StatusBadge, spans, plain text, Tak/Nie, humanized tokens, enabled/disabled, active/inactive, healthy/degraded, succeeded/failed/rejected. | Central status/token catalog defines stable key, PL/EN label, semantic meaning, color, icon, accessibility text, and allowed surfaces. | Statuses across UI. | Create catalog and migrate statuses. | Status catalog tests. | No local status map guard. | Statuses render only through catalog; boolean Tak/Nie only for real logical questions. |
| P28-TABLE-009 | Specific column/status defects remain: hidden package status despite filter, Guard inconsistency, moduleKey titles, previous/new enabled rendering drift, Permissions public ID first, unknown ineffective reason raw code, Files raw scanState/public ID, FeatureFlags local maps, User Edit lifecycle Tak/Nie. | Each defect is fixed through table/status/glossary contracts. | Listed tables/pages. | Migrate each listed defect. | Regression tests per listed defect. | Technical-token/status guard. | Listed defects are absent. |
| P28-LOC-001 | Formatters default to mixed `en`/`pl` when locale is not passed. | Dates, datetimes, time, money, number, percent, filesize, durations, exported values, mail values, print/PDF values receive effective locale from one source. | Frontend/backend/export/mail/print formatters. | Centralize locale source. | Formatter tests in PL/EN. | Formatter-without-locale guard. | Formatting is locale-consistent. |
| P28-LOC-002 | Missing translations fall back to humanized raw English-like tokens; hardcoded accessibility copy and Error View local dictionary exist. | Missing regular/manager translations fail tests; Admin diagnostics may show explicitly marked technical keys; accessibility copy uses translation keys; Error View uses canonical localization. | Localization. | Remove fallback/local dictionaries and hardcoded copy (`Section navigation`, `Saved table view`, `Authentication form`). | Missing-translation tests. | No local translation dictionary guard. | No accidental English copy in Polish UI except approved diagnostics. |
| P28-TT-001 | TimeTracking has legacy manager report, new manager routes, local tabs, shell subnav, duplicate sidebar links. | One route-backed model for user work-time, manager work-time, and Admin work-time. | TimeTracking navigation. | Remove legacy routes/pages and duplicate navigation. | Navigation parity tests. | Legacy route guard. | Only accepted TimeTracking route model remains. |
| P28-TT-002 | `UserReport` mixes types, status mappings, formatting, actions, filters, tables, tabs, and dialogs. | Logic is split into typed composables, status catalog, action contract, DataTable, formatters, and workflow components. | TimeTracking frontend. | Refactor report component. | Vitest/component tests. | God-component guard. | `UserReport` responsibilities are decomposed. |
| P28-TT-003 | Manager and Admin TimeTracking views lack parity. | Analogous views share names, section order, filters, statuses, detail views, dialogs; differences come only from scope/permission. | TimeTracking manager/Admin. | Align views/contracts. | Manager/Admin parity tests. | View parity guard. | Differences are documented and permission/scope-based. |
| P28-TT-004 | TimeTracking audit/transactions are incomplete for corrections, decisions, manual entries, session endings, force close, break conversion, other work, category changes, and maintenance. | Every listed operation has event, before/after, reason, actor context, and transaction atomicity. | TimeTracking backend. | Add complete audit and transaction boundaries. | Operation tests success/rejected/failed. | Audit coverage guard. | TimeTracking operation evidence is complete. |
| P28-MIG-001 | Pre-production migrations contain create-plus-follow-up history for tables not yet deployed. | Canonical create migrations define final schema with columns, indexes, FKs, checks, triggers, constraints, rollback where contracted, and no local-only historical alters. | All migrations. | Inventory migrations by module; squash create/alter chains. | `migrate:fresh` and rollback/recreate tests. | Pre-deployment migration-squash guard. | Fresh PostgreSQL migration creates final schema. |
| P28-MIG-002 | Migrations may use PostgreSQL `after`, unqualified names, `search_path`, or `public` for Atlas tables. | No `after`; schema-qualified names; application does not rely on `search_path`; `public` only for documented framework/package exceptions. | Migrations, config, app SQL. | Remove `after`, check `DB_SEARCH_PATH`, schema-qualify names. | Migration/schema guard tests. | Guard blocks `after`, unqualified Atlas table, search-path reliance, and unauthorized public table. | Schema ownership rules hold. |
| P28-MIG-003 | Schema details need verification for Spatie Permission, Fortify/session tables, Audit triggers, partial unique indexes, TimeTracking constraints, Privacy/Files retention relationships. | Canonical migrations contain correct ownership and constraints. | Listed persistence areas. | Verify and repair in canonical creates. | Integration tests for constraints/indexes/triggers. | Schema invariant guard. | Listed persistence areas pass fresh tests. |
| P28-SEED-001 | Phase 27b seeder contract is not implemented; seeders can bypass invariants through `forceFill`, direct membership/manager/role/permission/TimeTracking/ManagedProcesses/Imports writes, and mixed raw SQL/contracts. | Seeders are deterministic, idempotent, invariant-preserving, non-production, and use public Application contracts or owner fixture builders. | DevelopmentBootstrapSeeder, DevelopmentDemoSeeder, E2eVisibilitySeeder, other seeders. | Audit and repair seeders; extract user helpers and fixture builders. | Repeated seeder/idempotency/invariant tests. | Production demo reset and fixture-builder registration guard. | Seeders preserve public IDs, password lifecycle, first-password, email verification, active state, sensitivity, avatar/MFA/session defaults, membership validity, DAG/head manager, permissions, provenance, activation, preferences, Files quarantine/scan, TimeTracking, managed-process/import consistency, and audit behavior. |
| P28-MAIL-001 | Phase 27c bilingual mail contract is not implemented. | Every Atlas-owned email uses Laravel translation keys, one branded template, PL+EN in one mail, effective locale first, same structure, plain-text fallback, no hardcoded copy/secrets/raw tokens/internal IDs/user diagnostics, PL-first and EN-first tests. | All mail paths. | Inventory and convert verification, first-password, password reset, lockout, suspicious login, notification-address verification, in-app notification email delivery, export/report completion, operational alerts, Files alerts, TimeTracking notifications, every Mailable, `toMail`, `Mail::raw`, content callback, queued mail. | Mail rendering tests per path in PL/EN. | Hardcoded mail copy scanner for subject/line/action/greeting/salutation/body/raw HTML/raw text/callback. | All Atlas-owned mail follows bilingual template. |
| P28-MAIL-002 | Effective mail locale is not a fully documented deterministic selector. | Locale order is user locale, team locale for team-scoped mail when user missing locale, `app.locale`, then `app.fallback_locale` only as technical fallback. | Mail delivery. | Implement selector and docs. | Locale-order tests. | Mail locale guard. | Mail ordering is deterministic. |
| P28-RUNTIME-001 | Production PHP image lacks proper Composer/frontend build, optimized autoloader, multistage build, pinned pnpm, clean runtime, entrypoint, cache/deploy contract, ownership, and smoke test. | One immutable non-root PHP runtime artifact for php-fpm, worker, scheduler with vendor from lockfile, built Vite assets, no Node/pnpm/tests in final stage unless role needs them, required extensions, Chromium only where needed, explicit entrypoint/cache/deploy contract. | Production Dockerfile/images. | Rebuild image strategy. | Production image build/smoke tests. | Docker build-context and runtime-content guard. | Image builds and serves app with assets. |
| P28-RUNTIME-002 | Nginx uses skeleton public dir, lacks real Vite assets/immutable artifact/readiness/static cache/security headers/upload limit, and exposes 443 without TLS listener. | Internal HTTP smoke stack works with real assets; 443 is not falsely exposed; Phase 29 owns HTTPS/certs/release routing/rollback. | Production nginx. | Rebuild nginx artifact strategy and config. | Nginx build/readiness/asset smoke. | False-443 and missing-assets guard. | Nginx serves app/assets over internal HTTP. |
| P28-RUNTIME-003 | Production secrets/env are incomplete and not typed. | Typed production config supports APP_KEY, DB password, Redis password, Meilisearch key, mail, Sentry, scanner, storage, release metadata, `_FILE`/entrypoint secrets, no secrets in image/Compose, and separate dev/test/e2e/prod templates. | Env/config/runtime. | Create config contract and templates. | Env validation tests. | Secret-in-image/Compose guard. | Required secrets are validated and externalized. |
| P28-RUNTIME-004 | `DB_SEARCH_PATH=public,core_identity,...` masks schema bugs. | Minimal documented exception only where framework requires; application uses schema-qualified names. | Dev/prod/test config. | Remove masking search path. | Schema qualification tests. | Search-path reliance guard. | App passes without broad Atlas search path. |
| P28-RUNTIME-005 | Queue models differ: Dev Container `queue:listen`, `composer dev`, production generic `queue:work`, Horizon installed but unused. | One target worker model, preferably Horizon if accepted, with versioned supervisor config, all queues, priorities, timeout/retry_after consistency, long-running process config, graceful terminate, readiness, failed jobs/retry operations. | Queues/Horizon. | Standardize worker runtime and queue list. | Worker smoke/readiness tests. | Queue list drift guard. | Managed-processes, imports, exports, search, files, files-large, notifications, default, and all actual queues are synchronized. |
| P28-RUNTIME-006 | Scheduler model lacks complete heartbeat/health/shutdown/locks/single-server/timezone/failure visibility/smoke parity. | One dev/prod scheduler model with heartbeat, healthcheck, graceful shutdown, locks, timezone, failures, and smoke test. | Scheduler. | Standardize scheduler runtime. | Scheduler heartbeat/smoke tests. | Scheduler parity guard. | Scheduler health reflects real execution. |
| P28-RUNTIME-007 | Healthchecks/readiness may check process existence instead of dependency chain. | Real checks cover nginx, php-fpm, app liveness/readiness, worker, Horizon, scheduler, PostgreSQL, Redis, Meilisearch, ClamAV, storage, Chromium, queues. | Health/runtime. | Implement real healthchecks. | Runtime smoke gate. | `depends_on: service_started` insufficiency guard. | Readiness fails when app cannot serve work. |
| P28-RUNTIME-008 | Files config references `clamav` but production Compose lacks ClamAV; fake scanner risk. | Production uses real ClamAV service or external endpoint; fake scanner only local/test; health shows signature version/state; scan queues/temp lifecycle/storage permissions work. | Files/ClamAV/runtime. | Add production scanner contract. | ClamAV/file scan smoke. | Fake-scanner production guard. | Production cannot start with fake scanner. |
| P28-RUNTIME-009 | Chromium/PDF runtime lacks exact contract. | Define binary path, version, sandbox for non-root, libraries, healthcheck, timeout, memory, render storage, cleanup, dev/prod/manual parity, no production Playwright browser download, true PDF test. | Exports/PDF/runtime. | Standardize PDF runtime. | PDF smoke test. | Chromium parity guard. | PDF generation works in target runtime. |
| P28-RUNTIME-010 | Dev Container reproducibility and risk documentation need hardening. | Document Docker socket, docker group, passwordless sudo risk; use `pnpm@11.18.0` from `packageManager`; align Playwright/PHP/Node/Composer; pin/update base images by policy. | Dev Container. | Update dev environment config/docs. | Config guard tests. | No floating pnpm/base-image drift guard. | Dev tooling remains reproducible and development-only privileges are documented. |
| P28-RUNTIME-011 | PostgreSQL 18 volume path/PGDATA behavior is unverified. | Confirm `/var/lib/postgresql`, `/var/lib/postgresql/data`, `PGDATA`, recreate durability, image-change durability, owner/permissions, backup access. | PostgreSQL runtime. | Test and document volume boundary. | Recreate persistence test. | PGDATA config guard. | Data persists across recreate with correct permissions. |
| P28-RUNTIME-012 | Backup image/interface boundary is not verified. | Backup image is buildable and has safe interface; Phase 28 does not claim one `pg_dump` is complete backup. | Backup container boundary. | Build and document preliminary interface. | Backup image build test. | Backup scope guard. | Phase 29 can implement schedule/retention/checksum/encryption/off-host/restore/monitoring/rollback. |
| P28-RUNTIME-013 | Docker build context can include `.git`, `.env`, secrets, host vendor/node_modules, test reports, Playwright reports, storage, docs not needed at runtime, Dev Container tooling, private exports, logs. | `.dockerignore` and COPY strategy include only runtime-required files. | Docker build context. | Repair ignore/COPY. | Image content tests. | Unwanted runtime file guard. | Final images exclude listed files. |
| P28-RUNTIME-014 | Runtime smoke gates are missing. | `composer check:foundation` covers compose config, production PHP build, nginx build, PostgreSQL/Redis/Meilisearch/ClamAV, php-fpm, nginx, readiness, fresh migration, route smoke, Vite asset smoke, worker smoke, scheduler heartbeat, file scan, PDF, clean shutdown, and restart with persisted data. | Quality/runtime. | Add public smoke command. | Run full gate before closure. | Phase completion guard. | Phase cannot close without green smoke stack. |
| P28-GUARD-001 | Frontend guardrails are mostly documentation. | Add executable checks for view registry, route/page/breadcrumb/navigation/permission/module gate, desktop/mobile parity, glossary labels, no legacy synonyms, one action/modal/status/table/form/nav contract, safe columns, saved views, cancel/back, responsive subnav, light/dark, keyboard/focus/screen-reader, console cleanliness, no unexpected 4xx/5xx, visual baselines, and Playwright workflows. | Frontend tests/guards. | Implement AST/typed registry/component/E2E checks. | Guard suite tests with fixtures. | Non-vacuous guard requirements. | Guards fail for duplicated or unsafe UI patterns. |
| P28-GUARD-002 | Standard `composer check` is not enough for foundation closure. | Add `composer check:foundation` running `composer check`, fresh PostgreSQL migration, migration/schema guardrails, deterministic seeders, seeder idempotency, PL/EN mail rendering, architecture graph validation, audit coverage validation, full Playwright, container config, production image builds, production smoke stack, ClamAV/PDF/worker/scheduler smoke. | Quality commands. | Add command and docs. | Command execution evidence. | Phase closure requires command. | Full Phase 28 gate passes. |
| P28-GUARD-003 | Release gate belongs to Phase 30 and must include Phase 29 evidence later. | Phase 30 release gate includes deployment, backup, restore, rollback, final E2E, security review, documentation review. | Roadmap/quality. | Keep release gate in Phase 30 docs. | Phase 30 dependency review. | No premature release gate execution. | Phase 28 does not run/redefine final release gate. |
| P28-MODAUD-001 | Identity module needs targeted audit for cycle, lifecycle, login, password reset, MFA, sessions, impersonation, admin mode, public contracts, lookups, Eloquent leakage, seeders, mails, audit, profile/security UI, docs/tests. | Identity complies with target graph, public API, UI, audit, seed, mail, and tests. | Identity. | Complete module-specific repair package. | Identity unit/feature/mail/Playwright tests. | Module inventory traceability. | Identity row has no unresolved Phase 28 issues. |
| P28-MODAUD-002 | Authorization needs targeted audit for dependency drift, direct SQL, membership ownership, roles, permissions, onboarding presets, copy/manual assignment, provenance, preview, effective permissions, stale writes, audit, UI parity, seeders, docs/tests. | Authorization uses owner contracts and one assignment workflow with full provenance. | Authorization. | Complete module-specific repair package. | Authorization feature/integration/UI tests. | Module inventory traceability. | Authorization row has no unresolved Phase 28 issues. |
| P28-MODAUD-003 | Teams needs targeted audit for Audit usage, memberships, effective dating, activation, head manager, DAG, integrated editor, Managers area removal, public lookup, active-team validation, seeders, audit, docs/tests. | Teams owns team and hierarchy workflows. | Teams. | Complete module-specific repair package. | Teams DAG/scope/UI/audit tests. | Module inventory traceability. | Teams row has no unresolved Phase 28 issues. |
| P28-MODAUD-004 | Users boundary with Identity and user CRUD/profile/team/authorization workflows need audit. | Users is thin orchestration with route/permission/audit parity and docs/tests. | Users. | Complete module-specific repair package. | Users CRUD/profile tests. | Module inventory traceability. | Users row has no unresolved Phase 28 issues. |
| P28-MODAUD-005 | Audit module needs full audit workstream closure. | Audit catalog, browser, atomicity, coverage, docs/tests are complete. | Audit. | Complete Audit workstream. | Audit guard/feature/integration tests. | Module inventory traceability. | Audit row has no unresolved Phase 28 issues. |
| P28-MODAUD-006 | Settings needs audit for Audit dependency, global/team/user/security settings, locale, theme, cache, validation, surface, public contracts, docs current/target. | Settings has consistent surface, audit, and public contracts. | Settings. | Complete module-specific repair package. | Settings feature/cache/audit tests. | Module inventory traceability. | Settings row has no unresolved Phase 28 issues. |
| P28-MODAUD-007 | Notifications needs audit for type catalog, PL/EN labels, preferences, extra verified emails, in-app/email delivery, bilingual template, links, queues, audit, technical tokens, docs/tests. | Notifications follows catalogs, bilingual mail, and safe UI. | Notifications. | Complete module-specific repair package. | Notification/mail/UI tests. | Module inventory traceability. | Notifications row has no unresolved Phase 28 issues. |
| P28-MODAUD-008 | Health needs audit for real checks, readiness, technical availability, queues, scheduler, storage, Meilisearch, ClamAV, Chromium, backup boundary, operator UI, alerts, runtime parity, docs/tests. | Health drives real technical availability. | Health. | Complete module-specific repair package. | Health/readiness/runtime tests. | Module inventory traceability. | Health row has no unresolved Phase 28 issues. |
| P28-MODAUD-009 | Files needs audit for foreign SQL, upload, quarantine, fake/real scanner, ClamAV, download, rescan, scan failure, acknowledge, replace, delete, anonymize, retention copies, audit, storage, queues, health, Admin UI, docs/tests. | Files has safe storage/scanning/audit/runtime behavior. | Files. | Complete module-specific repair package. | Files feature/integration/smoke tests. | Module inventory traceability. | Files row has no unresolved Phase 28 issues. |
| P28-MODAUD-010 | Exports needs audit for Core/Optional dependency, orchestration, artifacts, Files, notifications, audit, Chromium/PDF, sync/async limits, print, Admin providers, availability, docs/tests. | Exports ownership and runtime dependencies are explicit. | Exports. | Complete module-specific repair package. | Export/PDF/mail/audit tests. | Module inventory traceability. | Exports row has no unresolved Phase 28 issues. |
| P28-MODAUD-011 | Privacy needs audit for dependency classification, high-risk continuation, one-time cleanup, legal holds, preview, execution, rejected attempts, lifecycle participants, files/search/exports, audit atomicity, UI copy, docs/tests. | Privacy high-risk workflows are explicit and audited. | Privacy. | Complete module-specific repair package. | Privacy feature/lifecycle/audit tests. | Module inventory traceability. | Privacy row has no unresolved Phase 28 issues. |
| P28-MODAUD-012 | FeatureFlags needs audit for local status/localization maps, overrides, activation, audit, health, permissions, route availability, frontend tables, docs/tests. | FeatureFlags uses shared catalogs and guarded surfaces. | FeatureFlags. | Complete module-specific repair package. | FeatureFlags UI/audit tests. | Module inventory traceability. | FeatureFlags row has no unresolved Phase 28 issues. |
| P28-MODAUD-013 | Integrations needs audit for connection test audit, credentials, adapter registry, retry, timeout, circuit breaker, sync runs, health, external API boundary, module gate, technical availability, UI copy, env config, docs/tests. | Integrations has audited operations and technical availability. | Integrations. | Complete module-specific repair package. | Integrations feature/health/audit tests. | Module inventory traceability. | Integrations row has no unresolved Phase 28 issues. |
| P28-MODAUD-014 | ManagedProcesses needs audit for foreign SQL, runner, state machine, logs, retry/cancel, schedules, acknowledgements, input files, audit, notifications, queues, scheduler, deactivation guards, Admin UI, docs/tests. | ManagedProcesses is boundary-safe and operationally complete. | ManagedProcesses. | Complete module-specific repair package. | Process state/UI/audit tests. | Module inventory traceability. | ManagedProcesses row has no unresolved Phase 28 issues. |
| P28-MODAUD-015 | Imports needs audit for frontend entrypoints, ManagedProcesses surface, Files, Integrations, idempotency, row errors, audit, notifications, queue, module gate, backend surface, seed fixtures, docs/tests. | Imports exposes accepted surfaces through ManagedProcesses and metadata. | Imports. | Complete module-specific repair package. | Import integration/UI tests. | Module inventory traceability. | Imports row has no unresolved Phase 28 issues. |
| P28-MODAUD-016 | Search needs audit for Meilisearch, Outbox, projections, indexing, rebuild, zero downtime, authorization filtering, module gate, health, technical tokens, audit, privacy lifecycle, docs/tests. | Search is operationally visible and boundary-safe. | Search. | Complete module-specific repair package. | Search rebuild/health/UI tests. | Module inventory traceability. | Search row has no unresolved Phase 28 issues. |
| P28-MODAUD-017 | Reports needs audit for frontend entrypoints, Reports versus Core Exports ownership, optionality, Files, managed processes, PDF, print, charts, notifications, audit, availability, docs/tests. | Reports metadata and Exports integration are explicit. | Reports. | Complete module-specific repair package. | Reports/export/PDF tests. | Module inventory traceability. | Reports row has no unresolved Phase 28 issues. |
| P28-MODAUD-018 | TimeTracking needs full workstream closure. | TimeTracking follows target navigation, UI, audit, transactions, module boundaries, docs/tests. | TimeTracking. | Complete TimeTracking workstream. | TimeTracking backend/frontend/E2E tests. | Module inventory traceability. | TimeTracking row has no unresolved Phase 28 issues. |
| P28-MODAUD-019 | Shared foundations need audit for ModuleRegistry, ModuleGate, activation, Outbox, saved views, DataTable, Inertia registry, route availability, navigation, breadcrumbs, observability, data lifecycle, global middleware, global providers. | Shared foundations are explicit, guarded, and not business-rule sinks. | Shared. | Complete shared-foundation repair package. | Architecture/shared foundation tests. | Module inventory traceability. | Shared foundation row has no unresolved Phase 28 issues. |
| P28-LEGACY-001 | Legacy code/docs may remain after migration. | Remove old Managers area, legacy manager report routes/pages, old action system, duplicate authorization workflow components, local status maps, local translation/fallback dictionaries, raw tables replaced by shared contract, unused routes/breadcrumbs/permissions/translations/tests/table columns/settings/DTOs/providers, old migrations after squash, legacy seed helpers, and docs describing removed solutions. | Whole repo. | Remove each legacy artifact after replacement. | Static no-reference tests. | Legacy reference guard. | Every removal has evidence of no references. |

## Detailed tasks by workstream

### P28-W01 — Inventory and traceability

- [x] Complete automated backend/runtime/audit scanner gate: Phase 28 has one canonical roadmap file, inline current-state evidence, a temporary repeatable scanner, tested scanner output, and a selected first real code package.
- [x] Complete `P28-W01D` frontend product consistency review; this cannot be closed by static script output alone.
- [ ] Create `P28-INV-001` module inventory.
- [ ] Create `P28-INV-002` backend surface inventory.
- [ ] Create `P28-INV-003` frontend route/view matrix.
- [ ] Create `P28-INV-004` audit event matrix.
- [ ] Create `P28-INV-005` runtime parity matrix.
- [ ] Map every issue ID in the register to an owner, implementation package, tests, docs, and legacy-removal evidence.
- [ ] Add a Phase 28 evidence log section entry for each completed package.

### P28-W02 — Module graph and public contract boundaries

- [ ] Complete `P28-ARCH-001` through `P28-ARCH-014`.
- [ ] Define owner-owned contracts for user lookup, team lookup, active-team validation, active membership, authorization subject resolution, audit display labels, lifecycle ownership, and export read models.
- [ ] Remove `Application/Public/Persistence/*DatabaseTable` as a cross-module API.
- [ ] Add architecture tests for real dependency graph, cycles, undeclared imports, declared unused dependencies, foreign SQL, and global provider/middleware imports.

### P28-W03 — ModuleRegistry, ModuleGate, activation, and technical availability

- [ ] Complete `P28-MOD-001` through `P28-MOD-005`.
- [ ] Decide and document whether each metadata category is executable/validated or removed.
- [ ] Add module startup tests for deployed/reduced/degraded combinations.
- [ ] Add deactivation guard tests and audit tests.

### P28-W04 — Audit coverage, contracts, atomicity, and enforcement

- [ ] Complete `P28-AUDIT-001` through `P28-AUDIT-011`.
- [ ] Preserve and strengthen internal `AuditRecorder`, internal `AuditEvent`, append-only triggers, sensitive-data redaction, security categories, correlation ID, effective/actual/impersonated actor, impersonation session, request-less safety, security history, impersonation history, and read-only Admin browser.
- [ ] Replace loose audit strings with registered catalogs per module.
- [ ] Replace Audit browser array processing with DB-backed read models and export path.
- [ ] Add audit catalog, coverage, secret, metadata, and atomicity guardrails.

### P28-W05 — Unified Atlas UI/UX contract and glossary

- [ ] Complete `P28-UI-001` through `P28-UI-005`.
- [ ] Create the binding PL/EN glossary with canonical labels, singular, plural, menu label, page label, form label, action verbs, status labels, and technical internal name.
- [ ] Add rendered copy audit for all PL/EN views.
- [ ] Preserve intentionally empty regular-user and manager dashboards.

### P28-W06 — Navigation, layout, and mobile/desktop parity

- [ ] Complete `P28-UI-006` through `P28-UI-008`.
- [ ] Migrate sidebar, mobile navigation, TopBar, shell subnavigation, breadcrumbs, route availability, active state, permissions, module gates, and mode to one registry.
- [ ] Add accessible mobile drawer and mobile subnavigation tests.

### P28-W07 — Actions, forms, CRUDs, tables, and shared states

- [ ] Complete `P28-ACTION-001` through `P28-ACTION-003`.
- [ ] Complete `P28-FORM-001` through `P28-FORM-006`.
- [ ] Complete `P28-TABLE-001` through `P28-TABLE-009`.
- [ ] Complete `P28-LOC-001` and `P28-LOC-002`.
- [ ] Add shared contracts for actions, confirmations, CRUD/form layouts, DataTable, filters, state presentations, status catalog, locale formatting, and missing translations.

### P28-W08 — Authorization, Teams, and integrated manager structure

- [ ] Complete `P28-AUTH-001` through `P28-AUTH-005`.
- [ ] Remove separate Managers area only after integrated Team Structure Editor is complete and tested.
- [ ] Persist assignment provenance and consolidate user-team authorization workflow.

### P28-W09 — Existing-view migration to the target UI contract

- [ ] Migrate every route in `P28-INV-003` to the target UI contract.
- [ ] Remove old route-backed and local-tab implementations after each migrated workflow.
- [ ] Add Playwright coverage for critical migrated workflows, PL/EN copy, light/dark themes, browser console cleanliness, and no unexpected 4xx/5xx.

### P28-W10 — TimeTracking and remaining module surfaces

- [ ] Complete `P28-TT-001` through `P28-TT-004`.
- [ ] Complete `P28-MODAUD-001` through `P28-MODAUD-019`.
- [ ] Use the backend surface inventory to expose, automate, document as internal-only, or remove every remaining backend/database surface. Known Phase 28 problems may not be moved to a later phase.

### P28-W11 — Migrations, PostgreSQL, and data ownership

- [ ] Complete `P28-MIG-001` through `P28-MIG-003`.
- [ ] Run fresh PostgreSQL migration and rollback/recreate tests.
- [ ] Document local reset expectations after the pre-production squash.

### P28-W12 — Seeders and deterministic test/demo data

- [ ] Complete `P28-SEED-001`.
- [ ] Preserve the full former Phase 27b contract: seeders must not silently bypass user accounts, teams, authorization, notifications, files, TimeTracking, or manager hierarchy invariants; use public contracts or dedicated owner fixture builders; justify and test any direct writes; keep idempotency; preserve deterministic verified demo accounts where required; update operations docs and permanent seeding rules as needed.

### P28-W13 — Bilingual mail

- [ ] Complete `P28-MAIL-001` and `P28-MAIL-002`.
- [ ] Preserve the full former Phase 27c contract: every Atlas-owned mail path must use the shared bilingual branded template, translation keys, effective-locale ordering, safe wording, no secrets/raw tokens/internal IDs, plain-text fallback, tests for Polish-first and English-first output, notification preference coverage, hardcoded-copy guardrails, and updated module/operations documentation.

### P28-W14 — Development, production image, and smoke-stack runtime

- [ ] Complete `P28-RUNTIME-001` through `P28-RUNTIME-004`.
- [ ] Complete `P28-RUNTIME-010` through `P28-RUNTIME-013`.
- [ ] Build reproducible immutable runtime images and internal HTTP smoke stack without implementing Phase 29 deployment.

### P28-W15 — Queues, scheduler, Horizon, Files, ClamAV, Chromium, Search, and managed processes

- [ ] Complete `P28-RUNTIME-005` through `P28-RUNTIME-009`.
- [ ] Complete `P28-RUNTIME-014`.
- [ ] Verify runtime parity for managed processes, imports, exports, search, files, notifications, scheduler, and PDF.

### P28-W16 — Tests, guardrails, Playwright, and legacy cleanup

- [ ] Complete `P28-GUARD-001` through `P28-GUARD-003`.
- [ ] Complete `P28-LEGACY-001`.
- [ ] Remove temporary Phase 28 inventory scaffolding (`tools/phase28/generate-inventory.php`, `Phase28InventoryGeneratorTest`, and generated inventory snapshots) or replace it with accepted permanent guardrails before final Phase 28 closure.
- [ ] Add and run `composer check:foundation`.
- [ ] Confirm all guardrails are non-vacuous and include fixture inputs.

### P28-W17 — Final Phase 28 closure

- [ ] Confirm every issue ID has implementation, test, guardrail, docs, evidence, and completion status.
- [ ] Confirm Phase 29 prerequisites are satisfied without moving Phase 29 deployment scope into Phase 28.
- [ ] Confirm Phase 30 final release audit remains not started.
- [ ] Update `WORKROAD.md` to mark Phase 28 complete only after every completion criterion is met.
- [ ] Record final quality-gate evidence.

## Required tests and guardrails

- [ ] Real module dependency graph test comparing imports to `ModuleDefinition`.
- [ ] Cycle detection test.
- [ ] Undeclared import test.
- [ ] Declared unused dependency test.
- [ ] Optional dependency reduced-mode test.
- [ ] Required dependency missing/inactive/degraded ModuleGate tests.
- [ ] Technical availability tests for health, integrations, binaries, services, queues, storage, config, and credentials.
- [ ] Cross-module SQL and public table-constant usage test.
- [ ] Global provider/middleware internal-import test.
- [ ] Non-vacuous architecture scanning tests.
- [ ] Audit catalog registration and hardcoded-action tests.
- [ ] Audit success/rejected/failed coverage tests.
- [ ] Audit atomicity and secret-field tests.
- [ ] DB-backed Audit browser pagination/export tests.
- [ ] View registry and route/page/breadcrumb/navigation/permission/module-gate tests.
- [ ] Desktop/mobile navigation parity tests.
- [ ] Glossary, no legacy synonym, missing translation, and no technical-token exposure tests.
- [ ] Single action, modal/confirmation, status catalog, DataTable, CRUD/form, and navigation registry tests.
- [ ] List/show/edit action parity tests.
- [ ] Safe DataTable column tests by role.
- [ ] Saved views disabled outside accepted surfaces.
- [ ] Cancel/back semantics and create/edit parity tests.
- [ ] Responsive subnavigation, keyboard/focus, screen-reader, light/dark, browser-console, and no unexpected 4xx/5xx tests.
- [ ] Visual screenshot baselines for critical shared components.
- [ ] Playwright coverage for critical full workflows.
- [ ] Fresh PostgreSQL migration and rollback/recreate tests.
- [ ] Migration/schema guardrails for `after`, unqualified Atlas tables, `search_path`, and unauthorized `public` tables.
- [ ] Seeder idempotency, normal-use-case parity, no duplicates, production demo-reset refusal, and no production fixture-builder registration tests.
- [ ] Mail rendering PL-first and EN-first tests plus hardcoded mail-copy scanner.
- [ ] Runtime smoke tests for Docker config, image builds, nginx/php-fpm, readiness, Vite assets, worker, scheduler, ClamAV, PDF, clean shutdown, and data-preserving restart.

## Required legacy removals

- [ ] Remove old Managers area.
- [ ] Remove legacy manager report routes/pages.
- [ ] Remove old action system.
- [ ] Remove duplicated authorization workflow components.
- [ ] Remove local status maps replaced by the catalog.
- [ ] Remove local translator/fallback dictionaries.
- [ ] Remove raw/manual tables replaced by the shared contract.
- [ ] Remove unused routes.
- [ ] Remove unused breadcrumbs.
- [ ] Remove unused permissions.
- [ ] Remove unused translations.
- [ ] Remove unused tests.
- [ ] Remove unused table columns.
- [ ] Remove unused settings.
- [ ] Remove unused DTOs.
- [ ] Remove unused providers.
- [ ] Remove old migrations after squash.
- [ ] Remove legacy seed helpers.
- [ ] Remove documentation describing removed solutions.
- [ ] Remove temporary Phase 28 inventory scaffolding after its data has been converted into permanent inventories or executable guardrails.
- [ ] Provide no-reference evidence for every removal.

## Required documentation updates

- [ ] Update `AGENTS.md` with permanent rules only, not a copy of this phase.
- [ ] Update architecture docs for module graph/public contracts, module metadata/activation, audit contracts/coverage, frontend UI/UX contract, glossary, actions/confirmations/dangerous operations, CRUD/forms, tables/filters/statuses/saved views, navigation/responsive shell, localization, mail architecture, runtime/container parity, migration/schema ownership, and test/guardrail architecture.
- [ ] Update every module document in [Module documentation](../modules/README.md) with ownership, public API, dependencies, permissions, settings, events, audit actions, routes/surfaces, activation, health, jobs/schedules, data ownership, integration points, current state, Phase 28 target, and known issue IDs.
- [ ] Update operations docs for Dev Container, production image build, production runtime, manual server installation, queues/Horizon, scheduler, Files/ClamAV, Chromium/PDF, seeding/demo data, mail testing, quality gates, migration reset after squash, deployment prerequisites for Phase 29, backup boundary, and testing environment.
- [ ] Update README and documentation indexes only at high level: current Phase 28, documentation location, internal Core Audit model, and runtime environments.
- [ ] Do not claim that Phase 28 implementation is complete until this file's completion criteria are satisfied.

## Required evidence

Each implementation package must append evidence in this file:

- package ID and issue IDs closed;
- changed files summary;
- old implementation removed;
- tests added/updated;
- quality commands run;
- runtime/container commands run when applicable;
- screenshots or Playwright artifacts for UI packages when applicable;
- documentation updated;
- remaining risks.

## Completion criteria

- [ ] `WORKROAD.md` points to Phase 28 while it is the first unfinished phase.
- [ ] Phase 27 and Phase 27a remain complete historical phases.
- [ ] No active Phase 27b or Phase 27c entries remain.
- [ ] Former Phase 27b scope is fully implemented, tested, documented, and evidenced through `P28-SEED-001`.
- [ ] Former Phase 27c scope is fully implemented, tested, documented, and evidenced through `P28-MAIL-001` and `P28-MAIL-002`.
- [ ] Former Phase 28 backend/database surface audit scope is fully implemented through `P28-INV-002`, `P28-MODAUD-*`, and related issue IDs.
- [ ] Every issue ID in the register is closed with implementation, tests, guardrail, documentation, and no-reference evidence where applicable.
- [ ] No known Phase 28 foundation issue is deferred to Phase 31 or later.
- [ ] All mandatory inventories are complete and traceable to implementation packages.
- [ ] Module dependency graph is explicit, acyclic, and tested against real imports.
- [ ] Cross-module SQL and public table constants are no longer used as inter-module APIs.
- [ ] ModuleRegistry, ModuleGate, activation, technical availability, and deactivation guards are executable and tested.
- [ ] Audit catalogs, coverage, atomicity, browser, exports, and docs are complete.
- [ ] Unified UI/UX, glossary, navigation, action, CRUD/form, DataTable, status, localization, and responsive contracts are implemented and guarded.
- [ ] Authorization/team assignment, provenance, and manager hierarchy use the integrated target workflows.
- [ ] TimeTracking follows the target navigation, UI, audit, transaction, and boundary contracts.
- [ ] Pre-production migration squash is complete and fresh PostgreSQL migration works.
- [ ] Seeders are deterministic, idempotent, invariant-preserving, and production-safe.
- [ ] All Atlas-owned mails follow the bilingual template contract.
- [ ] Dev/runtime images and internal production smoke stack are reproducible and pass smoke gates.
- [ ] Queues, Horizon, scheduler, ClamAV, Chromium/PDF, Search, Files, and ManagedProcesses have parity checks and smoke evidence.
- [ ] All required legacy removals are done with no-reference evidence.
- [ ] `composer check` passes.
- [ ] `composer check:foundation` passes.
- [ ] Canonical docs and indexes are updated without claiming unimplemented work is complete.
- [ ] Phase 29 remains not started and retains production deployment/backup/restore/rollback scope.
- [ ] Phase 30 remains not started and retains final release audit scope.

## Phase execution protocol

1. Start each package by reading this file and relevant docs.
2. Select one closed package from the next incomplete workstream.
3. Record issue IDs and inventories affected.
4. Implement the target behavior.
5. Remove replaced legacy behavior in the same package.
6. Add/repair tests and guardrails.
7. Update canonical docs.
8. Run relevant quality gates.
9. Record evidence.
10. Update checkboxes truthfully.
11. Report the approximate remaining Phase 28 work percentage.
12. Present the final diff summary, quality commands, and proposed Conventional Commit message so the completed package can be committed as one coherent change after owner approval.

## Final quality-gate record

Phase 28 preparation and scanner work has started. Final foundation-repair implementation and quality-gate evidence remain open until Phase 28 closure.

## Phase 28 evidence log

| Date | Package | Issue IDs | Evidence | Quality commands | Remaining risks |
| --- | --- | --- | --- | --- | --- |
| 2026-08-08 | `P28-W01A` inventory baseline and traceability start | Partial `P28-INV-001` through `P28-INV-005`; maps all issue families to future packages | Recorded the first inline baseline in this phase file. Confirmed representative current-state findings: 14 public persistence table classes, 204 `Application/Public/Persistence/*DatabaseTable` uses in `app`, 133 App-owned admin routes in the initial route aggregation, 62 Vue pages, 31 migrations, 5 seeders, hardcoded/raw mail paths, direct seeder writes, broad `DB_SEARCH_PATH`, `pnpm@latest`, worker model drift, and missing production ClamAV service evidence. | `php artisan route:list --json`; PHP route aggregation one-liner; `find`/`rg` inventory commands used for the baseline. | This package is a baseline only. Mandatory inventory checkboxes remain open until `P28-W01B` completes full generated matrices for modules, backend surfaces, frontend routes/views, audit events, and runtime parity. |
| 2026-08-08 | `P28-W01B` repeatable inventory scanner, first slice | Partial `P28-INV-001` through `P28-INV-005`; supports future `P28-ARCH-014`, `P28-GUARD-001`, and `P28-GUARD-002` | Added `tools/phase28/generate-inventory.php` and `Phase28InventoryGeneratorTest`. The scanner reports deployed modules, dependency declarations, actual module imports, public persistence class usage, static route prefixes, framework-resolved route/view surface buckets, frontend pages, direct Inertia render pages, audit action/result strings, mail signals, seeder direct-write signals, runtime drift signals, global provider/middleware/shared module imports, migration schema operations, PostgreSQL `after` usage, and orphan module roots such as `app/Modules/Application/Demo`. | `php -l tools/phase28/generate-inventory.php`; `vendor/bin/pint --test tools/phase28/generate-inventory.php tests/Unit/Foundation/Phase28InventoryGeneratorTest.php`; `php artisan test --filter=Phase28InventoryGeneratorTest`; `php tools/phase28/generate-inventory.php --json`; `php tools/phase28/generate-inventory.php --markdown`; `php tools/phase28/generate-inventory.php --framework-routes-markdown`; `git diff --check`. | Mandatory inventory checkboxes remain open. |
| 2026-08-09 | `P28-W01B` persistence matrix expansion | Partial `P28-INV-002`, `P28-MIG-001` through `P28-MIG-003`; supports future `P28-ARCH-004` and `P28-GUARD-002` | Extended the scanner/test coverage with `--persistence-markdown` and recorded the current persistence totals inline in this file: 100 `Schema::create/table` operations, 899 column signals, 193 index/unique/primary signals, 103 foreign-key signals, 13 PostgreSQL `after` usages, 5 unresolved dynamic Spatie permission table operations, and 3 public/vendor Pulse tables. | `php -l tools/phase28/generate-inventory.php`; `php artisan test --filter=Phase28InventoryGeneratorTest`; `php tools/phase28/generate-inventory.php --persistence-markdown`. | Static extraction still requires manual confirmation for dynamic config-backed tables, indirect migration logic, and final fresh PostgreSQL schema evidence during W11. Mandatory inventory checkboxes remain open. |
| 2026-08-09 | `P28-W01B` runtime/config matrix expansion | Partial `P28-INV-005`; supports future `P28-RUNTIME-001` through `P28-RUNTIME-014`, `P28-MOD-003`, and `P28-GUARD-003` | Extended the scanner/test coverage with `--runtime-markdown` and recorded the current runtime totals inline in this file. The scanner records package manager, queue/Horizon, scheduler, PostgreSQL/search-path, Redis, Meilisearch, ClamAV, Chromium/PDF, network exposure, secrets, storage, and backup signals across `.env.example`, config files, Dockerfiles, Compose files, package manifests, and Playwright config. | `php -l tools/phase28/generate-inventory.php`; `php artisan test --filter=Phase28InventoryGeneratorTest`; `php tools/phase28/generate-inventory.php --runtime-markdown`. | Static extraction cannot prove runtime behavior, container buildability, smoke readiness, queue execution, scheduler heartbeat, ClamAV reachability, Chromium/PDF rendering, or PostgreSQL volume durability. Mandatory inventory checkboxes remain open. |
| 2026-08-09 | `P28-W01B` audit event matrix expansion | Partial `P28-INV-004`; supports future `P28-AUDIT-001` through `P28-AUDIT-011` and `P28-MODAUD-*` | Extended the scanner/test coverage with `--audit-markdown` and recorded the current audit totals inline in this file: 294 static audit signals across 15 owners, including 46 recorder-contract consumers, 50 event constructors, 76 `action` arguments, 54 `result` arguments, and 68 `source` arguments. | `php -l tools/phase28/generate-inventory.php`; `php artisan test --filter=Phase28InventoryGeneratorTest`; `php tools/phase28/generate-inventory.php --audit-markdown`; `php tools/phase28/generate-inventory.php --markdown`. | Static extraction cannot prove complete success/rejection/failure coverage, transaction atomicity, actor/target/team/correlation metadata, browser visibility, retention class, or dynamic expression catalog values. Mandatory inventory checkboxes remain open. |
| 2026-08-09 | `P28-W01B` backend surface matrix expansion | Partial `P28-INV-002`; supports future `P28-GUARD-001`, `P28-GUARD-002`, `P28-MAIL-*`, `P28-SEED-001`, and `P28-RUNTIME-005`/`006` | Extended the scanner/test coverage with `--backend-surfaces-markdown` and recorded the current backend surface totals inline in this file: 410 static backend surface signals, including 195 HTTP routes, 79 controllers, 36 export providers, 14 command classes, 1 job class, 12 queue signals, 3 schedule signals, 31 mail signals, 5 notification classes, 29 managed-process signals, and 5 seeder classes. | `php -l tools/phase28/generate-inventory.php`; `php artisan test --filter=Phase28InventoryGeneratorTest`; `php tools/phase28/generate-inventory.php --backend-surfaces-markdown`; `php tools/phase28/generate-inventory.php --markdown`. | Static extraction cannot prove exact authorization, module-gate behavior, operation ownership, UI state coverage, mail locale behavior, queue execution, or whether each internal item should remain internal, be exposed, be automated, or be removed. Mandatory inventory checkboxes remain open. |
| 2026-08-09 | `P28-W01C` automated prep consolidation | Automated scanner gate for backend/runtime/audit; keeps `P28-INV-*` open for final traceability | Consolidated all Phase 28 roadmap evidence into this single phase file, removed separate generated roadmap snapshots, kept the temporary scanner as a local Phase 28 work aid, added `--prep-report-markdown`, and selected `P28-W02A` as the first real code package after prep. | `php tools/phase28/generate-inventory.php --json`; `php tools/phase28/generate-inventory.php --prep-report-markdown`; `php artisan test --filter=Phase28InventoryGeneratorTest`; `git diff --check`. | Automated backend/runtime/audit prep is ready, but full prep remains open because `P28-W01D` must perform manual frontend product consistency review. Phase 28 implementation remains at 0%. |
| 2026-08-09 | `P28-W01D` frontend product consistency baseline | Partial `P28-INV-003`; supports future `P28-UI-*`, `P28-NAV-*`, `P28-ACTION-*`, `P28-FORM-*`, `P28-TABLE-*`, `P28-LOC-*`, `P28-TT-*`, and `P28-AUTH-*` | Extended the temporary scanner/test coverage with `--frontend-consistency-markdown` and recorded the manual product review baseline inline in this file. The review identifies separate Admin Managers CRUD/navigation, desktop/mobile navigation drift, TimeTracking user/manager/admin parity risk, overloaded user profile/MFA raw fetch, create/edit/show/list parity risk, fragmented table/status/action/filter/state patterns, technical-token exposure risk, and breadcrumb/title/icon parity requirements. | `php -l tools/phase28/generate-inventory.php`; `php tools/phase28/generate-inventory.php --frontend-consistency-markdown`; `php tools/phase28/generate-inventory.php --prep-report-markdown`; `php artisan test --filter=Phase28InventoryGeneratorTest`; `git diff --check`. | This package closes Phase 28 preparation only. It does not repair UI. Browser-rendered light/dark/mobile review and Playwright/console-clean evidence remain required in the relevant frontend implementation packages. |

Temporary scaffolding note: `tools/phase28/generate-inventory.php` and `tests/Unit/Foundation/Phase28InventoryGeneratorTest.php` are Phase 28 working aids only. Before final Phase 28 closure, convert any still-needed checks into permanent guardrails and remove the temporary Phase 28-specific files so they do not remain as long-term repository noise.
