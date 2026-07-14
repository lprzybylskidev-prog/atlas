## Phase 9 — Manager hierarchy

### Implementation contract

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

- [ ] Implement team-scoped manager relationships.
- [ ] Support multiple direct managers.
- [ ] Support manager-to-manager supervision.
- [ ] Support head manager per team assignment.
- [ ] Add `valid_from` and `valid_to`.
- [ ] Preserve full relationship history.
- [ ] Prevent self-management.
- [ ] Prevent cycles.
- [ ] Implement direct-report scope.
- [ ] Implement head-manager subtree scope.
- [ ] Build manager hierarchy query/read models.
- [ ] Build admin manager-management screens.
- [ ] Add hierarchy tree view.
- [ ] Add team filter.
- [ ] Add impact preview before changes.
- [ ] Require reason for changes.
- [ ] Audit all changes.
- [ ] Add permissions for read, create, update, and end relationship.
- [ ] Add development-only demo seeders for example manager relationships after real hierarchy tables exist.
- [ ] Commit manager hierarchy.
