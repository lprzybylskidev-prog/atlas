# Phase 37 — Enterprise OIDC identities and authentication-method separation

**Status:** `not started`

## Objective

Add provider-neutral enterprise OIDC login while retaining the canonical Atlas User as the application identity and separating Atlas account eligibility from authentication method.

The baseline supports Microsoft Entra ID, Keycloak, and generic standards-compliant OIDC. Only one OIDC provider may be active for an Atlas installation at a time.

## Dependencies

- [Phase 35 — Runtime Settings, localized reference data, Team timezones, and Business Calendars](phase-35-runtime-settings-localized-reference-data-team-timezones-business-calendars.md) must be complete.
- Existing Identity, Authorization, Sessions, MFA, Admin Mode/high-risk reauthentication, and Audit capabilities.

## Related documentation

- Modules: [Identity, authentication, users, and sessions](../modules/identity-authentication-and-sessions.md)
- Modules: [Authorization](../modules/authorization.md)
- Modules: [Settings](../modules/settings.md)
- Architecture: [Security baseline](../architecture/security-baseline.md)

## Implementation contract

### Canonical user and provisioning model

OIDC does not replace Atlas User or introduce provider-specific User domain models. It creates an external authentication identity linked to a pre-existing Atlas User. Admin provisions the Atlas User first. Public registration and just-in-time User creation are forbidden.

### Secure initial linking

First login may link only when the Atlas User exists and is eligible, the provider identity and verified email are valid, the verified email exactly and unambiguously matches the intended User, and no conflicting external identity exists. Never link on unverified email or display name. Unsafe or ambiguous cases fail and require Admin resolution.

Persist provider issuer/identity plus stable subject. Subsequent logins resolve by stable provider subject, not repeated mutable-email matching.

### Authentication-method separation

Atlas independently enforces User existence, active/disabled state, authorization, Team/module access, and session/security constraints.

Local authentication retains the local password lifecycle/history/expiration, Atlas MFA, rate limits/lockouts, and local high-risk reauthentication. OIDC authentication delegates password and MFA policy to the provider; Atlas must not require a local password or let local password history/expiration/first-password state incorrectly block a valid OIDC login.

Do not stack Atlas TOTP on a successful OIDC login. Provider authentication assurance owns OIDC MFA; Atlas MFA remains for local administrator access.

### Login modes and recovery

Support installation-wide `local + OIDC` and `OIDC-only for ordinary users` modes. OIDC-only blocks ordinary local-password login but retains at least one safe emergency local administrative recovery path protected by canonical authorization, Atlas MFA, and high-risk controls—not role-name string checks.

Provider outage yields safe OIDC unavailability while emergency local Admin access remains possible. A provider-disabled account fails OIDC authentication without recreating or silently remapping the Atlas User.

### High-risk reauthentication and Admin Mode

Local sessions use canonical local reauthentication. OIDC sessions require fresh provider authentication appropriate to provider capabilities; do not ask OIDC-only users for an Atlas password. Preserve existing Admin Mode and high-risk intent.

### Provider configuration and lifecycle

Admin UI uses Phase 35 runtime Settings and write-only secrets for provider preset/type, issuer/discovery URL, client ID, client secret, callback metadata, and necessary claim mapping. Admin can safely test configuration before enforcement without exposing secrets.

At most one provider is active installation-wide. The design stays provider-neutral for migration, without simultaneous multi-provider selection UX. Normal users cannot unlink identities; linking, unlinking, relinking, and provider migration are Admin-managed and audited.

Audit configuration, enable/disable, mode changes, identity lifecycle, failed sensitive linking, and recovery changes without tokens or secrets.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P37-W01 — Identity/authentication-method model and persistence

- [ ] Model account eligibility independently from local and OIDC authentication methods.
- [ ] Add provider-neutral external identity persistence keyed by issuer/provider and stable subject.
- [ ] Preserve existing local authentication and privacy lifecycle contracts.

### P37-W02 — Provider-neutral OIDC client, discovery, and secure callback flow

- [ ] Implement standards-compliant discovery, state/nonce/PKCE and callback validation as appropriate.
- [ ] Validate issuer, subject, claims, verified-email status, and authentication responses safely.
- [ ] Prevent token, secret, and sensitive-claim leakage to logs, Audit, Diagnostics, and frontend props.

### P37-W03 — Existing-user secure linking and Admin identity management

- [ ] Implement exact, verified, unambiguous first linking to pre-created Users only.
- [ ] Resolve subsequent login by stable issuer/subject and prohibit unsafe email relinking.
- [ ] Add Admin-only link, unlink, relink, conflict-resolution, and provider-migration workflows.

### P37-W04 — Local+OIDC and OIDC-only login policy

- [ ] Implement both installation-wide login modes and localized login UX.
- [ ] Block ordinary local login in OIDC-only mode without blocking account-independent eligibility checks.
- [ ] Preserve a canonical permission/security-based emergency local Admin path.

### P37-W05 — MFA, high-risk reauthentication, and Admin Mode integration

- [ ] Keep Atlas MFA for local authentication and provider assurance for OIDC authentication.
- [ ] Implement fresh provider reauthentication for OIDC high-risk/Admin Mode flows.
- [ ] Prevent local password state from blocking OIDC-only Users or being requested from them.

### P37-W06 — Entra, Keycloak, and generic OIDC provider acceptance

- [ ] Verify Microsoft Entra ID, Keycloak, and generic compliant-provider behavior.
- [ ] Verify one-active-provider enforcement and safe provider migration.
- [ ] Cover provider-disabled users, outage, claim differences, and failure recovery.

### P37-W07 — Provider configuration UI, write-only secret handling, and test connection

- [ ] Add permission-protected Admin configuration through declared runtime Settings.
- [ ] Keep client secrets write-only, replaceable, encrypted, and structurally audited.
- [ ] Add a safe pre-enforcement configuration test with non-secret results.

### P37-W08 — Failure/recovery security, Audit, browser acceptance, tests, and documentation

- [ ] Add security and Audit coverage for configuration, modes, linking lifecycle, failures, and recovery.
- [ ] Cover critical flows in Chromium and Firefox, Polish and English, and applicable themes.
- [ ] Verify no JIT provisioning, SAML, SCIM, simultaneous providers, or provider-specific User coupling exists.
- [ ] Update Identity, Authorization, Sessions, Admin Mode, Settings, security, operations, and testing documentation.

## Out of scope

- JIT Atlas User creation or public registration.
- SAML or SCIM.
- Simultaneous multiple active providers.
- Provider-specific User domain coupling.
- Self-service identity unlinking.

## Completion criteria

- [ ] OIDC links only to pre-created eligible Atlas Users through safe verified matching.
- [ ] Subsequent authentication uses stable issuer/subject identity.
- [ ] Local and OIDC credential/MFA policies are correctly separated.
- [ ] Both login modes and emergency local Admin recovery work safely.
- [ ] Exactly one provider can be active, with Entra, Keycloak, and generic OIDC acceptance.
- [ ] Runtime secrets are write-only and all sensitive lifecycle actions are audited safely.
- [ ] Tests and canonical documentation are current and `WORKROAD.md` status is `complete`.
