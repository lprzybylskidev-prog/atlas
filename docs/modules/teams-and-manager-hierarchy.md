# Teams and manager hierarchy

Canonical behavior for teams, assignments, effective dates, manager DAG, direct reports, subtree scope, and manager administration.

## Teams and Managers

A user may belong to multiple teams but has one active team per session.

Current implementation foundation:

- `App\Modules\Core\Teams\TeamsModule` owns team identity and team permission declarations;
- `teams.id` is the internal BIGINT identifier;
- `teams.public_id` is the public ULID identifier;
- `App\Modules\Core\Teams\Domain\ValueObjects\TeamPublicId` is the typed domain identifier for team public IDs;
- `team_user_assignments` stores the current team membership foundation used by active-team authorization checks.
- `App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider` exposes the narrow public bootstrap contract used by first-administrator, system bootstrap, and development bootstrap flows.
- `App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager` exposes Admin user-team membership operations for adding and removing user-team access from User and Team administration workflows.
- `App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy` exposes stable manager hierarchy reads, impact previews, relationship changes, head-manager changes, and direct-report/subtree scopes for TimeTracking and later modules.

Admin user-team access management:

- Admin user creation requires at least one team assignment;
- Admin user creation/editing and team creation/editing list and manage active user-team memberships;
- Admin team creation/editing also manages team-scoped module activation overrides. The same override can be managed from `/admin/modules/{module}` by attaching teams to a module, and from `/admin/teams/{team}/edit` by attaching modules to a team.
- Admin can add access to an active team the user does not currently belong to, including team-scoped roles and direct permissions;
- Admin can update a user's team-scoped roles and direct permissions from either User or Team administration;
- Admin can remove access from a team only with a reason;
- removing access ends the effective `team_user_assignments` row through `valid_to`, removes user-specific role and direct-permission assignments in that team, audits the operation, and invalidates user sessions operating in that team.

Manager relationships are team-scoped and stored in `core_teams.team_manager_relationships`.

Support:

- multiple direct managers;
- managers supervising managers;
- hierarchical directed acyclic graphs;
- head manager flag per team assignment;
- `valid_from`;
- `valid_to`;
- full history;
- no self-management enforced by database check and application validation;
- no cycles enforced by application DAG validation before a new active relationship is saved.

An active relationship is one whose `valid_from` is null or not in the future and whose `valid_to` is null or in the future. Ending a relationship sets `valid_to`, `ended_by_user_id`, and `end_reason`; historical relationship rows are not destructively deleted.

A normal manager sees direct reports only.

A head manager sees the entire subtree under them, still constrained by permissions.

Admin manager administration starts at `/admin/managers`. The index lists users who are managers in the selected team, exposes filters for manager type and direct/subtree report presence, and links to `/admin/managers/create?team={team}` for adding a new manager relationship. Manager create and detail pages at `/admin/managers/create?team={team}` and `/admin/managers/{user}/edit?team={team}` support:

- selecting a manager context and adding multiple direct-report relationships with one effective date and reason;
- ending manager relationships;
- assigning head managers;
- viewing the hierarchy tree below one manager;
- seeing active direct-report relationship start dates and creation reasons;
- filtering by team;
- validity periods;
- cycle validation;
- impact preview;
- mandatory reason;
- audit.

Audited manager hierarchy actions include `team.manager_relationship.created`, `team.manager_relationship.ended`, and `team.head_manager.updated`.

Granular permissions include `admin.managers.index`, `admin.managers.create`, `admin.managers.edit`, `admin.managers.store`, `admin.managers.end`, `admin.managers.head.update`, `teams.managers.view`, `teams.managers.create`, `teams.managers.update`, `teams.managers.terminate`, `teams.managers.tree`, `teams.managers.history`, and `teams.managers.head.update`.

Development reset does not seed representative manager hierarchies after Phase 25 cleanup. Tests and future business modules must create their own explicit manager fixtures.

---
