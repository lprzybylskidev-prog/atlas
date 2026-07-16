# Atlas modular-monolith architecture

Canonical cross-module architecture. Read this before changing module boundaries, layers, public contracts, transactions, events, persistence, IDs, money, timezone, migrations, or shared runtime boundaries.

## Architecture

Use a modular monolith with DDD boundaries from the beginning.

Main module categories:

- `Core`
- `Optional Foundation`
- `Application`

Suggested root structure:

```text
app/Modules/
  Core/
  Optional/
  Application/
app/Shared/
```

Current foundation structure:

- `app/Modules/Core/Identity` owns the current Fortify authentication actions, Identity presentation provider, and user persistence model.
- `app/Modules/Core/Users` is the Core user-lifecycle module root and currently depends on Identity while user administration use cases are being built.
- `app/Modules/Optional` is reserved for optional foundation modules.
- `app/Modules/Application` is reserved for concrete business-domain modules.
- `app/Shared/Domain/Money` owns framework-independent money and currency primitives.
- `app/Shared/Application`, `app/Shared/Domain`, `app/Shared/Infrastructure`, and `app/Shared/Presentation` are the only shared layer roots.
- The default Laravel skeleton directories `app/Actions`, `app/Models`, `app/Support`, and `app/Http/Controllers` are intentionally not used.

Each module uses:

```text
Module/
  Domain/
  Application/
  Infrastructure/
  Presentation/
```

### Layer responsibilities

#### Domain

- pure PHP;
- no Laravel dependencies;
- no Eloquent;
- no infrastructure concerns;
- aggregates, entities, value objects, domain services, domain events, repository interfaces, domain exceptions.

#### Application

- use-case orchestration;
- commands, queries, handlers, DTOs;
- transaction boundaries;
- authorization coordination where applicable;
- no persistence implementation;
- no UI logic;
- no hidden domain rules that belong in aggregates or value objects.

#### Infrastructure

- Eloquent persistence models;
- repository implementations;
- Redis;
- queues;
- external API adapters;
- filesystem adapters;
- search adapters;
- framework-specific implementations.

#### Presentation

- controllers;
- Form Requests;
- API Resources;
- console commands;
- Inertia entrypoints;
- route definitions.

### Controllers and requests

- controllers must remain thin;
- Form Requests perform validation and authorization only;
- Form Requests must not construct domain entities;
- no business logic in controllers, requests, Eloquent models, or frontend components.

### Domain model

- aggregates enforce invariants;
- entities expose intent methods, not public setters;
- avoid anemic domain models;
- use immutable value objects where rules exist;
- relations between aggregates use typed IDs;
- one aggregate root per aggregate;
- Domain Services only for domain logic that does not naturally belong to an entity or value object.

### Persistence

- Eloquent models are persistence models only;
- domain entities and Eloquent models remain separate;
- mappings occur in repositories or dedicated mappers;
- repository interfaces live in Domain;
- implementations live in Infrastructure;
- no generic CRUD BaseRepository;
- writes use aggregate repositories;
- reads use query handlers and read models.

### CQRS

Use application-level CQRS:

- Commands mutate state.
- Queries read state.
- Commands and Queries contain no business logic.
- Queries return read DTOs or views.
- Commands should not return full aggregates unless justified.

Do not introduce automatic event sourcing or a separate read database.

### Events

Separate:

- Domain Events
- Integration Events

Rules:

- event names use past tense;
- domain events originate from the domain;
- integration events cross module or system boundaries;
- events must not hide simple sequential flow;
- external side effects occur after transaction commit;
- use the Outbox Pattern for reliable integration events.

### Transactions

- one transaction per use case;
- transaction boundaries belong in Application;
- aggregate changes and event persistence are atomic;
- external side effects happen after commit;
- keep transactions short.

### Module boundaries

A module must not:

- access another module's Eloquent model;
- query another module's tables directly;
- depend on another module's Infrastructure;
- use internal classes of another module.

Allowed communication:

- public application contracts;
- typed module APIs;
- Integration Events.

Dependencies must be acyclic.

---

## Module Registry and Public API

Every deployed module has an explicit `ModuleDefinition` manifest registered in `config/modules.php`.

The manifest declares the module key, category, required dependencies, optional dependencies, Service Provider, activation support, integrations, health checks, and frontend entrypoints. `ModuleRegistry` rejects duplicate keys, missing required dependencies, dependency cycles, and invalid startup order during application registration.

Modules expose cross-module synchronous APIs only through:

```text
Application/Public/
  Contracts/
  DTOs/
  Commands/
  Queries/
  Events/
```

Everything outside `Application/Public` is internal to the owning module unless a dedicated architecture document grants a narrow exception.

Public contracts are owned by the provider module. A consuming module must depend on the provider's public contract instead of redefining a pseudo-contract for the provider's internal behavior.

Public names must be business-specific, for example `UserDirectory`, `TeamDirectory`, or `NotificationPublisher`. Avoid generic names such as `ModuleService`, `DataProvider`, `CommonService`, `Manager`, or `Helper`.

Public DTOs are immutable, minimal, framework-independent, and must not expose Eloquent models, aggregates, table names, or persistence structure.

Interfaces and public capability contracts live in local `Contracts` namespaces. Typed exceptions live in local `Exceptions` namespaces. Traits are not created speculatively; if a trait is genuinely needed, it belongs in a local `Concerns` namespace and must keep behavior explicit.

Use synchronous public contracts when the caller requires a result inside the current use case. Use Integration Events when no immediate response is needed, multiple consumers may react, processing may occur after commit, or receiver unavailability must not block the source use case.

Do not create preemptive `V1` or `V2` public-contract namespaces inside the monolith. Change synchronous contracts atomically with all in-repository consumers, tests, and documentation. Add parallel versions only when two versions must genuinely coexist during a migration.

Public-contract deprecation requires:

1. deprecation marker;
2. documented replacement;
3. discovery of all consumers;
4. migration of all consumers;
5. a test confirming no remaining usage;
6. a separate removal commit.

### Module registration conventions

Each module owns one Service Provider under its `Presentation/Providers` namespace unless a documented technical reason places it elsewhere inside the same module. The provider is the module's explicit integration point with Laravel and registers only that module's bindings, routes, migrations, commands, listeners, schedules, views, frontend entrypoints, and contribution providers.

Do not register module behavior through directory scanning. Module registration is explicit through the manifest and provider.

Module web routes live under module-owned Presentation route files once module route registration is active. Until then, root route files remain split by delivery area. Controllers stay thin and route files do not contain business logic.

Module migrations are owned by the module whose tables they create or modify. Cross-module table changes require an explicit architecture decision and must not be hidden inside another module's migration.

### PostgreSQL schema ownership

Atlas uses PostgreSQL schemas as an explicit persistence ownership boundary. Each Atlas-owned table belongs to the schema of the module or shared infrastructure capability that owns it. The default `public` schema is not the home for Atlas-owned module data.

Schemas complement module boundaries; they do not replace them. A schema-qualified table name is not permission for another module to query that table. Cross-module synchronous access still goes through `Application/Public` contracts, and asynchronous communication still goes through Integration Events.

Schema names are stable lowercase `snake_case` identifiers:

- `core_identity` for authentication identity, credentials, sessions, and password lifecycle persistence;
- `core_users` for user-lifecycle persistence that is not owned by Identity;
- `core_teams` for teams and team membership persistence;
- `core_authorization` for roles, permissions, permission assignments, onboarding packages, and authorization package persistence;
- `core_audit` for audit and security audit persistence;
- `core_settings` for typed settings persistence;
- `core_notifications` for notification and realtime delivery persistence once Phase 15 starts;
- `shared` for shared technical infrastructure such as Outbox, saved table views, module activation state, and framework runtime tables that Atlas intentionally owns centrally;
- `optional_<module>` for optional foundation modules;
- `application_<module>` for debt collection business modules.

The `public` schema is allowed only for PostgreSQL extension metadata, the Laravel migration repository table unless a later operations decision moves it, and explicitly documented package-owned compatibility tables. New Atlas-owned tables must not be created in `public`.

Migrations must create schemas before creating tables, and must use schema-qualified names for tables, foreign-key references, indexes, and raw SQL. Eloquent models, query builders, package configuration, seeders, tests, and operational scripts must not rely on PostgreSQL `search_path` for Atlas-owned tables.

Cross-schema foreign keys are allowed only when the canonical architecture permits the dependency. They enforce integrity only; they do not grant an exception to the rule against direct cross-module queries or mutations.

Current table ownership:

| Schema | Tables |
| --- | --- |
| `core_identity` | `users`, `password_reset_tokens`, `user_password_histories`, `user_webauthn_credentials`, `sessions` |
| `core_teams` | `teams`, `team_user_assignments` |
| `core_authorization` | `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, `authorization_onboarding_packages`, `user_onboarding_packages` |
| `core_audit` | `audit_events`, `audit_security_events` |
| `core_settings` | `settings_global_values`, `settings_team_values`, `settings_user_values`, `settings_security_values` |
| `shared` | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `outbox_events`, `outbox_consumed_events`, `table_saved_views`, `table_saved_view_defaults`, `module_global_states`, `module_team_states`, `module_activation_schedules`, `module_activation_history` |
| `public` allowlist | `migrations`, package-owned local diagnostics tables such as Telescope tables |

Runtime table references use `App\Shared\Infrastructure\Database\DatabaseTable` constants for schema-qualified Atlas-owned table names. Migrations create required schemas through `App\Shared\Infrastructure\Database\DatabaseSchema`. The configured PostgreSQL `search_path` includes Atlas schemas only so Laravel database maintenance commands can see and wipe all schemas deterministically; application code must still use schema-qualified Atlas-owned table names.

Module contribution contracts are framework-independent declarations consumed by shared Presentation/Admin infrastructure:

- `ModuleMenuContribution` returns `ModuleMenuItem` values;
- `ModulePermissionContribution` returns `ModulePermissionDefinition` values;
- `ModuleBreadcrumbContribution` returns `ModuleBreadcrumbDefinition` values;
- `ModuleHealthCheckContribution` returns `ModuleHealthCheckDefinition` values;
- `ModuleScheduleContribution` returns `ModuleScheduledTask` values;
- frontend entrypoints use `ModuleFrontendEntrypoint` values or manifest entrypoint declarations.

Contribution consumers must still pass through backend authorization and `ModuleGate`. UI visibility is never authorization.

## Integration Events and Outbox

Reliable Integration Events use one shared Outbox infrastructure.

Rules:

- create the Integration Event and Outbox record in the same database transaction as the originating business change;
- the Outbox record contains stable event type, event ID, schema version, payload, source module, occurred time, correlation ID, and optional causation ID;
- publishing happens only after commit through a dedicated relay/dispatcher;
- delivery is at-least-once, so every consumer must be idempotent;
- retries use bounded exponential backoff;
- exhausted events move to an explicit failed/dead-letter state and remain inspectable;
- successful records are retained according to a configurable retention policy and then cleaned by a scheduled job;
- do not publish reliable cross-module Integration Events directly from a transaction or bypass the Outbox;
- operational status, lag, retries, failures, and manual safe replay are visible in Admin;
- replay never changes the original event identity;
- correlation and causation metadata are propagated through dispatch and consumption.

Current durable storage uses the shared `outbox_events` table.

The table stores:

- internal BIGINT `id`;
- unique ULID `event_id`;
- stable technical `event_type`;
- integer `schema_version`;
- `source_module`;
- JSONB `payload`;
- `occurred_at`;
- required `correlation_id`;
- optional `causation_id`;
- `status`;
- `attempts`;
- `next_attempt_at`;
- `published_at`;
- `failed_at`;
- `failure_details`;
- timestamps.

Application use cases record reliable Integration Events through `App\Shared\Application\Outbox\Contracts\OutboxEventRecorder` while the owning business transaction is open. The current infrastructure implementation inserts `pending` records.

The Outbox relay uses `App\Shared\Application\Outbox\Contracts\OutboxRelay` with an explicit `OutboxEventPublisher`. It claims due `pending` records in a database transaction, marks them `publishing`, commits, publishes after commit, and marks successful records `published`.

If publishing fails, the relay increments attempts and either schedules a bounded backoff retry or moves the record to `failed`. Failed records are inspectable dead-letter records.

`App\Shared\Application\Outbox\Contracts\OutboxMaintenance` owns retention cleanup, safe replay of failed records without changing the original `event_id`, and lag metrics for pending, publishing, failed, and oldest-pending age.

Consumers use `App\Shared\Application\Outbox\Contracts\OutboxConsumerDeduplicator` to atomically record `(event_id, consumer)` delivery. The shared `outbox_consumed_events` table prevents duplicate processing for the same consumer while allowing multiple independent consumers to process the same Integration Event.

### Integration Event schema evolution

Every Integration Event includes stable event type, unique event ID, occurrence time, correlation ID, optional causation ID, source module, integer schema version, and minimal payload.

Schema version lives in event metadata. Compatible evolution may add optional fields and consumers must ignore unknown fields. Existing field meaning must not change. Removing a field or making a breaking change requires a migration period and a new schema version.

---

## Deactivation Guards

Modules that can have unsafe in-flight work expose a typed `ModuleDeactivationGuard`.

The guard receives a `ModuleDeactivationRequest` containing module key, optional team ID, and requester identity. It returns `ModuleDeactivationAssessment` with:

- blocking process identifiers;
- human-readable reasons;
- supported safe actions such as complete, cancel, or retry.

Module deactivation must use this contract and must not inspect foreign module tables to guess active work.

---

## Data Lifecycle Participation

Modules participate in cross-module deletion and anonymization through `App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant`.

Each participant can:

- preview affected data sets;
- report blockers;
- execute idempotent deletion or anonymization steps;
- return auditable step results tied to a correlation ID.

The full administrative orchestration is implemented later, but modules must shape their deletion/anonymization behavior around this minimal shared contract.

## Base Module Classification

### Core

- Identity
- Users
- Teams
- Authorization
- Audit
- Notifications
- Settings
- Files
- Admin
- Health

### Optional Foundation

- TimeTracking
- Imports
- Search
- Integrations
- FeatureFlags

Reports/exports/print and realtime/WebSockets are shared cross-cutting capabilities by default, not automatically activatable modules. A Atlas may introduce a dedicated Reports or Realtime module only when it has an independent business boundary, lifecycle, permissions, or activation needs.

### Application

Business-specific modules of the Atlas.

Shared foundation modules must not assume the internal rules of a specific debt collection `Application` module.

---

### Public query result shapes

Public module Queries return framework-independent DTOs.

For collections:

- use immutable typed collection/result objects;
- potentially large results use a framework-independent page result containing items, page/cursor metadata, and total only when the query can provide it safely;
- do not expose Laravel paginator classes, Eloquent collections, query builders, or database cursors across module boundaries;
- cursor pagination is preferred for large mutable datasets;
- ordering and continuation semantics are part of the public Query contract.

The shared foundation types are:

- `TypedCollectionResult`;
- `PageResult` with `PageMetadata`;
- `CursorPageResult` with `PageCursor`.

## Identity and Public IDs

Use:

- internal BIGINT primary keys;
- public ULID `public_id` in URLs, APIs, logs, and external references;
- typed domain IDs;
- explicit external identifier mappings.

For imported identifiers, prefer:

- source plus external identifier;
- or a dedicated mapping table.

Do not use an ambiguous single `outer_id` when one entity may have multiple external identifiers.

---

## Database Rules

PostgreSQL only.

All schema changes use migrations.

### Foreign keys

- use `RESTRICT`;
- never use cascade delete.

### Money

- represent money as an immutable value object containing integer minor units and an ISO 4217 currency code;
- use INTEGER or BIGINT minor units;
- never use float, double, or decimal/numeric for application money values;
- configure a default application currency, initially `PLN`, but never infer currency solely from that default when persisting or exchanging a money value;
- arithmetic and comparisons require matching currencies unless an explicit exchange-rate use case performs conversion;
- external/original currency may be preserved separately where business rules require it.

### Schema design

- use precise types;
- enforce uniqueness in the database;
- design indexes from actual queries;
- update indexes when query patterns or data volume change;
- use `EXPLAIN ANALYZE` where appropriate;
- select only required columns;
- paginate potentially large datasets;
- use chunking, cursors, and queues for mass work;
- prohibit N+1 queries;
- use explicit eager loading.

Raw SQL is allowed when safer or faster and must be tested.

### Timezone

Central timezone defaults to `Europe/Warsaw` through configuration such as `APP_TIMEZONE`.

Apply it consistently to:

- Laravel;
- PHP;
- PostgreSQL;
- containers;
- scheduler;
- queues;
- logs;
- reports.

Store technical timestamps in UTC where appropriate and convert for presentation and business calendar rules.

### Migration lifecycle

Maintain a production deployment flag such as:

```text
PRODUCTION_DEPLOYED: false
```

Before first production deployment:

- one table per migration file;
- include schema, indexes, and constraints for that table in the same migration;
- edit existing migrations rather than adding repair migrations;
- choose logical column order from the beginning.

After first production deployment:

- set the flag to true;
- migrations become forward-only;
- never edit migrations already executed in production.

Use safe migration strategies for large tables.

Do not create fake rollback logic.

---

### Cache

Use Redis consciously.

Rules:

- explicit TTL except documented exceptions;
- versioned, module-scoped keys;
- explicit invalidation;
- no caching to hide bad architecture;
- protect sensitive content;
- use namespaces for cache, session, queue, locks, and rate limits;
- prevent stampedes;
- domain must not depend on cache.

### Queues

Use Redis and Horizon.

Initial queues:

- `default`
- `imports`
- `integrations`
- `notifications`
- `search`
- `maintenance`

Jobs must be:

- idempotent;
- bounded by timeout;
- configured with backoff;
- observable;
- compatible with failed/dead-letter handling.

Heavy work must not stay in HTTP requests.

Use scheduler separately.

Do not use `sleep()` as workflow control.

### API

Use versioning such as `/api/v1` only for an explicitly approved API surface.

A versioned route prefix does not make an API public by itself.

- first-party Inertia/browser traffic uses the normal session, CSRF, active-team, permission, and module-gate model;
- external API access is disabled by default;
- when a Atlas exposes an external API, it must define an explicit client identity and authentication scheme appropriate to that integration, such as scoped personal/service tokens or OAuth2;
- do not add Passport, Sanctum token mode, or generic API keys preemptively without an accepted use case;
- external credentials are scoped, revocable, expiring where possible, rate-limited, and audited.

Rules:

- DTOs and API Resources;
- never expose Eloquent models;
- validate in Presentation;
- integrations implemented as Infrastructure adapters;
- retries only for safe operations;
- idempotency keys for mutations;
- timeout and circuit breaker;
- correlation ID;
- secrets from environment/secrets;
- sanitized logs.

---
