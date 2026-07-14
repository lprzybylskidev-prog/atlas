## Phase 20 — Integrations

### Implementation contract

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
