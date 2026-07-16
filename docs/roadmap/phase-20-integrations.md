## Phase 20 — Integrations

**Status:** `not started`

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

- [ ] Document first-party versus external API authentication boundaries.
- [ ] Keep external API access disabled by default.
- [ ] Define a reusable external-client identity/scope/credential contract without selecting unused token technology.
- [ ] Require explicit auth, revocation, expiry, rate limits, team/module scope, and audit for every external API surface.
- [ ] Create optional `Integrations` module.
- [ ] Define adapter contracts.
- [ ] Add external-ID mapping.
- [ ] Add synchronization history.
- [ ] Add idempotency.
- [ ] Add timeout.
- [ ] Add retry and backoff.
- [ ] Add circuit breaker.
- [ ] Add correlation IDs.
- [ ] Add secret-safe logging.
- [ ] Add integration status admin screen.
- [ ] Add last success and last error.
- [ ] Add test-connection action.
- [ ] Add source-of-truth documentation pattern.
- [ ] Commit Integrations module.

## Completion criteria

- [ ] External systems are behind typed adapters and explicit credential/scope contracts.
- [ ] First-party browser traffic remains session/CSRF/team/permission/module-gate based.
- [ ] External API access remains disabled until a concrete integration fully defines security, audit, and lifecycle.
- [ ] Relevant tests and documentation are current.
