# Module registry and activation

Canonical rules for deployed modules, dependencies, ModuleGate, global/team activation, schedules, cache, and deactivation guards.

## Module Registry

Atlas uses an explicit module manifest registry. There is no directory, namespace, or attribute scanning.

Deployed manifests are listed in `config/modules.php` under `deployed`.

Each entry must be a class-string implementing `App\Shared\Application\Modules\Contracts\ModuleDefinition`. During application registration, `App\Providers\AppServiceProvider` instantiates the configured manifests and builds `App\Shared\Application\Modules\ModuleRegistry`. Invalid configuration fails startup before the application can serve requests.

The initial deployed manifests are registered explicitly in `config/modules.php`. Operational activation is completed by the roadmap's Module Availability and Activation phase after authorization, active-team, audit, settings, shared UI, and table foundations exist.

`ModuleDefinition` declares:

- stable `ModuleKey`;
- `ModuleCategory`: `core`, `optional`, or `application`;
- required dependency keys;
- optional dependency keys;
- Service Provider class;
- global activation support;
- team activation support;
- integration identifiers;
- health-check identifiers;
- frontend entrypoints.

`ModuleRegistry` is the canonical deployed-module catalog. It:

- stores one manifest per key;
- rejects duplicate keys;
- rejects missing required dependencies;
- rejects required-dependency cycles;
- computes deterministic startup order with dependencies before dependents.

Optional dependencies may be absent from the deployed registry, but the consuming module must enter a documented reduced mode when using behavior that depends on them.

### Central module enforcement

One central `ModuleGate`/module-access service is the source of truth for effective module access.

It evaluates in this order:

1. module exists in the deployed registry;
2. required dependencies exist and are valid;
3. module is technically available;
4. global activation permits use;
5. team activation permits use;
6. active-team context is valid;
7. required permission is effective.

Controllers, middleware, jobs, commands, public endpoints, and composable-view data providers use this central gate rather than reproducing activation logic. Early deployed-state and ModuleGate primitives already exist; full operational activation, scheduling, cache invalidation, and end-to-end enforcement are completed in Phase 14.

The current central evaluator is `App\Shared\Application\Modules\Contracts\ModuleGate`.

`DefaultModuleGate` evaluates a `ModuleAccessRequest` from a `ModuleGateStateProvider` in the canonical order above and returns a stable `ModuleAccessDecision` with a `ModuleAccessDenialReason`.

The runtime provider is `App\Shared\Infrastructure\Modules\RegistryModuleGateStateProvider`. It uses the explicit deployed registry, active `teams` table state, operational module activation state, and `App\Modules\Core\Authorization\Application\Public\EffectivePermissionChecker` to decide current access.

Operational activation is persisted in typed PostgreSQL tables:

- `module_global_states` stores the current global state per module;
- `module_team_states` stores explicit team overrides, while no row means inheritance from global state;
- `module_activation_schedules` stores future scheduled changes and their outcome;
- `module_activation_history` stores append-only activation history.

Runtime checks cache effective module state in Redis through `App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService`; PostgreSQL remains the source of truth.

A declared required dependency that is not deployed is an invalid configuration and must fail startup/readiness with a clear error.

Modules that may have unsafe in-flight work implement `App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard` and register it with the central `App\Shared\Application\Modules\Contracts\ModuleDeactivationGuardRegistry`. It returns blocking process identifiers, human-readable reasons, safe completion/cancellation options, and whether deactivation may proceed. Module deactivation must not guess this from foreign tables.

Phase 14 provides the registry and enforcement hook. Concrete guards are owned by the later modules that introduce real unsafe processes such as imports, integrations, report generation, or TimeTracking sessions.

### Module activation

Two levels exist:

#### Deployment availability

Defines whether a module is technically available in a Atlas deployment.

Changing deployment availability requires restart or deploy.

#### Operational activation

For technically available optional modules, administrators may configure:

- globally active;
- globally inactive;
- active for selected teams;
- scheduled future activation or deactivation.

Rules:

- Core modules cannot be operationally disabled;
- admin UI cannot activate a technically unavailable module;
- backend must enforce state, not only hide UI;
- state affects routes, menu, permissions, jobs, schedules, reports, integrations, and listeners;
- module activation history is stored in typed tables, not an uncontrolled JSON blob;
- records include effective dates, actor, reason, and history;
- absence of a per-team override means inheritance from global state;
- modules are never physically removed from the repository;
- disabling a module never removes data and never rolls back migrations;
- re-enabling restores access to existing data;
- disabling is blocked while unsafe active processes exist;
- dependent modules are not disabled automatically;
- required dependencies block deactivation;
- optional dependencies may enter a documented reduced mode.

All migrations of technically available modules run during deploy, even when operationally inactive.

Core modules must not hold foreign keys to optional module tables.

Admin module activation screens are available at `/admin/modules`. Administrators can inspect deployed modules, see active-team effective state, manage global state where supported, manage team overrides from the module detail screen, manage the same team overrides from team create/edit workflows, schedule future activation changes, cancel scheduled changes, and inspect recent history.

---
