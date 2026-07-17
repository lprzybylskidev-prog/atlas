## Phase 17 — Manager hierarchy

**Status:** `complete`

## Objective

Implement team-scoped manager hierarchy after active-team context, audit, settings, shared UI/tables, module activation, and Admin operational visibility are complete.

## Dependencies

- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Teams and manager hierarchy](../modules/teams-and-manager-hierarchy.md)

## Implementation contract

- Manager relationships are scoped to a team.
- A user may have multiple direct managers.
- Managers may supervise other managers.
- The relationship graph is directed and acyclic.
- Self-management and cycles are forbidden at domain and database/application validation levels.
- Relationships have `valid_from`, `valid_to`, complete history, actor, reason, and audit.
- A team assignment may carry a `head manager` flag.
- A normal manager sees direct reports only.
- A head manager sees the full subtree beneath them, still restricted by permissions.
- Admin provides manager management from the beginning:
  - assign manager relationships;
  - end relationships rather than destructively delete history;
  - assign/remove head-manager status;
  - view hierarchy tree;
  - filter by team;
  - preview the impact before saving;
  - show cycle errors clearly;
  - require a reason;
  - audit every change.
- Manager permissions are granular for read, create, update, terminate relationship, view tree, view history, and head-manager operations.
- The hierarchy is reused by TimeTracking. Any future substitution or delegated-approval mechanism requires a separate explicit design decision.

## Tasks

- [x] Implement team-scoped manager relationships.
- [x] Support multiple direct managers.
- [x] Support manager-to-manager supervision.
- [x] Support head manager per team assignment.
- [x] Add `valid_from` and `valid_to`.
- [x] Preserve full relationship history.
- [x] Prevent self-management.
- [x] Prevent cycles.
- [x] Implement direct-report scope.
- [x] Implement head-manager subtree scope.
- [x] Build manager hierarchy query/read models.
- [x] Build admin manager-management screens.
- [x] Add hierarchy tree view.
- [x] Add team filter.
- [x] Add impact preview before changes.
- [x] Require reason for changes.
- [x] Audit all changes.
- [x] Add permissions for read, create, update, and end relationship.
- [x] Add development-only demo seeders for example manager relationships after real hierarchy tables exist.
- [x] Commit manager hierarchy.

## Completion criteria

- [x] Manager hierarchy has complete historical, audited, team-scoped DAG behavior.
- [x] Direct-report and subtree scopes are exposed through stable contracts for TimeTracking and later modules.
- [x] Admin screens use shared UI/table primitives.
- [x] Relevant tests and documentation are current.
