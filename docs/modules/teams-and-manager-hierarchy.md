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
- `App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider` exposes the narrow public bootstrap contract used by first-administrator and demo setup flows.
- `App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager` exposes Admin user-team membership operations for adding and removing user-team access from User and Team administration workflows.

Admin user-team access management:

- Admin user creation requires at least one team assignment;
- Admin user creation/editing and team creation/editing list and manage active user-team memberships;
- Admin can add access to an active team the user does not currently belong to, including team-scoped roles and direct permissions;
- Admin can update a user's team-scoped roles and direct permissions from either User or Team administration;
- Admin can remove access from a team only with a reason;
- removing access ends the effective `team_user_assignments` row through `valid_to`, removes user-specific role and direct-permission assignments in that team, audits the operation, and invalidates user sessions operating in that team.

Manager relationships are team-scoped.

Support:

- multiple direct managers;
- managers supervising managers;
- hierarchical directed acyclic graphs;
- head manager flag per team assignment;
- `valid_from`;
- `valid_to`;
- full history;
- no self-management;
- no cycles.

A normal manager sees direct reports only.

A head manager sees the entire subtree under them, still constrained by permissions.

The Admin panel must support:

- assigning managers;
- ending manager relationships;
- assigning head managers;
- viewing hierarchy trees;
- filtering by team;
- history;
- validity periods;
- cycle validation;
- impact preview;
- mandatory reason;
- audit.

---
