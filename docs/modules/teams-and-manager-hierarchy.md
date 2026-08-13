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
- `App\Shared\Application\Teams\Contracts\TeamLookup` exposes owner-owned public ID/internal ID resolution, active-team validation lookups, active user-team assignment ID/summary lookups, active head-manager existence checks, all-team cache invalidation IDs, all-team lookup summaries, internal-ID summary maps, and display summaries for cross-module read/runtime surfaces that need team labels, assignment labels, head-manager eligibility, active-team state, authorization context, or impersonation/session display without querying Teams tables.
- `App\Shared\Application\Teams\Contracts\UserTeamMembershipManager` exposes Admin user-team membership operations for adding and removing user-team access from User and Team administration workflows. Teams resolves user public IDs, names, and email labels through Identity `UserLookup`; membership reads do not query Identity tables directly.
- `App\Shared\Application\Teams\Contracts\UserTeamMembershipProvisioner` exposes the narrow owner-owned membership provisioning operation used by Authorization assignment/bootstrap/copy flows that must ensure a team assignment exists before assigning team-scoped roles or permissions.
- `App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy` exposes stable manager hierarchy reads, relationship and structural-role impact previews, atomic relationship and structural-role changes, and manager scopes for TimeTracking and later modules. Manager hierarchy persistence reads Teams-owned relationship rows first and enriches user display data through Identity `UserLookup`.

Admin user-team access management:

- Admin user creation requires at least one team assignment;
- Admin user creation/editing manages user-context team access. Team creation may establish initial assignments, while Team Structure is the only team-context surface that adds or ends memberships after creation. Team Edit links to Team Structure and does not expose a competing membership mutation workflow;
- Team Edit shows current member authorization only as read-only context, with explicit role/direct-permission counts and no authorization or membership mutation controls;
- Admin team creation/editing also manages team-scoped module activation overrides. The same override can be managed from `/admin/modules/{module}` by attaching teams to a module, and from `/admin/teams/{team}/edit` by attaching modules to a team.
- Admin can add access to an active team the user does not currently belong to, including team-scoped roles and direct permissions;
- Admin can update a user's team-scoped roles and direct permissions from User administration; Team Structure owns team-context membership and hierarchy mutations;
- Admin can remove access from a team only with a reason;
- removing access ends the effective `team_user_assignments` row through `valid_to`, removes user-specific role and direct-permission assignments in that team, audits the operation, and invalidates user sessions operating in that team.

## Privacy Lifecycle

Teams registers `TeamUserDataLifecycleParticipant` for `user` subjects. Privacy execution does not delete teams or historical relationship rows. It ends active team assignments while preserving their structural-role history, ends active manager relationships where the user is manager or report, and redacts actor-only references (`created_by_user_id`, `ended_by_user_id`) to preserve hierarchy history without keeping unnecessary personal actor links.

Manager relationships are team-scoped and stored in `core_teams.team_manager_relationships`.

Support:

- multiple direct managers;
- managers supervising managers;
- hierarchical directed acyclic graphs;
- one explicit Teams-owned structural role (`employee`, `manager`, or `head_manager`) per team assignment, separate from Authorization roles;
- `valid_from`;
- `valid_to`;
- full history;
- no self-management enforced by database check and application validation;
- no cycles enforced by application DAG validation before a new active relationship is saved.

An active relationship is one whose `valid_from` is null or not in the future and whose `valid_to` is null or in the future. Ending a relationship sets `valid_to`, `ended_by_user_id`, and `end_reason`; historical relationship rows are not destructively deleted.

A normal Manager may exist with zero direct reports. Normal relationship edges may connect a Manager to an Employee or another Manager, and one subordinate may have multiple Managers while the graph remains acyclic. An Employee cannot own outgoing relationships, and a Head Manager cannot participate in incoming or outgoing normal relationship edges.

A normal Manager sees direct reports according to the current manager-scope consumer contract. A Head Manager sees the whole active Team without synthetic relationship edges, still constrained by permissions.

Manager hierarchy administration is integrated into the owning team at `/admin/teams/{team}/structure`. The Team Edit action opens this editor; there is no separate Managers Admin area. The editor supports:

- viewing active members and keyboard-expandable effective-dated membership history;
- adding team members and ending active membership with a mandatory reason through the Teams-owned membership use case;
- adding a Manager relationship through desktop drag-and-drop or the equivalent keyboard/mobile action, with an effective date and reason while preserving existing Managers;
- ending manager relationships;
- previewing and changing a member's structural role with a mandatory reason, optimistic concurrency, last-required-Head-Manager protection, and atomic cleanup of relationship edges invalidated by the transition;
- viewing concise role-specific cards and expandable relationship details instead of a primary hierarchy tree;
- seeing active direct-report relationship start dates and creation reasons;
- filtering by team;
- validity periods;
- cycle validation;
- impact preview;
- mandatory reason;
- audit;
- optimistic concurrency through a structure version;
- protection against removing the last active head manager;
- membership-removal blocking while the member is a head manager or participates in active manager relationships;
- one responsive and keyboard-accessible team-context surface with an explicit empty state.

Audited manager hierarchy actions include `team.manager_relationship.created`, `team.manager_relationship.ended`, `team.structural_role.changed`, and `team.structural_role.change_rejected`. Successful membership, relationship, and structural-role evidence is persisted in the same transaction as the state change; an audit failure rolls the mutation back. Rejected structural-role evidence is recorded only after the attempted business-state transaction has rolled back.

Granular Admin route permissions are `admin.teams.structure.show`, `admin.teams.structure.relationships.store`, `admin.teams.structure.relationships.end`, and `admin.teams.structure.structural-role.update`. Manager application/scope permissions remain `teams.managers.view`, `teams.managers.create`, `teams.managers.update`, `teams.managers.terminate`, `teams.managers.tree`, `teams.managers.history`, and `teams.managers.head.update`.

Development reset does not seed generic representative manager hierarchies after Phase 25 cleanup. The Phase 27 TimeTracking development demo is the current explicit exception: it creates a small manager hierarchy only for TimeTracking review data. Tests and future business modules must create their own explicit manager fixtures.

The exception uses Teams membership and `ManagerHierarchy` contracts. Repeated seeding preserves active membership validity, the two Head Manager structural roles, the three Manager structural roles, the 51-edge acyclic hierarchy, and existing relationship public IDs; seeder classes do not write Teams tables.

Atlas is still before its first production deployment, so the explicit structural-role column and constraint replace the legacy boolean in the canonical Teams create migration rather than in a follow-up compatibility migration. Existing development and test databases adopt that canonical schema through the standard fresh-migration workflow. Deterministic development/e2e reconstruction assigns every Head Manager first, then every active relationship owner as Manager, and leaves remaining members as Employee; it preserves effective-dated membership and relationship history instead of rewriting old rows into synthetic role history.

---

## Phase 28 foundation repair target

Current state: Teams owns membership and the integrated team structure editor. The editor groups members once by explicit structural role, gives Head Managers whole-Team scope, adds Manager relationships through desktop drag-and-drop or the equivalent keyboard/mobile action, and changes structural roles through one reasoned impact-preview workflow. Adding a Manager preserves existing Manager relationships. The earlier tree/reparent-centric editor and its unused atomic reparent contract were removed after the additive relationship workflow became canonical. Phase 28 removed the duplicated separate Admin Managers area after route, permission, UI, DAG, audit, concurrency, and legacy-reference coverage was moved to the Teams surface.

Target state: Teams owns team membership, active-team validation, public team summaries, manager DAG, head-manager protection, and the integrated team structure editor. The separate Managers CRUD/Admin area is removed while manager panel and manager scope remain. Phase 28 boundary slices added `TeamLookup` display summaries, public/internal ID resolution, active-team validation, active user-team assignment ID/summary lookups, active head-manager checks, all-team internal ID enumeration, all-team summaries, and internal-ID summary maps so Audit browser filters, Notifications delivery/realtime paths, ModuleGate, module activation cache invalidation, Admin System Status active-team resolution, TimeTracking tracked assignment/report/break-policy reads, TimeTracking closed-period eligibility, and Admin module activation team/history/schedule surfaces no longer query Teams tables directly. Teams membership, session-limit, privacy lifecycle, and manager hierarchy surfaces now use Identity `UserLookup` for user ID/display enrichment instead of importing Identity persistence table constants.

Tracked issue IDs: `P28-ARCH-001`, `P28-ARCH-004`, `P28-ARCH-005`, `P28-ARCH-012`, `P28-AUTH-001`, `P28-AUTH-002`, `P28-AUTH-005`, `P28-SEED-001`, `P28-MODAUD-003`.
