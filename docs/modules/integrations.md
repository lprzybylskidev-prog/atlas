# Integrations module

Canonical current behavior for external adapters, credentials, retries, idempotency, circuit breaking, audit, API boundaries, and operational visibility.

## Scope

The Integrations module is an Optional Foundation module for future concrete external systems. It does not enable any public external API by itself.

The module provides:

- typed adapter contracts;
- a configured adapter registry;
- external-client credential policy checks;
- external-ID mapping;
- synchronization history;
- idempotency keys;
- retry/backoff execution with correlation IDs;
- circuit-breaker state;
- secret-safe audit and operational visibility;
- an Admin status and test-connection screen.

## API Boundaries

First-party browser traffic continues to use Laravel session authentication, CSRF protection, active-team context, permissions, Admin mode where required, and ModuleGate.

External API access is disabled by default through `ATLAS_INTEGRATIONS_EXTERNAL_API_ENABLED=false`. A later concrete integration must explicitly define client identity, scopes, credential lifecycle, revocation, expiry, rate limits, team/module scope, and audit before any external API surface is enabled.

`/api/v1` is a versioning convention for future APIs. It is not automatically public and must not expose Eloquent models.

## Adapter Contracts

External systems are implemented as Infrastructure adapters behind `IntegrationAdapter`.

Each adapter declares an `IntegrationDefinition` with:

- stable integration key;
- human name;
- adapter class;
- source-of-truth statement;
- provided scopes;
- required modules;
- optional modules;
- whether the adapter itself is eligible for external API access.

Adapters are registered explicitly in `config('atlas.integrations.adapters')`. There is no directory scanning. Atlas starts with no configured adapters until a real integration is accepted.

## Persistence

The module owns PostgreSQL schema `optional_integrations`.

Tables:

- `integration_connections`;
- `integration_credentials`;
- `external_id_mappings`;
- `synchronization_runs`;
- `idempotency_keys`;
- `circuit_breakers`.

External identifiers are mapped by integration key, source system, entity type, external ID, and optional team. Internal references use public identifiers at module boundaries.

## Reliability

`IntegrationOperationRunner` wraps integration operations with:

- ModuleGate enforcement for the Integrations module;
- generated correlation ID;
- optional idempotency key claim;
- retry attempts;
- backoff delay;
- synchronization history start/finish records;
- circuit-breaker failure tracking;
- audit records for success, failure, replay, and blocked external API attempts.

Circuit states are:

- `closed`;
- `open`;
- `half_open`.

Open circuits block execution until their configured open interval expires, after which the next attempt moves to half-open.

## Admin

Admin route:

- `GET /admin/integrations`;
- `POST /admin/integrations/{integration}/test`.

Permissions:

- `admin.integrations.index`;
- `admin.integrations.test`.

The Admin screen shows registered adapters, source-of-truth notes, last success, last error, circuit state, recent synchronization runs, and global external API boundary status.

Test connection actions are permission-protected and use the adapter's typed `testConnection` method. Admin never displays secrets.

## Deactivation

The module registers a deactivation guard. Deactivation is blocked while synchronization runs are active or integration circuit breakers are open and awaiting operational review.

## Configuration

Environment variables:

- `ATLAS_INTEGRATIONS_EXTERNAL_API_ENABLED`, default `false`;
- `ATLAS_INTEGRATIONS_RETRY_MAX_ATTEMPTS`, default `3`;
- `ATLAS_INTEGRATIONS_RETRY_BASE_DELAY_MS`, default `100`;
- `ATLAS_INTEGRATIONS_TIMEOUT_MS`, default `5000`;
- `ATLAS_INTEGRATIONS_CIRCUIT_FAILURE_THRESHOLD`, default `3`;
- `ATLAS_INTEGRATIONS_CIRCUIT_OPEN_SECONDS`, default `60`.

Secrets remain outside the repository. Do not log tokens, API keys, raw credentials, full payloads, or unnecessary personal data.

## Optional Dependencies

An adapter may declare optional modules. If an optional module is inactive, the adapter must run in reduced mode and skip only the capability that depends on that module. Required module absence or ModuleGate denial blocks the operation.

## Source Of Truth

Every concrete integration must document the source of truth per synchronized data type before data is exchanged. When Atlas is not the source of truth, incoming data must still pass request-boundary validation and Application/Domain invariants before persistence.

---
