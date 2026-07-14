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

---
