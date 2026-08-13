# Authorization

Canonical current authorization behavior, framework boundary, permission catalogs, roles, team scope, and authorization enforcement.

## Authorization

### Framework boundary

Spatie models and APIs belong to Authorization Infrastructure.

- Domain code never imports Spatie or Eloquent authorization classes;
- ordinary route/use-case authorization is performed before domain execution in Presentation/Application;
- modules needing cross-module authorization information use typed public contracts from `Authorization/Application/Public`;
- domain invariants describe business capability/state and do not depend on framework permission names;
- no module reads Spatie tables directly.

Use `spatie/laravel-permission` with teams.

Rules:

- permission is the smallest authorization unit;
- effective access is the union of permissions granted by assigned roles and direct permission grants;
- direct grants only add access; Atlas has no permission deny layer, negative permission, or deny-overrides precedence mechanism;
- every protected route has a permission exactly equal to its route name;
- public and purely technical routes are exceptions;
- add business permissions where route permission alone is insufficient;
- never check role names in business code;
- roles are small functional packages of permissions;
- role names describe permission bundles, not people, job titles, hierarchy state, or business data scope;
- hierarchical cumulative roles may be used, for example:
    - `scans.read`
    - `scans.create`
    - `scans.update`
    - `scans.delete`
- each higher level includes the previous permissions;
- all backend operations validate active team context;
- UI visibility never replaces backend authorization.

Administrator has the complete permission set but still uses normal use cases, authorization mechanisms, validations, confirmations, and audit. Do not implement a hidden unconditional superadmin bypass.

Current implementation foundation:

- `App\Modules\Core\Authorization\AuthorizationModule` owns authorization contracts and infrastructure;
- `App\Shared\Application\Authorization\Contracts\EffectivePermissionChecker` is the public contract for cross-module effective permission checks;
- `App\Shared\Application\Authorization\Contracts\AdministratorAccessLookup` is the public contract for checking administrator-level access without exposing Spatie role/permission tables to Identity impersonation eligibility.
- `App\Modules\Core\Authorization\Infrastructure\Persistence\SpatieEffectivePermissionChecker` evaluates active-team-scoped direct and role permissions from the Spatie tables without exposing Spatie APIs to other modules; user/team public-ID resolution and active membership validation are delegated to Identity/Teams public contracts;
- `App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry` collects module-owned typed permission catalogs registered through the shared module permission contribution contract;
- protected named web routes use `route.permission`, which requires the effective permission name to match the route name;
- public, session-context, or technical route exceptions are currently `login`, `password.email`, `password.reset`, `password.confirm`, `password.confirm.store`, `password.confirmation`, `locale.update`, `team.select`, `team.select.store`, `team.switch`, and `theme.update`.
- Admin permission administration screens are available at `/admin/authorization/permissions` and show permission name, owning module, team scope, current module activation state, effective assignment state, and ineffectiveness reason in the selected active-team context.
- Admin role administration screens are available at `/admin/authorization/roles`; role creation and editing use separate Admin views instead of inline index forms and let administrators manage the role's direct permission membership.
- Admin preset administration is available at `/admin/authorization/packages`; preset creation and editing use separate Admin views and let administrators manage team-scoped local presets from existing roles and permissions.
- Admin team administration screens are available at `/admin/teams`; team creation and editing use separate Admin views and show team identity and active state. After creation, team-context membership mutation is owned exclusively by `/admin/teams/{team}/structure`; user-context access and authorization assignments remain available from User administration.
- Admin module activation screens are available at `/admin/modules`; module activation can also be managed from team creation and editing workflows.
- Admin user administration is available at `/admin/users`, shows users in the shared TanStack `DataTable`, supports current account-status actions, requires at least one team assignment during user creation, shows exact effective team-scoped assignments before submission, can apply a package or copy another user's role/direct-permission assignments in the selected team, and is the canonical authorization-mutation surface for user team access, roles, direct permissions, and supported user-team policy overrides. It routes account creation through the normal user creation use case.
- User Edit keeps persisted direct grants separate from role-derived grants. A permission granted by a selected role is shown checked and disabled with every granting role identified; removing a role immediately recomputes the role-derived, direct, and effective states without creating or deleting an overlapping real direct grant.
- Team Edit presents current member roles, direct permissions, policy overrides, current assignment source, and effective permissions as read-only context. It has no member-authorization Save/reason controls; User Edit remains the only Admin authorization-mutation surface.
- interactive role and permission options use active-locale human labels. Their technical keys remain secondary Admin metadata.
- Current Admin tables use the shared `DataTable` wrapper with backend-validated query-string state, server-side pagination/sorting/filtering, and saved views. Report/export actions are owned by the Reports module lifecycle rather than generated locally in the browser.

Starter roles:

- Atlas starter roles are small functional permission bundles such as `workspace.access`, `admin.users.read`, `admin.users.manage`, `admin.teams.read`, `admin.teams.manage`, `authorization.roles.read`, `authorization.roles.manage`, `authorization.presets.manage`, `authorization.permissions.read`, `teams.managers.read`, `teams.managers.manage`, `system.status.read`, and `system.operations.manage`;
- cumulative starter roles include the lower-level permissions required for that functional area, for example manage-level roles include their read/index permissions;
- `teams.managers.*` roles grant only manager-administration permissions; manager hierarchy scope still comes from the team-scoped manager hierarchy, not from the role name;
- `teams.managers.read` grants manager hierarchy Admin read/tree/history permissions, and `teams.managers.manage` additionally grants relationship creation, termination, and head-manager update permissions;
- `system.administrator` is the special bootstrap/full-access role created from all currently registered permission catalogs;
- `system.administrator` is not a model for ordinary company roles or presets;
- starter role installation creates missing roles only and does not silently update existing roles when permissions are added later.
- production-safe system bootstrap creates the mandatory `Administration` team, synchronizes the `system.administrator` role with all currently registered permissions, and enables deployed module access needed for that team through normal module activation state.

Operational CLI:

- `authorization:update-administrator-role` shows the administrator role permission diff;
- `authorization:update-administrator-role --apply --reason="..."` adds only missing permissions and records security audit;
- `atlas:first-administrator --name="..." --email="..." --team="..."` creates the first administrator only while no administrator role assignment exists;
- first administrator bootstrap creates the account through the normal user creation use case, sends the standard first-password link, and does not accept or generate a final password.
- local/development preview administrator creation is owned by `Database\Seeders\DevelopmentBootstrapSeeder`, not by the development demo seeder.
- production-safe role/permission installation is exposed through `AuthorizationBootstrapper`; non-production E2E workspace access uses the owner `AuthorizationFixtureBuilder`; TimeTracking demo assignments use the normal assignment manager and persist manual provenance with an explicit fixture reason.

Presets:

- `OnboardingPackageCatalog` exposes active team-scoped presets and their exact initial roles, direct permissions, and role-template permissions;
- `authorization_onboarding_packages` stores admin-managed local preset definitions in a team context; preset names are unique only inside a team and are never global authorization defaults;
- `PackageRoleManager` can create a role from a preset or add only missing preset permissions to an existing role while leaving unrelated extra permissions untouched;
- `ApplyOnboardingPackageToUser` applies a selected preset only during user creation inside a selected team assignment, records `user_onboarding_packages`, and audits the assignment;
- preset definitions are one-time starting assignments only and never synchronize existing users or roles automatically.
- user creation may alternatively copy the source user's selected-team role and direct-permission assignments as a one-time snapshot, and the source user must have active access to that selected team.
- user and team creation may also provide explicit team-scoped user role and direct-permission assignments;
- user-side and team-side administration compose the same `UserTeamAuthorizationWorkflow` frontend workflow and call the same `UserTeamAuthorizationManager` backend use case;
- `user_team_assignment_provenance` stores the truthful current assignment source (`manual`, `preset`, or `copy`), current source metadata where applicable, applying/updating context, resulting roles/direct permissions/policy limits, and optimistic version;
- a manual authorization edit changes the current source to `manual` and clears obsolete preset/copy source metadata. Historical source and mutation facts remain in Audit rather than a divergence field or message in the main editing UI;
- the pre-production canonical create migration no longer creates `diverged_at`; existing development databases are rebuilt through the documented `migrate:fresh` workflow so no historical divergence column survives into the accepted schema;
- stale assignment updates are rejected by the persisted optimistic version;
- users do not receive global role or permission assignments outside a team context.
- removing a user's team access also removes that user's direct role and permission assignments in the removed team.
- operational module activation never grants permissions automatically;
- assigned permissions remain stored while a module is inactive;
- effective permission checks include module activation and return `authorization.module_inactive` when the assigned permission belongs to an inactive module in the selected team context.

## Privacy Lifecycle

Authorization registers `UserAuthorizationDataLifecycleParticipant` for `user` subjects. Privacy execution removes the user's team-scoped role assignments, direct permission assignments, and onboarding-package snapshots. It does not delete role definitions, permission definitions, role-permission mappings, onboarding package definitions, or module activation state because those records are system configuration rather than personal controlled copies.

Permission catalog Admin read surfaces and exports resolve active-team context through Teams `TeamLookup`. Onboarding package creation, package reads, effective permission checks, assignment previews, administrator-level impersonation eligibility checks, and authorization lifecycle user-ID resolution use Identity `UserLookup` and Teams `TeamLookup` instead of reading Identity/Teams persistence directly. Authorization delegates team-membership provisioning to Teams `UserTeamMembershipProvisioner` instead of writing Teams tables directly.

---

## Phase 28 foundation repair target

Current state: Authorization uses owner-owned Identity/Teams lookups and membership provisioning. Phase 28 consolidated assignment handling on owner use cases, added truthful persisted provenance and stale-write protection, and retained atomic security audit evidence for assignment replacement. Phase 29 made Team Structure the canonical team-context membership surface; Team Edit no longer duplicates add/remove membership controls.

Target state achieved for seeding: Authorization depends only through owner-owned public contracts, delegates membership mutation to the Teams owner, uses one user-team assignment workflow from user, team, and deterministic fixture contexts, persists truthful manual/preset/copy provenance, and has complete audit, authorization, UI, seeder, and architecture coverage.

Tracked issue IDs: `P28-ARCH-001`, `P28-ARCH-004`, `P28-ARCH-011`, `P28-AUTH-003`, `P28-AUTH-004`, `P28-AUTH-005`, `P28-SEED-001`, `P28-MODAUD-002`.
