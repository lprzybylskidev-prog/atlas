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
- `app/Shared/Domain/Money` owns framework-independent money and currency primitives.
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
