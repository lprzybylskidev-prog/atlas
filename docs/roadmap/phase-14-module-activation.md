## Phase 14 — Module availability and activation

**Status:** `in progress`

## Objective

Complete operational module activation and ModuleGate enforcement after active-team context, audit, settings, shared UI, and table primitives are available.

## Dependencies

- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Module registry and activation](../architecture/module-registry-and-activation.md)
- [Modular monolith architecture](../architecture/modular-monolith.md)

## Implementation contract

- Module lifecycle has two independent levels:
  - deployment availability: whether the code is technically available in this application deployment;
  - operational activation: whether a technically available module is active globally or for a selected team.
- Changing deployment availability requires restart or deployment.
- Operational state may be global, per team, or scheduled for a future effective time.
- Core modules cannot be disabled operationally.
- Admin cannot activate a technically unavailable module.
- Backend enforcement is mandatory; hiding menu items is insufficient.
- Module state controls routes, use cases, menu, permissions, jobs, schedules, reports, listeners, search, and integrations.
- Operational activation uses typed relational tables, not an uncontrolled JSON settings blob.
- Use a current-state plus append-only-history model. Do not reconstruct effective state through event sourcing.
- Use a dedicated global current-state table with one record per module and fields equivalent to:
  - `module_key`;
  - current enabled state;
  - `enabled_from`;
  - `disabled_from`;
  - actor;
  - mandatory reason;
  - optimistic-lock `version`;
  - timestamps.
- Use a dedicated team current-state table with one record per module/team override and fields equivalent to:
  - `module_key`;
  - `team_id`;
  - explicit `enabled` or `disabled` state;
  - `enabled_from`;
  - `disabled_from`;
  - actor;
  - mandatory reason;
  - optimistic-lock `version`;
  - timestamps.
- No per-team current-state row means inheritance from the global state.
- Use a dedicated schedule table with:
  - public ULID;
  - module key;
  - global or team scope;
  - optional team ID;
  - target state;
  - `effective_at`;
  - status: `scheduled`, `applied`, `cancelled`, or `failed`;
  - creator;
  - optional cancellation actor;
  - mandatory scheduling reason;
  - optional cancellation reason;
  - timestamps.
- Use a dedicated append-only history table with:
  - module key;
  - global or team scope;
  - optional team ID;
  - previous state;
  - new state;
  - source: `manual`, `scheduled`, or `system`;
  - optional schedule reference;
  - actor;
  - mandatory reason;
  - effective timestamp;
  - correlation ID;
  - timestamps.
- Read current effective state from the current-state tables, not by replaying history.
- History is append-only and never edited to represent current state.
- Scheduler applies due changes transactionally, updates current state, records schedule outcome, writes history, and invalidates cache.
- Multiple future changes may exist for the same module and scope when they create a valid ordered timeline, for example enable on one date and disable later.
- Reject ambiguous or conflicting schedules for the same module and scope, including contradictory changes at the same effective time.
- Cancelling a schedule never deletes it; set `cancelled`, preserve actor and cancellation reason, and retain it for audit.
- Applied and failed schedules remain available for audit and diagnostics.
- A manual state change may invalidate conflicting future schedules only after impact preview and an explicit administrator decision.
- Every invalidated schedule is cancelled explicitly and audited separately.
- Use optimistic locking on current-state rows to prevent silent concurrent overwrite.
- Cache effective module state in Redis for runtime checks.
- Redis is never the source of truth.
- On cache miss, resolve effective state from PostgreSQL.
- A global state change invalidates the global cache and all affected team-effective-state cache entries.
- A team state change invalidates that team's effective-state cache entry.
- Manual changes, scheduled application, scheduled failure recovery, and cancellation use one shared cache invalidation mechanism.
- Disabling never removes code, data, permission assignments, or migrations and never rolls schema back.
- Re-enabling restores access to existing data.
- All migrations for technically available modules run during deployment even if operationally inactive.
- A module with active unsafe processes cannot be disabled. Examples include active TimeTracking work sessions, running imports, pending critical jobs, or integration transitions that cannot be safely paused.
- Before disabling, show an impact preview including dependent modules, active processes, jobs, schedules, routes, reports, integrations, and affected teams.
- Required dependencies block disabling until dependants are disabled or reconfigured.
- Dependants are never automatically disabled.
- Optional dependencies may switch to a documented reduced mode.
- Dependency validation runs both at application startup and during every Admin change.
- Module activation never grants permissions automatically.
- Permissions remain registered and assignable while their module is inactive so roles can be prepared before activation.
- Backend checks module activity before checking permission.
- Authorization read models and Admin UI distinguish:
  - assigned permission;
  - effective permission;
  - ineffective permission because the module is inactive;
  - ineffective permission for another explicit reason.
- Activating a module warns if nobody in the target team has an effective entry permission.
- Disabling a module preserves role/user assignments. Re-enabling makes them effective automatically when every other authorization condition is satisfied.
- Every activation, deactivation, schedule, cancellation, dependency rejection, cache-invalidating state change, scheduler failure, and failed attempt is audited.
- Permission-package template ownership and application are implemented by the Authorization phase. This phase integrates module state with permission effectiveness after Authorization exists.
- Integrate the central `ModuleGate` into HTTP middleware, Application use cases, jobs, commands, public endpoints, navigation, and composable-view data providers.
- This phase completes the active-team, effective-permission, and module-state enforcement declared by the frontend contracts in Phase 3.
- Deactivation consults every registered active-process guard and returns an exact impact/blocker preview.

## Tasks

- [ ] Integrate `ModuleGate` across HTTP, jobs, commands, public endpoints, navigation, and composable-view data providers.
- [ ] Complete active-team, permission, and module-state enforcement for composable view elements.
- [ ] Add tests proving direct element/data endpoint calls cannot bypass the gate.
- [ ] Aggregate module active-process/deactivation guards into disable impact preview and enforcement.
- [ ] Add tests for deactivation while jobs/processes are running.
- [ ] Implement deployment-level technical module availability.
- [ ] Implement global operational activation.
- [ ] Implement per-team operational activation.
- [ ] Implement inheritance from global to team state.
- [ ] Implement scheduled future activation and deactivation.
- [ ] Store activation in typed relational tables.
- [ ] Implement separate global and per-team current-state tables.
- [ ] Implement append-only module-state history.
- [ ] Implement scheduled state changes with `scheduled`, `applied`, `cancelled`, and `failed` statuses.
- [ ] Implement valid ordered future enable/disable timelines.
- [ ] Reject ambiguous or conflicting schedules for the same module and scope.
- [ ] Implement explicit schedule cancellation with actor and reason.
- [ ] Implement manual-change impact preview for conflicting future schedules.
- [ ] Implement optimistic locking on current-state rows.
- [ ] Cache effective module state in Redis without making cache the source of truth.
- [ ] Implement global and team-scoped cache invalidation for manual and scheduled changes.
- [ ] Add scheduler failure diagnostics and audit.
- [ ] Store effective dates, actor, reason, and history.
- [ ] Prevent operational disabling of Core modules.
- [ ] Prevent admin activation of technically unavailable modules.
- [ ] Enforce module state on backend routes and use cases.
- [ ] Enforce module state on menus.
- [ ] Enforce module state on permissions.
- [ ] Enforce module state on jobs and schedules.
- [ ] Enforce module state on reports and integrations.
- [ ] Validate required dependencies during runtime changes.
- [ ] Support reduced mode for optional dependencies.
- [ ] Block disabling while unsafe active processes exist.
- [ ] Show dependency and impact preview before changes.
- [ ] Require permission and reason for changes.
- [ ] Audit every activation change.
- [ ] Ensure disabling never deletes data or rolls back migrations.
- [ ] Ensure re-enabling restores access to existing data.
- [ ] Add admin UI for module activation.
- [ ] Add tests for global and per-team activation.
- [ ] Ensure module activation never grants permissions automatically.
- [ ] Keep module permissions registered even when the module is inactive.
- [ ] Allow inactive-module permissions to be assigned to roles in preparation for later activation.
- [ ] Mark inactive-module permissions as ineffective in the current global/team context.
- [ ] Enforce module activity before evaluating route or business permissions.
- [ ] Distinguish assigned, effective, and ineffective permissions in authorization read models.
- [ ] Add an admin permission report showing why an assigned permission is ineffective.
- [ ] Show an activation warning when no role or user in the target team has an effective entry permission for the module.
- [ ] Keep permission assignments intact when a module is disabled.
- [ ] Restore permission effectiveness automatically when the module is re-enabled and all other authorization conditions are met.
- [ ] Add tests proving that activation alone grants no access.
- [ ] Add tests proving that assigned permissions remain ineffective while the module is disabled.
- [ ] Add tests proving that existing assignments become effective after safe module reactivation.
- [ ] Commit module activation system.

## Completion criteria

- [ ] Operational activation is stored in typed relational current-state, schedule, and append-only history tables.
- [ ] Backend, jobs, commands, navigation, permissions, and composable-view data providers enforce the central ModuleGate.
- [ ] Deactivation previews and blockers use registered guards instead of foreign-table inspection.
- [ ] Later optional modules can rely on activation without building their own gating.
