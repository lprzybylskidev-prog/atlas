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
- every protected route has a permission exactly equal to its route name;
- public and purely technical routes are exceptions;
- add business permissions where route permission alone is insufficient;
- never check role names in business code;
- roles are small functional packages of permissions;
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
- `App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker` is the public contract for cross-module effective permission checks;
- `App\Modules\Core\Authorization\Infrastructure\Persistence\SpatieEffectivePermissionChecker` evaluates active-team-scoped direct and role permissions from the Spatie tables without exposing Spatie APIs to other modules;
- `App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry` collects module-owned typed permission catalogs registered through the shared module permission contribution contract;
- protected named web routes use `route.permission`, which requires the effective permission name to match the route name;
- public or technical route exceptions are currently `login`, `password.email`, `password.reset`, `password.confirm`, `password.confirm.store`, `password.confirmation`, and `locale.update`.
- Admin permission administration screens are available at `/admin/authorization/permissions` and show permission name, owning module, team scope, current module activation state, effective assignment state, and ineffectiveness reason in the selected active-team context.
- Admin role administration screens are available at `/admin/authorization/roles`; role creation and editing use separate Admin views instead of inline index forms and let administrators manage the role's direct permission membership.
- Admin onboarding preset administration is available at `/admin/authorization/packages`; preset creation and editing use separate Admin views and let administrators manage local presets from existing roles and permissions.
- Admin team administration screens are available at `/admin/teams`; team creation and editing use separate Admin views and show team identity and active state.
- Admin user administration is available at `/admin/users`, shows users in the shared TanStack `DataTable`, supports current account-status actions, shows exact onboarding package contents before submission, can copy active-team role/direct-permission assignments from another user with preview, and routes account creation through the normal user creation use case.
- Current Admin tables use the shared `DataTable` wrapper with backend-validated query-string state, server-side pagination/sorting/filtering, saved views, and client CSV, XLSX, PDF, and print export actions for the currently loaded visible dataset. Server-side queued exports remain part of the later reporting/export lifecycle.

Starter roles:

- `user` starts with the authenticated dashboard permission;
- `manager` extends the user baseline with manager-facing visibility permission, but does not grant manager hierarchy scope by role name;
- `administrator` is created from all currently registered permission catalogs;
- starter role installation creates missing roles only and does not silently update existing roles when permissions are added later.

Operational CLI:

- `authorization:update-administrator-role` shows the administrator role permission diff;
- `authorization:update-administrator-role --apply --reason="..."` adds only missing permissions and records security audit;
- `atlas:first-administrator --name="..." --email="..." --team="..."` creates the first administrator only while no administrator role assignment exists;
- first administrator bootstrap creates the account through the normal user creation use case, sends the standard first-password link, and does not accept or generate a final password.

Onboarding packages:

- `OnboardingPackageCatalog` declares Core starter packages and their exact initial roles, direct permissions, and role-template permissions;
- `authorization_onboarding_packages` stores admin-managed local preset definitions;
- `PackageRoleManager` can create a role from a package or add only missing package permissions to an existing role while leaving unrelated extra permissions untouched;
- `ApplyOnboardingPackageToUser` applies a selected package only during user creation, records `user_onboarding_packages`, and audits the assignment;
- package definitions are presets only and never synchronize existing users or roles automatically.
- user creation may alternatively copy the source user's active-team role and direct-permission assignments as a one-time snapshot.

---
