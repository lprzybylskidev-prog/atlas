# Phase 29 — Foundation acceptance repair and rendered workflow closure

**Status:** `in progress`

## Objective

Close concrete acceptance gaps discovered after completion of Phase 28 without reopening or rewriting the historical Phase 28 implementation record.

This phase is a focused post-Phase-28 repair. It exists because several Phase 28 contracts were marked complete while the resulting repository still contains observable or structurally incomplete behavior in four areas:

1. Atlas-owned localization can still render missing translation keys or incomplete PL/EN catalogs.
2. `DataTable.vue` still retains too many responsibilities for the accepted decomposed DataTable contract.
3. the integrated Team Structure Editor does not yet expose the complete accepted team-membership and hierarchy-editing workflow.
4. TimeTracking does not yet have the browser-level E2E coverage required by its accepted Phase 28 completion contract.

Phase 29 closes only these known acceptance gaps and any directly necessary regression work discovered while implementing them.

Production deployment, HTTPS, backups, restore, release switching, and rollback remain Phase 30 scope.

The final whole-application release audit remains Phase 31 scope.

## Dependencies

* [Phase 28 — Foundation repair and consolidation](phase-28-foundation-repair-and-consolidation.md)
* [Frontend UI](../architecture/frontend-ui.md)
* [Foundation repair target contracts](../architecture/foundation-repair-target-contracts.md)
* [Tables, reports, exports, and print](../architecture/tables-reports-exports-and-print.md)
* [UI glossary](../architecture/ui-glossary.md)
* [Authorization](../modules/authorization.md)
* [Teams and manager hierarchy](../modules/teams-and-manager-hierarchy.md)
* [TimeTracking](../modules/time-tracking.md)
* [Quality gates and git](../operations/quality-gates-and-git.md)
* [Testing environment](../operations/testing-environment.md)

## Phase boundary

Phase 29 is deliberately narrow.

It MUST NOT become a second general Phase 28.

Do not:

* reopen completed Phase 28 workstreams merely to improve them further;
* create unrelated architecture refactors;
* add new business features;
* redesign working modules that are outside the issue register below;
* start Phase 30 deployment work;
* start Phase 31 final whole-application verification;
* create lettered phases;
* create endless micro-packages solely to report progress.

A defect found while implementing this phase belongs here only when it directly blocks one of the registered Phase 29 acceptance contracts.

Unrelated new debt must be recorded separately according to `WORKROAD.md`.

## Execution discipline

Phase 29 is strictly sequential.

Workstream order:

1. `P29-W01` — Localization completeness and raw-key elimination
2. `P29-W02` — DataTable responsibility decomposition
3. `P29-W03` — Complete Team Structure Editor
4. `P29-W04` — TimeTracking browser-level E2E closure
5. `P29-W05` — Final Phase 29 regression and acceptance gate

At any time there is exactly one active workstream.

Do not start a later workstream while any mandatory checkbox in an earlier workstream remains open.

Do not select work based on simplicity, speed, convenience, or file locality.

A workstream is complete only when all its implementation, tests, permanent guardrails, documentation, legacy-removal requirements, and acceptance evidence are complete.

Preparation, inventory generation, scanner output, partial progress, or local package completion do not count as workstream completion.

## Issue register

### P29-LOC-001 — Missing Atlas translation keys can reach rendered UI

#### Problem

Atlas-owned Vue/TypeScript/PHP surfaces can reference a translation key that is absent from the effective translation catalog. The frontend translator exposes missing translations as `[translation:<key>]`, which means a structurally valid application can still ship raw translation identifiers to users.

#### Target

Every Atlas-owned user-facing translation key used by the current application exists in both Polish and English catalogs.

Missing translation keys fail permanent automated tests before runtime.

The runtime diagnostic fallback may remain explicit for development/debugging, but normal tested product surfaces must never rely on it.

#### Done

* every currently used Atlas translation key exists in PL and EN;
* no route-backed tested surface renders `[translation:...]`;
* no route-backed tested surface renders a namespaced Atlas translation key as visible copy;
* missing-key regression tests are permanent and non-vacuous.

### P29-LOC-002 — PL/EN catalog parity is incomplete or insufficiently enforced

#### Problem

The presence of large `pl.json` and `en.json` files does not prove that Atlas-owned translation namespaces have complete parity.

#### Target

Polish and English catalogs expose the same accepted Atlas-owned translation key set.

The permanent guard must detect a key present in only one locale.

Dynamic translation families must be explicitly enumerable or registered. Broad wildcard suppression is not acceptable.

#### Done

* PL/EN Atlas key sets are equal;
* dynamic status/action/filter families are covered through explicit catalogs or enumerated accepted keys;
* mutation/fixture tests prove that the guard fails when a translation is removed from either locale.

### P29-LOC-003 — Translation usage is not checked against catalogs strongly enough

#### Problem

A catalog parity test alone can still pass when both catalogs are missing the same key referenced by application code.

#### Target

Permanent localization verification cross-checks Atlas-owned translation usage against both locale catalogs.

Coverage must include current user-facing translation references from:

* Vue pages;
* shared Vue components;
* TypeScript navigation/status/action/localization services;
* backend Inertia props that carry translation keys;
* breadcrumbs;
* Atlas-owned validation/helper/flash copy where namespaced translation keys are used.

Dynamic keys must use registered finite catalogs rather than becoming scanner blind spots.

#### Done

* a referenced key absent from both catalogs fails the quality gate;
* a key absent from only one locale fails the quality gate;
* the test contains explicit mutation fixtures proving both failures;
* there is no broad ignore rule capable of hiding an entire application namespace.

### P29-LOC-004 — Rendered localization acceptance is incomplete

#### Problem

Static catalog checks cannot prove what the browser actually renders.

#### Target

Playwright verifies rendered Polish and English product surfaces.

At minimum, every current route-backed shell and every major Admin/user/manager route represented by the canonical route/view contract must be rendered in both supported locales where the route is applicable.

Tests must reject:

* `[translation:...]`;
* visible Atlas translation-key prefixes such as `pages.`, `navigation.`, `actions.`, `auth.`, `breadcrumbs.`, `datatable.`, `filters.` when they appear as untranslated product copy;
* accidental missing-key placeholders.

Approved technical diagnostics shown intentionally on Admin technical surfaces are not translation-key exceptions unless explicitly documented.

#### Done

Rendered UI does not expose Atlas localization keys in accepted PL/EN product flows.

---

### P29-TABLE-001 — `DataTable.vue` still owns god-component responsibilities

#### Problem

Phase 28 accepted a decomposed DataTable contract, but the central `DataTable.vue` still coordinates and directly owns substantial implementation logic for multiple concerns.

A component-size threshold and the presence of a few extracted child components are not sufficient evidence of responsibility decomposition.

#### Target

`DataTable.vue` becomes a composition/orchestration component.

Focused units own the implementation logic for at least:

* query/applied state synchronization;
* sorting and pagination state;
* column visibility/order;
* selection;
* persisted local table state;
* saved-view state and mutations;
* row/bulk action execution and confirmation;
* formatting;
* server/client table-state adaptation;
* loading/empty/error result presentation.

Existing public DataTable behavior must remain compatible for current consumers unless an intentional typed contract migration is performed across all consumers in this same workstream.

Do not create duplicate table systems.

#### Done

* DataTable is composed from focused components/composables/services;
* extracted units have meaningful unit/component coverage;
* central DataTable does not directly implement every responsibility above;
* ordinary existing tables continue using the canonical DataTable contract;
* saved views, filtering, sorting, selection, row actions, bulk actions, exports, responsive behavior, and states remain functional.

### P29-TABLE-002 — DataTable responsibility guard is too weak

#### Problem

A guard based primarily on a line-count ceiling can pass while a god-component still owns most table behavior.

#### Target

Permanent guardrails verify architecture/responsibility boundaries rather than only file length.

A line-count threshold may exist as a secondary signal but MUST NOT be the primary acceptance criterion.

Tests must prove that extracted responsibilities stay outside the central component and that reintroducing known ownership logic fails the guard.

#### Done

* responsibility guard is semantic/structural enough to detect re-centralization;
* mutation fixtures prove the guard fails;
* DataTable focused units have direct tests;
* existing DataTable consumer behavior remains covered.

---

### P29-TEAM-001 — Team Structure Editor does not own the complete accepted membership workflow

#### Problem

The integrated Teams structure surface exists, but the accepted Phase 28 contract requires team structure editing to include team members, add/remove membership, current/history membership state, and hierarchy in one canonical team context.

Parallel mutation workflows must not remain split across unrelated team surfaces.

#### Target

The Team Structure Editor is the canonical team-context surface for:

* active members;
* membership history;
* adding a member;
* ending/removing active membership;
* head-manager state;
* manager/report relationships;
* direct reports;
* subtree/hierarchy visualization.

The editor may reuse Teams-owned backend use cases and contracts. Do not duplicate persistence logic.

If Team Edit currently exposes a competing membership mutation workflow, remove the duplicate mutation path after the structure editor replacement is complete. A summary/link from Team Edit to Team Structure is acceptable.

#### Done

Membership and hierarchy can be coherently managed from Team Structure without a second competing administration workflow.

### P29-TEAM-002 — Manager move/reparent is not an explicit atomic workflow

#### Problem

Ending an old relationship and creating a new relationship as separate user operations is not equivalent to an accepted move/reparent operation.

#### Target

Provide a semantic manager-hierarchy move/reparent use case.

A move must atomically:

1. identify the current active parent relationship;
2. validate the new manager;
3. validate team membership and active membership;
4. reject self-management;
5. reject DAG cycles;
6. preserve effective-dated history;
7. enforce optimistic concurrency/stale-write protection;
8. enforce head-manager invariants;
9. apply accepted active-process blockers;
10. close the previous relationship and create the new relationship in one transaction;
11. record canonical audit evidence for the semantic move.

No partially moved hierarchy may remain if any step fails.

#### Done

A report/subtree can be reparented through one accepted workflow with transaction, validation, concurrency, history, and audit coverage.

### P29-TEAM-003 — Team Structure destructive/blocking rules require complete enforcement

#### Target

Revalidate and permanently test the accepted Team Structure invariants:

* manager and report must be eligible active team members;
* no self-manager relationship;
* no cycles;
* no invalid effective-date overlap;
* last required head manager cannot be removed;
* head-manager changes remain valid;
* operations blocked by active processes are rejected where the current accepted contract requires it;
* stale versions are rejected;
* failed changes do not leave partial hierarchy state;
* audit events represent successful and rejected high-value hierarchy mutations according to the accepted audit policy.

#### Done

Backend feature/integration tests prove all accepted invariants and transaction boundaries.

### P29-TEAM-004 — Team Structure browser acceptance is incomplete

#### Target

Add real Playwright workflows, not only route/heading assertions.

Cover at minimum:

* open Team Structure from Teams;
* inspect active members;
* inspect membership/history state;
* add member;
* remove/end membership;
* assign/change head manager where permitted;
* create manager/report relationship;
* move/reparent an existing report;
* reject invalid self/cycle/stale/blocking operation;
* verify preserved hierarchy after refresh;
* mobile interaction for the critical editor workflow;
* keyboard/focus behavior for expandable/interactive controls.

Do not restore a separate `/admin/managers` area.

#### Done

The canonical team structure can be administered through browser-level tests without a separate Managers CRUD.

---

### P29-TT-001 — TimeTracking user workflows lack required browser-level closure

#### Problem

Current Playwright coverage does not prove the accepted TimeTracking user workflows.

#### Target

Using deterministic E2E fixtures and current canonical TimeTracking routes, add browser-level coverage for representative user workflows.

Cover, where present in the accepted current implementation:

* work-time report/navigation;
* active work session lifecycle;
* break lifecycle;
* work outside the computer / other work;
* return/resume transitions;
* session ending/final state;
* correction-request creation;
* relevant validation/error states;
* offline/activity/lock or transition behavior where browser interaction is part of the accepted contract.

Do not invent new routes solely for tests.

#### Done

Critical user TimeTracking behavior is exercised by Playwright rather than inferred from backend tests.

### P29-TT-002 — TimeTracking manager composition and decision workflows lack required E2E

#### Target

Add Playwright coverage for the accepted manager scope.

Cover representative:

* manager TimeTracking navigation;
* scoped report;
* direct-report/subtree visibility;
* report filters and section switching;
* correction/decision workflow;
* accepted manager operations;
* permission/scope denial for data outside manager scope;
* resulting user-visible state after manager action.

#### Done

Manager TimeTracking composition/parity is proven through browser behavior and authorization boundaries.

### P29-TT-003 — TimeTracking Admin, maintenance, correction, and notification workflows lack browser closure

#### Target

Add browser-level coverage for representative accepted Admin/operational workflows including:

* Admin TimeTracking report/operations;
* maintenance workflow and affected-session behavior;
* correction state transition;
* manager/Admin decision outcome where applicable;
* resulting notification or user-visible delivery state where the current contract promises notification;
* high-risk/permission confirmation where applicable;
* canonical status rendering and translated copy.

Tests may use backend fixture setup where creating the prerequisite historical state entirely through UI would add noise, but the product action and observable result being verified must occur through the browser.

#### Done

The previously accepted TimeTracking operational contract has representative browser proof.

### P29-TT-004 — TimeTracking E2E must verify browser quality, not only successful navigation

#### Target

New TimeTracking Playwright tests must additionally protect:

* canonical route model;
* no legacy manager-report route;
* no unexpected 4xx/5xx during tested flows;
* browser console cleanliness;
* failed asset/API request detection;
* relevant PL/EN rendered copy;
* mobile behavior for at least the critical user or manager workflow;
* current shared status/action/table contracts.

Run the new scenarios in every Playwright browser project required by the repository quality gate.

#### Done

TimeTracking browser coverage is an application workflow test, not a heading smoke test.

---

### P29-GATE-001 — Phase 29 final acceptance

Phase 29 is complete only when all four repair areas above are actually closed.

The final gate must prove:

* no missing Atlas translation keys in either locale;
* no used Atlas translation key absent from catalogs;
* no raw missing-translation marker on accepted rendered routes;
* DataTable responsibility decomposition is real and permanently guarded;
* Team Structure is the complete canonical membership/hierarchy mutation surface;
* move/reparent is semantic and atomic;
* Team Structure browser workflows pass;
* TimeTracking user/manager/Admin critical E2E passes;
* no separate Managers area returns;
* no legacy TimeTracking manager-report route returns;
* intentionally empty regular-user and manager dashboards remain intentionally empty;
* existing Phase 28 architecture, Audit, migration, seeder, bilingual-mail, runtime, and module-boundary guards continue passing.

## Detailed tasks by workstream

### P29-W01 — Localization completeness and raw-key elimination

* [x] Inventory every Atlas-owned user-facing translation-key reference used by the current frontend and backend-rendered UI.
* [x] Complete missing Polish translations.
* [x] Complete missing English translations.
* [x] Enforce PL/EN Atlas catalog parity.
* [x] Enforce used-key → PL catalog existence.
* [x] Enforce used-key → EN catalog existence.
* [x] Register/enumerate accepted dynamic translation families.
* [x] Add mutation fixtures proving the localization guards fail when a used translation is removed from PL.
* [x] Add mutation fixtures proving the localization guards fail when a used translation is removed from EN.
* [x] Remove broad localization scanner exclusions that can hide whole namespaces.
* [x] Add rendered Playwright detection for `[translation:...]`.
* [x] Add rendered Playwright detection for visible untranslated Atlas key namespaces.
* [x] Walk the canonical route/view set in Polish.
* [x] Walk the canonical route/view set in English.
* [x] Update frontend/localization/glossary documentation.
* [x] Close `P29-LOC-001` through `P29-LOC-004`.

### P29-W02 — DataTable responsibility decomposition

* [x] Inventory responsibilities still directly owned by `DataTable.vue`.
* [x] Extract query/applied state coordination.
* [x] Extract sorting/pagination coordination.
* [x] Extract column visibility/order responsibility.
* [x] Extract selection responsibility.
* [x] Extract persisted local table-state responsibility.
* [x] Extract saved-view orchestration.
* [x] Extract row/bulk action execution and confirmation responsibility.
* [x] Keep formatting in focused formatting services/composables.
* [x] Keep loading/empty/error/result states in focused shared units.
* [x] Preserve current DataTable consumer API or migrate all consumers coherently in this workstream.
* [x] Add direct tests for extracted units.
* [x] Replace line-count-only responsibility evidence with permanent structural/responsibility guards.
* [x] Add mutation fixtures proving responsibility guardrails fail when known logic is moved back into the central component.
* [x] Re-run DataTable saved-view/filter/sort/pagination/selection/action/export/state tests.
* [x] Update frontend/table architecture documentation.
* [x] Close `P29-TABLE-001` and `P29-TABLE-002`.

Acceptance evidence: the 1,173-line host inventory identified direct ownership of every registered responsibility. The host now retains its existing props and `bulkAction` event as a 352-line composition surface backed by `useDataTableController`, focused pure state units, existing saved-view/pagination/result components, and the existing formatting service. Vitest directly covers the extracted query, filter, sorting, pagination, column, selection, local-persistence, and saved-view units. The permanent responsibility guard rejects re-centralization and its mutation fixtures cover every known responsibility family plus removal of a required focused unit. The targeted PHP table-registration test and the full frontend Vitest/typecheck/lint/build gates pass.

### P29-W03 — Complete Team Structure Editor

* [ ] Make active team membership visible in Team Structure.
* [ ] Make membership history accessible in Team Structure.
* [ ] Add member workflow in Team Structure.
* [ ] End/remove membership workflow in Team Structure.
* [ ] Preserve/reuse canonical Teams-owned backend membership use cases.
* [ ] Remove competing membership mutation UI outside Team Structure after replacement is complete.
* [ ] Preserve a summary/link from Team Edit if useful, but not a second mutation workflow.
* [ ] Keep head-manager management inside Team Structure.
* [ ] Keep manager/report hierarchy inside Team Structure.
* [ ] Implement semantic atomic move/reparent use case.
* [ ] Preserve effective-dated relationship history.
* [ ] Enforce active membership validation.
* [ ] Enforce self-cycle and DAG validation.
* [ ] Enforce stale-write/optimistic-concurrency validation.
* [ ] Enforce head-manager invariant.
* [ ] Enforce accepted active-process blocker behavior.
* [ ] Ensure failed mutations roll back completely.
* [ ] Add canonical audit evidence for move/reparent and membership/hierarchy mutations.
* [ ] Add backend tests for add/remove/history/move/reparent/invariants/concurrency/rollback/audit.
* [ ] Add desktop Playwright Team Structure workflow.
* [ ] Add mobile Playwright Team Structure workflow.
* [ ] Add keyboard/focus assertions for the critical editor interactions.
* [ ] Confirm `/admin/managers` and duplicate Managers CRUD remain absent.
* [ ] Update Teams/Authorization/frontend documentation.
* [ ] Close `P29-TEAM-001` through `P29-TEAM-004`.

### P29-W04 — TimeTracking browser-level E2E closure

* [ ] Add deterministic E2E fixture support required by the browser workflows without bypassing domain invariants.
* [ ] Add TimeTracking user workflow Playwright coverage.
* [ ] Add session/break/other-work representative browser lifecycle coverage.
* [ ] Add correction-request browser coverage.
* [ ] Add manager report/scope browser coverage.
* [ ] Add manager correction/decision browser coverage.
* [ ] Add out-of-scope authorization negative coverage.
* [ ] Add Admin TimeTracking operations browser coverage.
* [ ] Add maintenance/affected-session representative browser coverage.
* [ ] Add resulting notification/user-visible delivery assertion where promised by the accepted workflow.
* [ ] Add high-risk confirmation coverage where the accepted workflow requires it.
* [ ] Verify canonical statuses/actions/tables in tested workflows.
* [ ] Verify Polish rendered TimeTracking copy.
* [ ] Verify English rendered TimeTracking copy.
* [ ] Add mobile coverage for a critical user or manager TimeTracking workflow.
* [ ] Reject unexpected browser console errors.
* [ ] Reject unexpected failed asset/API requests.
* [ ] Confirm legacy manager-report routes remain absent.
* [ ] Run TimeTracking scenarios in all browser projects required by the repository Playwright gate.
* [ ] Update TimeTracking/testing documentation.
* [ ] Close `P29-TT-001` through `P29-TT-004`.

### P29-W05 — Final Phase 29 regression and acceptance gate

* [ ] Confirm every Phase 29 issue ID is closed with implementation, focused tests, permanent guardrails, documentation, and legacy-removal evidence where applicable.
* [ ] Run targeted localization tests.
* [ ] Run targeted DataTable/frontend tests.
* [ ] Run targeted Teams/Authorization tests.
* [ ] Run targeted TimeTracking backend/frontend tests.
* [ ] Run full Playwright gate required by Atlas.
* [ ] Run `composer check`.
* [ ] Run `composer check:foundation`.
* [ ] Confirm permanent guardrails are non-vacuous and mutation-tested where required.
* [ ] Confirm Phase 28 remains historical and is not rewritten into an active phase.
* [ ] Confirm Phase 30 deployment remains `not started`.
* [ ] Confirm Phase 31 final verification remains `not started`.
* [ ] Update affected canonical architecture/module/testing documentation.
* [ ] Update `WORKROAD.md` to mark Phase 29 complete only after all acceptance criteria are satisfied.
* [ ] Record final Phase 29 quality-gate evidence.

## Required permanent guardrails

* [x] PL/EN catalog parity guard.
* [x] Used translation key exists in PL guard.
* [x] Used translation key exists in EN guard.
* [x] Dynamic translation-family registration guard.
* [x] Missing-translation mutation fixtures.
* [x] Rendered missing-key Playwright assertion.
* [ ] DataTable responsibility-boundary guard.
* [ ] DataTable responsibility mutation fixture.
* [ ] No separate Managers-area regression guard.
* [ ] Team Structure canonical-mutation-surface guard.
* [ ] Team hierarchy atomic move/reparent tests.
* [ ] Team hierarchy DAG/self/head-manager/membership/stale/blocker regression tests.
* [ ] Legacy TimeTracking manager-report absence guard.
* [ ] TimeTracking user/manager/Admin Playwright workflow coverage.
* [ ] Browser console and failed-request assertions for new critical E2E flows.

## Completion criteria

* [x] Every `P29-LOC-*` issue is complete.
* [ ] Every `P29-TABLE-*` issue is complete.
* [ ] Every `P29-TEAM-*` issue is complete.
* [ ] Every `P29-TT-*` issue is complete.
* [ ] `P29-GATE-001` is complete.
* [x] No Atlas-owned translation key is visibly rendered as untranslated product copy in the accepted browser route matrix.
* [x] Polish and English Atlas translation catalogs are complete and permanently guarded against drift.
* [ ] `DataTable.vue` is genuinely composed from focused responsibility units rather than passing only a size threshold.
* [ ] Team Structure is the canonical complete team membership and manager-hierarchy editing surface.
* [ ] Manager move/reparent is atomic, validated, concurrency-safe, effective-dated, and audited.
* [ ] TimeTracking has representative browser-level user, manager, and Admin workflow coverage rather than only route smoke tests.
* [ ] Intentionally empty user and manager dashboards remain intentionally empty.
* [ ] No Phase 30 deployment implementation has been pulled into Phase 29.
* [ ] No Phase 31 final whole-app verification has been falsely claimed by Phase 29.
* [ ] `composer check` passes.
* [ ] `composer check:foundation` passes.
* [ ] Canonical documentation matches the implemented behavior.
* [ ] `WORKROAD.md` marks Phase 29 complete only after every criterion above is true.
