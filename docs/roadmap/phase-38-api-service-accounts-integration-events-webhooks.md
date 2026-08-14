# Phase 38 — Atlas API, Service Accounts, Integration Events, OpenAPI, and webhooks

**Status:** `not started`

## Objective

Turn the existing Integrations, Integration Event, and Outbox foundations into a safe, documented machine-to-machine surface with hardened event contracts, `/api/v1` conventions, Service Accounts, API-only permissions, expiring tokens, OpenAPI documentation, and outgoing webhooks.

Do not create a second Integrations, Outbox, or event platform.

## Dependencies

- [Phase 33 — Foundation extension-point, duplication, and consumer audit](phase-33-foundation-extension-points-and-duplication-audit.md) must be complete.
- [Phase 35 — Runtime Settings, localized reference data, Team timezones, and Business Calendars](phase-35-runtime-settings-localized-reference-data-team-timezones-business-calendars.md) must be complete.
- [Phase 36 — Reusable data integrity and business-support primitives](phase-36-reusable-data-integrity-business-primitives.md) must be complete.
- Existing Integrations, Authorization, Audit, Outbox/Integration Events, rate limiting, Health, and Managed Processes foundations.

## Related documentation

- Modules: [Integrations](../modules/integrations.md)
- Modules: [Authorization](../modules/authorization.md)
- Architecture: [Modular-monolith architecture](../architecture/modular-monolith.md)
- Architecture: [Security baseline](../architecture/security-baseline.md)
- Operations: [Health, observability, and maintenance](../operations/health-observability-and-maintenance.md)

## Implementation contract

### Integration Events and Outbox

Preserve the distinction between internal Domain Events and selected/versioned Integration Events allowed across module or system boundaries. Do not publish every Domain Event externally.

Create one canonical catalog with stable event type, owner, schema version, minimal typed payload, sensitive-data classification, external/webhook eligibility, and compatibility expectations. Never leak Eloquent models, database schema, internal aggregates, secrets, or unrelated private fields.

Use explicit schema versions and backward-compatible evolution rules; breaking payload changes require explicit version evolution. This does not justify speculative version namespaces for synchronous in-process contracts.

Reuse and harden the existing Outbox, preserving atomic business-state/event persistence, after-commit relay, correlation, causation, event identity, retries, dead-letter handling, retention/replay, and consumer idempotency. Do not add a separate event table or relay for webhooks.

### Atlas REST API

Use `/api/v1` and define authentication, authorization, resources, error and validation envelopes, pagination, filtering, sorting, idempotency, rate limiting, request/correlation IDs, versioning, safe timestamps, and localization where applicable.

API implementation remains module-owned; there is no god-controller and no automatic exposure of repositories or tables. Modules explicitly declare surfaces with code-owned stable `api_name` values and registered `api.{api_name}.{permission_name}` permissions. Admin assigns but cannot invent APIs or permission keys.

### Service Accounts and authorization

A Service Account is a separate machine principal, not a User. It has no browser login, session, local password, OIDC identity, MFA, impersonation, active-Team selection, Team membership, or human permissions.

Admin manages stable code, display name, purpose/contact metadata, lifecycle, API permission bundles, tokens, and Audit identity. Service Accounts receive only registered `api.*` permissions. API roles/templates contain only those permissions.

Core API permissions do not define future partner/object scope. Business modules own their business-data policies; contractors are not modeled as Teams and Core does not invent a universal partner domain.

### API tokens

A Service Account may have multiple simultaneous active tokens for rotation. Tokens authenticate the same principal and use its current permissions; there is no token-scope layer.

Tokens have name, creation, mandatory non-null expiry, last use, and revocation metadata. Expiry cannot be extended; continued access uses a newly created replacement token. Plaintext is shown exactly once, never persisted, and stored only through a safe verification representation. Expired, revoked, or disabled-account credentials fail. Test and production use separate credentials; a token is not an environment selector.

### OpenAPI and idempotency

Select one repository-owned mechanism that reproducibly documents the actual API with authentication, permissions, examples, errors, schemas, and versions, plus drift protection. Do not maintain an unaudited hand-written divergent spec.

Use explicit idempotency keys for mutation endpoints where duplicate delivery is realistic. Do not impose idempotency persistence on reads.

### Outgoing webhooks

Webhooks deliver only explicitly allowlisted external Integration Events. Admin manages subscriptions; external clients cannot self-register arbitrary URLs in the baseline. Subscriptions define target, selected eligible event types, lifecycle, signing secret, and ownership/integration association where appropriate.

Sign deliveries through reviewed HMAC or equivalent and support stable event/delivery IDs, timestamp/replay protection where appropriate, retries/backoff, bounded failures, idempotent identity, history, authorized replay, disable controls, and safe response metadata. Signing secrets are generated/set, shown once where appropriate, replaceable, and never returned afterward. Never log secrets or sensitive payloads.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P38-W01 — Integration Event inventory, catalog, compatibility, and data policy

- [ ] Inventory current Domain/Integration Events, publishers, consumers, payloads, and external eligibility.
- [ ] Implement the canonical event catalog, schema compatibility policy, and sensitive-data classification.
- [ ] Migrate duplicate/ad hoc registrations and add payload/privacy guards.

### P38-W02 — Outbox, relay, idempotent-consumer hardening, and test helpers

- [ ] Verify and harden atomic persistence, after-commit relay, retries, dead letters, retention, replay, and correlation/causation.
- [ ] Standardize consumer deduplication/idempotency and deterministic test helpers.
- [ ] Add failure, concurrency, replay, compatibility, and operational tests without a second relay.

### P38-W03 — API conventions, `/api/v1`, module exposure contract, and error model

- [ ] Define and implement canonical API routing, representation, errors, pagination, filtering, sorting, timestamps, versioning, and correlation.
- [ ] Add explicit module-owned API registration and code-owned `api_name`/permission catalogs.
- [ ] Enforce rate limiting, validation, Authorization, ModuleGate, and no automatic persistence exposure.

### P38-W04 — Service Account principal and API-only authorization

- [ ] Implement a separate machine-principal model and Admin lifecycle.
- [ ] Reuse appropriate Authorization infrastructure while limiting assignments to registered `api.*` permissions.
- [ ] Permanently prohibit User/session/OIDC/MFA/impersonation/Team membership and normal UI permissions.

### P38-W05 — Expiring token lifecycle, rotation, revocation, and Audit

- [ ] Implement multiple simultaneous credentials with mandatory expiry and safe verification storage.
- [ ] Show plaintext exactly once; implement replacement-token rotation, revocation, and last-use metadata.
- [ ] Prohibit null/indefinite expiry, expiry extension, token scopes, and environment-selector semantics.
- [ ] Add secret-safe Audit and authentication failure coverage.

### P38-W06 — Module-owned API endpoints, idempotency, rate limits, and OpenAPI

- [ ] Implement accepted module-owned `/api/v1` endpoints through public/use-case boundaries.
- [ ] Add explicit mutation idempotency where duplicate delivery is realistic.
- [ ] Produce reproducible OpenAPI documentation and enforce route/schema/permission drift protection.
- [ ] Test resource, error, pagination, filtering, authorization, rate-limit, and compatibility contracts.

### P38-W07 — Admin-managed signed webhook subscriptions and delivery pipeline

- [ ] Implement allowlisted event subscriptions and write-only signing-secret lifecycle.
- [ ] Deliver through the existing Outbox/Integration Event foundation with reviewed signing and replay protection.
- [ ] Prohibit public self-registration, arbitrary event selection, and secret/payload leakage.

### P38-W08 — Retry, replay, history, health, and operational security

- [ ] Implement bounded retry/backoff, failure history, authorized replay, disable controls, and safe response metadata.
- [ ] Add Health/System Status and Managed Process integration where appropriate without exposing payloads.
- [ ] Verify revocation, disabled principals/subscriptions, replay identity, and failure isolation.

### P38-W09 — Browser/Admin acceptance, API integration tests, compatibility tests, documentation, and closure

- [ ] Add Admin browser acceptance for Service Accounts, token rotation, OpenAPI access, subscriptions, delivery history, replay, and secrets.
- [ ] Add end-to-end API authentication, Authorization, idempotency, rate-limit, event compatibility, and webhook-signature tests.
- [ ] Update Integrations, Authorization, Audit, architecture, security, deployment, operations, and developer documentation.
- [ ] Verify no token scopes, Team-context Service Accounts, arbitrary APIs, public subscriptions, or workflow/rules engine exists.

## Out of scope

- Public Service Account registration or browser login.
- Team membership, active-Team context, or human permissions for Service Accounts.
- Token permission scopes or indefinite/extendable tokens.
- Arbitrary Admin-created APIs, permissions, or webhook event types.
- Public self-service webhook subscriptions.
- Generic event-to-action rules, IF/THEN automation, workflow, or approval engines.

## Completion criteria

- [ ] Integration Events have stable versioned catalog contracts and reuse the existing reliable Outbox.
- [ ] `/api/v1` is module-owned, documented, permission-driven, and protected by canonical conventions.
- [ ] Service Accounts are distinct machine principals with only `api.*` permissions and no Team context.
- [ ] Tokens expire mandatorily, are replaced rather than extended, have no scopes, and never persist plaintext.
- [ ] Reproducible OpenAPI remains aligned with implemented routes and schemas.
- [ ] Webhooks are Admin-managed, allowlisted, signed, retryable, replayable, and secret safe.
- [ ] Tests and canonical documentation are current and `WORKROAD.md` status is `complete`.
