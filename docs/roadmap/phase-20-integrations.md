# Phase 20 — Integrations

**Status:** `complete`

## Objective

Implement typed external integration foundations before imports or business modules expose API adapters, synchronization, external credentials, or circuit-breaker behavior.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Integrations module documentation](../modules/integrations.md)

## Implementation contract

- External systems are Infrastructure adapters behind typed contracts.
- API uses versioning such as `/api/v1`.
- API input uses DTOs and output uses Resources/read DTOs; Eloquent models are never exposed.
- Define source of truth per synchronized data type.
- Maintain external identifier mappings because one internal entity may have several source identifiers.
- Integrations use timeout, safe retry, backoff, circuit breaker, idempotency, correlation IDs, and sanitized logs.
- Mutating APIs support idempotency keys where repeat delivery is possible.
- Admin shows integration status, last success, last error, circuit state, and a permission-protected test connection.
- Secrets remain outside the repository and never enter logs or audit.
- `/api/v1` is not automatically a public external API.
- First-party browser traffic continues to use session/CSRF/team/permission/module-gate authorization.
- External API access is disabled until a concrete integration defines client identity, scopes, credential lifecycle, revocation, expiration, rate limits, and audit.
- Select token or OAuth technology only for an accepted external use case; do not install a generic auth server preemptively.

## Tasks

- [x] Document first-party versus external API authentication boundaries.
- [x] Keep external API access disabled by default.
- [x] Define a reusable external-client identity/scope/credential contract without selecting unused token technology.
- [x] Require explicit auth, revocation, expiry, rate limits, team/module scope, and audit for every external API surface.
- [x] Create optional `Integrations` module.
- [x] Define adapter contracts.
- [x] Add external-ID mapping.
- [x] Add synchronization history.
- [x] Add idempotency.
- [x] Add timeout.
- [x] Add retry and backoff.
- [x] Add circuit breaker.
- [x] Add correlation IDs.
- [x] Add secret-safe logging.
- [x] Add integration status admin screen.
- [x] Enforce ModuleGate, active-team context, and module activation in every integration adapter, test-connection action, webhook/public endpoint, and queued integration job.
- [x] Register integration deactivation guards for unsafe in-flight transitions and circuit states.
- [x] Document optional-dependency reduced mode for integrations that depend on inactive optional modules.
- [x] Add last success and last error.
- [x] Add test-connection action.
- [x] Add source-of-truth documentation pattern.
- [x] Commit Integrations module.

## Completion criteria

- [x] External systems are behind typed adapters and explicit credential/scope contracts.
- [x] First-party browser traffic remains session/CSRF/team/permission/module-gate based.
- [x] External API access remains disabled until a concrete integration fully defines security, audit, and lifecycle.
- [x] Relevant tests and documentation are current.
