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
- health-check identifiers;

`ModuleRegistry` is the canonical deployed-module catalog. It:

- stores one manifest per key;
- rejects duplicate keys;
- rejects missing required dependencies;
- rejects dependency cycles, including cycles through deployed optional dependencies;
- rejects duplicate, self-referential, and overlapping required/optional dependencies;
- rejects Core modules that declare Optional modules as required dependencies;
- rejects missing service-provider classes and empty or duplicated health-check metadata;
- computes deterministic startup order with required dependencies and deployed optional dependencies before dependents.

Optional dependencies may be absent from the deployed registry, but the consuming module must enter a documented reduced mode when using behavior that depends on them.

Phase 28 completed validation of the real dependency graph, optional-dependency reduced modes, technical availability, activation state, and metadata execution.

### Executable metadata policy

Every `ModuleDefinition` metadata category has one accepted runtime meaning:

- required and optional dependencies drive startup order, reduced deployment variants, ModuleGate dependency decisions, and reverse-dependency deactivation protection;
- the service-provider class is loaded by the application composition root and must exist;
- global/team activation flags are enforced by the activation service; Core manifests must advertise neither mutable scope;
- health-check identifiers are resolved against Health readiness results; unknown or missing results make the module unavailable;
- frontend availability is executed by the typed route-availability and navigation contribution registries, not duplicated in module manifests.

The former `integrations` and `frontendEntrypoints` manifest categories were removed in Phase 28 because they were decorative duplicates. External integration availability is represented by executable health checks and owning integration adapters; frontend availability is represented by route/navigation contributions. Empty, duplicated, stale, or purely descriptive values are forbidden. A module that has no executable health requirement returns an empty list; descriptive capability inventories belong in canonical module documentation, not in `ModuleDefinition`.

### Central module enforcement

One central `ModuleGate`/module-access service is the source of truth for effective module access.

It evaluates in this order:

1. module exists in the deployed registry;
2. required dependencies exist, are technically available, and are active for the same global/team context;
3. module is technically available;
4. global activation permits use;
5. team activation permits use;
6. active-team context is valid;
7. required permission is effective.

Controllers, middleware, jobs, commands, public endpoints, and composable-view data providers use this central gate rather than reproducing activation logic. Early deployed-state and ModuleGate primitives already exist; full operational activation, scheduling, cache invalidation, and end-to-end enforcement are completed in Phase 14.

The current central evaluator is `App\Shared\Application\Modules\Contracts\ModuleGate`.

`DefaultModuleGate` evaluates a `ModuleAccessRequest` from a `ModuleGateStateProvider` in the canonical order above and returns a stable `ModuleAccessDecision` with a `ModuleAccessDenialReason`. Required dependency failures are reported before the requested module's own activation state so operators can see that a dependency is blocking access instead of only seeing the target module's state.

The runtime provider is `App\Shared\Infrastructure\Modules\RegistryModuleGateStateProvider`. It uses the explicit deployed registry, the Teams-owned `App\Shared\Application\Teams\Contracts\TeamLookup` contract for active-team validation and ID resolution, operational module activation state, and `App\Shared\Application\Authorization\Contracts\EffectivePermissionChecker` to decide current access. For each required dependency, it checks the dependency's effective state for the same active team before evaluating the target module's own technical availability and activation state.

Operational activation is persisted in typed PostgreSQL tables:

- `module_global_states` stores the current global state per module;
- `module_team_states` stores explicit team overrides, while no row means inheritance from global state;
- `module_activation_schedules` stores future scheduled changes and their outcome;
- `module_activation_history` stores append-only activation history.

Runtime checks cache effective module state in Redis through `App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService`; PostgreSQL remains the source of truth. Activation changes and schedules validate technical availability before enabling a module, so a module that is currently technically unavailable cannot be enabled immediately or scheduled for future enablement.

A declared required dependency that is not deployed is an invalid configuration and must fail startup/readiness with a clear error.

Modules that may have unsafe in-flight work implement `App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard` and register it with the central `App\Shared\Application\Modules\Contracts\ModuleDeactivationGuardRegistry`. It returns blocking process identifiers, human-readable reasons, safe completion/cancellation options, and whether deactivation may proceed. Owner guards cover active export generation, integration runs, managed-process runs and enabled schedules (including imports and search indexing), and TimeTracking sessions/corrections/maintenance/report work. Files scanning and Privacy operations are Core-owned and therefore cannot be operationally deactivated; their work is additionally represented by their owning process/runtime controls rather than a bypassable module switch.

Before owner guards run, activation checks the registry's reverse required-dependency graph. A globally enabled required dependent blocks global deactivation; an effectively enabled dependent in the same team blocks team deactivation. Optional consumers do not block deactivation and must use their documented reduced mode. Deactivation attempts, guard/dependency rejections, and successful changes are audited; the success record shares the activation transaction.

Modules that expose operational health/status issues to Admin System Status implement `App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics` and register contributors with the `atlas.module_operational_diagnostics` service tag. `App\Shared\Application\Modules\ModuleOperationalDiagnosticsRegistry` aggregates contributors by module key. Shared/Admin status composition may ask this registry for issue rows, but the owning module must perform any persistence reads needed to produce those rows.

Concrete guards remain owned by the modules that introduce unsafe work, while Shared owns only aggregation, reverse-dependency enforcement, and activation audit orchestration.

### Module activation

Two levels exist:

#### Deployment availability

Defines whether a module is technically available in an Atlas deployment.

Phase 28 added `App\Shared\Application\Modules\Contracts\ModuleTechnicalAvailability` and the readiness-backed `App\Shared\Infrastructure\Modules\ReadinessModuleTechnicalAvailability`. `DatabaseModuleActivationService` now calculates `technicallyAvailable` from deployed module metadata and the current Health readiness report instead of treating deployment as availability. A module with no declared health checks is technically available once deployed. A module that declares health checks is technically available only when every declared readiness check is present and not unhealthy. Degraded readiness remains visible to operators but does not block ModuleGate unless the underlying readiness check reports an unhealthy state. This makes stale `ModuleDefinition::healthChecks()` metadata unsafe by design; unsupported decorative module-name checks were removed from current manifests.

The Health module itself does not declare readiness checks for its own module availability. It owns and reports the readiness graph; consumer modules declare the specific checks they require.

Changing deployment availability requires restart or deploy.

#### Operational activation

For technically available optional and application modules, administrators may configure:

- globally active;
- globally inactive, including the expected state for newly deployed application modules awaiting business acceptance;
- active for selected teams;
- scheduled future activation or deactivation.

Rules:

- Core modules cannot be operationally disabled;
- system bootstrap may keep Core and accepted foundation modules available, but Application-category modules are not automatically enabled merely because they are deployed;
- admin UI and backend activation commands cannot activate a technically unavailable module;
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

Core modules must not hold foreign keys to optional module tables or require Optional modules in `ModuleDefinition`. If a Core module can use an Optional module, that relationship is declared as an optional dependency and the Core module must provide a documented reduced mode when the optional module is absent.

Admin module activation screens are available at `/admin/modules`. Administrators can inspect deployed modules, see active-team effective state on the index, manage global state where supported from the module detail screen, manage team overrides from a separate team-configuration screen, manage the same team overrides from team create/edit workflows, schedule future activation changes, cancel scheduled changes, and inspect recent history.

---
