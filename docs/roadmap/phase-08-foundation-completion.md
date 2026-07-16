## Phase 8 — Foundation completion and roadmap dependency repair

**Status:** `complete`

## Objective

Close the partial foundations that were pulled forward during phases 5-7 before Atlas starts the next implementation phase.

This phase is deliberately corrective. It does not implement new business functionality. It makes the already-used module, authorization, Admin UI, table, visibility, audit-recording, and documentation foundations complete enough for every later phase to depend on them without local substitutes or provisional contracts.

## Dependencies

- [Phase 5 — Modular architecture skeleton](phase-05-modular-architecture.md)
- [Phase 6 — Core identity and authentication](phase-06-identity-authentication.md)
- [Phase 7 — Authorization and teams](phase-07-authorization-teams.md)
- [Modular monolith architecture](../architecture/modular-monolith.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)
- [Tables, reports, exports, charts, and print](../architecture/tables-reports-exports-and-print.md)
- [Module registry and activation](../architecture/module-registry-and-activation.md)
- [Testing environment](../operations/testing-environment.md)

## Implementation contract

- Treat completed phases 0-7 as historical record; do not delete or rewrite their completed checkboxes.
- Resolve the stale Phase 5 blocked state now that Phase 6 and Phase 7 authorization/team primitives exist.
- Complete the first permission/module-gated UI visibility e2e scenarios required by Phase 5 using explicit users, teams, permissions, active-team state, and module-state fixtures.
- Verify that the current Admin Users, Teams, Roles, Permissions, and onboarding preset screens use shared UI/table primitives consistently and do not retain local one-off table, modal, confirmation, toast, or flash behavior that would be replaced later.
- Record any remaining shared UI/table gaps as explicit open tasks in Phase 9 or Phase 10, not as hidden assumptions.
- Verify that Phase 7's current DataTable foundation is documented as an existing limited foundation, while the full server-side table/saved-view contract remains Phase 10.
- Verify that existing security-audit recording used by Identity and Authorization is documented as a temporary/core recorder that Phase 11 must consolidate into the full Audit module, not as the final audit architecture.
- Verify that current module activation state shown in permission screens is clearly documented as based on the current ModuleGate/deployed-state primitives until Phase 14 implements operational activation.
- Update roadmap phase dependencies so no future phase knowingly uses a shared capability before the phase that completes its currently known contract.
- Add the permanent roadmap-planning rule to the repository working rules.

## Tasks

- [x] Audit phases 5-7 implementation against the later shared UI, table, ModuleGate, audit, session, and authorization contracts.
- [x] Resolve the Phase 5 status in `WORKROAD.md` after verifying the only remaining open visibility item has a current executable location.
- [x] Add permission-gated UI visibility e2e coverage for current Admin screens.
- [x] Add module-gated UI visibility e2e coverage for current Admin/composable-view behavior using the current ModuleGate primitives.
- [x] Verify current Admin tables, row actions, bulk actions, modal confirmations, flash/toast messages, and export actions use shared primitives.
- [x] Remove or replace any remaining local one-off Admin UI primitives that duplicate the shared foundation.
- [x] Update architecture documentation for current DataTable scope versus the full Phase 10 table contract.
- [x] Update audit documentation to distinguish existing security-audit recording from the full Phase 11 Audit module.
- [x] Update module-activation documentation to distinguish deployed-state/module-gate primitives from full operational activation.
- [x] Update roadmap phase dependencies and completion criteria for all future phases.
- [x] Add the dependency-first roadmap-planning rule to `AGENTS.md`.
- [x] Run relevant backend, frontend, and Playwright checks for the repaired foundations.
- [x] Commit foundation completion and roadmap dependency repair.

## Completion criteria

- [x] All already-used shared foundations are either complete for their current contract or have an explicit next phase before any broader use.
- [x] No future phase depends on a known shared capability whose full current contract is still scheduled later.
- [x] Phase 5 is no longer blocked by already-completed Phase 6/7 primitives.
- [x] Documentation names the urgent closure work instead of treating partial implementations as final.
- [x] Relevant automated checks pass.
