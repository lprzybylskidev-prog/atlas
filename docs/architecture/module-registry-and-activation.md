# Module registry and activation

Canonical rules for deployed modules, dependencies, ModuleGate, global/team activation, schedules, cache, and deactivation guards.

## Module Registry

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

Controllers, middleware, jobs, commands, public endpoints, and composable-view data providers use this central gate rather than reproducing activation logic.

A declared required dependency that is not deployed is an invalid configuration and must fail startup/readiness with a clear error.

Modules that may have unsafe in-flight work implement a typed active-process/deactivation-guard contract. It returns blocking process identifiers, human-readable reasons, safe completion/cancellation options, and whether deactivation may proceed. Module deactivation must not guess this from foreign tables.

Every module has a typed manifest implementing a shared contract such as `ModuleDefinition`.

A manifest declares at least:

- stable technical key;
- category: `core`, `optional`, or `application`;
- required dependencies;
- optional dependencies;
- Service Provider;
- supported global activation;
- supported per-team activation;
- integrations;
- health checks;
- frontend entrypoints when required.

Manifest registration is explicit in a central catalog such as `config/modules.php`.

No directory or namespace scanning.

`ModuleRegistry` must:

- instantiate only explicitly registered manifests;
- reject duplicate keys;
- detect missing dependencies;
- detect dependency cycles;
- determine a safe startup order;
- fail application startup with a clear exception on invalid configuration.

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

---
